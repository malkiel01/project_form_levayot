<?php
// test_fix.php - בדיקה ותיקון מהיר
session_start();
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>בדיקה ותיקון מהיר</title>
    <style>
        body { font-family: Arial; direction: rtl; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; }
        .box { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>בדיקה ותיקון מהיר</h1>

    <?php
    // 1. בדיקת חיבור לדטהבייס
    echo "<div class='box'>";
    echo "<h2>1. בדיקת חיבור לדטהבייס</h2>";
    
    try {
        $db = new PDO(
            "mysql:host=mbe-plus.com;dbname=mbeplusc_kadisha_v7;charset=utf8mb4", 
            "mbeplusc_test", 
            "Gxfv16be"
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p class='success'>✓ חיבור לדטהבייס הצליח!</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ שגיאה בחיבור: " . $e->getMessage() . "</p>";
        die();
    }
    echo "</div>";

    // 2. בדיקת Session
    echo "<div class='box'>";
    echo "<h2>2. בדיקת Session</h2>";
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "<p class='success'>✓ Session פעיל</p>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
    } else {
        echo "<p class='error'>✗ Session לא פעיל</p>";
    }
    echo "</div>";

    // 3. בדיקת טבלאות
    echo "<div class='box'>";
    echo "<h2>3. בדיקת טבלאות</h2>";
    
    $tables = [
        'users' => 'משתמשים',
        'deceased_forms' => 'טפסי נפטרים',
        'cemeteries' => 'בתי עלמין',
        'user_permissions' => 'הרשאות',
        'purchase_forms' => 'רכישות',
        'activity_logs' => 'לוגים'
    ];
    
    foreach ($tables as $table => $desc) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p class='success'>✓ טבלת $desc ($table) - $count רשומות</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ טבלת $desc ($table) - לא קיימת או בעיה</p>";
        }
    }
    echo "</div>";

    // 4. בדיקת משתמשים
    echo "<div class='box'>";
    echo "<h2>4. משתמשים במערכת</h2>";
    
    try {
        $users = $db->query("SELECT id, username, email, permission_level FROM users")->fetchAll();
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>שם משתמש</th><th>אימייל</th><th>הרשאה</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['permission_level']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p class='error'>בעיה בקריאת משתמשים: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // 5. יצירת משתמש בדיקה
    echo "<div class='box'>";
    echo "<h2>5. יצירת משתמש בדיקה</h2>";
    
    try {
        // בדוק אם משתמש בדיקה קיים
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'test'");
        $stmt->execute();
        
        if (!$stmt->fetchColumn()) {
            // צור משתמש בדיקה
            $stmt = $db->prepare("
                INSERT INTO users (username, password, email, permission_level, is_active) 
                VALUES ('test', ?, 'test@example.com', 'admin', 1)
            ");
            $stmt->execute([password_hash('test123', PASSWORD_DEFAULT)]);
            echo "<p class='success'>✓ משתמש בדיקה נוצר: test / test123</p>";
        } else {
            echo "<p class='info'>משתמש בדיקה כבר קיים</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>בעיה ביצירת משתמש: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // 6. קישורים מהירים
    echo "<div class='box'>";
    echo "<h2>6. קישורים מהירים</h2>";
    ?>
    
    <p><strong>לבדיקה:</strong></p>
    <ul>
        <li><a href="auth/login.php">כניסה למערכת</a> (test / test123)</li>
        <li><a href="dashboard.php">דשבורד</a></li>
        <li><a href="form/index.php?type=deceased">טופס נפטר חדש</a></li>
        <li><a href="form/index.php?type=purchase">טופס רכישה חדש</a></li>
    </ul>
    
    <p><strong>SQL ליצירת טבלאות (העתק והרץ ב-phpMyAdmin):</strong></p>
    <textarea style="width: 100%; height: 200px;">
-- רק אם הטבלאות לא קיימות:
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    has_permission BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_permission (user_id, permission_name)
);

CREATE TABLE IF NOT EXISTS purchase_forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    buyer_first_name VARCHAR(100) NOT NULL,
    buyer_last_name VARCHAR(100) NOT NULL,
    buyer_id VARCHAR(20),
    buyer_phone VARCHAR(20),
    buyer_email VARCHAR(100),
    cemetery_id INT NOT NULL,
    block_id INT,
    plot_row VARCHAR(10),
    plot_number VARCHAR(10),
    purchase_date DATE NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method VARCHAR(50),
    notes TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
    </textarea>
    <?php echo "</div>"; ?>
</body>
</html>