-- Update existing activity_logs table to remove ip_address column
ALTER TABLE activity_logs DROP COLUMN IF EXISTS ip_address; 