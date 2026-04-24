<?php
/**
 * Patient Portal Join Room Page
 * Displays embedded Jitsi Meet for patient
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Common\Session\SessionWrapperFactory;

// Use same session verification as portal/home.php
require_once(__DIR__ . '/../../../../../vendor/autoload.php');
$globalsBag = OEGlobalsBag::getInstance();

// Set landing page before verify_session.php
$landingpage = "index.php?site=" . urlencode($globalsBag->getString('site_id') ?: 'default');
require_once(__DIR__ . '/../../../../../portal/verify_session.php');

$srcdir = $globalsBag->getString('srcdir');
require_once(__DIR__ . '/../../../../../interface/globals.php');

$session = SessionWrapperFactory::getInstance()->getWrapper();
$pid = $session->get('pid') ?? $_SESSION['pid'] ?? null;

if (empty($pid)) {
    http_response_code(403);
    die(xlt('Access Denied. Please log in to the patient portal.'));
}

// Load module classes
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . '/../../src'
);

use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitRoomService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;

$pcEid = $_GET['eid'] ?? null;
if (!$pcEid) {
    http_response_code(400);
    die(xlt('Invalid appointment ID'));
}

try {
    $room = TelevisitRoomService::getRoomByAppointment($pcEid);
    if (!$room) {
        http_response_code(404);
        die(xlt('Room not found for this appointment'));
    }

    if ($room['pid'] != $pid) {
        http_response_code(403);
        die(xlt('Access Denied. This appointment does not belong to you.'));
    }

    $baseUrl = TelevisitConfigService::getBaseUrl();
    $roomUrl = rtrim($baseUrl, '/') . '/' . $room['room_name'];
} catch (\Exception $e) {
    error_log("PAHO Jitsi Televisit Portal Error: " . $e->getMessage());
    http_response_code(500);
    die(xlt('Error loading televisit room. Please contact support.'));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Join Televisit'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #0066cc; color: white; padding: 10px 15px; }
        .header h3 { margin: 0; font-size: 16px; }
        .info-box { background: #e6f2ff; padding: 20px; text-align: center; font-size: 14px; color: #004d99; }
    </style>
</head>
<body>
    <div class="header">
        <h3><?php echo xlt('Televisit'); ?></h3>
    </div>
    <div class="info-box">
        <strong><?php echo xlt('Redirecting to Televisit...'); ?></strong>
        <br><br>
        <?php echo xlt('Please allow camera and microphone access when prompted.'); ?>
        <br><br>
        <a href="<?php echo attr($roomUrl); ?>">
            <?php echo xlt('Click here if you are not redirected automatically'); ?>
        </a>
    </div>
    <script>
        window.location.href = <?php echo json_encode($roomUrl); ?>;
    </script>
</body>
</html>
