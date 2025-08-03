<?php
// export_structure.php - הכנס את הקוד הזה לקובץ PHP והרץ אותו

// הגדרות מסד נתונים - עדכן לפי הפרטים שלך
$host = 'mbe-plus.com';
$username = 'mbeplusc_test'; // שם המשתמש שלך
$password = 'Gxfv16be'; // הכנס את הסיסמה
$database = 'mbeplusc_kadisha_v7';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>מבנה מסד נתונים</title>";
    echo "<style>body{font-family:Arial;direction:rtl;} pre{background:#f5f5f5;padding:10px;} .table{border:1px solid #ccc;margin:10px 0;padding:10px;}</style></head><body>";
    
    echo "<h1>מבנה מסד נתונים: $database</h1>";
    echo "<p>תאריך: " . date('Y-m-d H:i:s') . "</p>";
    
    // קבלת רשימת טבלאות
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>רשימת טבלאות (" . count($tables) . "):</h2><ul>";
    foreach ($tables as $table) {
        echo "<li><a href='#$table'>$table</a></li>";
    }
    echo "</ul>";
    
    // מעבר על כל טבלה
    foreach ($tables as $table) {
        echo "<div class='table'>";
        echo "<h2 id='$table'>טבלה: $table</h2>";
        
        // SHOW CREATE TABLE
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<h3>CREATE TABLE:</h3>";
        echo "<pre>" . htmlspecialchars($create['Create Table']) . "</pre>";
        
        // פרטי עמודות
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>עמודות:</h3>";
        echo "<table border='1' style='border-collapse:collapse;width:100%'>";
        echo "<tr><th>שם</th><th>סוג</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // ספירת שורות
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>מספר שורות:</strong> {$count['count']}</p>";
        } catch (Exception $e) {
            echo "<p><strong>מספר שורות:</strong> לא ניתן לספור</p>";
        }
        
        // אם זה field_permissions, הצג את הנתונים
        if ($table === 'field_permissions') {
            echo "<h3>נתונים בטבלה:</h3>";
            try {
                $stmt = $pdo->query("SELECT * FROM `field_permissions` ORDER BY id");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($data) {
                    echo "<table border='1' style='border-collapse:collapse;width:100%'>";
                    // כותרות
                    echo "<tr>";
                    foreach (array_keys($data[0]) as $header) {
                        echo "<th>$header</th>";
                    }
                    echo "</tr>";
                    // נתונים
                    foreach ($data as $row) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    // בדיקת כפילויות
                    echo "<h3>בדיקת כפילויות בfield_permissions:</h3>";
                    $stmt = $pdo->query("
                        SELECT field_name, COUNT(*) as count, GROUP_CONCAT(id) as ids
                        FROM field_permissions 
                        GROUP BY field_name 
                        HAVING COUNT(*) > 1
                    ");
                    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($duplicates) {
                        echo "<table border='1' style='border-collapse:collapse'>";
                        echo "<tr><th>שדה</th><th>כמות</th><th>IDs</th></tr>";
                        foreach ($duplicates as $dup) {
                            echo "<tr style='background:#ffcccc'>";
                            echo "<td>{$dup['field_name']}</td>";
                            echo "<td>{$dup['count']}</td>";
                            echo "<td>{$dup['ids']}</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p style='color:green'>אין כפילויות!</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p>שגיאה בקריאת נתונים: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "</div><hr>";
    }
    
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "שגיאת חיבור: " . $e->getMessage();
}
?>