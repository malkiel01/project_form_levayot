<?php
// api/test.php - בדיקת חיבור ל-API
// אל תציג שום דבר לפני הטעינה של config
$configPath = __DIR__ . '/../../config.php';

if (file_exists($configPath)) {
    // שמור את הפלט בבאפר
    ob_start();
    require_once $configPath;
    ob_end_clean();
}

// כעת אפשר להציג
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת API</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h2>בדיקת חיבור ל-API</h2>
    
    <?php
    echo "<p>נתיב לקונפיג: $configPath</p>";
    echo "<p>קובץ קיים: " . (file_exists($configPath) ? '<span class="success">כן</span>' : '<span class="error">לא</span>') . "</p>";
    
    if (file_exists($configPath)) {
        echo "<h3>בדיקת SESSION:</h3>";
        if (isset($_SESSION)) {
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            if (isset($_SESSION['user_id'])) {
                echo "<p class='success'>✓ משתמש מחובר: ID " . $_SESSION['user_id'] . "</p>";
                echo "<p>רמת הרשאה: " . ($_SESSION['permission_level'] ?? 'לא מוגדר') . "</p>";
            } else {
                echo "<p class='error'>✗ משתמש לא מחובר</p>";
            }
        } else {
            echo "<p class='error'>✗ SESSION לא פעיל</p>";
        }
        
        echo "<h3>בדיקת חיבור למסד נתונים:</h3>";
        
        // נסה להתחבר ישירות
        try {
            // בדוק אם יש פונקציה getDbConnection
            if (function_exists('getDbConnection')) {
                $db = getDbConnection();
                if ($db) {
                    echo "<p class='success'>✓ יש חיבור למסד נתונים (דרך getDbConnection)</p>";
                    $pdo = $db; // השתמש בחיבור הזה
                }
            } elseif (isset($pdo)) {
                echo "<p class='success'>✓ יש חיבור למסד נתונים (משתנה גלובלי)</p>";
            } else {
                echo "<p class='error'>✗ אין חיבור למסד נתונים</p>";
                echo "<p>מנסה להתחבר ישירות...</p>";
                
                // בדוק אם יש קבועים של חיבור
                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                    try {
                        $pdo = new PDO(
                            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                            DB_USER,
                            DB_PASS,
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );
                        echo "<p class='success'>✓ התחברתי בהצלחה!</p>";
                    } catch (PDOException $e) {
                        echo "<p class='error'>✗ שגיאה בחיבור: " . $e->getMessage() . "</p>";
                    }
                } else {
                    echo "<p class='error'>✗ חסרים פרטי חיבור למסד נתונים</p>";
                }
            }
            
            // אם יש חיבור, בדוק טבלאות
            if (isset($pdo)) {
                $tables = ['cemeteries', 'blocks', 'plots', 'rows', 'areaGraves', 'graves'];
                echo "<h3>בדיקת טבלאות:</h3>";
                
                foreach ($tables as $table) {
                    try {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        $exists = $stmt->rowCount() > 0;
                        echo "<p>טבלה $table: " . ($exists ? "<span class='success'>✓ קיימת</span>" : "<span class='error'>✗ לא קיימת</span>") . "</p>";
                    } catch (PDOException $e) {
                        echo "<p class='error'>שגיאה בבדיקת $table: " . $e->getMessage() . "</p>";
                    }
                }
                
                // בדוק טבלת הרשאות
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
                    $permTableExists = $stmt->rowCount() > 0;
                    echo "<p>טבלת user_permissions: " . ($permTableExists ? "<span class='success'>✓ קיימת</span>" : "<span class='warning'>⚠ לא קיימת (הרשאות רק למנהלים)</span>") . "</p>";
                } catch (PDOException $e) {
                    echo "<p class='warning'>לא ניתן לבדוק טבלת הרשאות</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>שגיאה כללית: " . $e->getMessage() . "</p>";
        }
    }
    ?>
    
    <hr>
    <h3>קישורים:</h3>
    <ul>
        <li><a href="cemetery-api.php?action=getStats">בדיקת API - getStats</a></li>
        <li><a href="create-tables.php">יצירת טבלאות</a></li>
        <li><a href="../">חזרה לממשק</a></li>
    </ul>
</body>
</html>