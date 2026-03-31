<?php
/**
 * Quick Configuration Script
 * Run this directly in browser: /interface/modules/custom_modules/paho_jitsi_televisit/quick_config.php
 */
require_once(__DIR__ . "/../../../globals.php");

use OpenEMR\Common\Acl\AclMain;

if (!AclMain::aclCheckCore('admin', 'super')) {
    die('Access Denied');
}

use OpenEMR\Core\ModulesClassLoader;
$classLoader = new ModulesClassLoader($GLOBALS['fileroot']);
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\PahoJitsiTelevisit\\', __DIR__ . '/src');
use OpenEMR\Modules\PahoJitsiTelevisit\Services\TelevisitConfigService;

if ($_POST['save'] ?? false) {
    $config = [
        'base_url' => $_POST['base_url'] ?? 'https://169.61.173.4:8440',
        'category_id' => (int)($_POST['category_id'] ?? 0),
    ];
    TelevisitConfigService::saveAllConfig($config);
    echo "<div style='color:green;padding:10px;background:#d4edda;border:1px solid #c3e6cb;margin:10px;'>Configuration saved!</div>";
}

$config = TelevisitConfigService::getAllConfig();
$catResult = sqlStatement("SELECT pc_catid, pc_catname, pc_constant_id FROM openemr_postcalendar_categories WHERE pc_active = 1 ORDER BY pc_catname");
?>
<!DOCTYPE html>
<html>
<head><title>Quick Config</title></head>
<body style="font-family:Arial;padding:20px;">
<h2>PAHO Jitsi Televisit - Quick Configuration</h2>
<form method="post">
    <p><label>Jitsi Base URL:<br>
    <input type="text" name="base_url" value="<?php echo htmlspecialchars($config['base_url']); ?>" style="width:400px;padding:5px;"></label></p>
    
    <p><label>Televisit Category:<br>
    <select name="category_id" style="width:400px;padding:5px;">
        <option value="">-- Select --</option>
        <?php while($cat = sqlFetchArray($catResult)): ?>
            <option value="<?php echo $cat['pc_catid']; ?>" <?php echo ($config['category_id'] == $cat['pc_catid']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['pc_catname']); ?>
                <?php if($cat['pc_constant_id'] == 'televisit') echo ' (Televisit)'; ?>
            </option>
        <?php endwhile; ?>
    </select></label></p>
    
    <p><button type="submit" name="save" value="1" style="padding:10px 20px;background:#0066cc;color:white;border:none;cursor:pointer;">Save Configuration</button></p>
</form>
</body>
</html>
