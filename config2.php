<?php
// config.php - קובץ הגדרות

// הגדרות חיבור לדטהבייס
define('DB_HOST', 'mbe-plus.com');
define('DB_NAME', 'mbeplusc_kadisha_v7');
define('DB_USER', 'mbeplusc_test');
define('DB_PASS', 'Gxfv16be');
define('DB_CHARSET', 'utf8mb4');

// הגדרות כלליות
define('SITE_URL', 'http://localhost/deceased_forms');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// הגדרות אבטחה
define('SESSION_NAME', 'deceased_forms_session');
define('CSRF_TOKEN_NAME', 'csrf_token');

// הגדרות זמן
date_default_timezone_set('Asia/Jerusalem');

// פונקציה ליצירת חיבור לדטהבייס
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// פונקציה ליצירת UUID
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// פונקציה לבדיקת הרשאות
function hasPermission($fieldName, $permissionLevel, $action = 'view') {
    $db = getDbConnection();
    $column = $action === 'edit' ? 'can_edit' : 'can_view';
    
    $stmt = $db->prepare("
        SELECT $column 
        FROM field_permissions 
        WHERE field_name = ? AND permission_level = ?
    ");
    $stmt->execute([$fieldName, $permissionLevel]);
    $result = $stmt->fetch();
    
    return $result ? (bool)$result[$column] : false;
}

// פונקציה לסניטציה של קלט
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// פונקציה לולידציה של תעודת זהות ישראלית
function validateIsraeliId($id) {
    $id = trim($id);
    if (!preg_match('/^\d{9}$/', $id)) {
        return false;
    }
    
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $digit = (int)$id[$i];
        $step = $digit * (($i % 2) + 1);
        $sum += $step > 9 ? $step - 9 : $step;
    }
    
    return $sum % 10 === 0;
}

// התחלת סשן
session_name(SESSION_NAME);
session_start();

// יצירת CSRF token אם לא קיים
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}