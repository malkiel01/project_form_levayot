<?php
// test-session.php - בדיקת SESSION
// חשוב! טען את הקונפיג כדי להשתמש באותו SESSION
require_once '../config.php';
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת SESSION</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h2>בדיקת SESSION</h2>
    
    <h3>פרטי SESSION:</h3>
    <p>Session Name: <strong><?php echo session_name(); ?></strong></p>
    <p>Session ID: <strong><?php echo session_id(); ?></strong></p>
    
    <h3>תוכן ה-SESSION הנוכחי:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="success">
            <h3>✓ משתמש מחובר:</h3>
            <ul>
                <li>User ID: <?php echo $_SESSION['user_id']; ?></li>
                <li>Username: <?php echo $_SESSION['username'] ?? 'לא מוגדר'; ?></li>
                <li>Permission Level: <?php echo $_SESSION['permission_level'] ?? 'לא מוגדר'; ?></li>
            </ul>
        </div>
    <?php else: ?>
        <div class="error">
            <h3>✗ משתמש לא מחובר</h3>
        </div>
    <?php endif; ?>
    
    <hr>
    
    <h3>פעולות:</h3>
    <ul>
        <li><a href="../auth/login.php">התחברות</a></li>
        <li><a href="../auth/logout.php">יציאה</a></li>
        <li><a href="index.php">חזרה לבתי עלמין</a></li>
        <li><a href="../">דשבורד ראשי</a></li>
    </ul>
    
    <hr>
    
    <h3>בדיקת Cookies:</h3>
    <pre><?php print_r($_COOKIE); ?></pre>
</body>
</html>