<?php

/**
 * Module Configuration Page
 * Settings UI for PAHO Jitsi Televisit module
 *
 * @package OpenEMR\Modules\PahoJitsiTelevisit
 * @license GNU General Public License 3
 */

$sessionAllowWrite = true;
require_once(__DIR__ . "/../../../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Core\ModulesClassLoader;

// Load module classes
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;

// Check admin access
if (!AclMain::aclCheckCore('admin', 'super')) {
    echo xlt('Access Denied');
    exit;
}

$module_config = 1;

// Handle form submission
$saveMessage = '';
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_config'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'])) {
        CsrfUtils::csrfNotVerified();
    }

    $config = [
        'base_url' => trim($_POST['base_url'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'room_naming' => $_POST['room_naming'] ?? 'deterministic',
        'jwt_secret' => trim($_POST['jwt_secret'] ?? ''),
        'jwt_app_id' => trim($_POST['jwt_app_id'] ?? 'paho-jitsi-televisit'),
    ];

    if (empty($config['base_url'])) {
        $saveError = xlt('Jitsi Base URL is required');
    } else {
        if (TelevisitConfigService::saveAllConfig($config)) {
            $saveMessage = xlt('Configuration saved successfully');
        } else {
            $saveError = xlt('Error saving configuration');
        }
    }
}

// Get current configuration
try {
    $currentConfig = TelevisitConfigService::getAllConfig();
} catch (\Exception $e) {
    error_log("PAHO Jitsi Televisit Config Error: " . $e->getMessage());
    $currentConfig = [
        'base_url' => '',
        'category_id' => null,
        'room_naming' => 'deterministic',
        'jwt_secret' => '',
        'jwt_app_id' => 'paho-jitsi-televisit',
    ];
}

// Get available calendar categories
$categories = [];
try {
    $catResult = sqlStatement(
        "SELECT pc_catid, pc_catname, pc_constant_id FROM openemr_postcalendar_categories WHERE pc_active = 1 ORDER BY pc_catname"
    );
    while ($cat = sqlFetchArray($catResult)) {
        $categories[] = $cat;
    }
} catch (\Exception $e) {
    error_log("PAHO Jitsi Televisit Category Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('PAHO Jitsi Televisit Configuration'); ?></title>
    <?php \OpenEMR\Core\Header::setupHeader(['common']); ?>
    <style>
        .config-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .config-section h3 {
            margin-top: 0;
            color: #0066cc;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background: #e6f2ff;
            padding: 15px;
            border-left: 4px solid #0066cc;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2><?php echo xlt('PAHO Jitsi Televisit Configuration'); ?></h2>

                <?php if ($saveMessage): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> <?php echo text($saveMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($saveError): ?>
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-triangle"></i> <?php echo text($saveError); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
                    <input type="hidden" name="save_config" value="1">

                    <!-- Basic Configuration -->
                    <div class="config-section">
                        <h3><?php echo xlt('Basic Configuration'); ?></h3>

                        <div class="form-group">
                            <label for="base_url"><?php echo xlt('Jitsi Base URL'); ?> *</label>
                            <input type="text" 
                                   id="base_url" 
                                   name="base_url" 
                                   value="<?php echo attr($currentConfig['base_url']); ?>"
                                   required>
                            <div class="help-text">
                                <?php echo xlt('Base URL of your Jitsi Meet instance (e.g., https://169.61.173.4:8440)'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="category_id"><?php echo xlt('Televisit Category'); ?></label>
                            <select id="category_id" name="category_id">
                                <option value=""><?php echo xlt('-- Select Category --'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo attr($cat['pc_catid']); ?>"
                                            <?php echo ($currentConfig['category_id'] == $cat['pc_catid']) ? 'selected' : ''; ?>>
                                        <?php echo text($cat['pc_catname']); ?>
                                        <?php if ($cat['pc_constant_id'] == 'televisit'): ?>
                                            (<?php echo xlt('Televisit'); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                <?php echo xlt('Select the calendar category that identifies televisit appointments'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="room_naming"><?php echo xlt('Room Naming Strategy'); ?></label>
                            <select id="room_naming" name="room_naming">
                                <option value="deterministic" <?php echo ($currentConfig['room_naming'] == 'deterministic') ? 'selected' : ''; ?>>
                                    <?php echo xlt('Deterministic (based on patient, provider, date, time)'); ?>
                                </option>
                            </select>
                            <div class="help-text">
                                <?php echo xlt('How Jitsi room names are generated. Deterministic ensures the same appointment always gets the same room.'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- JWT Configuration (Future) -->
                    <div class="config-section">
                        <h3><?php echo xlt('JWT Authentication (Future)'); ?></h3>
                        <div class="info-box">
                            <i class="fa fa-info-circle"></i>
                            <?php echo xlt('JWT authentication is not yet implemented. These fields are reserved for future use.'); ?>
                        </div>

                        <div class="form-group">
                            <label for="jwt_secret"><?php echo xlt('JWT Secret'); ?></label>
                            <input type="text" 
                                   id="jwt_secret" 
                                   name="jwt_secret" 
                                   value="<?php echo attr($currentConfig['jwt_secret']); ?>"
                                   disabled>
                            <div class="help-text">
                                <?php echo xlt('Future: JWT secret for signing tokens (currently disabled)'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="jwt_app_id"><?php echo xlt('JWT App ID'); ?></label>
                            <input type="text" 
                                   id="jwt_app_id" 
                                   name="jwt_app_id" 
                                   value="<?php echo attr($currentConfig['jwt_app_id']); ?>">
                            <div class="help-text">
                                <?php echo xlt('Application ID for JWT tokens'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> <?php echo xlt('Save Configuration'); ?>
                        </button>
                        <a href="<?php echo $GLOBALS['webroot']; ?>/interface/modules/zend_modules/module/Installer/Installer.php" class="btn btn-secondary">
                            <?php echo xlt('Back to Modules'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

