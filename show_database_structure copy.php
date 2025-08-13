<?php
// show_database_structure.php - הצגת מבנה מסד הנתונים

require_once 'config.php';

// בדיקת הרשאות - רק למנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

$db = getDbConnection();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מבנה מסד הנתונים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .table-name {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            margin: -20px -20px 20px -20px;
            border-radius: 10px 10px 0 0;
        }
        .field-type {
            color: #28a745;
            font-family: monospace;
        }
        .field-key {
            background: #ffc107;
            color: #000;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .field-null {
            color: #6c757d;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .stats {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">מבנה מסד הנתונים</h1>
        
        <?php
        try {
            // קבלת שם מסד הנתונים
            $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
            echo "<div class='alert alert-info'>מסד נתונים: <strong>$dbName</strong></div>";
            
            // קבלת רשימת כל הטבלאות
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<div class='stats'>";
            echo "<h5>סטטיסטיקות:</h5>";
            echo "<p>סה\"כ טבלאות: <strong>" . count($tables) . "</strong></p>";
            echo "</div>";
            
            // יצירת תוכן עניינים
            echo "<div class='table-container'>";
            echo "<h3>תוכן עניינים</h3>";
            echo "<ul class='list-unstyled'>";
            foreach ($tables as $table) {
                echo "<li><a href='#table-$table'>$table</a></li>";
            }
            echo "</ul>";
            echo "</div>";
            
            // הצגת כל טבלה
            foreach ($tables as $table) {
                echo "<div class='table-container' id='table-$table'>";
                echo "<h3 class='table-name'>$table</h3>";
                
                // קבלת מידע על הטבלה
                $tableInfo = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                $createStatement = $tableInfo['Create Table'];
                
                // קבלת מבנה הטבלה
                $columns = $db->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll();
                
                // קבלת אינדקסים
                $indexes = $db->query("SHOW INDEXES FROM `$table`")->fetchAll();
                
                // קבלת מספר רשומות
                $rowCount = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                
                echo "<p class='text-muted'>מספר רשומות: <strong>$rowCount</strong></p>";
                
                // טבלת עמודות
                echo "<h5>עמודות:</h5>";
                echo "<table class='table table-bordered table-sm'>";
                echo "<thead class='table-light'>";
                echo "<tr>";
                echo "<th>שם עמודה</th>";
                echo "<th>סוג</th>";
                echo "<th>Null</th>";
                echo "<th>מפתח</th>";
                echo "<th>ברירת מחדל</th>";
                echo "<th>תוספת</th>";
                echo "<th>הערה</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
                    echo "<td class='field-type'>" . htmlspecialchars($column['Type']) . "</td>";
                    echo "<td class='field-null'>" . htmlspecialchars($column['Null']) . "</td>";
                    echo "<td>";
                    if ($column['Key'] == 'PRI') {
                        echo "<span class='field-key'>PRIMARY</span>";
                    } elseif ($column['Key'] == 'UNI') {
                        echo "<span class='field-key'>UNIQUE</span>";
                    } elseif ($column['Key'] == 'MUL') {
                        echo "<span class='field-key'>INDEX</span>";
                    }
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
                    echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Comment']) . "</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                
                // אינדקסים
                if ($indexes) {
                    echo "<h5>אינדקסים:</h5>";
                    echo "<table class='table table-bordered table-sm'>";
                    echo "<thead class='table-light'>";
                    echo "<tr>";
                    echo "<th>שם אינדקס</th>";
                    echo "<th>עמודה</th>";
                    echo "<th>ייחודי</th>";
                    echo "<th>סוג</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    $processedIndexes = [];
                    foreach ($indexes as $index) {
                        if (!in_array($index['Key_name'], $processedIndexes)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
                            echo "<td>" . ($index['Non_unique'] ? 'לא' : 'כן') . "</td>";
                            echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
                            echo "</tr>";
                            $processedIndexes[] = $index['Key_name'];
                        }
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                }
                
                // הצגת CREATE TABLE
                echo "<h5>CREATE TABLE Statement:</h5>";
                echo "<div class='position-relative'>";
                echo "<button class='btn btn-sm btn-secondary copy-btn' onclick='copyToClipboard(\"create-$table\")'>העתק</button>";
                echo "<pre id='create-$table'>" . htmlspecialchars($createStatement) . "</pre>";
                echo "</div>";
                
                echo "</div>";
            }
            
            // הצגת קשרים בין טבלאות (Foreign Keys)
            echo "<div class='table-container'>";
            echo "<h3 class='table-name'>קשרים בין טבלאות (Foreign Keys)</h3>";
            
            $foreignKeys = $db->query("
                SELECT 
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_SCHEMA = '$dbName'
                ORDER BY TABLE_NAME, COLUMN_NAME
            ")->fetchAll();
            
            if ($foreignKeys) {
                echo "<table class='table table-bordered'>";
                echo "<thead class='table-light'>";
                echo "<tr>";
                echo "<th>טבלה</th>";
                echo "<th>עמודה</th>";
                echo "<th>מפנה ל-טבלה</th>";
                echo "<th>עמודה</th>";
                echo "<th>שם קשר</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                
                foreach ($foreignKeys as $fk) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fk['TABLE_NAME']) . "</td>";
                    echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
                    echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
                    echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
                    echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
            } else {
                echo "<p>לא נמצאו קשרי Foreign Key</p>";
            }
            
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>שגיאה: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
    
    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('הועתק ללוח!');
        });
    }
    </script>
</body>
</html>