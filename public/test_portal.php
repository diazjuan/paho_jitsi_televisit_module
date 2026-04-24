<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "Testing portal_join.php...<br>";

// Test path resolution
$sessionPath = __DIR__ . '/../../../../../src/Common/Session/SessionUtil.php';
$globalsPath = __DIR__ . '/../../../globals.php';

echo "SessionUtil path: " . $sessionPath . "<br>";
echo "SessionUtil exists: " . (file_exists($sessionPath) ? 'YES' : 'NO') . "<br>";
echo "Globals path: " . $globalsPath . "<br>";
echo "Globals exists: " . (file_exists($globalsPath) ? 'YES' : 'NO') . "<br>";

if (file_exists($sessionPath)) {
    echo "Attempting to require SessionUtil...<br>";
    try {
        require_once($sessionPath);
        echo "SessionUtil loaded successfully<br>";
    } catch (\Exception $e) {
        echo "Error loading SessionUtil: " . $e->getMessage() . "<br>";
    }
}
