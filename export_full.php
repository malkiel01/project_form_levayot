<?php
// export_full.php - ייצוא מלא (מבנה + נתונים)

require_once 'config.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

$db = getDbConnection();
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();

// הגדרת headers להורדת קובץ
$filename = "full_backup_" . $dbName . "_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// פתיחת output
echo "-- Full Database Backup\n";
echo "-- Database: $dbName\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Server version: " . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
echo "-- PHP Version: " . phpversion() . "\n";
echo "-- --------------------------------------------------------\n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

echo "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
echo "/*!40101 SET NAMES utf8mb4 */;\n\n";

echo "-- Database: `$dbName`\n";
echo "-- --------------------------------------------------------\n\n";

try {
    // קבלת רשימת כל הטבלאות
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for table `$table`\n";
        echo "-- --------------------------------------------------------\n\n";
        
        // מבנה הטבלה
        echo "DROP TABLE IF EXISTS `$table`;\n";
        $createTable = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        echo $createTable['Create Table'] . ";\n\n";
        
        // נתוני הטבלה
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
        if ($count > 0) {
            echo "-- --------------------------------------------------------\n";
            echo "-- Dumping data for table `$table` ($count rows)\n";
            echo "-- --------------------------------------------------------\n\n";
            
            // קבלת שמות העמודות
            $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            
            // יצירת INSERT statements
            $stmt = $db->query("SELECT * FROM `$table`");
            $rowCount = 0;
            $values = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $valueSet = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $valueSet[] = 'NULL';
                    } elseif (is_numeric($value) && !preg_match('/^0[0-9]+$/', $value)) {
                        $valueSet[] = $value;
                    } else {
                        $escaped = str_replace(
                            ['\\', "'", '"', "\n", "\r", "\t", "\0"],
                            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t', '\\0'],
                            $value
                        );
                        $valueSet[] = "'" . $escaped . "'";
                    }
                }
                $values[] = '(' . implode(', ', $valueSet) . ')';
                $rowCount++;
                
                // הכנסה בקבוצות של 50 רשומות או בסוף
                if (count($values) >= 50 || $rowCount == $count) {
                    echo "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                    echo implode(",\n", $values) . ";\n\n";
                    $values = [];
                }
            }
        }
    }
    
    // ייצוא Views
    $views = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll();
    if ($views) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Views\n";
        echo "-- --------------------------------------------------------\n\n";
        
        foreach ($views as $view) {
            $viewName = $view[0];
            echo "-- View: `$viewName`\n\n";
            echo "DROP VIEW IF EXISTS `$viewName`;\n";
            
            $createView = $db->query("SHOW CREATE VIEW `$viewName`")->fetch();
            // הסרת DEFINER clause
            $viewDef = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s+/', '', $createView['Create View']);
            echo $viewDef . ";\n\n";
        }
    }
    
    // ייצוא Stored Procedures אם קיימים
    $procedures = $db->query("SHOW PROCEDURE STATUS WHERE Db = '$dbName'")->fetchAll();
    if ($procedures) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Stored Procedures\n";
        echo "-- --------------------------------------------------------\n\n";
        
        foreach ($procedures as $proc) {
            $procName = $proc['Name'];
            echo "-- Procedure: `$procName`\n\n";
            echo "DROP PROCEDURE IF EXISTS `$procName`;\n";
            echo "DELIMITER $$\n";
            
            $createProc = $db->query("SHOW CREATE PROCEDURE `$procName`")->fetch();
            $procDef = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s+/', '', $createProc['Create Procedure']);
            echo $procDef . "$$\n";
            echo "DELIMITER ;\n\n";
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

// הוספת הערות סיום
echo "\n-- --------------------------------------------------------\n";
echo "-- Backup completed successfully\n";
echo "-- Total tables: " . count($tables) . "\n";
echo "-- Backup size: " . strlen(ob_get_contents()) . " bytes\n";
echo "-- --------------------------------------------------------\n";
?>