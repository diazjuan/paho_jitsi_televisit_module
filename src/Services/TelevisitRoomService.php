<?php

/**
 * Televisit Room Service
 * Handles Jitsi room generation and management
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

namespace OpenEMR\Modules\PahoJitsiTelevisit\Services;

class TelevisitRoomService
{
    /**
     * Generate deterministic room name based on patient, provider, and appointment
     *
     * @param int $pid Patient ID
     * @param int $providerId Provider ID
     * @param string $appointmentDate Appointment date (Y-m-d format)
     * @param string $appointmentTime Appointment time (H:i format)
     * @return string Room name
     */
    public static function generateRoomName($pid, $providerId, $appointmentDate, $appointmentTime)
    {
        // Format: televisit-{pid}-{provider}-{date}-{time}
        // Remove special characters and ensure URL-safe format
        $datePart = str_replace('-', '', (string)$appointmentDate);
        $timePart = str_replace(':', '', (string)$appointmentTime);
        
        $roomName = sprintf(
            'televisit-%d-%d-%s-%s',
            (int)$pid,
            (int)$providerId,
            $datePart,
            $timePart
        );
        
        // Ensure room name is URL-safe (lowercase, alphanumeric and hyphens only)
        $roomName = strtolower(preg_replace('/[^a-z0-9-]/', '', $roomName));
        
        return $roomName;
    }

    /**
     * Get or create room for an appointment
     *
     * @param int $pcEid Appointment ID (pc_eid)
     * @param int $pid Patient ID
     * @param int $providerId Provider ID
     * @param string $appointmentDate Appointment date (Y-m-d format)
     * @param string $appointmentTime Appointment time (H:i:s format)
     * @return array Room data
     */
    public static function getOrCreateRoom($pcEid, $pid, $providerId, $appointmentDate, $appointmentTime)
    {
        // Check if room already exists
        $existing = sqlQuery(
            "SELECT * FROM paho_jitsi_televisit_rooms WHERE pc_eid = ?",
            [$pcEid]
        );

        if ($existing) {
            return $existing;
        }

        // Generate room name
        $timePart = substr((string)$appointmentTime, 0, 5); // Get HH:MM format
        $roomName = self::generateRoomName($pid, $providerId, $appointmentDate, $timePart);

        // Get base URL and ensure it's HTTPS
        $baseUrl = TelevisitConfigService::getBaseUrl();
        // Force HTTPS if not already
        if (stripos($baseUrl, 'http://') === 0) {
            $baseUrl = str_replace('http://', 'https://', $baseUrl);
        } elseif (stripos($baseUrl, 'https://') !== 0) {
            // If no scheme, add https://
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }
        $roomUrl = rtrim($baseUrl, '/') . '/' . $roomName;

        // Create room record
        $sql = "INSERT INTO paho_jitsi_televisit_rooms 
                (pc_eid, pid, provider_id, room_name, room_url, status) 
                VALUES (?, ?, ?, ?, ?, 'scheduled')";
        
        sqlStatement($sql, [$pcEid, $pid, $providerId, $roomName, $roomUrl]);

        // Return created room
        return sqlQuery(
            "SELECT * FROM paho_jitsi_televisit_rooms WHERE pc_eid = ?",
            [$pcEid]
        );
    }

    /**
     * Get room by appointment ID
     *
     * @param int $pcEid Appointment ID
     * @return array|null Room data or null if not found
     */
    public static function getRoomByAppointment($pcEid)
    {
        $result = sqlQuery(
            "SELECT * FROM paho_jitsi_televisit_rooms WHERE pc_eid = ?",
            [$pcEid]
        );
        
        if ($result) {
            // Ensure room URL is HTTPS (fix any HTTP URLs stored in database)
            if (!empty($result['room_url']) && stripos($result['room_url'], 'http://') === 0) {
                $result['room_url'] = str_replace('http://', 'https://', $result['room_url']);
                // Update in database for future use
                sqlStatement(
                    "UPDATE paho_jitsi_televisit_rooms SET room_url = ? WHERE pc_eid = ?",
                    [$result['room_url'], $pcEid]
                );
            }
        }
        
        return $result ?: null;
    }

    /**
     * Update room status
     *
     * @param int $pcEid Appointment ID
     * @param string $status New status
     * @return bool
     */
    public static function updateRoomStatus($pcEid, $status)
    {
        $allowedStatuses = ['scheduled', 'active', 'completed', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $updates = ['status = ?'];
        $params = [$status];

        if ($status === 'active') {
            // Validate sqlQuery result before accessing key
            $currentRoom = sqlQuery("SELECT started_at FROM paho_jitsi_televisit_rooms WHERE pc_eid = ?", [$pcEid]);
            if ($currentRoom && empty($currentRoom['started_at'])) {
                $updates[] = 'started_at = NOW()';
            }
        }

        if ($status === 'completed' || $status === 'cancelled') {
            $updates[] = 'ended_at = NOW()';
        }

        $sql = "UPDATE paho_jitsi_televisit_rooms SET " . implode(', ', $updates) . " WHERE pc_eid = ?";
        $params[] = $pcEid;

        return sqlStatement($sql, $params) !== false;
    }

    /**
     * Resolve encounter row for this televisit room when encounter_id is set and matches the patient.
     * Avoids treating "any encounter today" as belonging to this appointment.
     *
     * @param array<string,mixed> $room Row from paho_jitsi_televisit_rooms
     * @param int|string|null     $pid  Patient id
     * @return array<string,mixed>|null array with keys encounter, id or null
     */
    public static function getLinkedEncounterRow(array $room, $pid): ?array
    {
        if (empty($room['encounter_id']) || $pid === null || $pid === '') {
            return null;
        }
        $enc = (int) $room['encounter_id'];
        $pidInt = (int) $pid;
        if ($enc <= 0 || $pidInt <= 0) {
            return null;
        }
        $row = sqlQuery(
            'SELECT fe.encounter, fe.id FROM form_encounter fe WHERE fe.pid = ? AND fe.encounter = ? LIMIT 1',
            [$pidInt, $enc]
        );
        if (empty($row) || empty($row['encounter'])) {
            return null;
        }
        return $row;
    }

    /**
     * Link encounter to room
     *
     * @param int $pcEid Appointment ID
     * @param int $encounterId Encounter ID
     * @return bool
     */
    public static function linkEncounter($pcEid, $encounterId)
    {
        return sqlStatement(
            "UPDATE paho_jitsi_televisit_rooms SET encounter_id = ? WHERE pc_eid = ?",
            [$encounterId, $pcEid]
        ) !== false;
    }

    /**
     * Get room by encounter ID
     *
     * @param int $encounterId Encounter ID
     * @return array|null Room data or null if not found
     */
    public static function getRoomByEncounter($encounterId)
    {
        $result = sqlQuery(
            "SELECT * FROM paho_jitsi_televisit_rooms WHERE encounter_id = ?",
            [$encounterId]
        );
        return $result ?: null;
    }
}
