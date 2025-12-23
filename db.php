<?php
// db.php - PostgreSQL (Neon)
$config = require __DIR__ . "/config.php";

try {
  $pdo = new PDO($config["db_url"], null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  header("Content-Type: application/json");
  echo json_encode(["error" => "PostgreSQL connection failed"]);
  exit;
}
