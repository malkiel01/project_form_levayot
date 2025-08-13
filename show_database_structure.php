<?php
// database_analyzer.php - ניתוח מקיף של מסד הנתונים

require_once 'config.php';

// בדיקת הרשאות - רק למנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied - Admins only');
}

$db = getDbConnection();

// פונקציה לבדיקת שדות בעייתיים
function checkProblematicFields($db, $table) {
    $problems = [];
    
    // רשימת שדות בעייתיים ידועים
    $problematicFields = [
        'deceased_name' => 'שדה זה לא צריך להיות קיים - יש deceased_first_name ו-deceased_last_name',
        'form_id' => 'בדוק אם צריך להיות form_uuid במקום',
        'form_uuid' => 'בדוק אם צריך להיות form_id במקום'
    ];
    
    $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll();
    
    foreach ($columns as $column) {
        if (isset($problematicFields[$column['Field']])) {
            $problems[] = [
                'field' => $column['Field'],
                'issue' => $problematicFields[$column['Field']],
                'type' => $column['Type']
            ];
        }
    }
    
    return $problems;
}

// פונקציה לבדיקת טריגרים
function getTableTriggers($db, $table) {
    return $db->query("SHOW TRIGGERS WHERE `Table` = '$table'")->fetchAll();
}

// פונקציה לבדיקת VIEWS
function getDatabaseViews($db, $dbName) {
    return $db->query("
        SELECT TABLE_NAME, VIEW_DEFINITION 
        FROM INFORMATION_SCHEMA.VIEWS 
        WHERE TABLE_SCHEMA = '$dbName'
    ")->fetchAll();
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניתוח מקיף של מסד הנתונים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    // פונקציה להעתקת כל התוכן
    function copyAllContent() {
        // אסוף את כל המידע החשוב
        let content = "=== ניתוח מסד נתונים ===\n";
        content += "תאריך: " + new Date().toLocaleString('he-IL') + "\n\n";
        
        // כותרות ראשיות
        document.querySelectorAll('h1, h2, h3, h4, h5').forEach(header => {
            if (header.textContent) {
                content += "\n" + header.textContent + "\n";
                content += "=".repeat(header.textContent.length) + "\n";
            }
        });
        
        // תוכן של alerts
        document.querySelectorAll('.alert').forEach(alert => {
            content += "\n" + alert.textContent.trim() + "\n";
        });
        
        // תוכן של critical-table
        document.querySelectorAll('.critical-table').forEach(ct => {
            content += "\n" + ct.textContent.trim() + "\n";
        });
        
        // תוכן של triggers
        document.querySelectorAll('.trigger-box').forEach(tb => {
            content += "\n--- TRIGGER ---\n" + tb.textContent.trim() + "\n";
        });
        
        // תוכן של views
        document.querySelectorAll('.view-box').forEach(vb => {
            content += "\n--- VIEW ---\n" + vb.textContent.trim() + "\n";
        });
        
        // תוכן של בעיות
        document.querySelectorAll('.problem').forEach(p => {
            content += "\n[בעיה] " + p.textContent.trim() + "\n";
        });
        
        // טבלאות
        document.querySelectorAll('table').forEach(table => {
            content += "\n--- טבלה ---\n";
            
            // כותרות
            let headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            content += headers.join(' | ') + "\n";
            content += "-".repeat(50) + "\n";
            
            // שורות
            table.querySelectorAll('tbody tr').forEach(tr => {
                let row = [];
                tr.querySelectorAll('td').forEach(td => {
                    row.push(td.textContent.trim());
                });
                content += row.join(' | ') + "\n";
            });
        });
        
        // סטטיסטיקות
        document.querySelectorAll('.stats').forEach(stat => {
            content += "\n--- סטטיסטיקות ---\n" + stat.textContent.trim() + "\n";
        });
        
        // CREATE statements
        document.querySelectorAll('pre').forEach(pre => {
            if (pre.textContent.includes('CREATE')) {
                content += "\n--- SQL ---\n" + pre.textContent + "\n";
            }
        });
        
        // העתק ללוח
        navigator.clipboard.writeText(content).then(() => {
            // שנה צבע הכפתור לירוק
            const btn = document.getElementById('copyAllBtn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '✅ הועתק בהצלחה!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            // החזר למצב המקורי אחרי 3 שניות
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 3000);
        }).catch(err => {
            alert('שגיאה בהעתקה: ' + err);
        });
    }
    
    // פונקציה ליצירת דוח JSON
    function generateJSONReport() {
        const report = {
            timestamp: new Date().toISOString(),
            database: document.querySelector('.alert-info strong')?.textContent || 'unknown',
            issues: [],
            triggers: [],
            views: [],
            tables: [],
            statistics: {}
        };
        
        // אסוף בעיות
        document.querySelectorAll('.alert-danger, .alert-warning').forEach(alert => {
            report.issues.push({
                type: alert.classList.contains('alert-danger') ? 'error' : 'warning',
                message: alert.textContent.trim()
            });
        });
        
        // אסוף טריגרים
        document.querySelectorAll('.trigger-box').forEach(tb => {
            const trigger = {};
            tb.querySelectorAll('p strong').forEach(strong => {
                const text = strong.parentElement.textContent;
                if (text.includes('טבלה:')) trigger.table = strong.textContent;
                if (text.includes('אירוע:')) trigger.event = strong.textContent;
            });
            trigger.statement = tb.querySelector('pre')?.textContent || '';
            report.triggers.push(trigger);
        });
        
        // אסוף views
        document.querySelectorAll('.view-box').forEach(vb => {
            const viewName = vb.querySelector('h5 strong')?.textContent || '';
            const definition = vb.querySelector('pre')?.textContent || '';
            report.views.push({ name: viewName, definition: definition });
        });
        
        // הורד כקובץ JSON
        const dataStr = JSON.stringify(report, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'database_analysis_' + Date.now() + '.json';
        link.click();
    }
    </script>
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
            max-height: 400px;
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
        .problem {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 10px 0;
        }
        .trigger-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 10px;
            margin: 10px 0;
        }
        .view-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin: 10px 0;
        }
        .critical-table {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">🔍 ניתוח מקיף של מסד הנתונים</h1>
        
        <!-- כפתורי העתקה והורדה -->
        <div class="text-center mb-4">
            <button id="copyAllBtn" class="btn btn-primary btn-lg me-2" onclick="copyAllContent()">
                <i class="fas fa-copy"></i> 📋 העתק את כל המידע לשליחה
            </button>
            <button class="btn btn-success btn-lg me-2" onclick="generateJSONReport()">
                <i class="fas fa-download"></i> 💾 הורד כקובץ JSON
            </button>
            <button class="btn btn-info btn-lg" onclick="window.print()">
                <i class="fas fa-print"></i> 🖨️ הדפס דוח
            </button>
        </div>
        
        <div class="alert alert-light border">
            <strong>הוראות:</strong>
            <ol class="mb-0">
                <li>לחץ על "העתק את כל המידע לשליחה" כדי להעתיק את כל הניתוח</li>
                <li>הדבק את המידע בצ'אט כדי שאוכל לנתח את הבעיות</li>
                <li>או הורד כקובץ JSON לשמירה מקומית</li>
            </ol>
        </div>
        
        <?php
        try {
            // קבלת שם מסד הנתונים
            $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
            echo "<div class='alert alert-info'>מסד נתונים: <strong>$dbName</strong></div>";
            
            // קבלת רשימת כל הטבלאות
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            // ===== בדיקות קריטיות =====
            echo "<div class='table-container'>";
            echo "<h3 class='table-name bg-danger'>🚨 בדיקות קריטיות</h3>";
            
            // בדיקת טבלת activity_log
            echo "<h4>בדיקת טבלת activity_log:</h4>";
            if (in_array('activity_log', $tables)) {
                $activityColumns = $db->query("SHOW COLUMNS FROM activity_log")->fetchAll(PDO::FETCH_COLUMN);
                echo "<div class='critical-table'>";
                echo "<strong>עמודות בטבלת activity_log:</strong><br>";
                echo "<ul>";
                $hasFormId = false;
                $hasFormUuid = false;
                foreach ($activityColumns as $col) {
                    if ($col == 'form_id') $hasFormId = true;
                    if ($col == 'form_uuid') $hasFormUuid = true;
                    echo "<li>$col</li>";
                }
                echo "</ul>";
                
                if ($hasFormId && !$hasFormUuid) {
                    echo "<div class='alert alert-success'>✅ הטבלה משתמשת ב-form_id (נכון)</div>";
                } elseif (!$hasFormId && $hasFormUuid) {
                    echo "<div class='alert alert-warning'>⚠️ הטבלה משתמשת ב-form_uuid במקום form_id</div>";
                } elseif ($hasFormId && $hasFormUuid) {
                    echo "<div class='alert alert-info'>ℹ️ הטבלה כוללת גם form_id וגם form_uuid</div>";
                } else {
                    echo "<div class='alert alert-danger'>❌ אין עמודת form_id או form_uuid!</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>❌ טבלת activity_log לא קיימת!</div>";
            }
            
            // בדיקת טבלת deceased_forms
            echo "<h4>בדיקת טבלת deceased_forms:</h4>";
            if (in_array('deceased_forms', $tables)) {
                $deceasedColumns = $db->query("SHOW COLUMNS FROM deceased_forms")->fetchAll(PDO::FETCH_COLUMN);
                echo "<div class='critical-table'>";
                
                // בדיקה אם יש deceased_name
                if (in_array('deceased_name', $deceasedColumns)) {
                    echo "<div class='alert alert-danger'>❌ נמצאה עמודה deceased_name - זה בעייתי!</div>";
                } else {
                    echo "<div class='alert alert-success'>✅ אין עמודה deceased_name (טוב)</div>";
                }
                
                // בדיקה אם יש את השדות הנכונים
                if (in_array('deceased_first_name', $deceasedColumns) && in_array('deceased_last_name', $deceasedColumns)) {
                    echo "<div class='alert alert-success'>✅ קיימות עמודות deceased_first_name ו-deceased_last_name</div>";
                } else {
                    echo "<div class='alert alert-warning'>⚠️ חסרות עמודות שם פרטי/משפחה</div>";
                }
                echo "</div>";
            }
            
            echo "</div>";
            
            // ===== TRIGGERS =====
            echo "<div class='table-container'>";
            echo "<h3 class='table-name bg-info'>🎯 טריגרים (Triggers)</h3>";
            
            $allTriggers = $db->query("SHOW TRIGGERS")->fetchAll();
            if ($allTriggers) {
                foreach ($allTriggers as $trigger) {
                    echo "<div class='trigger-box'>";
                    echo "<h5>טריגר: <strong>{$trigger['Trigger']}</strong></h5>";
                    echo "<p>טבלה: <strong>{$trigger['Table']}</strong></p>";
                    echo "<p>אירוע: <strong>{$trigger['Event']}</strong></p>";
                    echo "<p>תזמון: <strong>{$trigger['Timing']}</strong></p>";
                    echo "<pre>" . htmlspecialchars($trigger['Statement']) . "</pre>";
                    
                    // בדיקה אם הטריגר מכיל deceased_name
                    if (strpos($trigger['Statement'], 'deceased_name') !== false) {
                        echo "<div class='alert alert-danger'>⚠️ הטריגר משתמש ב-deceased_name!</div>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<p>לא נמצאו טריגרים במסד הנתונים</p>";
            }
            
            echo "</div>";
            
            // ===== VIEWS =====
            echo "<div class='table-container'>";
            echo "<h3 class='table-name bg-success'>👁️ תצוגות (Views)</h3>";
            
            $views = getDatabaseViews($db, $dbName);
            if ($views) {
                foreach ($views as $view) {
                    echo "<div class='view-box'>";
                    echo "<h5>View: <strong>{$view['TABLE_NAME']}</strong></h5>";
                    echo "<pre>" . htmlspecialchars($view['VIEW_DEFINITION']) . "</pre>";
                    
                    // בדיקה אם ה-VIEW מכיל deceased_name
                    if (strpos($view['VIEW_DEFINITION'], 'deceased_name') !== false) {
                        echo "<div class='alert alert-danger'>⚠️ ה-View משתמש ב-deceased_name!</div>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<p>לא נמצאו Views במסד הנתונים</p>";
            }
            
            echo "</div>";
            
            // ===== סטטיסטיקות כלליות =====
            echo "<div class='stats'>";
            echo "<h5>📊 סטטיסטיקות כלליות:</h5>";
            echo "<p>סה\"כ טבלאות: <strong>" . count($tables) . "</strong></p>";
            echo "<p>סה\"כ טריגרים: <strong>" . count($allTriggers) . "</strong></p>";
            echo "<p>סה\"כ Views: <strong>" . count($views) . "</strong></p>";
            
            // ספירת רשומות בטבלאות חשובות
            $importantTables = ['deceased_forms', 'activity_log', 'users', 'graves'];
            foreach ($importantTables as $table) {
                if (in_array($table, $tables)) {
                    $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    echo "<p>רשומות ב-$table: <strong>$count</strong></p>";
                }
            }
            echo "</div>";
            
            // ===== תוכן עניינים =====
            echo "<div class='table-container'>";
            echo "<h3>📑 תוכן עניינים - טבלאות</h3>";
            echo "<div class='row'>";
            foreach ($tables as $i => $table) {
                if ($i % 3 == 0 && $i > 0) echo "</div><div class='row'>";
                echo "<div class='col-md-4'>";
                echo "<a href='#table-$table'>📋 $table</a>";
                echo "</div>";
            }
            echo "</div>";
            echo "</div>";
            
            // ===== הצגת כל טבלה =====
            foreach ($tables as $table) {
                echo "<div class='table-container' id='table-$table'>";
                echo "<h3 class='table-name'>📋 $table</h3>";
                
                // בדיקת בעיות בטבלה
                $problems = checkProblematicFields($db, $table);
                if ($problems) {
                    echo "<div class='alert alert-warning'>";
                    echo "<h5>⚠️ בעיות פוטנציאליות שנמצאו:</h5>";
                    foreach ($problems as $problem) {
                        echo "<div class='problem'>";
                        echo "<strong>שדה: {$problem['field']}</strong> ({$problem['type']})<br>";
                        echo "{$problem['issue']}";
                        echo "</div>";
                    }
                    echo "</div>";
                }
                
                // קבלת מידע על הטבלה
                $tableInfo = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                $createStatement = $tableInfo['Create Table'];
                
                // קבלת מבנה הטבלה
                $columns = $db->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll();
                
                // קבלת אינדקסים
                $indexes = $db->query("SHOW INDEXES FROM `$table`")->fetchAll();
                
                // קבלת מספר רשומות
                $rowCount = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                
                // בדיקת טריגרים לטבלה
                $tableTriggers = getTableTriggers($db, $table);
                
                echo "<div class='row mb-3'>";
                echo "<div class='col-md-3'><strong>מספר רשומות:</strong> $rowCount</div>";
                echo "<div class='col-md-3'><strong>מספר עמודות:</strong> " . count($columns) . "</div>";
                echo "<div class='col-md-3'><strong>מספר אינדקסים:</strong> " . count(array_unique(array_column($indexes, 'Key_name'))) . "</div>";
                echo "<div class='col-md-3'><strong>מספר טריגרים:</strong> " . count($tableTriggers) . "</div>";
                echo "</div>";
                
                // טבלת עמודות
                echo "<h5>📊 עמודות:</h5>";
                echo "<table class='table table-bordered table-sm'>";
                echo "<thead class='table-light'>";
                echo "<tr>";
                echo "<th>#</th>";
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
                
                foreach ($columns as $i => $column) {
                    echo "<tr>";
                    echo "<td>" . ($i + 1) . "</td>";
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
                    echo "<h5>🔑 אינדקסים:</h5>";
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
                
                // טריגרים של הטבלה
                if ($tableTriggers) {
                    echo "<h5>🎯 טריגרים של הטבלה:</h5>";
                    foreach ($tableTriggers as $trigger) {
                        echo "<div class='trigger-box'>";
                        echo "<strong>{$trigger['Trigger']}</strong> - {$trigger['Event']} {$trigger['Timing']}<br>";
                        echo "<pre style='max-height: 200px;'>" . htmlspecialchars($trigger['Statement']) . "</pre>";
                        echo "</div>";
                    }
                }
                
                // הצגת CREATE TABLE
                echo "<h5>📝 CREATE TABLE Statement:</h5>";
                echo "<div class='position-relative'>";
                echo "<button class='btn btn-sm btn-secondary copy-btn' onclick='copyToClipboard(\"create-$table\")'>העתק</button>";
                echo "<pre id='create-$table'>" . htmlspecialchars($createStatement) . "</pre>";
                echo "</div>";
                
                echo "</div>";
            }
            
            // ===== קשרים בין טבלאות (Foreign Keys) =====
            echo "<div class='table-container'>";
            echo "<h3 class='table-name'>🔗 קשרים בין טבלאות (Foreign Keys)</h3>";
            
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
            
            // ===== סיכום בעיות =====
            echo "<div class='table-container'>";
            echo "<h3 class='table-name bg-warning text-dark'>📊 סיכום בעיות וממצאים</h3>";
            
            echo "<div class='row'>";
            echo "<div class='col-md-6'>";
            echo "<h5>❌ בעיות קריטיות:</h5>";
            echo "<ul id='critical-issues'>";
            // JavaScript ימלא את זה
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='col-md-6'>";
            echo "<h5>⚠️ אזהרות:</h5>";
            echo "<ul id='warnings'>";
            // JavaScript ימלא את זה
            echo "</ul>";
            echo "</div>";
            echo "</div>";
            
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>שגיאה: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
    
    <script>
    // אסוף בעיות קריטיות ואזהרות
    document.addEventListener('DOMContentLoaded', function() {
        const criticalIssues = document.getElementById('critical-issues');
        const warnings = document.getElementById('warnings');
        
        // אסוף בעיות מ-alerts
        document.querySelectorAll('.alert-danger').forEach(alert => {
            const li = document.createElement('li');
            li.textContent = alert.textContent.trim();
            criticalIssues.appendChild(li);
        });
        
        document.querySelectorAll('.alert-warning').forEach(alert => {
            const li = document.createElement('li');
            li.textContent = alert.textContent.trim();
            warnings.appendChild(li);
        });
        
        // אם אין בעיות
        if (criticalIssues.children.length === 0) {
            criticalIssues.innerHTML = '<li class="text-success">לא נמצאו בעיות קריטיות ✅</li>';
        }
        if (warnings.children.length === 0) {
            warnings.innerHTML = '<li class="text-success">לא נמצאו אזהרות ✅</li>';
        }
    });
    
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = 'הועתק!';
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('btn-success');
            }, 2000);
        });
    }
    </script>
    
    <!-- הוסף Font Awesome לאייקונים -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>