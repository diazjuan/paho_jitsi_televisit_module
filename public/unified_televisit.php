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

// Function to get absolute HTTPS URL
function getAbsoluteHttpsUrl($relativePath) {
    // Determine if we're on HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    $scheme = $isHttps ? 'https://' : 'https://'; // Force HTTPS
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '169.61.173.4';
    
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

    // Get base URL and ensure it's HTTPS
    $baseUrl = TelevisitConfigService::getBaseUrl();
    // Force HTTPS if not already
    if (stripos($baseUrl, 'http://') === 0) {
        $baseUrl = str_replace('http://', 'https://', $baseUrl);
    } elseif (stripos($baseUrl, 'https://') !== 0) {
        // If no scheme, add https://
        $baseUrl = 'https://' . ltrim($baseUrl, '/');
    }
    $roomUrl = rtrim($baseUrl, '/') . '/' . $room['room_name'];
} catch (\Exception $e) {
    error_log("[PAHO Jitsi Televisit] Error in unified_televisit.php: " . $e->getMessage());
    die(xlt('Error loading televisit session. Please contact support. Details: ') . $e->getMessage());
}

try {
    // Get patient ID and encounter info
    $appointment = sqlQuery(
        "SELECT pc_pid, pc_aid FROM openemr_postcalendar_events WHERE pc_eid = ?",
        [$pcEid]
    );

    $pid = $appointment['pc_pid'] ?? $room['pid'] ?? null;
    $encounterUrl = '';
    $encounterExists = false;

    if ($pid) {
        // Check if encounter exists for today
        $encounter = sqlQuery(
            "SELECT fe.encounter, fe.id FROM form_encounter fe 
             WHERE fe.pid = ? AND DATE(fe.date) = CURDATE() 
             ORDER BY fe.id DESC LIMIT 1",
            [$pid]
        );
        
        if ($encounter) {
            $encounterExists = true;
            $encounterUrl = getAbsoluteHttpsUrl('/interface/patient_file/encounter/encounter_top.php?set_encounter=' . attr_url($encounter['encounter']) . '&set_pid=' . attr_url($pid));
        } else {
            // Link to create new encounter form
            $encounterUrl = getAbsoluteHttpsUrl('/interface/forms/newpatient/new.php?autoloaded=1&calenc=' . attr_url($pcEid) . '&pid=' . attr_url($pid));
        }
    }
} catch (\Exception $e) {
    error_log("[PAHO Jitsi Televisit] Error getting encounter info: " . $e->getMessage());
    // Continue with empty encounter URL - user can still use Jitsi
    $pid = $room['pid'] ?? null;
    $encounterUrl = $pid ? getAbsoluteHttpsUrl('/interface/forms/newpatient/new.php?autoloaded=1&calenc=' . attr_url($pcEid) . '&pid=' . attr_url($pid)) : '';
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
                <a href="<?php echo attr($encounterUrl); ?>" target="_blank">
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

        // Monitor Jitsi iframe loading with faster mixed content detection
        function monitorJitsiLoad() {
            var jitsiFrame = document.getElementById('jitsiFrame');
            var loadingOverlay = document.getElementById('jitsiLoadingOverlay');
            var fallbackButton = document.getElementById('fallbackButton');
            var iframeLoaded = false;
            var mixedContentDetected = false;
            
            // Listen for console errors (mixed content warnings)
            var originalError = console.error;
            console.error = function() {
                var message = Array.prototype.slice.call(arguments).join(' ');
                if (message.indexOf('Mixed Content') !== -1 || 
                    message.indexOf('mixed content') !== -1 ||
                    message.indexOf('blocked') !== -1 && message.indexOf('http://') !== -1) {
                    mixedContentDetected = true;
                    console.error = originalError; // Restore original
                    showJitsiFallback();
                }
                originalError.apply(console, arguments);
            };
            
            // Check if iframe loaded successfully
            var checkInterval = setInterval(function() {
                if (mixedContentDetected) {
                    clearInterval(checkInterval);
                    return;
                }
                
                try {
                    // Try to access iframe - if we can, it's loaded
                    var iframeDoc = jitsiFrame.contentDocument || jitsiFrame.contentWindow.document;
                    if (iframeDoc && iframeDoc.body) {
                        // Iframe is accessible and loaded
                        iframeLoaded = true;
                        clearInterval(checkInterval);
                        if (loadingOverlay) {
                            loadingOverlay.style.display = 'none';
                        }
                        console.log('Jitsi iframe loaded successfully');
                        return;
                    }
                } catch (e) {
                    // Cross-origin or blocked - this is expected
                    // Check if it's a mixed content issue by checking the iframe src
                    var iframeSrc = jitsiFrame.src;
                    if (iframeSrc && iframeSrc.indexOf('https://') === 0) {
                        // Iframe src is HTTPS but we can't access it, might be mixed content
                        // Wait a bit more before deciding
                    }
                }
            }, 300);
            
            // Faster timeout for mixed content detection (3 seconds)
            setTimeout(function() {
                if (mixedContentDetected) {
                    return;
                }
                
                clearInterval(checkInterval);
                if (loadingOverlay && loadingOverlay.style.display !== 'none' && !iframeLoaded) {
                    // Check one more time
                    try {
                        var iframeDoc = jitsiFrame.contentDocument || jitsiFrame.contentWindow.document;
                        if (iframeDoc && iframeDoc.body) {
                            iframeLoaded = true;
                            if (loadingOverlay) {
                                loadingOverlay.style.display = 'none';
                            }
                            return;
                        }
                    } catch (e) {
                        // Can't access iframe - likely blocked
                    }
                    
                    // Show fallback after 3 seconds if not loaded
                    showJitsiFallback();
                }
            }, 3000);
            
            // If iframe loads, hide loading overlay
            jitsiFrame.addEventListener('load', function() {
                setTimeout(function() {
                    if (!mixedContentDetected) {
                        // Check if we can actually access the content
                        try {
                            var iframeDoc = jitsiFrame.contentDocument || jitsiFrame.contentWindow.document;
                            if (iframeDoc && iframeDoc.body) {
                                iframeLoaded = true;
                                if (loadingOverlay) {
                                    loadingOverlay.style.display = 'none';
                                }
                                clearInterval(checkInterval);
                            } else {
                                // Loaded but can't access - might be mixed content
                                setTimeout(function() {
                                    if (!iframeLoaded) {
                                        showJitsiFallback();
                                    }
                                }, 2000);
                            }
                        } catch (e) {
                            // Can't access - might be mixed content
                            setTimeout(function() {
                                if (!iframeLoaded) {
                                    showJitsiFallback();
                                }
                            }, 2000);
                        }
                    }
                }, 1000);
            });
            
            // If iframe errors, show fallback immediately
            jitsiFrame.addEventListener('error', function() {
                clearInterval(checkInterval);
                mixedContentDetected = true;
                showJitsiFallback();
            });
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
            // Check if we're on HTTPS
            var isHttps = window.location.protocol === 'https:';
            var jitsiUrl = <?php echo json_encode($roomUrl); ?>;
            
            // If we're on HTTPS, Jitsi iframe often fails due to mixed content
            // (Jitsi may load HTTP resources internally)
            // Automatically open in separate window for better UX
            if (isHttps) {
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
                // Not HTTPS, should work fine with iframe
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

