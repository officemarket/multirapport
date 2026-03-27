-- ============================================================================
-- SQL Script for MultiRapport Module
-- Creates extrafield for invoice credit status
-- ============================================================================

-- Add extrafield for invoice credit status
-- This allows tracking which invoices are marked as Credit

-- First check if the extrafield already exists
INSERT INTO llx_extrafields (name, entity, elementtype, tms, label, type, size, fieldunique, fieldrequired, perms, pos, default_value, computed, printable, printablelabel, unique_key, param, langfile, enabled, help)
SELECT 'multirapport_iscredit', 1, 'facture', NOW(), 'Credit Status', 'boolean', '1', 0, 0, '', 100, '0', '', 0, '', NULL, '', '', 1, 'Indicates if the invoice is marked as Credit'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_extrafields WHERE name = 'multirapport_iscredit' AND elementtype = 'facture');

-- Create index for better performance on credit status queries
-- Note: This will be created by Dolibarr extrafield system automatically
