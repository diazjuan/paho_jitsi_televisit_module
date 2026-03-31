<?php

/**
 * Patient Portal Join Room Page
 * Displays embedded Jitsi Meet for patient
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

// Will start the (patient) portal OpenEMR session/cookie.
require_once(__DIR__ . '/../../../../../src/Common/Session/SessionUtil.php');
OpenEMR\Common\Session\SessionUtil::portalSessionStart();

if (isset($_SESSION['pid']) && isset($_SESSION['patient_portal_onsite_two'])) {
    $pid = $_SESSION['pid'];
    $ignoreAuth_onsite_portal = true;
    require_once(__DIR__ . '/../../../../../interface/globals.php');
} else {
    OpenEMR\Common\Session\SessionUtil::portalSessionCookieDestroy();
    $ignoreAuth = false;
    require_once(__DIR__ . '/../../../../../interface/globals.php');
    if (!isset($_SESSION['authUserID'])) {
        die(xlt('Access Denied. Please log in to the patient portal.'));
    }
}

use OpenEMR\Core\ModulesClassLoader;

// Load module classes
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . '/../../src'
);

use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitRoomService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;

// Check if patient is logged in (portal context)
if (!isset($_SESSION['pid']) || empty($_SESSION['pid'])) {
    http_response_code(403);
    die(xlt('Access Denied. Please log in to the patient portal.'));
}

$pcEid = $_GET['eid'] ?? null;
if (!$pcEid) {
    http_response_code(400);
    die(xlt('Invalid appointment ID'));
}

try {
    // Get room data
    $room = TelevisitRoomService::getRoomByAppointment($pcEid);
    if (!$room) {
        http_response_code(404);
        die(xlt('Room not found for this appointment'));
    }

    // Verify this appointment belongs to the logged-in patient
    if ($room['pid'] != $_SESSION['pid']) {
        http_response_code(403);
        die(xlt('Access Denied. This appointment does not belong to you.'));
    }

    // Get base URL
    $baseUrl = TelevisitConfigService::getBaseUrl();
    $roomUrl = rtrim($baseUrl, '/') . '/' . $room['room_name'];
} catch (\Exception $e) {
    error_log("PAHO Jitsi Televisit Portal Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    die(xlt('Error loading televisit room. Please contact support.') . ' (' . htmlspecialchars($e->getMessage()) . ')');
}

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
        .info-box {
            background: #e6f2ff;
            padding: 10px 15px;
            border-bottom: 1px solid #b3d1ff;
            font-size: 14px;
            color: #004d99;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3><?php echo xlt('Televisit'); ?></h3>
        </div>
        <div class="info-box" style="text-align: center; padding: 20px;">
            <i class="fa fa-info-circle"></i> 
            <strong><?php echo xlt('Redirecting to Televisit...'); ?></strong>
            <br><br>
            <?php echo xlt('Please allow camera and microphone access when prompted.'); ?>
            <br><br>
            <a href="<?php echo attr($roomUrl); ?>" style="color: #0066cc; text-decoration: underline;">
                <?php echo xlt('Click here if you are not redirected automatically'); ?>
            </a>
        </div>
    </div>
    <script>
        // Redirect to Jitsi room (mixed content prevents iframe embedding HTTP->HTTPS)
        window.location.href = <?php echo json_encode($roomUrl); ?>;
    </script>
</body>
</html>
