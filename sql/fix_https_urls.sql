-- Fix HTTPS URLs for PAHO Jitsi Televisit
-- This script updates any HTTP URLs to HTTPS in the database
-- Run this after enabling HTTPS on your OpenEMR installation

-- Update room URLs from HTTP to HTTPS
UPDATE paho_jitsi_televisit_rooms 
SET room_url = REPLACE(room_url, 'http://', 'https://')
WHERE room_url LIKE 'http://%';

-- Update base URL configuration if it's HTTP
UPDATE globals 
SET gl_value = REPLACE(gl_value, 'http://', 'https://')
WHERE gl_name = 'paho_jitsi_base_url' 
  AND gl_value LIKE 'http://%';

-- Verify the changes
SELECT 'Room URLs' as type, COUNT(*) as count, 
       SUM(CASE WHEN room_url LIKE 'https://%' THEN 1 ELSE 0 END) as https_count,
       SUM(CASE WHEN room_url LIKE 'http://%' THEN 1 ELSE 0 END) as http_count
FROM paho_jitsi_televisit_rooms
UNION ALL
SELECT 'Base URL Config' as type, COUNT(*) as count,
       SUM(CASE WHEN gl_value LIKE 'https://%' THEN 1 ELSE 0 END) as https_count,
       SUM(CASE WHEN gl_value LIKE 'http://%' THEN 1 ELSE 0 END) as http_count
FROM globals
WHERE gl_name = 'paho_jitsi_base_url';

