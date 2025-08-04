<?php
// includes/debug_dashboard.php - קובץ בדיקה זמני

echo "<h1>בדיקת מיקומים</h1>";
echo "<pre>";

// הדפס את המיקום הנוכחי
echo "Current directory: " . __DIR__ . "\n";
echo "Parent directory: " . dirname(__DIR__) . "\n\n";

// בדוק אילו קבצים קיימים
echo "Files in current directory (includes/):\n";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "  - $file\n";
    }
}

echo "\nChecking file existence:\n";
echo "nav.php exists: " . (file_exists(__DIR__ . '/nav.php') ? 'YES' : 'NO') . "\n";
echo "dashboard.php exists: " . (file_exists(__DIR__ . '/dashboard.php') ? 'YES' : 'NO') . "\n";
echo "../config.php exists: " . (file_exists(dirname(__DIR__) . '/config.php') ? 'YES' : 'NO') . "\n";

echo "</pre>";

// נסה לטעון את config
echo "<h2>נסיון טעינת config.php:</h2>";
if (file_exists(dirname(__DIR__) . '/config.php')) {
    require_once dirname(__DIR__) . '/config.php';
    echo "<p style='color:green;'>✓ Config loaded successfully</p>";
    echo "<p>SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NOT DEFINED') . "</p>";
} else {
    echo "<p style='color:red;'>✗ Config file not found!</p>";
}

// נסה לטעון את nav.php
echo "<h2>נסיון טעינת nav.php:</h2>";
if (file_exists(__DIR__ . '/nav.php')) {
    echo "<p style='color:green;'>✓ nav.php file exists</p>";
    // אל תטען אותו עדיין כי הוא מצפה ל-session
} else {
    echo "<p style='color:red;'>✗ nav.php file not found!</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>לחץ כאן לנסות את הדשבורד</a></p>";