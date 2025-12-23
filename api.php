<?php
// api.php
header("Content-Type: application/json; charset=utf-8");
require __DIR__ . "/db.php";

$action = $_GET["action"] ?? "";

function json_input() {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond($payload, $code = 200) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

if ($action === "list") {
  $search = trim($_GET["search"] ?? "");
  $where = "";
  $params = [];

  if ($search !== "") {
    $where = "WHERE c.full_name LIKE :q
              OR c.id_number LIKE :q
              OR p.contract_no LIKE :q";
    $params[":q"] = "%{$search}%";
  }

  $sql = "
    SELECT
      p.id, p.contract_no, p.status, p.start_date, p.due_date,
      p.amount_srd, p.amount_eur, p.amount_usd,
      c.full_name, c.id_type, c.id_number
    FROM pawns p
    JOIN customers c ON c.id = p.customer_id
    $where
    ORDER BY p.created_at DESC
    LIMIT 200
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  respond(["rows" => $st->fetchAll()]);
}

if ($action === "details") {
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) respond(["error" => "Invalid id"], 400);

  $st = $pdo->prepare("
    SELECT
      p.*, c.full_name, c.phone, c.address, c.id_type, c.id_number
    FROM pawns p
    JOIN customers c ON c.id = p.customer_id
    WHERE p.id = :id
  ");
  $st->execute([":id" => $id]);
  $pawn = $st->fetch();
  if (!$pawn) respond(["error" => "Not found"], 404);

  $st2 = $pdo->prepare("SELECT * FROM pawn_items WHERE pawn_id = :id ORDER BY id ASC");
  $st2->execute([":id" => $id]);
  $items = $st2->fetchAll();

  respond(["pawn" => $pawn, "items" => $items]);
}

if ($action === "create") {
  $data = json_input();

  // Basic validation
  $full_name   = trim($data["full_name"] ?? "");
  $id_type     = $data["id_type"] ?? "ID";
  $id_number   = trim($data["id_number"] ?? "");
  $phone       = trim($data["phone"] ?? "");
  $address     = trim($data["address"] ?? "");

  $contract_no = trim($data["contract_no"] ?? "");
  $start_date  = $data["start_date"] ?? "";
  $due_date    = $data["due_date"] ?? "";
  $interest_pct= floatval($data["interest_pct"] ?? 0);
  $notes       = trim($data["notes"] ?? "");

  $amount_srd  = floatval($data["amount_srd"] ?? 0);
  $amount_eur  = floatval($data["amount_eur"] ?? 0);
  $amount_usd  = floatval($data["amount_usd"] ?? 0);

  $fees_srd    = floatval($data["fees_srd"] ?? 0);
  $fees_eur    = floatval($data["fees_eur"] ?? 0);
  $fees_usd    = floatval($data["fees_usd"] ?? 0);

  $item_category = $data["item_category"] ?? "OTHER";
  $item_desc     = trim($data["item_description"] ?? "");
  $serial_no     = trim($data["serial_no"] ?? "");
  $weight_g      = $data["weight_g"] === "" ? null : (floatval($data["weight_g"] ?? 0));
  $purity        = trim($data["purity"] ?? "");

  if ($full_name === "" || $id_number === "" || $contract_no === "" || $start_date === "" || $due_date === "" || $item_desc === "") {
    respond(["error" => "Missing required fields"], 400);
  }

  try {
    $pdo->beginTransaction();

    // Create or fetch customer by (id_type, id_number)
    $st = $pdo->prepare("SELECT id FROM customers WHERE id_type = :id_type AND id_number = :id_number");
    $st->execute([":id_type" => $id_type, ":id_number" => $id_number]);
    $cust = $st->fetch();

    if ($cust) {
      $customer_id = intval($cust["id"]);
      // Optionally update name/phone/address
      $up = $pdo->prepare("
        UPDATE customers
        SET full_name = :full_name, phone = :phone, address = :address
        WHERE id = :id
      ");
      $up->execute([
        ":full_name" => $full_name,
        ":phone" => ($phone === "" ? null : $phone),
        ":address" => ($address === "" ? null : $address),
        ":id" => $customer_id
      ]);
    } else {
      $ins = $pdo->prepare("
        INSERT INTO customers (full_name, id_type, id_number, phone, address)
        VALUES (:full_name, :id_type, :id_number, :phone, :address)
      ");
      $ins->execute([
        ":full_name" => $full_name,
        ":id_type" => $id_type,
        ":id_number" => $id_number,
        ":phone" => ($phone === "" ? null : $phone),
        ":address" => ($address === "" ? null : $address),
      ]);
      $customer_id = intval($pdo->lastInsertId());
    }

    // Insert pawn contract
    $insPawn = $pdo->prepare("
      INSERT INTO pawns (
        contract_no, customer_id, status, start_date, due_date,
        amount_srd, amount_eur, amount_usd,
        interest_pct,
        fees_srd, fees_eur, fees_usd,
        notes
      )
      VALUES (
        :contract_no, :customer_id, 'OPEN', :start_date, :due_date,
        :amount_srd, :amount_eur, :amount_usd,
        :interest_pct,
        :fees_srd, :fees_eur, :fees_usd,
        :notes
      )
    ");
    $insPawn->execute([
      ":contract_no" => $contract_no,
      ":customer_id" => $customer_id,
      ":start_date" => $start_date,
      ":due_date" => $due_date,
      ":amount_srd" => $amount_srd,
      ":amount_eur" => $amount_eur,
      ":amount_usd" => $amount_usd,
      ":interest_pct" => $interest_pct,
      ":fees_srd" => $fees_srd,
      ":fees_eur" => $fees_eur,
      ":fees_usd" => $fees_usd,
      ":notes" => ($notes === "" ? null : $notes),
    ]);
    $pawn_id = intval($pdo->lastInsertId());

    // Insert item
    $insItem = $pdo->prepare("
      INSERT INTO pawn_items (pawn_id, category, description, serial_no, weight_g, purity)
      VALUES (:pawn_id, :category, :description, :serial_no, :weight_g, :purity)
    ");
    $insItem->execute([
      ":pawn_id" => $pawn_id,
      ":category" => $item_category,
      ":description" => $item_desc,
      ":serial_no" => ($serial_no === "" ? null : $serial_no),
      ":weight_g" => $weight_g,
      ":purity" => ($purity === "" ? null : $purity),
    ]);

    $pdo->commit();
    respond(["ok" => true, "pawn_id" => $pawn_id]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    // avoid leaking internals
    respond(["error" => "Create failed (maybe duplicate contract_no?)"], 400);
  }
}

if ($action === "update_status") {
  $data = json_input();
  $id = intval($data["id"] ?? 0);
  $status = $data["status"] ?? "OPEN";

  $allowed = ["OPEN","REDEEMED","EXPIRED","SOLD"];
  if ($id <= 0 || !in_array($status, $allowed, true)) {
    respond(["error" => "Invalid request"], 400);
  }

  $st = $pdo->prepare("UPDATE pawns SET status = :status WHERE id = :id");
  $st->execute([":status" => $status, ":id" => $id]);
  respond(["ok" => true]);
}

respond(["error" => "Unknown action"], 404);
