<?php
// debug.php - בדיקת שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>בדיקת שגיאות</h2>";

// בדיקה 1: נתיב לקונפיג
$configPath = '../config.php';
echo "<p>1. בדיקת config.php:</p>";
if (file_exists($configPath)) {
    echo "<p style='color: green;'>✓ הקובץ קיים</p>";
    
    // נסה לטעון
    try {
        ob_start();
        require_once $configPath;
        ob_end_clean();
        echo "<p style='color: green;'>✓ הקובץ נטען בהצלחה</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ שגיאה בטעינת הקובץ: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ הקובץ לא נמצא בנתיב: $configPath</p>";
}

// בדיקה 2: auth_check.php
$authPath = '../includes/auth_check.php';
echo "<p>2. בדיקת auth_check.php:</p>";
if (file_exists($authPath)) {
    echo "<p style='color: green;'>✓ הקובץ קיים</p>";
    
    // בדוק אם יש את הפונקציה
    if (function_exists('checkPageAccess')) {
        echo "<p style='color: green;'>✓ הפונקציה checkPageAccess קיימת</p>";
    } else {
        echo "<p style='color: orange;'>⚠ הפונקציה checkPageAccess לא נמצאה</p>";
    }
} else {
    echo "<p style='color: red;'>✗ הקובץ לא נמצא בנתיב: $authPath</p>";
}

// בדיקה 3: SESSION
echo "<p>3. בדיקת SESSION:</p>";
if (isset($_SESSION)) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>✗ SESSION לא פעיל</p>";
}

// בדיקה 4: הרשאות
echo "<p>4. בדיקת הרשאות:</p>";
if (isset($_SESSION['user_id'])) {
    echo "<p>משתמש: " . $_SESSION['user_id'] . "</p>";
    echo "<p>רמת הרשאה: " . ($_SESSION['permission_level'] ?? 'לא מוגדר') . "</p>";
    
    if ($_SESSION['permission_level'] >= 4) {
        echo "<p style='color: green;'>✓ יש הרשאת מנהל</p>";
    } else {
        echo "<p style='color: orange;'>⚠ אין הרשאת מנהל</p>";
    }
} else {
    echo "<p style='color: red;'>✗ משתמש לא מחובר</p>";
}

// בדיקה 5: קבועים
echo "<p>5. בדיקת קבועים:</p>";
$constants = ['SITE_NAME', 'SITE_URL', 'LOGIN_URL'];
foreach ($constants as $const) {
    if (defined($const)) {
        echo "<p style='color: green;'>✓ $const = " . constant($const) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ $const לא מוגדר</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>חזרה לindex.php</a></p>";
?>