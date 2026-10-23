-- =============================================================================
--  Database Cleanup — Remove Unused Tables
-- =============================================================================
--  Run this ONCE in phpMyAdmin to remove tables that are no longer used.
--
--  ⚠️ WARNING: This is DESTRUCTIVE and cannot be undone.
--  ⚠️ Backup your database first (phpMyAdmin → Export → Go).
-- =============================================================================

-- Remove unused tables
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS contact_inquiries;

-- =============================================================================
--  End of cleanup
-- =============================================================================