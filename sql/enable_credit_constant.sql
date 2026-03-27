-- Enable Credit status feature in Dolibarr
-- Run this SQL query in your Dolibarr database

INSERT INTO llx_const (name, entity, value, type, visible, note) 
SELECT 'MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED', 1, '1', 'chaine', 1, 'Enable invoice credit status feature'
WHERE NOT EXISTS (SELECT 1 FROM llx_const WHERE name = 'MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED');

-- Verify
SELECT name, value FROM llx_const WHERE name = 'MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED';
