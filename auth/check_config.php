<?php
// בדיקת הגדרות config.php

echo "<h2>בדיקת הגדרות המערכת</h2>";

// בדיקת קובץ config.php
if (file_exists('../config.php')) {
    echo "<p style='color: green;'>✓ קובץ config.php נמצא</p>";
    
    require_once '../config.php';
    
    // בדיקת קבועים
    echo "<h3>קבועים מוגדרים:</h3>";
    $constants = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'SITE_URL', 'DASHBOARD_URL', 'FORM_URL'
    ];
    
    echo "<table border='1'>";
    echo "<tr><th>קבוע</th><th>ערך</th><th>סטטוס</th></tr>";
    foreach ($constants as $const) {
        if (defined($const)) {
            $value = constant($const);
            if ($const === 'DB_PASS') {
                $value = '***' . substr($value, -3); // הסתר את רוב הסיסמה
            }
            echo "<tr>";
            echo "<td>$const</td>";
            echo "<td>$value</td>";
            echo "<td style='color: green;'>✓</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>$const</td>";
            echo "<td>-</td>";
            echo "<td style='color: red;'>✗ לא מוגדר</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // בדיקת פונקציות
    echo "<h3>פונקציות:</h3>";
    if (function_exists('getDbConnection')) {
        echo "<p style='color: green;'>✓ פונקציה getDbConnection קיימת</p>";
    } else {
        echo "<p style='color: red;'>✗ פונקציה getDbConnection לא קיימת</p>";
    }
    
    if (function_exists('sanitizeInput')) {
        echo "<p style='color: green;'>✓ פונקציה sanitizeInput קיימת</p>";
    } else {
        echo "<p style='color: red;'>✗ פונקציה sanitizeInput לא קיימת</p>";
    }
    
    if (function_exists('generateUUID')) {
        echo "<p style='color: green;'>✓ פונקציה generateUUID קיימת</p>";
    } else {
        echo "<p style='color: red;'>✗ פונקציה generateUUID לא קיימת</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ קובץ config.php לא נמצא!</p>";
}

// בדיקת Session
echo "<h3>Session:</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✓ Session פעיל</p>";
} else {
    echo "<p style='color: red;'>✗ Session לא פעיל</p>";
}

echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// בדיקת נתיבים
echo "<h3>נתיבים:</h3>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Parent directory: " . dirname(__DIR__) . "</p>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        direction: rtl;
        padding: 20px;
        background-color: #f5f5f5;
    }
    table {
        border-collapse: collapse;
        margin: 10px 0;
        background: white;
    }
    td, th {
        padding: 8px;
        border: 1px solid #ddd;
    }
    th {
        background-color: #007bff;
        color: white;
    }
    pre {
        background: #f0f0f0;
        padding: 10px;
        border-radius: 5px;
    }
</style>