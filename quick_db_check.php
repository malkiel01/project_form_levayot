<?php
// quick_db_check.php - בדיקה מהירה של הטבלאות החסרות
// שים את הקובץ הזה בתיקיית הפרויקט והרץ אותו

// נסה לטעון config.php
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // אם אין config.php, השתמש בהגדרות ידניות
    define('DB_HOST', 'mbe-plus.com');
    define('DB_NAME', 'mbeplusc_kadisha_v7');
    define('DB_USER', 'mbeplusc_test');
    define('DB_PASS', 'Gxfv16be');
    define('DB_CHARSET', 'utf8mb4');
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקה מהירה - טבלאות חסרות</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .sql-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .alert {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 בדיקה מהירה - טבלאות חסרות</h1>
    
    <?php
    // התחברות למסד נתונים
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo '<div class="alert alert-success">✅ התחברות למסד הנתונים הצליחה!</div>';
        
        // קבלת רשימת טבלאות קיימות
        $stmt = $db->query("SHOW TABLES");
        $existingTables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existingTables[] = $row[0];
        }
        
        // רשימת הטבלאות החדשות הנדרשות
        $newTables = [
            'form_types' => 'טבלת סוגי טפסים',
            'purchase_forms' => 'טבלת טפסי רכישות',
            'form_documents' => 'טבלת מסמכים גנרית',
            'purchase_payments' => 'טבלת תשלומים'
        ];
        
        $missingTables = [];
        $existingNewTables = [];
        
        foreach ($newTables as $table => $description) {
            if (in_array($table, $existingTables)) {
                $existingNewTables[$table] = $description;
            } else {
                $missingTables[$table] = $description;
            }
        }
        
        // בדיקת עמודת form_type ב-field_permissions
        $hasFormTypeColumn = false;
        if (in_array('field_permissions', $existingTables)) {
            $stmt = $db->query("SHOW COLUMNS FROM field_permissions LIKE 'form_type'");
            $hasFormTypeColumn = $stmt->fetch() !== false;
        }
        
        ?>
        
        <h2>📊 סטטוס טבלאות חדשות</h2>
        
        <?php if (!empty($missingTables)): ?>
            <div class="alert alert-danger">
                <h3>❌ טבלאות חסרות (<?= count($missingTables) ?>)</h3>
                <table>
                    <tr>
                        <th>שם טבלה</th>
                        <th>תיאור</th>
                    </tr>
                    <?php foreach ($missingTables as $table => $desc): ?>
                    <tr>
                        <td><strong><?= $table ?></strong></td>
                        <td><?= $desc ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($existingNewTables)): ?>
            <div class="alert alert-success">
                <h3>✅ טבלאות חדשות שכבר קיימות (<?= count($existingNewTables) ?>)</h3>
                <table>
                    <tr>
                        <th>שם טבלה</th>
                        <th>תיאור</th>
                        <th>רשומות</th>
                    </tr>
                    <?php foreach ($existingNewTables as $table => $desc): ?>
                    <tr>
                        <td><strong><?= $table ?></strong></td>
                        <td><?= $desc ?></td>
                        <td>
                            <?php
                            try {
                                $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                                echo number_format($count);
                            } catch (Exception $e) {
                                echo '<span class="error">שגיאה</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if (!$hasFormTypeColumn): ?>
            <div class="alert alert-warning">
                <h3>⚠️ עמודת form_type חסרה ב-field_permissions</h3>
                <p>העמודה הזו נדרשת לתמיכה בסוגי טפסים מרובים.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <h3>✅ עמודת form_type קיימת ב-field_permissions</h3>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($missingTables) || !$hasFormTypeColumn): ?>
            <h2>🛠️ SQL ליצירת הטבלאות החסרות</h2>
            <p>העתק והרץ את הקוד הבא ב-phpMyAdmin או בכל כלי ניהול MySQL:</p>
            
            <div class="sql-box">
                <pre><?php
                
// SQL ליצירת הטבלאות החסרות
$sql = "";

if (isset($missingTables['form_types'])) {
    $sql .= "-- יצירת טבלת סוגי טפסים
CREATE TABLE IF NOT EXISTS `form_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type_key` VARCHAR(50) NOT NULL UNIQUE,
  `type_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `table_name` VARCHAR(100) NOT NULL,
  `form_class` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50),
  `color` VARCHAR(7),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- הוספת סוגי הטפסים
INSERT INTO `form_types` (`type_key`, `type_name`, `description`, `table_name`, `form_class`, `icon`, `color`) VALUES
('deceased', 'טפסי נפטרים', 'טפסים לרישום נפטרים ופרטי קבורה', 'deceased_forms', 'DeceasedForm', 'fa-cross', '#6c757d'),
('purchase', 'טפסי רכישות', 'טפסים לרכישת חלקות קבורה ושירותים', 'purchase_forms', 'PurchaseForm', 'fa-shopping-cart', '#28a745');

";
}

if (isset($missingTables['purchase_forms'])) {
    $sql .= "-- יצירת טבלת טפסי רכישות
CREATE TABLE IF NOT EXISTS `purchase_forms` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_uuid` VARCHAR(36) NOT NULL UNIQUE,
  `status` ENUM('draft', 'in_progress', 'completed', 'archived') DEFAULT 'draft',
  `progress_percentage` INT(3) DEFAULT 0,
  
  -- פרטי הרוכש
  `buyer_type` ENUM('individual', 'company') NOT NULL,
  `buyer_id_type` ENUM('tz', 'passport', 'company_id') NOT NULL,
  `buyer_id_number` VARCHAR(20) NOT NULL,
  `buyer_name` VARCHAR(255) NOT NULL,
  `buyer_phone` VARCHAR(20),
  `buyer_email` VARCHAR(100),
  `buyer_address` TEXT,
  
  -- פרטי הרכישה
  `purchase_type` ENUM('grave', 'plot', 'structure', 'service') NOT NULL,
  `purchase_date` DATE NOT NULL,
  `payment_method` ENUM('cash', 'check', 'credit', 'transfer', 'installments'),
  `total_amount` DECIMAL(10,2),
  `paid_amount` DECIMAL(10,2) DEFAULT 0,
  `installments_count` INT(3),
  
  -- מיקום הקבר/חלקה
  `cemetery_id` INT(11),
  `block_id` INT(11),
  `section_id` INT(11),
  `row_id` INT(11),
  `grave_id` INT(11),
  `plot_id` INT(11),
  
  -- פרטים נוספים
  `contract_number` VARCHAR(100),
  `notes` TEXT,
  `special_conditions` TEXT,
  
  -- חתימות
  `buyer_signature` TEXT,
  `seller_signature` TEXT,
  
  -- מטה-דטה
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT(11),
  `updated_by` INT(11),
  
  PRIMARY KEY (`id`),
  INDEX `idx_form_uuid` (`form_uuid`),
  INDEX `idx_buyer_id` (`buyer_id_number`),
  INDEX `idx_purchase_date` (`purchase_date`),
  INDEX `idx_contract_number` (`contract_number`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

";
}

if (!$hasFormTypeColumn) {
    $sql .= "-- עדכון טבלת הרשאות שדות
ALTER TABLE `field_permissions` 
ADD COLUMN `form_type` VARCHAR(50) NOT NULL DEFAULT 'deceased' AFTER `id`,
DROP INDEX `unique_field_permission`,
ADD UNIQUE KEY `unique_field_permission` (`form_type`, `field_name`, `permission_level`);

";
}

if (!$hasFormTypeColumn || isset($missingTables['purchase_forms'])) {
    $sql .= "-- הוספת הרשאות לשדות טפסי רכישות
INSERT INTO `field_permissions` (`form_type`, `field_name`, `permission_level`, `can_view`, `can_edit`, `is_required`) VALUES
-- הרשאות לעורך (רמה 2) - טפסי רכישות
('purchase', 'buyer_type', 2, TRUE, TRUE, TRUE),
('purchase', 'buyer_id_type', 2, TRUE, TRUE, TRUE),
('purchase', 'buyer_id_number', 2, TRUE, TRUE, TRUE),
('purchase', 'buyer_name', 2, TRUE, TRUE, TRUE),
('purchase', 'buyer_phone', 2, TRUE, TRUE, FALSE),
('purchase', 'buyer_email', 2, TRUE, TRUE, FALSE),
('purchase', 'buyer_address', 2, TRUE, TRUE, FALSE),
('purchase', 'purchase_type', 2, TRUE, TRUE, TRUE),
('purchase', 'purchase_date', 2, TRUE, TRUE, TRUE),
('purchase', 'payment_method', 2, TRUE, TRUE, TRUE),
('purchase', 'total_amount', 2, TRUE, TRUE, TRUE),
('purchase', 'paid_amount', 2, TRUE, TRUE, TRUE),
('purchase', 'installments_count', 2, TRUE, TRUE, FALSE),
('purchase', 'contract_number', 2, TRUE, TRUE, FALSE),
('purchase', 'notes', 2, TRUE, TRUE, FALSE),
('purchase', 'special_conditions', 2, TRUE, TRUE, FALSE),

-- הרשאות למנהל (רמה 4) - טפסי רכישות
('purchase', 'cemetery_id', 4, TRUE, TRUE, FALSE),
('purchase', 'block_id', 4, TRUE, TRUE, FALSE),
('purchase', 'section_id', 4, TRUE, TRUE, FALSE),
('purchase', 'row_id', 4, TRUE, TRUE, FALSE),
('purchase', 'grave_id', 4, TRUE, TRUE, FALSE),
('purchase', 'plot_id', 4, TRUE, TRUE, FALSE);

";
}

if (isset($missingTables['form_documents'])) {
    $sql .= "-- יצירת טבלת מסמכים גנרית
CREATE TABLE IF NOT EXISTS `form_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_id` INT(11) NOT NULL,
  `form_type` VARCHAR(50) NOT NULL,
  `document_type` VARCHAR(100),
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11),
  `mime_type` VARCHAR(100),
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` INT(11),
  PRIMARY KEY (`id`),
  INDEX `idx_form_type` (`form_id`, `form_type`),
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

";
}

if (isset($missingTables['purchase_payments'])) {
    $sql .= "-- יצירת טבלת תשלומים
CREATE TABLE IF NOT EXISTS `purchase_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_form_id` INT(11) NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash', 'check', 'credit', 'transfer'),
  `reference_number` VARCHAR(100),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT(11),
  PRIMARY KEY (`id`),
  INDEX `idx_purchase_form` (`purchase_form_id`),
  FOREIGN KEY (`purchase_form_id`) REFERENCES `purchase_forms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

";
}

// עדכונים נוספים לטבלאות קיימות
$sql .= "-- עדכון טבלת הלוג לתמיכה בסוגי טפסים
ALTER TABLE `activity_log` 
ADD COLUMN `form_type` VARCHAR(50) DEFAULT 'deceased' AFTER `form_id`;

-- עדכון טבלת ההתראות לתמיכה בסוגי טפסים
ALTER TABLE `notifications` 
ADD COLUMN `form_type` VARCHAR(50) DEFAULT 'deceased' AFTER `form_id`;
";

echo htmlspecialchars($sql);
                ?></pre>
            </div>
            
            <button onclick="copySQL()" class="btn btn-success">📋 העתק SQL</button>
            
        <?php else: ?>
            <div class="alert alert-success">
                <h2>✅ מצוין! כל הטבלאות החדשות כבר קיימות!</h2>
                <p>המערכת מוכנה לתמיכה בסוגי טפסים מרובים.</p>
            </div>
        <?php endif; ?>
        
        <h2>📝 בדיקות נוספות</h2>
        
        <?php
        // בדיקת קבצי PHP חדשים
        $requiredFiles = [
            'BaseForm.php' => 'מחלקת בסיס לטפסים',
            'PurchaseForm.php' => 'מחלקת טפסי רכישות',
            'form_configs/purchase_fields.php' => 'הגדרות שדות טפסי רכישות'
        ];
        
        echo '<h3>קבצי PHP נדרשים:</h3>';
        echo '<table>';
        echo '<tr><th>קובץ</th><th>תיאור</th><th>סטטוס</th></tr>';
        
        foreach ($requiredFiles as $file => $desc) {
            $exists = file_exists($file);
            echo '<tr>';
            echo '<td>' . $file . '</td>';
            echo '<td>' . $desc . '</td>';
            echo '<td>' . ($exists ? '<span class="success">✓ קיים</span>' : '<span class="error">✗ חסר</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // בדיקת תיקיות
        $requiredDirs = [
            'form_configs' => 'תיקיית הגדרות טפסים',
            'uploads/purchase' => 'תיקיית העלאות לטפסי רכישות'
        ];
        
        echo '<h3>תיקיות נדרשות:</h3>';
        echo '<table>';
        echo '<tr><th>תיקייה</th><th>תיאור</th><th>סטטוס</th></tr>';
        
        foreach ($requiredDirs as $dir => $desc) {
            $exists = is_dir($dir);
            echo '<tr>';
            echo '<td>' . $dir . '</td>';
            echo '<td>' . $desc . '</td>';
            echo '<td>' . ($exists ? '<span class="success">✓ קיימת</span>' : '<span class="error">✗ חסרה</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        ?>
        
        <h2>🚀 הצעדים הבאים</h2>
        <ol>
            <li>הרץ את ה-SQL למעלה ב-phpMyAdmin או בכל כלי ניהול MySQL</li>
            <li>ודא שכל הקבצים החדשים קיימים (BaseForm.php, PurchaseForm.php וכו')</li>
            <li>צור את התיקיות החסרות אם צריך</li>
            <li>בדוק שהקובץ DeceasedForm.php יורש מ-BaseForm</li>
            <li>נסה ליצור טופס רכישה חדש מהדשבורד</li>
        </ol>
        
        <div style="margin-top: 30px;">
            <a href="database_analyzer.php" class="btn">🔍 בדיקה מלאה של מסד הנתונים</a>
            <a href="<?= DASHBOARD_FULL_URL ?>" class="btn btn-success">🏠 חזרה לדשבורד</a>
        </div>
        
    <?php
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">';
        echo '<h3>❌ שגיאה בהתחברות למסד נתונים</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>
</div>

<script>
function copySQL() {
    const sqlBox = document.querySelector('.sql-box pre');
    const range = document.createRange();
    range.selectNode(sqlBox);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    document.execCommand('copy');
    window.getSelection().removeAllRanges();
    alert('ה-SQL הועתק ללוח!');
}
</script>

</body>
</html>