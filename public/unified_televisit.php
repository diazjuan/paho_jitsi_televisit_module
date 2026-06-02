<?php

/**
 * Unified Televisit Provider Page
 * Combines Jitsi video and encounter form in one window
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

require_once(__DIR__ . '/../../../../globals.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\ModulesClassLoader;

// Check access control - must be provider/staff
if (!AclMain::aclCheckCore('patients', 'appt', '', ['write', 'wsome'])) {
    echo xlt('Access Denied');
    exit;
}

// Load module classes
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . '/../../src'
);

use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitRoomService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;

// Absolute URL for same-origin iframes/assets (must match page scheme or browser load fails).
function getAbsoluteHttpsUrl($relativePath) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
               || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    $scheme = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    // Remove port if it's standard HTTPS port
    if (strpos($host, ':443') !== false) {
        $host = str_replace(':443', '', $host);
    }
    
    $webroot = $GLOBALS['webroot'] ?? '';
    $fullPath = ltrim($relativePath, '/');
    if (!empty($webroot) && $webroot !== '/') {
        $fullPath = ltrim($webroot, '/') . '/' . ltrim($fullPath, '/');
    }
    
    return $scheme . $host . '/' . ltrim($fullPath, '/');
}

$pcEid = $_GET['eid'] ?? null;
if (!$pcEid) {
    echo xlt('Invalid appointment ID');
    exit;
}

try {
    // Get room data
    $room = TelevisitRoomService::getRoomByAppointment($pcEid);
    if (!$room) {
        throw new \Exception(xlt('Room not found for this appointment'));
    }

    // Update status to active
    TelevisitRoomService::updateRoomStatus($pcEid, 'active');

    // Get base URL. Respect the configured scheme: Jitsi runs over plain HTTP
    // locally (no SSL certs) and HTTPS in staging/production. Do NOT force HTTPS
    // here -- upgrading a local http:// config to a non-existent https:// endpoint
    // breaks both the embed and the popup. Only supply a default scheme when the
    // admin left it off. Mixed-content (https page + http Jitsi) is handled in JS.
    $baseUrl = TelevisitConfigService::getBaseUrl();
    if (stripos($baseUrl, 'http://') !== 0 && stripos($baseUrl, 'https://') !== 0) {
        $baseUrl = 'https://' . ltrim($baseUrl, '/');
    }
    $roomUrl = rtrim($baseUrl, '/') . '/' . $room['room_name'];
} catch (\Exception $e) {
    error_log("[PAHO Jitsi Televisit] Error in unified_televisit.php: " . $e->getMessage());
    die(xlt('Error loading televisit session. Please contact support. Details: ') . $e->getMessage());
}

try {
    $encounterNewTabShimUrl = '';
    // Get patient ID and encounter info
    $appointment = sqlQuery(
        "SELECT pc_pid, pc_aid FROM openemr_postcalendar_events WHERE pc_eid = ?",
        [$pcEid]
    );

    $pid = $appointment['pc_pid'] ?? $room['pid'] ?? null;
    $encounterUrl = '';
    $encounterExists = false;

    if ($pid) {
        $chromeShimBase = $GLOBALS['webroot'] . '/interface/modules/custom_modules/' . basename(dirname(__DIR__)) . '/public/televisit_encounter.php';
        // Only open an existing encounter when this televisit room is explicitly linked (paho_jitsi_televisit_rooms.encounter_id).
        // A blanket "any encounter today" match was wrong for televisit: it showed unrelated visits for the same patient.
        $encounter = TelevisitRoomService::getLinkedEncounterRow($room, $pid);

        if ($encounter) {
            $encounterExists = true;
            $encounterUrl = getAbsoluteHttpsUrl('/interface/patient_file/encounter/encounter_top.php?set_encounter=' . attr_url($encounter['encounter']) . '&set_pid=' . attr_url($pid));
            $encounterNewTabShimUrl = $chromeShimBase . '?' . http_build_query([
                'inner' => 'patient_file/encounter/encounter_top.php',
                'set_encounter' => $encounter['encounter'],
                'set_pid' => $pid,
            ], '', '&', PHP_QUERY_RFC3986);
        } else {
            // Link to create new encounter form
            $encounterUrl = getAbsoluteHttpsUrl('/interface/forms/newpatient/new.php?autoloaded=1&calenc=' . attr_url($pcEid) . '&pid=' . attr_url($pid));
            $encounterNewTabShimUrl = $chromeShimBase . '?' . http_build_query([
                'inner' => 'forms/newpatient/new.php',
                'autoloaded' => '1',
                'calenc' => $pcEid,
                'pid' => $pid,
            ], '', '&', PHP_QUERY_RFC3986);
        }
    }
} catch (\Exception $e) {
    error_log("[PAHO Jitsi Televisit] Error getting encounter info: " . $e->getMessage());
    // Continue with empty encounter URL - user can still use Jitsi
    $pid = $room['pid'] ?? null;
    $encounterUrl = $pid ? getAbsoluteHttpsUrl('/interface/forms/newpatient/new.php?autoloaded=1&calenc=' . attr_url($pcEid) . '&pid=' . attr_url($pid)) : '';
    $encounterNewTabShimUrl = '';
    if ($pid) {
        $encounterNewTabShimUrl = $GLOBALS['webroot'] . '/interface/modules/custom_modules/' . basename(dirname(__DIR__)) . '/public/televisit_encounter.php'
            . '?' . http_build_query([
                'inner' => 'forms/newpatient/new.php',
                'autoloaded' => '1',
                'calenc' => $pcEid,
                'pid' => $pid,
            ], '', '&', PHP_QUERY_RFC3986);
    }
    $encounterExists = false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Televisit - Room'); ?> <?php echo text($room['room_name']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo getAbsoluteHttpsUrl('/public/assets/fontawesome/css/font-awesome.min.css'); ?>">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        .unified-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
        }
        .header-bar {
            background: linear-gradient(to right, #0066cc, #0052a3);
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            z-index: 1000;
        }
        .header-bar h3 {
            margin: 0;
            font-size: 16px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .header-actions a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            font-size: 13px;
        }
        .header-actions a:hover {
            background: rgba(255,255,255,0.2);
        }
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .jitsi-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #000;
            border-right: 2px solid #0066cc;
        }
        .jitsi-container {
            flex: 1;
            position: relative;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .jitsi-placeholder {
            text-align: center;
            color: white;
            padding: 40px;
        }
        .jitsi-placeholder h4 {
            color: white;
            margin-bottom: 20px;
        }
        .jitsi-placeholder .btn-launch {
            display: inline-block;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px;
            cursor: pointer;
        }
        .jitsi-placeholder .btn-launch:hover {
            background: #218838;
            color: white;
        }
        .jitsi-iframe-container {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .jitsi-iframe-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .encounter-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f5f5f5;
        }
        .encounter-header {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .encounter-iframe-container {
            flex: 1;
            overflow: hidden;
        }
        .encounter-iframe-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .resizer {
            width: 4px;
            background: #0066cc;
            cursor: col-resize;
            flex-shrink: 0;
        }
        .resizer:hover {
            background: #0052a3;
        }
        .info-banner {
            background: #e6f2ff;
            padding: 8px 15px;
            border-bottom: 1px solid #b3d1ff;
            font-size: 12px;
            color: #004d99;
            flex-shrink: 0;
        }
        @media (max-width: 1024px) {
            .main-content {
                flex-direction: column;
            }
            .jitsi-section {
                height: 40vh;
                border-right: none;
                border-bottom: 2px solid #0066cc;
            }
            .resizer {
                height: 4px;
                width: 100%;
                cursor: row-resize;
            }
        }
    </style>
</head>
<body>
    <script>
<?php require $GLOBALS['srcdir'] . '/restoreSession.php'; ?>
if (typeof window.restoreSession !== 'function') {
    window.restoreSession = function () { return true; };
}
if (typeof window.xl !== 'function') {
    window.xl = function (s) { return s; };
}
if (typeof window.dlgopen !== 'function') {
    window.dlgopen = function (url, winname) {
        if (url) {
            window.open(url, winname || '_blank');
        }
        return null;
    };
}
if (typeof window.timed_out === 'undefined') {
    window.timed_out = false;
}
    </script>
    <script>
    // Encounter iframe's top is this popup (not main/tabs). OpenEMR newpatient + validation call:
    // top.restoreSession (from restoreSession.php above), top.left_nav, window.top.jsGlobals (submitme).
    // left_nav.loadFrame navigates #encounterFrame (duplicate Cancel, form Cancel, etc.).
    (function () {
        var webroot = <?php echo json_encode(rtrim($GLOBALS['webroot'] ?? '', '/')); ?>;
        var apptPid = <?php echo json_encode((string)($pid ?? '')); ?>;

        function pahoUnifiedResolveInterfaceUrl(url) {
            var u = (url || '').toString().trim();
            if (!u) {
                return u;
            }
            if (/^https?:\/\//i.test(u)) {
                return u;
            }
            u = u.replace(/^\//, '');
            var base = webroot ? (webroot + '/') : '/';
            if (u.indexOf('interface/') === 0) {
                return base + u;
            }
            return base + 'interface/' + u;
        }

        function pahoUnifiedAppendSetPidIfNeeded(src) {
            if (!apptPid || src.indexOf('set_pid=') !== -1) {
                return src;
            }
            if (src.indexOf('patient_file/encounter/') === -1) {
                return src;
            }
            return src + (src.indexOf('?') !== -1 ? '&' : '?') + 'set_pid=' + encodeURIComponent(apptPid);
        }

        function pahoUnifiedLoadFrame(id, name, url) {
            var frame = document.getElementById('encounterFrame');
            if (!frame) {
                return;
            }
            var src = pahoUnifiedResolveInterfaceUrl(url);
            frame.src = pahoUnifiedAppendSetPidIfNeeded(src);
        }

        if (!window.left_nav) {
            window.left_nav = {};
        }
        window.left_nav.setPatientEncounter = window.left_nav.setPatientEncounter || function () {};
        window.left_nav.setEncounter = window.left_nav.setEncounter || function () {};
        window.left_nav.syncRadios = window.left_nav.syncRadios || function () {};
        window.left_nav.removeOptionSelected = window.left_nav.removeOptionSelected || function () {};
        window.left_nav.clearEncounter = window.left_nav.clearEncounter || function () {};
        window.left_nav.toggleFrame = window.left_nav.toggleFrame || function () {};
        window.left_nav.loadFrame = pahoUnifiedLoadFrame;

        if (!window.jsGlobals) {
            window.jsGlobals = {};
        }
        if (typeof window.jsGlobals.date_display_format === 'undefined') {
            window.jsGlobals.date_display_format = <?php echo json_encode($GLOBALS['date_display_format'] ?? ''); ?>;
        }
        if (typeof window.jsGlobals.time_display_format === 'undefined') {
            window.jsGlobals.time_display_format = <?php echo json_encode($GLOBALS['time_display_format'] ?? ''); ?>;
        }
    })();
    </script>
    <div class="unified-container">
        <!-- Header Bar -->
        <div class="header-bar">
            <h3>
                <i class="fa fa-video-camera"></i> 
                <?php echo xlt('Televisit'); ?> - <?php echo text($room['room_name']); ?>
            </h3>
            <div class="header-actions">
                <a href="<?php echo attr($roomUrl); ?>" target="_blank">
                    <i class="fa fa-external-link"></i> <?php echo xlt('Open Jitsi in New Tab'); ?>
                </a>
                <?php if ($encounterUrl): ?>
                <a href="<?php echo attr($encounterNewTabShimUrl ?: $encounterUrl); ?>" target="_blank">
                    <i class="fa fa-file-text-o"></i> <?php echo xlt('Open Encounter in New Tab'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <i class="fa fa-info-circle"></i> 
            <?php echo xlt('Click "Launch Jitsi" to start video conference. Encounter form loads automatically.'); ?>
        </div>

        <!-- Main Content: Split View -->
        <div class="main-content">
            <!-- Left: Jitsi Section -->
            <div class="jitsi-section" id="jitsiSection">
                <div class="jitsi-container" id="jitsiContainer">
                    <!-- Try to embed Jitsi directly (may fail due to mixed content, but we'll try) -->
                    <div class="jitsi-iframe-container" id="jitsiIframeContainer">
                        <iframe 
                            id="jitsiFrame"
                            src="<?php echo attr($roomUrl); ?>"
                            allow="camera; microphone; fullscreen; display-capture"
                            allowfullscreen
                            style="width: 100%; height: 100%; border: none;"
                            onerror="showJitsiFallback()">
                        </iframe>
                        <div id="jitsiLoadingOverlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; flex-direction: column; color: white;">
                            <i class="fa fa-spinner fa-spin fa-3x" style="margin-bottom: 20px;"></i>
                            <h4><?php echo xlt('Loading Video Conference...'); ?></h4>
                            <p style="margin-top: 10px; font-size: 14px; color: #aaa;">
                                <?php echo xlt('If video does not appear, click below to open in a new window'); ?>
                            </p>
                            <button onclick="openJitsiPopup()" class="btn-launch" style="margin-top: 20px; border: none; cursor: pointer; display: none;" id="fallbackButton">
                                <i class="fa fa-external-link"></i> <?php echo xlt('Open in New Window'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="jitsi-placeholder" id="jitsiPlaceholder" style="display: none;">
                        <!-- Fallback content will be inserted here if embedding fails -->
                    </div>
                </div>
            </div>

            <!-- Resizer (for desktop) -->
            <div class="resizer" id="resizer"></div>

            <!-- Right: Encounter Form Section -->
            <div class="encounter-section">
                <div class="encounter-header">
                    <i class="fa fa-file-text-o"></i> 
                    <?php echo $encounterExists ? xlt('Encounter Form') : xlt('Patient Chart - Create Encounter'); ?>
                </div>
                <div class="encounter-iframe-container">
                    <?php if ($encounterUrl): ?>
                        <iframe 
                            id="encounterFrame"
                            src="<?php echo attr($encounterUrl); ?>"
                            allowfullscreen
                            style="width: 100%; height: 100%; border: none;">
                        </iframe>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <i class="fa fa-exclamation-triangle fa-3x" style="color: #ffc107; margin-bottom: 20px;"></i>
                            <h4><?php echo xlt('Patient ID not found'); ?></h4>
                            <p><?php echo xlt('Unable to load encounter form. Please check the appointment details.'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calculate window positions for side-by-side layout
        function calculateWindowPositions() {
            var screenWidth = window.screen.availWidth;
            var screenHeight = window.screen.availHeight;
            var screenLeft = window.screen.availLeft || 0;
            var screenTop = window.screen.availTop || 0;
            
            // Each window takes half the screen
            var windowWidth = Math.floor(screenWidth / 2) - 20;
            var windowHeight = screenHeight - 100;
            
            return {
                left: screenLeft + 10,
                right: screenLeft + windowWidth + 30,
                top: screenTop + 50,
                width: windowWidth,
                height: windowHeight
            };
        }

        // Monitor Jitsi iframe loading.
        //
        // IMPORTANT: Jitsi is served from a DIFFERENT origin than this page
        // (different host/port), so it is a cross-origin iframe. The browser's
        // same-origin policy means we can NEVER read jitsiFrame.contentDocument --
        // any attempt throws. The previous implementation treated that throw as
        // "embedding blocked / mixed content" and showed the fallback, which hid a
        // perfectly working Jitsi embed. We must NOT inspect contentDocument.
        //
        // The only reliable cross-origin signal is the iframe 'load' event: if it
        // fires, the navigation succeeded and we hide the loading overlay. If it
        // never fires within the timeout (TLS/cert failure, network block, etc.)
        // we surface the popup fallback.
        function monitorJitsiLoad() {
            var jitsiFrame = document.getElementById('jitsiFrame');
            var loadingOverlay = document.getElementById('jitsiLoadingOverlay');
            var iframeLoaded = false;

            function markLoaded() {
                if (iframeLoaded) {
                    return;
                }
                iframeLoaded = true;
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
                console.log('Jitsi iframe load event fired');
            }

            jitsiFrame.addEventListener('load', markLoaded);

            // Hard failure (rare): browser fires error on the frame element.
            jitsiFrame.addEventListener('error', function() {
                showJitsiFallback();
            });

            // Safety net: if the load event never fires, the embed could not load
            // (e.g. the cert does not match the Jitsi host, or the request was
            // blocked). Offer the popup so the visit can still proceed.
            setTimeout(function() {
                if (!iframeLoaded) {
                    showJitsiFallback();
                }
            }, 15000);
        }
        
        // Show fallback when embedding fails (mixed content)
        function showJitsiFallback() {
            var jitsiPlaceholder = document.getElementById('jitsiPlaceholder');
            var jitsiIframeContainer = document.getElementById('jitsiIframeContainer');
            var loadingOverlay = document.getElementById('jitsiLoadingOverlay');
            
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
            
            jitsiIframeContainer.style.display = 'none';
            jitsiPlaceholder.style.display = 'block';
            
            jitsiPlaceholder.innerHTML = 
                '<h4 style="color: #ffc107;"><i class="fa fa-exclamation-triangle"></i> <?php echo xlt('Video Cannot Be Embedded'); ?></h4>' +
                '<p style="color: white; margin: 15px 0; line-height: 1.6;">' + 
                '<?php echo xlt('Due to browser security (HTTP/HTTPS mixed content), video cannot be embedded directly. Click below to open in a new window positioned next to this one.'); ?>' + 
                '</p>' +
                '<button onclick="openJitsiPopup()" class="btn-launch" style="border: none; cursor: pointer;">' +
                '<i class="fa fa-external-link"></i> <?php echo xlt('Open Jitsi in New Window'); ?>' + 
                '</button>';
        }
        
        // Open Jitsi in popup window (fallback)
        function openJitsiPopup() {
            var pos = calculateWindowPositions();
            
            // Position current window on the left (encounter form)
            try {
                window.moveTo(pos.left, pos.top);
                window.resizeTo(pos.width, pos.height);
            } catch (e) {
                // Window positioning may be restricted
                console.log('Window positioning restricted');
            }
            
            // Open Jitsi on the right side
            var jitsiWindow = window.open(
                <?php echo json_encode($roomUrl); ?>, 
                'jitsi_televisit',
                'width=' + pos.width + ',height=' + pos.height + 
                ',left=' + pos.right + ',top=' + pos.top + 
                ',resizable=yes,scrollbars=yes,location=yes,toolbar=no,menubar=no'
            );
            
            if (!jitsiWindow || jitsiWindow.closed) {
                alert('<?php echo xla('Popup blocked. Please allow popups for this site and try again.'); ?>');
            } else {
                // Focus back on this window
                window.focus();
            }
        }
        
        // Start monitoring when page loads
        window.addEventListener('load', function() {
            var jitsiUrl = <?php echo json_encode($roomUrl); ?>;

            // The only case the browser forbids embedding is mixed content:
            // an HTTPS page trying to iframe an HTTP resource. Now that Jitsi has
            // valid SSL certs (staging) -- and on local HTTP setups where there is
            // no scheme mismatch -- the iframe embeds normally. We force the popup
            // ONLY for the genuine mixed-content case. monitorJitsiLoad() still
            // detects any other load failure and offers the popup as a fallback.
            var isMixedContent = (window.location.protocol === 'https:')
                && /^http:\/\//i.test(jitsiUrl);

            if (isMixedContent) {
                // Hide iframe immediately and show placeholder with auto-open option
                var jitsiIframeContainer = document.getElementById('jitsiIframeContainer');
                var jitsiPlaceholder = document.getElementById('jitsiPlaceholder');
                var loadingOverlay = document.getElementById('jitsiLoadingOverlay');
                
                if (jitsiIframeContainer && jitsiPlaceholder) {
                    // Hide iframe container
                    jitsiIframeContainer.style.display = 'none';
                    loadingOverlay.style.display = 'none';
                    
                    // Show placeholder with auto-open message
                    jitsiPlaceholder.style.display = 'block';
                    jitsiPlaceholder.innerHTML = 
                        '<h4 style="color: #ffc107;"><i class="fa fa-exclamation-triangle"></i> <?php echo xlt('Video Conference'); ?></h4>' +
                        '<p style="color: white; margin: 15px 0; line-height: 1.6;">' + 
                        '<?php echo xlt('Due to browser security, the video conference will open in a separate window. This ensures all features work correctly.'); ?>' + 
                        '</p>' +
                        '<button onclick="openJitsiPopup()" class="btn-launch" style="border: none; cursor: pointer; margin-top: 10px;">' +
                        '<i class="fa fa-external-link"></i> <?php echo xlt('Open Video Conference'); ?>' + 
                        '</button>' +
                        '<p style="color: #aaa; font-size: 12px; margin-top: 15px;">' +
                        '<?php echo xlt('The video window will be positioned next to this window automatically.'); ?>' +
                        '</p>';
                    
                    // Auto-open after 1 second
                    setTimeout(function() {
                        openJitsiPopup();
                    }, 1000);
                } else {
                    // Fallback: try to monitor but expect failure
                    monitorJitsiLoad();
                }
            } else {
                // No mixed content: embed the Jitsi iframe directly.
                monitorJitsiLoad();
            }
        });

        // Auto-launch Jitsi when page loads (optional - can be disabled)
        // Uncomment the line below to auto-launch Jitsi on page load
        // window.addEventListener('load', function() { setTimeout(launchJitsi, 500); });

        // Resizer functionality for split view
        var resizer = document.getElementById('resizer');
        var jitsiSection = document.getElementById('jitsiSection');
        var encounterSection = document.querySelector('.encounter-section');
        var isResizing = false;

        resizer.addEventListener('mousedown', function(e) {
            isResizing = true;
            document.addEventListener('mousemove', handleResize);
            document.addEventListener('mouseup', stopResize);
        });

        function handleResize(e) {
            if (!isResizing) return;
            
            var containerWidth = document.querySelector('.main-content').offsetWidth;
            var newJitsiWidth = ((e.clientX / containerWidth) * 100);
            
            if (newJitsiWidth > 20 && newJitsiWidth < 80) {
                jitsiSection.style.flex = '0 0 ' + newJitsiWidth + '%';
                encounterSection.style.flex = '0 0 ' + (100 - newJitsiWidth) + '%';
            }
        }

        function stopResize() {
            isResizing = false;
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
        }

        // Handle iframe session restoration
        if (typeof top.restoreSession === 'function') {
            // Ensure session is restored for encounter iframe
            var encounterFrame = document.getElementById('encounterFrame');
            if (encounterFrame) {
                encounterFrame.addEventListener('load', function() {
                    try {
                        if (encounterFrame.contentWindow && typeof encounterFrame.contentWindow.restoreSession === 'function') {
                            encounterFrame.contentWindow.restoreSession();
                        }
                    } catch (e) {
                        // Cross-origin or other iframe access issue - ignore
                    }
                });
            }
        }
    </script>
</body>
</html>

