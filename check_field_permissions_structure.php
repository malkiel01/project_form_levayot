<?php
// check_field_permissions_structure.php - בדיקת מבנה הטבלה
require_once 'config.php';

try {
    $db = getDbConnection();
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>בדיקת מבנה טבלה</title></head><body dir='rtl'>";
    echo "<h1>בדיקת מבנה טבלת field_permissions</h1>";
    
    // 1. הצג את המבנה של הטבלה
    echo "<h2>מבנה הטבלה:</h2>";
    $stmt = $db->query("DESCRIBE field_permissions");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>שם עמודה</th><th>סוג</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. הצג דוגמאות של נתונים
    echo "<h2>דוגמאות נתונים:</h2>";
    $stmt = $db->query("SELECT * FROM field_permissions LIMIT 5");
    $data = $stmt->fetchAll();
    
    if ($data) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        // כותרות
        echo "<tr>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        // נתונים
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                $display = htmlspecialchars(substr($value ?? '', 0, 50));
                if (strlen($value ?? '') > 50) $display .= '...';
                echo "<td>" . $display . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. בדוק איזה מבנה קיים
    echo "<h2>בדיקת סוג המבנה:</h2>";
    
    $hasOldStructure = false;
    $hasNewStructure = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'permission_level') $hasOldStructure = true;
        if ($col['Field'] === 'view_permission_levels') $hasNewStructure = true;
    }
    
    if ($hasOldStructure && !$hasNewStructure) {
        echo "<p style='color: red;'><strong>זוהה מבנה ישן!</strong> הטבלה משתמשת במבנה עם permission_level</p>";
    } elseif (!$hasOldStructure && $hasNewStructure) {
        echo "<p style='color: green;'><strong>זוהה מבנה חדש!</strong> הטבלה משתמשת במבנה עם view_permission_levels</p>";
    } else {
        echo "<p style='color: orange;'><strong>מבנה לא ברור!</strong> בדוק את העמודות</p>";
    }
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "שגיאה: " . $e->getMessage();
}
?>