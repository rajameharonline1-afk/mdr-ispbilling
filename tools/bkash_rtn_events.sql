-- bKash RTN (Real-time Payment Notifications) events table
-- Separate from PGW (Tokenized Checkout) flow tables.

CREATE TABLE IF NOT EXISTS `bkash_rtn_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_hash` CHAR(64) NOT NULL,
  `event_type` VARCHAR(64) DEFAULT NULL,
  `event_id` VARCHAR(128) DEFAULT NULL,
  `payment_id` VARCHAR(64) DEFAULT NULL,
  `trx_id` VARCHAR(64) DEFAULT NULL,
  `status` VARCHAR(32) DEFAULT NULL,
  `amount` DECIMAL(12,2) DEFAULT NULL,
  `payer_msisdn` VARCHAR(32) DEFAULT NULL,
  `merchant_invoice_number` VARCHAR(64) DEFAULT NULL,
  `raw_body` MEDIUMTEXT,
  `headers_json` MEDIUMTEXT,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remote_ip` VARCHAR(64) DEFAULT NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT 0,
  `process_attempts` INT NOT NULL DEFAULT 0,
  `processed_at` DATETIME DEFAULT NULL,
  `applied_client_id` INT DEFAULT NULL,
  `applied_amount` DECIMAL(12,2) DEFAULT NULL,
  `last_error` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_hash` (`event_hash`),
  KEY `idx_processed_received` (`processed`, `received_at`),
  KEY `idx_trx` (`trx_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_ref` (`merchant_invoice_number`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

