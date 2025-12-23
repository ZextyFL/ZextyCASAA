CREATE DATABASE IF NOT EXISTS pawnshop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pawnshop;

-- Customers
CREATE TABLE customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150) NOT NULL,
  id_type       ENUM('ID','PASSPORT','DRIVER_LICENSE') NOT NULL DEFAULT 'ID',
  id_number     VARCHAR(80) NOT NULL,
  phone         VARCHAR(30) NULL,
  address       VARCHAR(200) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_id (id_type, id_number)
);

-- Pawns / Contracts
CREATE TABLE pawns (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  contract_no     VARCHAR(40) NOT NULL,
  customer_id     INT NOT NULL,
  status          ENUM('OPEN','REDEEMED','EXPIRED','SOLD') NOT NULL DEFAULT 'OPEN',
  start_date      DATE NOT NULL,
  due_date        DATE NOT NULL,

  -- Base amounts (you can decide base is SRD, or just store all three directly)
  amount_srd      DECIMAL(18,2) NOT NULL DEFAULT 0,
  amount_eur      DECIMAL(18,2) NOT NULL DEFAULT 0,
  amount_usd      DECIMAL(18,2) NOT NULL DEFAULT 0,

  interest_pct    DECIMAL(6,2) NOT NULL DEFAULT 0,  -- e.g. 10.00
  fees_srd        DECIMAL(18,2) NOT NULL DEFAULT 0,
  fees_eur        DECIMAL(18,2) NOT NULL DEFAULT 0,
  fees_usd        DECIMAL(18,2) NOT NULL DEFAULT 0,

  notes           TEXT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_contract (contract_no),
  KEY idx_status (status),
  KEY idx_due (due_date),

  CONSTRAINT fk_pawn_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE RESTRICT
);

-- Items per contract (gold/electronics/etc)
CREATE TABLE pawn_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  pawn_id       INT NOT NULL,
  category      ENUM('GOLD','JEWELRY','ELECTRONICS','OTHER') NOT NULL DEFAULT 'OTHER',
  description   VARCHAR(250) NOT NULL,
  serial_no     VARCHAR(120) NULL,
  weight_g      DECIMAL(10,3) NULL,   -- grams (for gold)
  purity        VARCHAR(30) NULL,     -- e.g. 24K, 18K, 750
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_pawn (pawn_id),
  CONSTRAINT fk_item_pawn
    FOREIGN KEY (pawn_id) REFERENCES pawns(id)
    ON DELETE CASCADE
);
