<?php
// show_database_structure.php - הצגת מבנה מסד הנתונים עם בדיקת קישורים מלאה

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
    <title>מבנה מסד הנתונים - מתקדם</title>
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
        .fk-in {
            background: #d1ecf1;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #bee5eb;
        }
        .fk-out {
            background: #f8d7da;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #f5c6cb;
        }
        .dependencies {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        .btn-check-relations {
            margin: 5px;
        }
        .relation-graph {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            font-family: monospace;
        }
        .alert-dependency {
            background: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">מבנה מסד הנתונים - ניתוח מתקדם</h1>
        
        <!-- כרטיסיות ניווט -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#structure">מבנה טבלאות</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#relations">קשרים וקישורים</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#dependencies">תלויות ובעיות</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#cleanup">כלי ניקוי</a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- כרטיסיית מבנה -->
            <div id="structure" class="tab-pane fade show active">
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
                    
                    // ספירת קשרי FK
                    $fkCount = $db->query("
                        SELECT COUNT(*) 
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                        WHERE REFERENCED_TABLE_NAME IS NOT NULL
                        AND TABLE_SCHEMA = '$dbName'
                    ")->fetchColumn();
                    echo "<p>סה\"כ קשרי Foreign Key: <strong>$fkCount</strong></p>";
                    echo "</div>";
                    
                    // יצירת תוכן עניינים
                    echo "<div class='table-container'>";
                    echo "<h3>תוכן עניינים</h3>";
                    echo "<div class='row'>";
                    foreach ($tables as $index => $table) {
                        if ($index % 3 == 0 && $index > 0) echo "</div><div class='row'>";
                        echo "<div class='col-md-4'><a href='#table-$table'>$table</a></div>";
                    }
                    echo "</div>";
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
                        
                        // בדיקת קישורים נכנסים (טבלאות שמצביעות אלי)
                        $incomingFK = $db->query("
                            SELECT 
                                TABLE_NAME,
                                COLUMN_NAME,
                                CONSTRAINT_NAME
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                            WHERE REFERENCED_TABLE_NAME = '$table'
                            AND TABLE_SCHEMA = '$dbName'
                        ")->fetchAll();
                        
                        if ($incomingFK) {
                            echo "<div class='fk-in'>";
                            echo "<strong>קישורים נכנסים (טבלאות שמצביעות לכאן):</strong><br>";
                            foreach ($incomingFK as $fk) {
                                echo "← <code>{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']}</code> ";
                                echo "<small class='text-muted'>({$fk['CONSTRAINT_NAME']})</small><br>";
                            }
                            echo "</div>";
                        }
                        
                        // בדיקת קישורים יוצאים (לאן אני מצביע)
                        $outgoingFK = $db->query("
                            SELECT 
                                COLUMN_NAME,
                                REFERENCED_TABLE_NAME,
                                REFERENCED_COLUMN_NAME,
                                CONSTRAINT_NAME
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                            WHERE TABLE_NAME = '$table'
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                            AND TABLE_SCHEMA = '$dbName'
                        ")->fetchAll();
                        
                        if ($outgoingFK) {
                            echo "<div class='fk-out'>";
                            echo "<strong>קישורים יוצאים (לאן הטבלה מצביעה):</strong><br>";
                            foreach ($outgoingFK as $fk) {
                                echo "→ <code>{$fk['COLUMN_NAME']}</code> מצביע ל- ";
                                echo "<code>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</code> ";
                                echo "<small class='text-muted'>({$fk['CONSTRAINT_NAME']})</small><br>";
                            }
                            echo "</div>";
                        }
                        
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
                            echo "</tr>";
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                        
                        // הצגת CREATE TABLE
                        echo "<h5>CREATE TABLE Statement:</h5>";
                        echo "<div class='position-relative'>";
                        echo "<button class='btn btn-sm btn-secondary copy-btn' onclick='copyToClipboard(\"create-$table\")'>העתק</button>";
                        echo "<pre id='create-$table'>" . htmlspecialchars($createStatement) . "</pre>";
                        echo "</div>";
                        
                        echo "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>שגיאה: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
            
            <!-- כרטיסיית קשרים -->
            <div id="relations" class="tab-pane fade">
                <div class="table-container">
                    <h3 class="table-name">מפת קשרים מלאה</h3>
                    <?php
                    // קבלת כל הקשרים
                    $allRelations = $db->query("
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
                    
                    if ($allRelations) {
                        // יצירת גרף קשרים
                        echo "<div class='relation-graph'>";
                        echo "<h5>תרשים קשרים:</h5>";
                        $relationMap = [];
                        foreach ($allRelations as $rel) {
                            if (!isset($relationMap[$rel['REFERENCED_TABLE_NAME']])) {
                                $relationMap[$rel['REFERENCED_TABLE_NAME']] = [];
                            }
                            $relationMap[$rel['REFERENCED_TABLE_NAME']][] = $rel['TABLE_NAME'];
                        }
                        
                        foreach ($relationMap as $parent => $children) {
                            echo "<div style='margin-bottom: 15px;'>";
                            echo "<strong>$parent</strong><br>";
                            foreach (array_unique($children) as $child) {
                                echo "&nbsp;&nbsp;&nbsp;└─ $child<br>";
                            }
                            echo "</div>";
                        }
                        echo "</div>";
                        
                        // טבלה מפורטת
                        echo "<h5 class='mt-4'>פרטי קשרים:</h5>";
                        echo "<table class='table table-bordered'>";
                        echo "<thead class='table-light'>";
                        echo "<tr>";
                        echo "<th>טבלה</th>";
                        echo "<th>עמודה</th>";
                        echo "<th>סוג קשר</th>";
                        echo "<th>טבלת יעד</th>";
                        echo "<th>עמודת יעד</th>";
                        echo "<th>שם אילוץ</th>";
                        echo "</tr>";
                        echo "</thead>";
                        echo "<tbody>";
                        
                        foreach ($allRelations as $rel) {
                            echo "<tr>";
                            echo "<td><strong>{$rel['TABLE_NAME']}</strong></td>";
                            echo "<td>{$rel['COLUMN_NAME']}</td>";
                            echo "<td><span class='badge bg-primary'>FOREIGN KEY</span></td>";
                            echo "<td><strong>{$rel['REFERENCED_TABLE_NAME']}</strong></td>";
                            echo "<td>{$rel['REFERENCED_COLUMN_NAME']}</td>";
                            echo "<td><code>{$rel['CONSTRAINT_NAME']}</code></td>";
                            echo "</tr>";
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                    } else {
                        echo "<p class='alert alert-info'>לא נמצאו קשרי Foreign Key במסד הנתונים</p>";
                    }
                    ?>
                </div>
            </div>
            
            <!-- כרטיסיית תלויות -->
            <div id="dependencies" class="tab-pane fade">
                <div class="table-container">
                    <h3 class="table-name">בדיקת תלויות ובעיות</h3>
                    
                    <?php
                    // בדיקת טבלאות ללא Primary Key
                    echo "<div class='dependencies'>";
                    echo "<h5>טבלאות ללא Primary Key:</h5>";
                    $noPK = $db->query("
                        SELECT TABLE_NAME
                        FROM INFORMATION_SCHEMA.TABLES
                        WHERE TABLE_SCHEMA = '$dbName'
                        AND TABLE_NAME NOT IN (
                            SELECT DISTINCT TABLE_NAME
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                            WHERE CONSTRAINT_NAME = 'PRIMARY'
                            AND TABLE_SCHEMA = '$dbName'
                        )
                    ")->fetchAll(PDO::FETCH_COLUMN);
                    
                    if ($noPK) {
                        echo "<ul>";
                        foreach ($noPK as $table) {
                            echo "<li class='text-danger'>$table</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<p class='text-success'>✓ כל הטבלאות מכילות Primary Key</p>";
                    }
                    echo "</div>";
                    
                    // בדיקת קשרים שבורים
                    echo "<div class='dependencies'>";
                    echo "<h5>בדיקת קשרים שבורים:</h5>";
                    foreach ($tables as $table) {
                        $brokenLinks = checkBrokenLinks($db, $table, $dbName);
                        if ($brokenLinks) {
                            echo "<div class='alert alert-dependency'>";
                            echo "<strong>$table:</strong><br>";
                            foreach ($brokenLinks as $issue) {
                                echo "- {$issue}<br>";
                            }
                            echo "</div>";
                        }
                    }
                    echo "<p class='text-muted'>הבדיקה מחפשת ערכים שלא קיימים בטבלאות המקושרות</p>";
                    echo "</div>";
                    
                    // טבלאות שאי אפשר למחוק
                    echo "<div class='dependencies'>";
                    echo "<h5>סדר מחיקת טבלאות (בגלל תלויות):</h5>";
                    $deletionOrder = calculateDeletionOrder($db, $tables, $dbName);
                    echo "<ol>";
                    foreach ($deletionOrder as $table) {
                        echo "<li>$table</li>";
                    }
                    echo "</ol>";
                    echo "</div>";
                    ?>
                </div>
            </div>
            
            <!-- כרטיסיית כלי ניקוי -->
            <div id="cleanup" class="tab-pane fade">
                <div class="table-container">
                    <h3 class="table-name">כלי ניקוי וניהול</h3>
                    
                    <div class="alert alert-warning">
                        <strong>אזהרה!</strong> הפעולות בחלק זה יכולות למחוק נתונים. השתמש בזהירות!
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>הסרת מפתחות זרים</h5>
                            <p>הסר את כל המפתחות הזרים כדי לאפשר מחיקת טבלאות</p>
                            <button class="btn btn-warning" onclick="showRemoveFKScript()">
                                הצג סקריפט הסרת FK
                            </button>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>ניקוי מסד נתונים</h5>
                            <p>מחיקת כל הטבלאות בסדר הנכון</p>
                            <button class="btn btn-danger" onclick="showCleanupScript()">
                                הצג סקריפט ניקוי
                            </button>
                        </div>
                    </div>
                    
                    <div id="scriptOutput" class="mt-4"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('הועתק ללוח!');
        });
    }
    
    function showRemoveFKScript() {
        const script = `-- הסרת כל המפתחות הזרים
SET FOREIGN_KEY_CHECKS = 0;

-- שאילתה למציאת כל המפתחות הזרים
SELECT CONCAT('ALTER TABLE \`', TABLE_NAME, '\` DROP FOREIGN KEY \`', CONSTRAINT_NAME, '\`;')
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_SCHEMA = DATABASE();

SET FOREIGN_KEY_CHECKS = 1;`;
        
        document.getElementById('scriptOutput').innerHTML = 
            '<pre class="bg-dark text-light p-3">' + script + '</pre>' +
            '<button class="btn btn-sm btn-secondary mt-2" onclick="copyScript()">העתק סקריפט</button>';
    }
    
    function showCleanupScript() {
        const script = `-- ניקוי מסד נתונים
SET FOREIGN_KEY_CHECKS = 0;

-- מחיקת Views
DROP VIEW IF EXISTS grave_full_path;
DROP VIEW IF EXISTS deceased_forms_view;
DROP VIEW IF EXISTS deceased_forms_full;

-- מחיקת טבלאות בסדר הנכון
DROP TABLE IF EXISTS deceased_documents;
DROP TABLE IF EXISTS deceased_forms;
DROP TABLE IF EXISTS graves;
DROP TABLE IF EXISTS areaGraves;
DROP TABLE IF EXISTS rows;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS plots;
DROP TABLE IF EXISTS blocks;
DROP TABLE IF EXISTS cemeteries;

SET FOREIGN_KEY_CHECKS = 1;

-- בדיקה שהכל נמחק
SHOW TABLES;`;
        
        document.getElementById('scriptOutput').innerHTML = 
            '<pre class="bg-dark text-light p-3">' + script + '</pre>' +
            '<button class="btn btn-sm btn-secondary mt-2" onclick="copyScript()">העתק סקריפט</button>';
    }
    
    function copyScript() {
        const pre = document.querySelector('#scriptOutput pre');
        navigator.clipboard.writeText(pre.textContent).then(() => {
            alert('הסקריפט הועתק ללוח!');
        });
    }
    </script>
</body>
</html>

<?php
// פונקציות עזר

function checkBrokenLinks($db, $table, $dbName) {
    $issues = [];
    
    // קבלת כל ה-FK של הטבלה
    $fks = $db->query("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_SCHEMA = '$dbName'
    ")->fetchAll();
    
    foreach ($fks as $fk) {
        // בדיקה אם יש ערכים שלא קיימים בטבלה המקושרת
        $brokenCount = $db->query("
            SELECT COUNT(*) 
            FROM `$table` t1
            LEFT JOIN `{$fk['REFERENCED_TABLE_NAME']}` t2 
                ON t1.`{$fk['COLUMN_NAME']}` = t2.`{$fk['REFERENCED_COLUMN_NAME']}`
            WHERE t1.`{$fk['COLUMN_NAME']}` IS NOT NULL 
            AND t2.`{$fk['REFERENCED_COLUMN_NAME']}` IS NULL
        ")->fetchColumn();
        
        if ($brokenCount > 0) {
            $issues[] = "$brokenCount רשומות עם {$fk['COLUMN_NAME']} שמצביע לערך לא קיים ב-{$fk['REFERENCED_TABLE_NAME']}";
        }
    }
    
    return $issues;
}

function calculateDeletionOrder($db, $tables, $dbName) {
    $dependencies = [];
    
    // בניית מפת תלויות
    foreach ($tables as $table) {
        $deps = $db->query("
            SELECT DISTINCT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
            AND TABLE_SCHEMA = '$dbName'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $dependencies[$table] = $deps;
    }
    
    // מיון טופולוגי
    $sorted = [];
    $visited = [];
    
    foreach ($tables as $table) {
        if (!isset($visited[$table])) {
            topologicalSort($table, $dependencies, $visited, $sorted);
        }
    }
    
    return $sorted;
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
    
    array_unshift($sorted, $table);
}
?>