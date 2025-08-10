<?php
// api/test.php - בדיקת חיבור ל-API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>בדיקת חיבור ל-API</h2>";

// בדיקת נתיב לקונפיג
$configPath = __DIR__ . '/../../config.php';
echo "<p>נתיב לקונפיג: $configPath</p>";
echo "<p>קובץ קיים: " . (file_exists($configPath) ? 'כן' : 'לא') . "</p>";

if (file_exists($configPath)) {
    require_once $configPath;
    
    echo "<h3>בדיקת SESSION:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<h3>בדיקת חיבור למסד נתונים:</h3>";
    if (isset($pdo)) {
        echo "<p style='color: green;'>✓ יש חיבור למסד נתונים</p>";
        
        // בדוק אם הטבלאות קיימות
        try {
            $tables = ['cemeteries', 'blocks', 'plots', 'rows', 'areaGraves', 'graves'];
            echo "<h3>בדיקת טבלאות:</h3>";
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->rowCount() > 0;
                echo "<p>טבלה $table: " . ($exists ? "<span style='color: green;'>✓ קיימת</span>" : "<span style='color: red;'>✗ לא קיימת</span>") . "</p>";
            }
            
            // בדוק טבלת הרשאות
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
            $permTableExists = $stmt->rowCount() > 0;
            echo "<p>טבלת user_permissions: " . ($permTableExists ? "<span style='color: green;'>✓ קיימת</span>" : "<span style='color: orange;'>⚠ לא קיימת (הרשאות רק למנהלים)</span>") . "</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>שגיאה בבדיקת טבלאות: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ אין חיבור למסד נתונים</p>";
    }
} else {
    echo "<p style='color: red;'>✗ קובץ config.php לא נמצא!</p>";
}

echo "<hr>";
echo "<p><a href='cemetery-api.php?action=getStats'>בדיקת API - getStats</a></p>";
echo "<p><a href='../'>חזרה לממשק</a></p>";
?>