<?php
require_once '../config.php';

// בדיקת חיבור לדאטאבייס
echo "<h2>בדיקת חיבור לדאטאבייס</h2>";
try {
    $db = getDbConnection();
    echo "<p style='color: green;'>✓ חיבור לדאטאבייס הצליח</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ שגיאה בחיבור: " . $e->getMessage() . "</p>";
    exit;
}

// בדיקת טבלת users
echo "<h2>בדיקת טבלת users</h2>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>עמודות בטבלה: " . implode(', ', $columns) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ שגיאה בקריאת טבלה: " . $e->getMessage() . "</p>";
}

// בדיקת משתמשים קיימים
echo "<h2>משתמשים במערכת</h2>";
try {
    $stmt = $db->query("SELECT id, username, email, is_active, permission_level FROM users");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Active</th><th>Permission</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>{$user['permission_level']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>אין משתמשים בטבלה</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ שגיאה: " . $e->getMessage() . "</p>";
}

// בדיקת סיסמאות
echo "<h2>בדיקת הצפנת סיסמאות</h2>";
$testPasswords = [
    'admin' => 'admin123',
    'editor' => 'editor123',
    'viewer' => 'viewer123'
];

foreach ($testPasswords as $username => $password) {
    echo "<h3>משתמש: $username</h3>";
    
    try {
        $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $hashedPassword = $user['password'];
            echo "<p>סיסמה מוצפנת במסד: " . substr($hashedPassword, 0, 20) . "...</p>";
            
            if (password_verify($password, $hashedPassword)) {
                echo "<p style='color: green;'>✓ הסיסמה '$password' תואמת להצפנה</p>";
            } else {
                echo "<p style='color: red;'>✗ הסיסמה '$password' לא תואמת להצפנה</p>";
                // ננסה ליצור הצפנה חדשה
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                echo "<p>הצפנה חדשה עבור '$password': $newHash</p>";
            }
        } else {
            echo "<p style='color: orange;'>משתמש לא נמצא</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ שגיאה: " . $e->getMessage() . "</p>";
    }
}

// יצירת משתמשי דמו אם לא קיימים
echo "<h2>יצירת משתמשי דמו</h2>";
$demoUsers = [
    ['username' => 'admin', 'email' => 'admin@cemetery.co.il', 'password' => 'admin123', 'full_name' => 'מנהל ראשי', 'permission_level' => 4],
    ['username' => 'editor', 'email' => 'editor@cemetery.co.il', 'password' => 'editor123', 'full_name' => 'עורך מערכת', 'permission_level' => 2],
    ['username' => 'viewer', 'email' => 'viewer@cemetery.co.il', 'password' => 'viewer123', 'full_name' => 'צופה', 'permission_level' => 1]
];

foreach ($demoUsers as $demoUser) {
    try {
        // בדוק אם המשתמש קיים
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$demoUser['username']]);
        
        if (!$checkStmt->fetch()) {
            // צור משתמש חדש
            $hashedPassword = password_hash($demoUser['password'], PASSWORD_DEFAULT);
            $insertStmt = $db->prepare("
                INSERT INTO users (username, email, password, full_name, permission_level, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $insertStmt->execute([
                $demoUser['username'],
                $demoUser['email'],
                $hashedPassword,
                $demoUser['full_name'],
                $demoUser['permission_level']
            ]);
            echo "<p style='color: green;'>✓ משתמש {$demoUser['username']} נוצר בהצלחה</p>";
        } else {
            // עדכן סיסמה
            $hashedPassword = password_hash($demoUser['password'], PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = ?, is_active = 1 WHERE username = ?");
            $updateStmt->execute([$hashedPassword, $demoUser['username']]);
            echo "<p style='color: blue;'>✓ סיסמת {$demoUser['username']} עודכנה</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ שגיאה ביצירת {$demoUser['username']}: " . $e->getMessage() . "</p>";
    }
}

// בדיקת סשן
echo "<h2>בדיקת Session</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// לינקים
echo "<h2>לינקים</h2>";
echo "<p><a href='login.php'>חזרה לדף התחברות</a></p>";
echo "<p><a href='test_login.php'>בדיקת התחברות ידנית</a></p>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        direction: rtl;
        padding: 20px;
        background-color: #f5f5f5;
    }
    h2 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }
    table {
        margin: 10px 0;
        background: white;
    }
    td, th {
        padding: 8px;
        border: 1px solid #ddd;
    }
    th {
        background-color: #007bff;
        color: white;
    }
    pre {
        background: #f0f0f0;
        padding: 10px;
        border-radius: 5px;
    }
</style>