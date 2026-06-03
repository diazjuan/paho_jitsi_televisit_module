<?php

/**
 * Televisit encounter wrapper (full-window iframe).
 *
 * This provides the minimum OpenEMR "chrome" globals (restoreSession/jsGlobals/left_nav)
 * that core encounter/new-encounter pages expect when opened outside main/tabs.
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

$sessionAllowWrite = true;
require_once(__DIR__ . '/../../../../globals.php');

use OpenEMR\Common\Acl\AclMain;

if (!AclMain::aclCheckCore('patients', 'appt', '', ['write', 'wsome'])) {
    echo xlt('Access Denied');
    exit;
}

$allowedInner = [
    'forms/newpatient/new.php',
    'patient_file/encounter/encounter_top.php',
];

$inner = $_GET['inner'] ?? '';
if (!in_array($inner, $allowedInner, true)) {
    http_response_code(400);
    echo xlt('Invalid request.');
    exit;
}

$params = $_GET;
unset($params['inner']);
$apptPidForJs = (string)($params['pid'] ?? $params['set_pid'] ?? '');

$innerUrl = $GLOBALS['webroot'] . '/interface/' . $inner;
if ($params !== []) {
    $innerUrl .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Encounter'); ?></title>
    <style>
        html, body { height: 100%; margin: 0; }
        #pahoEncounterInner { width: 100%; height: 100vh; border: 0; display: block; }
    </style>
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
    (function () {
        var webroot = <?php echo json_encode(rtrim($GLOBALS['webroot'] ?? '', '/')); ?>;
        var apptPid = <?php echo json_encode($apptPidForJs); ?>;

        function pahoResolveInterfaceUrl(url) {
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

        function pahoAppendSetPidIfNeeded(src) {
            if (!apptPid || src.indexOf('set_pid=') !== -1) {
                return src;
            }
            if (src.indexOf('patient_file/encounter/') === -1) {
                return src;
            }
            return src + (src.indexOf('?') !== -1 ? '&' : '?') + 'set_pid=' + encodeURIComponent(apptPid);
        }

        function pahoShimLoadFrame(id, name, url) {
            var frame = document.getElementById('pahoEncounterInner');
            if (!frame) {
                return;
            }
            var src = pahoResolveInterfaceUrl(url);
            frame.src = pahoAppendSetPidIfNeeded(src);
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
        window.left_nav.loadFrame = pahoShimLoadFrame;

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
</head>
<body>
<iframe id="pahoEncounterInner" name="enc" src="<?php echo attr($innerUrl); ?>"></iframe>
</body>
</html>

