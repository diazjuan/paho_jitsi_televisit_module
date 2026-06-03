<?php

/**
 * Provider Join Room Page
 * Displays embedded Jitsi Meet for provider
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

require_once(__DIR__ . '/../../../../globals.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\ModulesClassLoader;

// Load module classes
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . '/../../src'
);

use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitRoomService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitTokenService;

// Check access control - must be provider/staff
if (!AclMain::aclCheckCore('patients', 'appt', '', ['write', 'wsome'])) {
    echo xlt('Access Denied');
    exit;
}

$pcEid = $_GET['eid'] ?? null;
if (!$pcEid) {
    echo xlt('Invalid appointment ID');
    exit;
}

// Get room data
$room = TelevisitRoomService::getRoomByAppointment($pcEid);
if (!$room) {
    echo xlt('Room not found for this appointment');
    exit;
}

// Update status to active
TelevisitRoomService::updateRoomStatus($pcEid, 'active');

// Mint a fresh moderator token for this provider and build the room URL.
// The provider is the logged-in staff member; the display name is non-security.
$providerName = TelevisitRoomService::getProviderDisplayName($room['provider_id'] ?? null);
$roomUrl = TelevisitTokenService::buildAuthenticatedRoomUrl($room['room_name'], $providerName, true);

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Join Televisit'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        .container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: #0066cc;
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h3 {
            margin: 0;
            font-size: 16px;
        }
        .jitsi-container {
            flex: 1;
            position: relative;
            background: #000;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3><?php echo xlt('Televisit Room'); ?>: <?php echo text($room['room_name']); ?></h3>
        </div>
        <div class="jitsi-container" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
            <div style="text-align: center; padding: 40px;">
                <h4><?php echo xlt('Opening Televisit Room...'); ?></h4>
                <p><?php echo xlt('Please allow camera and microphone access when prompted.'); ?></p>
                <br>
                <a href="<?php echo attr($roomUrl); ?>" 
                   style="display: inline-block; padding: 15px 30px; background: #0066cc; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    <i class="fa fa-video-camera"></i> <?php echo xlt('Open Televisit Room'); ?>
                </a>
                <br><br>
                <small style="color: #666;">
                    <?php echo xlt('If the room does not open automatically, click the button above'); ?>
                </small>
            </div>
        </div>
    </div>
    <script>
        // Open Jitsi in new window/tab (mixed content prevents iframe embedding HTTP->HTTPS)
        // Try to open in popup first, fallback to redirect
        var jitsiWindow = window.open(<?php echo json_encode($roomUrl); ?>, 'jitsi_televisit', 'width=1280,height=720,resizable=yes,scrollbars=yes');
        if (!jitsiWindow || jitsiWindow.closed || typeof jitsiWindow.closed == 'undefined') {
            // Popup blocked, redirect instead
            setTimeout(function() {
                window.location.href = <?php echo json_encode($roomUrl); ?>;
            }, 2000);
        }
    </script>
</body>
</html>

