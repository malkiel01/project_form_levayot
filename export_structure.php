<?php
// export_structure.php - ייצוא מבנה הטבלאות בלבד

require_once 'config.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

$db = getDbConnection();
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();

// הגדרת headers להורדת קובץ
$filename = "structure_" . $dbName . "_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// פתיחת output
echo "-- Database Structure Export\n";
echo "-- Database: $dbName\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- PHP Version: " . phpversion() . "\n";
echo "-- --------------------------------------------------------\n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n\n";

echo "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
echo "/*!40101 SET NAMES utf8mb4 */;\n\n";

echo "-- --------------------------------------------------------\n\n";

try {
    // קבלת רשימת כל הטבלאות
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for table `$table`\n";
        echo "-- --------------------------------------------------------\n\n";
        
        // הוספת DROP TABLE אם קיימת
        echo "DROP TABLE IF EXISTS `$table`;\n";
        
        // קבלת CREATE TABLE statement
        $createTable = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        echo $createTable['Create Table'] . ";\n\n";
    }
    
    // ייצוא Views אם קיימים
    $views = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll();
    if ($views) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Views\n";
        echo "-- --------------------------------------------------------\n\n";
        
        foreach ($views as $view) {
            $viewName = $view[0];
            echo "-- View structure for `$viewName`\n\n";
            echo "DROP VIEW IF EXISTS `$viewName`;\n";
            
            $createView = $db->query("SHOW CREATE VIEW `$viewName`")->fetch();
            echo $createView['Create View'] . ";\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}

echo "COMMIT;\n\n";
echo "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
?>