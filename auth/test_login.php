<?php
// require_once '../config.php';

// $message = '';

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = $_POST['username'] ?? '';
//     $password = $_POST['password'] ?? '';
    
//     $message .= "<h3>ניסיון התחברות:</h3>";
//     $message .= "<p>Username: $username</p>";
//     $message .= "<p>Password: $password</p>";
    
//     try {
//         $db = getDbConnection();
        
//         // חיפוש המשתמש
//         $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
//         $stmt->execute([$username, $username]);
//         $user = $stmt->fetch();
        
//         if ($user) {
//             $message .= "<p style='color: green;'>✓ משתמש נמצא</p>";
//             $message .= "<p>ID: {$user['id']}</p>";
//             $message .= "<p>Username: {$user['username']}</p>";
//             $message .= "<p>Email: {$user['email']}</p>";
//             $message .= "<p>Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
//             $message .= "<p>Permission Level: {$user['permission_level']}</p>";
            
//             // בדיקת סיסמה
//             if (password_verify($password, $user['password'])) {
//                 $message .= "<p style='color: green;'>✓ סיסמה נכונה!</p>";
                
//                 // הגדרת סשן
//                 $_SESSION['user_id'] = $user['id'];
//                 $_SESSION['username'] = $user['username'];
//                 $_SESSION['full_name'] = $user['full_name'];
//                 $_SESSION['permission_level'] = $user['permission_level'];
                
//                 $message .= "<p style='color: green;'>✓ סשן הוגדר בהצלחה</p>";
//                 $message .= "<p><a href='" . DASHBOARD_URL . "'>לחץ כאן למעבר לדשבורד</a></p>";
                
//             } else {
//                 $message .= "<p style='color: red;'>✗ סיסמה שגויה</p>";
                
//                 // ננסה להשוות לסיסמה ישירות (לצורך דיבוג)
//                 if ($user['password'] === $password) {
//                     $message .= "<p style='color: orange;'>⚠ הסיסמה תואמת בהשוואה ישירה - נראה שהיא לא מוצפנת!</p>";
                    
//                     // ניצור הצפנה חדשה
//                     $newHash = password_hash($password, PASSWORD_DEFAULT);
//                     $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
//                     $updateStmt->execute([$newHash, $user['id']]);
//                     $message .= "<p style='color: blue;'>✓ הסיסמה הוצפנה ועודכנה במסד</p>";
//                     $message .= "<p>נסה להתחבר שוב</p>";
//                 }
//             }
//         } else {
//             $message .= "<p style='color: red;'>✗ משתמש לא נמצא</p>";
//         }
        
//     } catch (Exception $e) {
//         $message .= "<p style='color: red;'>✗ שגיאה: " . $e->getMessage() . "</p>";
//     }
// }
?>
<!-- <!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת התחברות</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .result {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>בדיקת התחברות</h1>
        
        <form method="POST">
            <div>
                <label>שם משתמש או אימייל:</label>
                <input type="text" name="username" value="admin" required>
            </div>
            
            <div>
                <label>סיסמה:</label>
                <input type="password" name="password" value="admin123" required>
            </div>
            
            <button type="submit">בדוק התחברות</button>
        </form>
        
        <?php if ($message): ?>
            <div class="result">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <h3>משתמשי דוגמה:</h3>
        <ul>
            <li>admin / admin123</li>
            <li>editor / editor123</li>
            <li>viewer / viewer123</li>
        </ul>
        
        <p>
            <a href="debug_login.php">חזרה לדף דיבוג</a> | 
            <a href="login.php">חזרה לדף התחברות</a>
        </p>
    </div>
</body>
</html> -->