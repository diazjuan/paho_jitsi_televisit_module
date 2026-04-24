<?php

/**
 * Bootstrap class for PAHO Jitsi Televisit Module
 * Uses official OpenEMR event system to inject UI without modifying core files
 *
 * FIXED VERSION - Resolves pid=0 validation bug. 
 * https://claude.ai/chat/0b74eb32-1668-48d5-818a-2d7fdd4aefa1
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

namespace OpenEMR\Modules\PahoJitsiTelevisit;

use OpenEMR\Events\Appointments\AppointmentSetEvent;
use OpenEMR\Events\Appointments\AppointmentRenderEvent;
use OpenEMR\Events\PatientPortal\AppointmentFilterEvent;
use OpenEMR\Events\Core\TemplatePageEvent;
use OpenEMR\Events\Core\TwigEnvironmentEvent;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitRoomService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Bootstrap
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string Module directory name
     */
    private $moduleDirectoryName = 'paho_jitsi_televisit_module';

    /**
     * Constructor
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Subscribe to OpenEMR events
     */
    public function subscribeToEvents()
    {
        // Hook into appointment creation/update to generate rooms
        $this->eventDispatcher->addListener(
            AppointmentSetEvent::EVENT_HANDLE,
            [$this, 'onAppointmentSet']
        );

        // Inject UI into appointment view (below patient info)
        $this->eventDispatcher->addListener(
            AppointmentRenderEvent::RENDER_BELOW_PATIENT,
            [$this, 'injectAppointmentButton']
        );

        // Add televisit data to patient portal appointments
        $this->eventDispatcher->addListener(
            AppointmentFilterEvent::EVENT_NAME,
            [$this, 'filterPatientPortalAppointment']
        );

        // Register Twig templates
        $this->eventDispatcher->addListener(
            TwigEnvironmentEvent::EVENT_CREATED,
            [$this, 'registerTwigTemplates']
        );

        // New encounter form: duplicate warning uses PHP "today"; televisit opens by appointment day (calenc).
        $this->eventDispatcher->addListener(
            TemplatePageEvent::RENDER_EVENT,
            [$this, 'adjustNewpatientDuplicateForCalendarContext']
        );
    }

    /**
     * Handle appointment creation/update event
     * Generate Jitsi room if appointment is Televisit category
     *
     * @param AppointmentSetEvent $event
     */
    public function onAppointmentSet(AppointmentSetEvent $event)
    {
        $appointmentData = $event->givenAppointmentData();
        $pcEid = $event->eid ?? null;

        // DEBUG LOGGING (can be removed in production)
        error_log("[PAHO DEBUG] onAppointmentSet triggered");
        error_log("[PAHO DEBUG] pc_eid: " . ($pcEid ?? 'NULL'));
        error_log("[PAHO DEBUG] Event data keys: " . implode(", ", array_keys($appointmentData)));

        if (!$pcEid) {
            error_log("[PAHO DEBUG] No pc_eid, exiting");
            return;
        }

        // Get appointment details from database
        $appointment = sqlQuery(
            "SELECT pc_eid, pc_pid, pc_aid, pc_eventDate, pc_startTime, pc_catid 
             FROM openemr_postcalendar_events 
             WHERE pc_eid = ?",
            [$pcEid]
        );

        if (!$appointment) {
            error_log("[PAHO DEBUG] Appointment not found in database for pc_eid: $pcEid");
            return;
        }

        error_log("[PAHO DEBUG] Appointment from DB: " . print_r($appointment, true));

        // Check if this is a Televisit category
        $catid = $appointment['pc_catid'] ?? null;
        if (!TelevisitConfigService::isTelevisitCategory($catid)) {
            error_log("[PAHO DEBUG] Not a televisit category (catid: $catid)");
            return;
        }

        // Extract appointment data
        $pid = $appointment['pc_pid'] ?? null;
        $providerId = $appointment['pc_aid'] ?? null;
        $appointmentDate = $appointment['pc_eventDate'] ?? null;
        $appointmentTime = $appointment['pc_startTime'] ?? null;

        // ✅ FIXED VALIDATION: Explicitly check for valid values
        // Don't use loose comparison (!$pid) because 0 is falsy in PHP
        if ($pid === null || $pid === '' || $pid === 0) {
            error_log("[PAHO DEBUG] Invalid patient ID: pid=" . var_export($pid, true));
            return;
        }

        if ($providerId === null || $providerId === '') {
            error_log("[PAHO DEBUG] Invalid provider ID: providerId=" . var_export($providerId, true));
            return;
        }

        if (empty($appointmentDate) || empty($appointmentTime)) {
            error_log("[PAHO DEBUG] Missing appointment date/time: date=$appointmentDate, time=$appointmentTime");
            return;
        }

        // All validations passed - create the room
        error_log("[PAHO DEBUG] Creating room for: eid=$pcEid, pid=$pid, provider=$providerId, date=$appointmentDate, time=$appointmentTime");

        try {
            $room = TelevisitRoomService::getOrCreateRoom(
                $pcEid,
                $pid,
                $providerId,
                $appointmentDate,
                $appointmentTime
            );
            error_log("[PAHO DEBUG] Room created successfully: " . print_r($room, true));
        } catch (\Exception $e) {
            error_log("[PAHO Jitsi Televisit] Error creating room: " . $e->getMessage());
            error_log("[PAHO DEBUG] Exception trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Inject Join Televisit button/panel into appointment view
     *
     * @param AppointmentRenderEvent $event
     */
    public function injectAppointmentButton(AppointmentRenderEvent $event)
    {
        error_log("[PAHO DEBUG] injectAppointmentButton called");
        
        $appointmentData = $event->getAppt();
        error_log("[PAHO DEBUG] Appointment data: " . print_r($appointmentData, true));
        
        $pcEid = $appointmentData['pc_eid'] ?? $_GET['eid'] ?? $_POST['eid'] ?? null;
        
        // If we have an EID but missing data, fetch from database
        if ($pcEid && empty($appointmentData['pc_catid'])) {
            $fullData = sqlQuery("SELECT * FROM openemr_postcalendar_events WHERE pc_eid = ?", [$pcEid]);
            if ($fullData) {
                $appointmentData = array_merge($appointmentData, $fullData);
            }
        }
        
        $catid = $appointmentData['pc_catid'] ?? null;

        if (!$pcEid || !TelevisitConfigService::isTelevisitCategory($catid)) {
            error_log("[PAHO DEBUG] Not rendering button: pcEid=$pcEid, catid=$catid");
            return;
        }

        $room = TelevisitRoomService::getRoomByAppointment($pcEid);
        if (!$room) {
            error_log("[PAHO DEBUG] No room found for appointment $pcEid");
            return;
        }

        error_log("[PAHO DEBUG] Rendering Televisit button for appointment $pcEid");

        $uniqueId = 'paho_televisit_' . $pcEid;
        $roomUrl = $room['room_url'];
        $joinUrl = $GLOBALS['webroot'] . '/interface/modules/custom_modules/' . $this->moduleDirectoryName . '/public/join_room.php?eid=' . attr_url($pcEid);
        
        // Get patient ID and check for existing encounter
        $pid = $appointmentData['pc_pid'] ?? null;
        $encounterUrl = '';
        $encounterExists = false;
        
        if ($pid) {
            // Only treat as "existing encounter" when this room row links to form_encounter (encounter_id).
            // Do not infer from "any encounter today" — that showed the wrong chart for this televisit appointment.
            $encounter = TelevisitRoomService::getLinkedEncounterRow($room, $pid);

            if ($encounter) {
                $encounterExists = true;
                $encounterUrl = $this->buildChromeShimUrl('patient_file/encounter/encounter_top.php', [
                    'set_encounter' => $encounter['encounter'],
                    'set_pid' => $pid,
                ]);
            } else {
                $encounterUrl = $this->buildChromeShimUrl('forms/newpatient/new.php', [
                    'autoloaded' => '1',
                    'calenc' => $pcEid,
                    'pid' => $pid,
                ]);
            }
        }
        
        ?>
        <!-- PAHO Jitsi Televisit Panel -->
        <div class="paho-televisit-panel" style="margin-top: 15px; border: 1px solid #e0e7ff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,102,204,0.1);">
            <!-- Collapsible Header -->
            <div onclick="document.getElementById('<?php echo attr($uniqueId); ?>_content').classList.toggle('d-none'); document.getElementById('<?php echo attr($uniqueId); ?>_icon').classList.toggle('fa-chevron-down'); document.getElementById('<?php echo attr($uniqueId); ?>_icon').classList.toggle('fa-chevron-up');"
                 style="background: linear-gradient(to right, #0066cc, #0052a3); padding: 12px 15px; cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-video-camera" style="color: white; font-size: 18px;"></i>
                    <span style="color: white; font-weight: 600; font-size: 15px;">
                        <?php echo xlt('Televisit'); ?>
                    </span>
                    <span style="color: rgba(255,255,255,0.8); font-size: 13px; margin-left: 10px;">
                        <?php echo xlt('Room'); ?>: <?php echo text($room['room_name']); ?>
                    </span>
                </div>
                <i id="<?php echo attr($uniqueId); ?>_icon" class="fa fa-chevron-down" style="color: white; font-size: 14px;"></i>
            </div>

            <!-- Content (Initially collapsed) -->
            <div id="<?php echo attr($uniqueId); ?>_content" class="d-none" style="padding: 15px; background: #f8faff;">
                <!-- Room URL Section -->
                <div style="margin-bottom: 15px; padding: 12px; background: white; border: 1px solid #d1e0ff; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <i class="fa fa-link" style="color: #0066cc; font-size: 16px;"></i>
                        <label style="margin: 0; color: #0066cc; font-weight: 600; font-size: 14px;">
                            <?php echo xlt('Room URL'); ?>
                        </label>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: stretch;">
                        <input type="text"
                               id="<?php echo attr($uniqueId); ?>_room_url"
                               value="<?php echo attr($roomUrl); ?>"
                               readonly
                               style="flex: 1; padding: 8px 10px; border: 1px solid #b3d1ff; border-radius: 4px; background: #f0f7ff; font-size: 12px; font-family: monospace; color: #333;">
                        <button class="btn btn-sm"
                                onclick="pahoTelevisitCopyLink('<?php echo attr($uniqueId); ?>_room_url', '<?php echo xla('✓ Room URL copied'); ?>')"
                                style="padding: 8px 16px; background: #0066cc; color: white; border: none; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;">
                            <i class="fa fa-copy"></i> <?php echo xlt('Copy'); ?>
                        </button>
                        <a href="<?php echo attr($roomUrl); ?>"
                           target="_blank"
                           class="btn btn-sm"
                           style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; white-space: nowrap;">
                            <i class="fa fa-external-link"></i> <?php echo xlt('Open'); ?>
                        </a>
                    </div>
                </div>

                <!-- Join Televisit Button -->
                <div style="margin-bottom: 12px;">
                    <button onclick="window.open('<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/<?php echo $this->moduleDirectoryName; ?>/public/unified_televisit.php?eid=<?php echo attr_url($pcEid); ?>', 'unified_televisit_window', 'width=1400,height=800,resizable=yes,scrollbars=yes')"
                            class="btn btn-primary btn-sm"
                            style="width: 100%; padding: 10px; background: #0066cc; color: white; border: none; border-radius: 4px; font-weight: 600;">
                        <i class="fa fa-video-camera"></i> <?php echo xlt('Open Unified Televisit View'); ?>
                    </button>
                    <small style="display: block; text-align: center; margin-top: 5px; color: #666; font-size: 11px;">
                        <?php echo xlt('Opens video and encounter form in one window'); ?>
                    </small>
                </div>

                <!-- Alternative: Separate Buttons -->
                <div style="margin-bottom: 12px; display: flex; gap: 8px;">
                    <button class="btn btn-sm"
                            onclick="window.open('<?php echo attr($roomUrl); ?>', 'jitsi_televisit', 'width=1280,height=720,resizable=yes')"
                            style="flex: 1; padding: 8px; background: #0066cc; color: white; border: none; border-radius: 4px; font-weight: 600;">
                        <i class="fa fa-video-camera"></i> <?php echo xlt('Jitsi Only'); ?>
                    </button>
                    <?php if ($pid && $encounterUrl): ?>
                    <a href="<?php echo attr($encounterUrl); ?>" 
                       target="_blank"
                       class="btn btn-sm"
                       style="flex: 1; padding: 8px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: 600; text-decoration: none; text-align: center;">
                        <i class="fa fa-file-text-o"></i> <?php echo xlt('Encounter'); ?>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Instructions -->
                <div style="padding: 10px; background: #fff9e6; border-left: 3px solid #ffa500; border-radius: 3px;">
                    <div style="display: flex; align-items: start; gap: 8px;">
                        <i class="fa fa-lightbulb-o" style="color: #ff8c00; font-size: 14px; margin-top: 2px;"></i>
                        <div style="flex: 1;">
                            <strong style="color: #cc6600; font-size: 13px;">
                                <?php echo xlt('Instructions'); ?>:
                            </strong>
                            <ol style="margin: 5px 0 0 0; padding-left: 18px; color: #8b6000; font-size: 12px; line-height: 1.6;">
                                <li><?php echo xlt('Click "Open Unified Televisit View" for best experience (video + forms in one window)'); ?></li>
                                <li><?php echo xlt('Or use separate buttons to open Jitsi and Encounter independently'); ?></li>
                                <li><?php echo xlt('Document the encounter while viewing the patient'); ?></li>
                                <li><?php echo xlt('The patient can join from the patient portal'); ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript for copy and embed functionality -->
        <script>
        function pahoTelevisitCopyLink(inputId, successMessage) {
            var input = document.getElementById(inputId);
            if (!input) {
                alert('<?php echo xla('Error: Field not found'); ?>');
                return;
            }

            input.select();
            input.setSelectionRange(0, 99999);

            try {
                var success = document.execCommand('copy');
                if (success) {
                    alert(successMessage);
                } else {
                    throw new Error('copy failed');
                }
            } catch (err) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(input.value).then(function() {
                        alert(successMessage);
                    }).catch(function() {
                        alert('<?php echo xla('Error copying. Please copy manually.'); ?>');
                    });
                } else {
                    alert('<?php echo xla('Please copy the link manually'); ?>');
                }
            }
        }
        </script>
        <?php
    }

    /**
     * Standalone-tab wrapper so core encounter JS sees top.restoreSession / top.jsGlobals / top.left_nav.
     *
     * @param string $innerPath Path under /interface/ (allow-listed)
     * @param array<string, scalar> $query Forwarded as query string to inner page (plus inner=)
     */
    private function buildChromeShimUrl(string $innerPath, array $query): string
    {
        $allowed = [
            'forms/newpatient/new.php',
            'patient_file/encounter/encounter_top.php',
        ];
        if (!in_array($innerPath, $allowed, true)) {
            $innerPath = 'forms/newpatient/new.php';
        }
        $query['inner'] = $innerPath;

        return $GLOBALS['webroot'] . '/interface/modules/custom_modules/' . $this->moduleDirectoryName
            . '/public/televisit_encounter.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Add televisit data to patient portal appointments
     *
     * @param AppointmentFilterEvent $event
     */
    public function filterPatientPortalAppointment(AppointmentFilterEvent $event)
    {
        $dbRecord = $event->getDbRecord();
        $appointment = $event->getAppointment();

        $pcEid = $dbRecord['pc_eid'] ?? null;
        $catid = $dbRecord['pc_catid'] ?? null;

        // Initialize televisit fields
        $appointment['showTelevisit'] = false;
        $appointment['televisitUrl'] = '';
        $appointment['televisitRoomName'] = '';

        if (!$pcEid || !TelevisitConfigService::isTelevisitCategory($catid)) {
            $event->setAppointment($appointment);
            return;
        }

        // Get room data
        $room = TelevisitRoomService::getRoomByAppointment($pcEid);
        if (!$room) {
            $event->setAppointment($appointment);
            return;
        }

        // Check if appointment is in the future or today
        $appointmentDateTime = \DateTime::createFromFormat(
            "Y-m-d H:i:s",
            ($dbRecord['pc_eventDate'] ?? '') . " " . ($dbRecord['pc_startTime'] ?? '00:00:00')
        );

        if ($appointmentDateTime && $appointmentDateTime >= new \DateTime('today')) {
            $appointment['showTelevisit'] = true;
            $appointment['televisitUrl'] = $GLOBALS['webroot'] . '/interface/modules/custom_modules/' . $this->moduleDirectoryName . '/public/portal_join.php?eid=' . $pcEid;
            $appointment['televisitRoomName'] = $room['room_name'];
        }

        $event->setAppointment($appointment);
    }

    /**
     * When newpatient is opened with calenc (calendar event id), align duplicate-encounter detection with the
     * appointment's pc_eventDate instead of the server's current calendar day. Core logic uses date('Y-m-d'),
     * which produced false "visit already exists today" for televisit/unified flows on a different appointment day.
     *
     * @param TemplatePageEvent $event
     */
    public function adjustNewpatientDuplicateForCalendarContext(TemplatePageEvent $event): void
    {
        if ($event->getPageName() !== 'newpatient/common.php') {
            return;
        }

        $calenc = $_GET['calenc'] ?? $_REQUEST['calenc'] ?? null;
        if ($calenc === null || $calenc === '') {
            return;
        }

        $pcEid = (int) $calenc;
        if ($pcEid <= 0) {
            return;
        }

        $appt = sqlQuery(
            'SELECT pc_eventDate FROM openemr_postcalendar_events WHERE pc_eid = ?',
            [$pcEid]
        );
        $eventDate = $appt['pc_eventDate'] ?? '';
        if ($eventDate === '' || $eventDate === null) {
            return;
        }
        $eventDateStr = (string) $eventDate;
        if (strpos($eventDateStr, '0000-00-00') === 0) {
            return;
        }

        $twigVars = $event->getTwigVariables();
        if (!empty($twigVars['viewmode'])) {
            return;
        }

        $pid = $twigVars['pid'] ?? null;
        if ($pid === null || $pid === '' || (int) $pid <= 0) {
            return;
        }

        $pid = (int) $pid;
        $dayStart = $eventDateStr . ' 00:00:00';
        $dayEnd = $eventDateStr . ' 23:59:59';

        $erow = sqlQuery(
            "SELECT fe.encounter, fe.date FROM form_encounter AS fe, forms AS f WHERE " .
            "fe.pid = ? AND fe.date >= ? AND fe.date <= ? AND " .
            "f.formdir = 'newpatient' AND f.form_id = fe.id AND f.deleted = 0 " .
            "ORDER BY fe.encounter DESC LIMIT 1",
            [$pid, $dayStart, $dayEnd]
        );

        $duplicate = ['isDuplicate' => false];
        if (!empty($erow['encounter'])) {
            $datePart = substr((string) $erow['date'], 0, 10);
            $formatted = oeFormatShortDate($datePart);
            $duplicate = [
                'isDuplicate' => true,
                'encounter' => $erow['encounter'],
                'date' => $formatted,
                'dateStr' => $formatted,
            ];
        }

        $event->setTwigVariables(['duplicate' => $duplicate]);
    }

    /**
     * Register Twig template paths for this module
     *
     * @param TwigEnvironmentEvent $event
     */
    public function registerTwigTemplates(TwigEnvironmentEvent $event)
    {
        $twigEnv = $event->getTwigEnvironment();
        $loader = $twigEnv->getLoader();

        $modulePath = $GLOBALS['fileroot'] . '/interface/modules/custom_modules/' . $this->moduleDirectoryName . '/templates';

        try {
            $loader->prependPath($modulePath);
        } catch (\Exception $e) {
            error_log("[PAHO Jitsi Televisit] Failed to register Twig templates: " . $e->getMessage());
        }
    }
}