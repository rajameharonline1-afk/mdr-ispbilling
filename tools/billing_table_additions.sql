-- Optional schema additions for billing table columns.
-- Review current schema before running and remove any columns that already exist.

ALTER TABLE clients
  ADD COLUMN client_type VARCHAR(100) NULL,
  ADD COLUMN protocol_type VARCHAR(100) NULL,
  ADD COLUMN mikrotik_status VARCHAR(50) NULL,
  ADD COLUMN billing_status VARCHAR(100) NULL,
  ADD COLUMN custom_status VARCHAR(100) NULL,
  ADD COLUMN connection_type VARCHAR(100) NULL,
  ADD COLUMN ip_address VARCHAR(100) NULL;

ALTER TABLE invoices
  ADD COLUMN vat DECIMAL(10,2) NULL;
