-- Manual Configuration SQL for PAHO Jitsi Televisit Module
-- Run these commands in your MySQL/MariaDB database

-- 1. Set Jitsi Base URL
INSERT INTO `globals` (`gl_name`, `gl_value`) 
VALUES ('paho_jitsi_base_url', 'https://169.61.173.4:8440')
ON DUPLICATE KEY UPDATE `gl_value` = 'https://169.61.173.4:8440';

-- 2. Find and set the Televisit category ID
-- First, find the category ID:
SELECT pc_catid, pc_catname, pc_constant_id 
FROM openemr_postcalendar_categories 
WHERE pc_constant_id = 'televisit';

-- Then update the globals with that ID (replace X with the actual pc_catid from above):
INSERT INTO `globals` (`gl_name`, `gl_value`) 
VALUES ('paho_jitsi_televisit_category_id', 'X')
ON DUPLICATE KEY UPDATE `gl_value` = 'X';

-- 3. Set room naming strategy (already set, but you can verify):
INSERT INTO `globals` (`gl_name`, `gl_value`) 
VALUES ('paho_jitsi_room_naming', 'deterministic')
ON DUPLICATE KEY UPDATE `gl_value` = 'deterministic';

-- 4. Set JWT App ID (for future use):
INSERT INTO `globals` (`gl_name`, `gl_value`) 
VALUES ('paho_jitsi_jwt_app_id', 'paho-jitsi-televisit')
ON DUPLICATE KEY UPDATE `gl_value` = 'paho-jitsi-televisit';

-- Verify all configuration:
SELECT gl_name, gl_value 
FROM globals 
WHERE gl_name LIKE 'paho_jitsi%'
ORDER BY gl_name;

