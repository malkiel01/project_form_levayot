// debug_viewer.php
<?php
$logFile = 'logs/ajax_save_' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
}
?>