<?php
// api/create-tables.php - יצירת טבלאות למערכת בתי עלמין
require_once '../../config.php';

// בדיקת הרשאות - רק מנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('אין הרשאה');
}

$queries = [
    // בתי עלמין
    "CREATE TABLE IF NOT EXISTS `cemeteries` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // גושים
    "CREATE TABLE IF NOT EXISTS `blocks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `cemetery_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `cemetery_id` (`cemetery_id`),
        CONSTRAINT `blocks_cemetery_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // חלקות
    "CREATE TABLE IF NOT EXISTS `plots` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `block_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `block_id` (`block_id`),
        CONSTRAINT `plots_block_fk` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // שורות
    "CREATE TABLE IF NOT EXISTS `rows` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `plot_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `plot_id` (`plot_id`),
        CONSTRAINT `rows_plot_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // אחוזות קבר
    "CREATE TABLE IF NOT EXISTS `areaGraves` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `row_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `row_id` (`row_id`),
        CONSTRAINT `areagraves_row_fk` FOREIGN KEY (`row_id`) REFERENCES `rows` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // קברים
    "CREATE TABLE IF NOT EXISTS `graves` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `areaGrave_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `grave_number` varchar(50) DEFAULT NULL,
        `code` varchar(50) DEFAULT NULL,
        `is_available` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `areaGrave_id` (`areaGrave_id`),
        CONSTRAINT `graves_areagrave_fk` FOREIGN KEY (`areaGrave_id`) REFERENCES `areaGraves` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "<h2>יצירת טבלאות למערכת בתי עלמין</h2>";

$success = true;
foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        // נמצא את שם הטבלה מהשאילתה
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $query, $matches);
        $tableName = $matches[1] ?? 'unknown';
        echo "<p style='color: green;'>✓ טבלה $tableName נוצרה בהצלחה</p>";
    } catch (PDOException $e) {
        $success = false;
        echo "<p style='color: red;'>✗ שגיאה ביצירת טבלה: " . $e->getMessage() . "</p>";
    }
}

if ($success) {
    echo "<h3 style='color: green;'>כל הטבלאות נוצרו בהצלחה!</h3>";
    
    // הוסף נתוני דוגמה
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM cemeteries");
        if ($stmt->fetchColumn() == 0) {
            echo "<h3>הוספת נתוני דוגמה...</h3>";
            
            // הוסף בית עלמין לדוגמה
            $pdo->exec("INSERT INTO cemeteries (name, code) VALUES ('בית עלמין ראשי', 'MAIN')");
            echo "<p style='color: blue;'>נוסף בית עלמין לדוגמה</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>לא ניתן להוסיף נתוני דוגמה: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='test.php'>בדיקת חיבור</a></p>";
echo "<p><a href='../'>חזרה לממשק</a></p>";
?>