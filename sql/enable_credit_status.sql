-- SQL to manually enable Credit status feature
-- Run this in your database if the constant was not created

INSERT INTO llx_const (name, entity, value, type, visible, note) 
SELECT 'MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED', 1, '1', 'chaine', 1, 'Enable Credit status for invoices'
WHERE NOT EXISTS (SELECT 1 FROM llx_const WHERE name = 'MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED');

-- Verify the constant was created
SELECT * FROM llx_const WHERE name LIKE '%MULTIRAPPORT%';
