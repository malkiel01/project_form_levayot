<?php
// debug_columns.php - בדיקת עמודות בטבלת users

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>בדיקת עמודות טבלת users</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #f4f4f4; }
        .success { color: green; }
        .error { color: red; }
        code { background: #f0f0f0; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>בדיקת עמודות טבלת users</h1>
    
    <?php
    try {
        $db = getDbConnection();
        
        // בדיקה 1: הצג את כל העמודות
        echo '<h2>1. רשימת עמודות בטבלה:</h2>';
        $stmt = $db->query("SHOW COLUMNS FROM users");
        $columns = $stmt->fetchAll();
        
        echo '<table>';
        echo '<tr><th>שם עמודה</th><th>סוג</th><th>Null</th><th>Key</th><th>Default</th></tr>';
        foreach ($columns as $col) {
            echo '<tr>';
            echo '<td><code>' . $col['Field'] . '</code></td>';
            echo '<td>' . $col['Type'] . '</td>';
            echo '<td>' . $col['Null'] . '</td>';
            echo '<td>' . $col['Key'] . '</td>';
            echo '<td>' . ($col['Default'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // בדיקה 2: נסה שאילתה פשוטה
        echo '<h2>2. בדיקת שאילתה פשוטה:</h2>';
        try {
            $stmt = $db->query("SELECT id, username, firstName, lastName FROM users LIMIT 1");
            $user = $stmt->fetch();
            if ($user) {
                echo '<div class="success">✓ השאילתה עבדה!</div>';
                echo '<pre>' . print_r($user, true) . '</pre>';
            } else {
                echo '<div class="warning">אין משתמשים בטבלה</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">✗ שגיאה בשאילתה: ' . $e->getMessage() . '</div>';
        }
        
        // בדיקה 3: נסה עם backticks
        echo '<h2>3. בדיקת שאילתה עם backticks:</h2>';
        try {
            $stmt = $db->query("SELECT `id`, `username`, `firstName`, `lastName` FROM `users` LIMIT 1");
            $user = $stmt->fetch();
            if ($user) {
                echo '<div class="success">✓ השאילתה עם backticks עבדה!</div>';
                echo '<pre>' . print_r($user, true) . '</pre>';
            }
        } catch (Exception $e) {
            echo '<div class="error">✗ שגיאה בשאילתה עם backticks: ' . $e->getMessage() . '</div>';
        }
        
        // בדיקה 4: שם מסד הנתונים
        echo '<h2>4. פרטי החיבור:</h2>';
        $dbname = $db->query("SELECT DATABASE()")->fetchColumn();
        echo '<p>מסד נתונים: <code>' . $dbname . '</code></p>';
        echo '<p>Charset: <code>' . DB_CHARSET . '</code></p>';
        
        // בדיקה 5: collation
        $collation = $db->query("SHOW VARIABLES LIKE 'collation_connection'")->fetch();
        echo '<p>Collation: <code>' . $collation['Value'] . '</code></p>';
        
    } catch (Exception $e) {
        echo '<div class="error">שגיאה כללית: ' . $e->getMessage() . '</div>';
    }
    ?>
    
    <h2>5. המלצות:</h2>
    <ul>
        <li>אם אתה רואה את העמודות אבל השאילתה נכשלת, יש בעיה ב-case sensitivity</li>
        <li>נסה להשתמש ב-backticks תמיד: <code>`firstName`</code></li>
        <li>ודא שה-charset וה-collation תואמים</li>
    </ul>
</body>
</html>