<?php
// check_logs.php - בדיקת מיקום הלוגים
require_once 'config.php';

echo "<h2>בדיקת מיקום לוגים</h2>";

// בדוק את error_log הנוכחי
echo "<h3>PHP Error Log:</h3>";
echo "<pre>";
echo "error_log setting: " . ini_get('error_log') . "\n";
echo "Current directory: " . __DIR__ . "\n";
echo "Logs directory exists: " . (is_dir(__DIR__ . '/logs') ? 'YES' : 'NO') . "\n";
echo "</pre>";

// בדוק קבצי לוג
echo "<h3>קבצי לוג קיימים:</h3>";
$logsDir = __DIR__ . '/logs/';
if (is_dir($logsDir)) {
    $files = scandir($logsDir);
    echo "<pre>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $fullPath = $logsDir . $file;
            echo $file . " - " . filesize($fullPath) . " bytes - " . date("Y-m-d H:i:s", filemtime($fullPath)) . "\n";
        }
    }
    echo "</pre>";
    
    // הצג את התוכן של הלוג האחרון
    $todayLog = $logsDir . 'ajax_save_' . date('Y-m-d') . '.log';
    if (file_exists($todayLog)) {
        echo "<h3>תוכן הלוג של היום:</h3>";
        echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 500px; overflow-y: auto;'>";
        echo htmlspecialchars(file_get_contents($todayLog));
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>תיקיית logs לא נמצאה!</p>";
    
    // נסה ליצור אותה
    if (mkdir($logsDir, 0777, true)) {
        echo "<p style='color: green;'>תיקיית logs נוצרה בהצלחה!</p>";
    } else {
        echo "<p style='color: red;'>לא ניתן ליצור את תיקיית logs!</p>";
    }
}

// בדוק את קובץ php_errors.log
$phpErrorsLog = __DIR__ . '/logs/php_errors.log';
if (file_exists($phpErrorsLog)) {
    echo "<h3>PHP Errors Log:</h3>";
    echo "<pre style='background: #ffe0e0; padding: 10px; max-height: 900px; overflow-y: auto;'>";
    echo htmlspecialchars(tail($phpErrorsLog, 50));
    echo "</pre>";
}

function tail($filename, $lines = 50) {
    $file = new SplFileObject($filename, 'r');
    $file->seek(PHP_INT_MAX);
    $last_line = $file->key();
    $lines = $last_line > $lines ? $lines : $last_line;
    $file->seek($last_line - $lines);
    $content = '';
    while (!$file->eof()) {
        $content .= $file->current();
        $file->next();
    }
    return $content;
}
?>