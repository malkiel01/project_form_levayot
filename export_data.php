<?php
// export_data.php - ייצוא נתונים בלבד

require_once 'config.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

$db = getDbConnection();
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();

// הגדרת headers להורדת קובץ
$filename = "data_" . $dbName . "_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// פתיחת output
echo "-- Database Data Export\n";
echo "-- Database: $dbName\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- --------------------------------------------------------\n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

echo "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
echo "/*!40101 SET NAMES utf8mb4 */;\n\n";

try {
    // קבלת רשימת כל הטבלאות
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // בדיקה אם יש נתונים בטבלה
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
        if ($count > 0) {
            echo "-- --------------------------------------------------------\n";
            echo "-- Dumping data for table `$table`\n";
            echo "-- --------------------------------------------------------\n\n";
            
            // ריקון הטבלה לפני הכנסת נתונים חדשים
            echo "TRUNCATE TABLE `$table`;\n\n";
            
            // קבלת כל הנתונים
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            // יצירת INSERT statements
            $insertCount = 0;
            $values = [];
            
            foreach ($rows as $row) {
                $valueSet = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $valueSet[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $valueSet[] = $value;
                    } else {
                        $escaped = str_replace(
                            ['\\', "'", '"', "\n", "\r", "\t"],
                            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'],
                            $value
                        );
                        $valueSet[] = "'" . $escaped . "'";
                    }
                }
                $values[] = '(' . implode(', ', $valueSet) . ')';
                
                // הכנסה בקבוצות של 100 רשומות
                if (count($values) >= 100) {
                    echo "INSERT INTO `$table` VALUES\n";
                    echo implode(",\n", $values) . ";\n\n";
                    $values = [];
                }
            }
            
            // הכנסת הרשומות הנותרות
            if (!empty($values)) {
                echo "INSERT INTO `$table` VALUES\n";
                echo implode(",\n", $values) . ";\n\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "COMMIT;\n\n";
echo "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
?>