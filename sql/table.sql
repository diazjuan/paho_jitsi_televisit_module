-- PAHO Jitsi Televisit Module - Database Schema
-- Version: 1.0.0
-- Uses SQL Upgrade Service syntax for safe installation

#IfNotTable paho_jitsi_televisit_rooms
CREATE TABLE `paho_jitsi_televisit_rooms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pc_eid` int(11) unsigned NOT NULL COMMENT 'Appointment ID from openemr_postcalendar_events',
  `encounter_id` bigint(20) DEFAULT NULL COMMENT 'Encounter ID if encounter created',
  `pid` bigint(20) NOT NULL COMMENT 'Patient ID',
  `provider_id` int(11) NOT NULL COMMENT 'Provider ID',
  `room_name` varchar(255) NOT NULL COMMENT 'Jitsi room identifier',
  `room_url` text NOT NULL COMMENT 'Full Jitsi room URL',
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `jwt_secret` varchar(255) DEFAULT NULL COMMENT 'Future: JWT secret for this room',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_appointment` (`pc_eid`),
  KEY `idx_patient` (`pid`),
  KEY `idx_provider` (`provider_id`),
  KEY `idx_room_name` (`room_name`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_appointment` FOREIGN KEY (`pc_eid`) REFERENCES `openemr_postcalendar_events` (`pc_eid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores Jitsi room mappings for televisit appointments';
#EndIf

-- Insert default configuration values into globals table
-- Default base URL is the staging Jitsi host; admins override it in module config
-- (mirrors the fallback in TelevisitConfigService::getBaseUrl).
#IfNotRow globals gl_name paho_jitsi_base_url
INSERT INTO `globals` (`gl_name`, `gl_value`) VALUES ('paho_jitsi_base_url', 'https://169.61.173.4:8440');
#EndIf

#IfNotRow globals gl_name paho_jitsi_televisit_category_id
INSERT INTO `globals` (`gl_name`, `gl_value`) VALUES ('paho_jitsi_televisit_category_id', '');
#EndIf

#IfNotRow globals gl_name paho_jitsi_room_naming
INSERT INTO `globals` (`gl_name`, `gl_value`) VALUES ('paho_jitsi_room_naming', 'deterministic');
#EndIf

#IfNotRow globals gl_name paho_jitsi_jwt_secret
INSERT INTO `globals` (`gl_name`, `gl_value`) VALUES ('paho_jitsi_jwt_secret', '');
#EndIf

#IfNotRow globals gl_name paho_jitsi_jwt_app_id
INSERT INTO `globals` (`gl_name`, `gl_value`) VALUES ('paho_jitsi_jwt_app_id', 'paho-jitsi-televisit');
#EndIf

-- Check if Televisit category exists, create if not
#IfNotRow openemr_postcalendar_categories pc_constant_id televisit
INSERT INTO `openemr_postcalendar_categories` (
    `pc_constant_id`, `pc_catname`, `pc_catcolor`, `pc_catdesc`,
    `pc_recurrtype`, `pc_enddate`, `pc_recurrspec`, `pc_recurrfreq`, `pc_duration`,
    `pc_end_date_flag`, `pc_end_date_type`, `pc_end_date_freq`, `pc_end_all_day`,
    `pc_dailylimit`, `pc_cattype`, `pc_active`, `pc_seq`, `aco_spec`
)
VALUES (
    'televisit', 'Televisit', '#0066cc', 'Teleconsultation appointment using Jitsi Meet',
    '0', NULL, 'a:5:{s:17:"event_repeat_freq";s:1:"0";s:22:"event_repeat_freq_type";s:1:"0";s:19:"event_repeat_on_num";s:1:"1";s:19:"event_repeat_on_day";s:1:"0";s:20:"event_repeat_on_freq";s:1:"0";}',
    '0', '1800', '0', NULL, '0', '0', '0', '0', '1', '10', 'encounters|notes'
);
#EndIf
