<?php
require_once(__DIR__ . '/../../../../globals.php');

use OpenEMR\Common\Acl\AclMain;

if (!AclMain::aclCheckCore('patients', 'appt')) {
    die('Access Denied');
}

$rooms = sqlStatement("
    SELECT r.*, 
           CONCAT(p.fname, ' ', p.lname) as patient_name,
           CONCAT(u.fname, ' ', u.lname) as provider_name,
           pc.pc_eventDate, pc.pc_startTime, pc.pc_title
    FROM paho_jitsi_televisit_rooms r
    LEFT JOIN openemr_postcalendar_events pc ON r.pc_eid = pc.pc_eid
    LEFT JOIN patient_data p ON r.pid = p.pid
    LEFT JOIN users u ON r.provider_id = u.id
    ORDER BY pc.pc_eventDate DESC, pc.pc_startTime DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Televisit Rooms</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #0066cc; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #e6f2ff; }
        .btn { padding: 8px 15px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 14px; }
        .btn:hover { background: #0052a3; }
        .status-active { color: green; font-weight: bold; }
        .status-completed { color: gray; }
    </style>
</head>
<body>
    <h2>🎥 Televisit Rooms</h2>
    <p>Click "Join Room" to open the Jitsi Meet video conference for each appointment.</p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Provider</th>
                <th>Room Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 0;
            while ($room = sqlFetchArray($rooms)): 
                $count++;
            ?>
            <tr>
                <td><?php echo text($room['pc_eventDate']); ?></td>
                <td><?php echo text(substr($room['pc_startTime'], 0, 5)); ?></td>
                <td><?php echo text($room['patient_name']); ?></td>
                <td><?php echo text($room['provider_name']); ?></td>
                <td><small><?php echo text($room['room_name']); ?></small></td>
                <td class="status-<?php echo attr($room['status']); ?>">
                    <?php echo text(ucfirst($room['status'])); ?>
                </td>
                <td>
                    <a href="<?php echo attr($room['room_url']); ?>" target="_blank" class="btn">
                        🎥 Join Room
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count == 0): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                    No televisit rooms found. Create a "Teleconsulta" appointment to generate a room.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
