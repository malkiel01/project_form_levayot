<?php
// test-session-cemeteries.php - בדיקת SESSION
session_start();
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת SESSION</title>
</head>
<body>
    <h2>בדיקת SESSION</h2>
    
    <h3>תוכן ה-SESSION הנוכחי:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
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
    
    <hr>
    
    <h3>Session ID:</h3>
    <p><?php echo session_id(); ?></p>
</body>
</html>