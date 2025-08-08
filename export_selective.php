<?php
// export_selective.php - ייצוא סלקטיבי של טבלאות נבחרות

require_once 'config.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

// בדיקת פרמטרים
if (!isset($_POST['tables']) || empty($_POST['tables'])) {
    die('No tables selected');
}

$db = getDbConnection();
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();
$selectedTables = $_POST['tables'];
$includeStructure = isset($_POST['include_structure']);
$includeData = isset($_POST['include_data']);
$dropTables = isset($_POST['drop_tables']);
$disableFK = isset($_POST['disable_fk']);

// הגדרת headers להורדת קובץ
$filename = "selective_backup_" . $dbName . "_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// פתיחת output
echo "-- Selective Database Backup\n";
echo "-- Database: $dbName\n";
echo "-- Selected tables: " . implode(', ', $selectedTables) . "\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Options: ";
echo ($includeStructure ? "Structure " : "");
echo ($includeData ? "Data " : "");
echo ($dropTables ? "Drop " : "");
echo "\n-- --------------------------------------------------------\n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n";

if ($disableFK) {
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
}
echo "\n";

echo "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
echo "/*!40101 SET NAMES utf8mb4 */;\n\n";

try {
    // מיון הטבלאות לפי תלויות
    $sortedTables = sortTablesByDependencies($db, $selectedTables, $dbName);
    
    foreach ($sortedTables as $table) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Table: `$table`\n";
        echo "-- --------------------------------------------------------\n\n";
        
        // מבנה הטבלה
        if ($includeStructure) {
            if ($dropTables) {
                echo "DROP TABLE IF EXISTS `$table`;\n";
            }
            
            $createTable = $db->query("SHOW CREATE TABLE `$table`")->fetch();
            echo $createTable['Create Table'] . ";\n\n";
        }
        
        // נתוני הטבלה
        if ($includeData) {
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            
            if ($count > 0) {
                echo "-- Data for table `$table` ($count rows)\n\n";
                
                // קבלת שמות העמודות
                $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                
                // אם לא נבחר מבנה, נוסיף TRUNCATE
                if (!$includeStructure) {
                    echo "TRUNCATE TABLE `$table`;\n\n";
                }
                
                // יצירת INSERT statements
                $stmt = $db->query("SELECT * FROM `$table`");
                $values = [];
                $rowCount = 0;
                
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
                    
                    // הכנסה בקבוצות של 50
                    if (count($values) >= 50 || $rowCount == $count) {
                        echo "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        echo implode(",\n", $values) . ";\n\n";
                        $values = [];
                    }
                }
            }
        }
    }
    
    // בדיקה אם יש Views שתלויים בטבלאות שנבחרו
    $views = $db->query("
        SELECT DISTINCT v.TABLE_NAME
        FROM INFORMATION_SCHEMA.VIEW_TABLE_USAGE v
        WHERE v.TABLE_SCHEMA = '$dbName'
        AND v.TABLE_NAME IN ('" . implode("','", $selectedTables) . "')
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    if ($views && $includeStructure) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Related Views\n";
        echo "-- --------------------------------------------------------\n\n";
        
        foreach ($views as $viewName) {
            echo "DROP VIEW IF EXISTS `$viewName`;\n";
            $createView = $db->query("SHOW CREATE VIEW `$viewName`")->fetch();
            $viewDef = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s+/', '', $createView['Create View']);
            echo $viewDef . ";\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}

if ($disableFK) {
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
}
echo "COMMIT;\n\n";
echo "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
echo "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
echo "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

// הוספת סיכום
echo "\n-- --------------------------------------------------------\n";
echo "-- Backup Summary\n";
echo "-- Tables backed up: " . count($selectedTables) . "\n";
echo "-- --------------------------------------------------------\n";

// פונקציה למיון טבלאות לפי תלויות
function sortTablesByDependencies($db, $tables, $dbName) {
    $dependencies = [];
    $sorted = [];
    
    // בניית מפת תלויות
    foreach ($tables as $table) {
        $deps = $db->query("
            SELECT DISTINCT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
            AND TABLE_SCHEMA = '$dbName'
            AND REFERENCED_TABLE_NAME IN ('" . implode("','", $tables) . "')
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $dependencies[$table] = $deps;
    }
    
    // מיון טופולוגי
    $visited = [];
    foreach ($tables as $table) {
        if (!isset($visited[$table])) {
            topologicalSort($table, $dependencies, $visited, $sorted);
        }
    }
    
    return array_reverse($sorted);
}

function topologicalSort($table, &$dependencies, &$visited, &$sorted) {
    $visited[$table] = true;
    
    if (isset($dependencies[$table])) {
        foreach ($dependencies[$table] as $dep) {
            if (!isset($visited[$dep])) {
                topologicalSort($dep, $dependencies, $visited, $sorted);
            }
        }
    }
    
    $sorted[] = $table;
}
?>