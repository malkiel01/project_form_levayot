<?php

// config.php - קובץ הגדרות משופר

// תיקון לבעיות Session במובייל
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // חשוב! Lax ולא Strict למובייל

// אם האתר ב-HTTPS (מומלץ מאוד!)
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
    ini_set('session.cookie_secure', '1');
}

// הגדרת זמן חיים ארוך יותר ל-session
ini_set('session.gc_maxlifetime', '7200'); // 2 שעות
ini_set('session.cookie_lifetime', '7200'); // 2 שעות

// הגדרות אבטחה
define('SESSION_NAME', 'deceased_forms_session');
define('CSRF_TOKEN_NAME', 'csrf_token');

// הגדר שם מותאם אישית ל-session
session_name(SESSION_NAME);

// התחל session רק אם לא פעיל
if (session_status() === PHP_SESSION_NONE) {
    // הגדרות נוספות לפני התחלת ה-session
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '', // ריק = הדומיין הנוכחי
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax' // חשוב למובייל!
    ]);
    
    session_start();
}

// יצירת CSRF token אם לא קיים
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// הגדרות חיבור לדטהבייס
define('DB_HOST', 'mbe-plus.com');
define('DB_NAME', 'mbeplusc_kadisha_v7');
define('DB_USER', 'mbeplusc_test');
define('DB_PASS', 'Gxfv16be');
define('DB_CHARSET', 'utf8mb4');

// הגדרות כלליות
define('SITE_URL', 'https://vaadma.cemeteries.mbe-plus.com/project_form_levayot');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// לינקים וקישורים
define('LOGIN_URL', 'auth/login.php');
define('LOGOUT_URL', 'auth/logout.php');
define('LOGIN_SITE_URL', SITE_URL .  'auth/login.php');

define('FORM_URL', 'form/index_deceased.php');
define('FORM_DECEASED_URL', 'form/index_deceased.php');
define('FORM_PURCHASE_URL', 'form/index_purchase.php');

define('DASHBOARD_FULL_URL', SITE_URL . '/includes/dashboard.php');
define('DASHBOARD_DECEASED_URL', SITE_URL . '/includes/dashboard_deceased.php');
define('DASHBOARD_PURCHASES_URL', SITE_URL . '/includes/dashboard_purchases.php');


define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar']);

// הגדרות זמן
date_default_timezone_set('Asia/Jerusalem');


// -----------
// עדכן את הפונקציה הזאת ב-config.php במקום הישנה

// פונקציה חדשה להעניק הרשאה ספציפית למשתמש
function grantUserPermission($fieldName, $userId, $action = 'view') {
    $db = getDbConnection();
    $column = $action === 'view' ? 'user_specific_view' : 'user_specific_edit';
    
    // קבלת ההרשאות הנוכחיות
    $stmt = $db->prepare("SELECT {$column} FROM field_permissions WHERE field_name = ?");
    $stmt->execute([$fieldName]);
    $current = $stmt->fetchColumn();
    
    $permissions = $current ? json_decode($current, true) : [];
    $permissions[$userId] = true;
    
    $updateStmt = $db->prepare("
        UPDATE field_permissions 
        SET {$column} = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE field_name = ?
    ");
    return $updateStmt->execute([json_encode($permissions), $fieldName]);
}

// פונקציה חדשה להסרת הרשאה ספציפית
function revokeUserPermission($fieldName, $userId, $action = 'view') {
    $db = getDbConnection();
    $column = $action === 'view' ? 'user_specific_view' : 'user_specific_edit';
    
    $stmt = $db->prepare("SELECT {$column} FROM field_permissions WHERE field_name = ?");
    $stmt->execute([$fieldName]);
    $current = $stmt->fetchColumn();
    
    if ($current) {
        $permissions = json_decode($current, true);
        unset($permissions[$userId]);
        $newValue = empty($permissions) ? null : json_encode($permissions);
        
        $updateStmt = $db->prepare("
            UPDATE field_permissions 
            SET {$column} = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE field_name = ?
        ");
        return $updateStmt->execute([$newValue, $fieldName]);
    }
    
    return true;
}
// -----------



// <?php
// // dashboard_router.php - הוסף את הפונקציות האלה לקובץ config.php

/**
 * פונקציה לקבלת כתובת הדשבורד המתאים למשתמש
 */
function getUserDashboardUrl($userId = null, $permissionLevel = null) {
    $db = getDbConnection();
    
    // אם לא נשלחו פרמטרים, קח מהסשן
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    if ($permissionLevel === null) {
        $permissionLevel = $_SESSION['permission_level'] ?? 1;
    }
    
    // מנהלים ועורכים מתקדמים - תמיד דשבורד ראשי
    if ($permissionLevel >= 3) {
        return DASHBOARD_FULL_URL;
    }
    
    // עורכים רגילים וצופים - בדוק הרשאות ספציפיות
    if ($permissionLevel == 2) {
        // בדוק אם יש הרשאה לדשבורד ספציפי
        $stmt = $db->prepare("
            SELECT dashboard_type 
            FROM user_dashboard_permissions 
            WHERE user_id = ? AND has_permission = 1
            ORDER BY 
                CASE dashboard_type 
                    WHEN 'main' THEN 1 
                    WHEN 'deceased' THEN 2 
                    WHEN 'purchases' THEN 3 
                END
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $allowedDashboard = $stmt->fetchColumn();
        
        if ($allowedDashboard) {
            switch($allowedDashboard) {
                case 'main':
                    return DASHBOARD_FULL_URL;
                case 'deceased':
                    return DASHBOARD_DECEASED_URL;
                case 'purchases':
                    return DASHBOARD_PURCHASES_URL;
            }
        }
    }
    
    // ברירת מחדל - דשבורד צפייה בלבד
    return SITE_URL . '/includes/dashboard_view_only.php';
}

/**
 * פונקציה להענקת הרשאה לדשבורד למשתמש
 */
function grantDashboardPermission($userId, $dashboardType, $grantedBy = null) {
    $db = getDbConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO user_dashboard_permissions 
            (user_id, dashboard_type, has_permission, created_by) 
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
            has_permission = 1,
            created_by = ?
        ");
        
        $grantedBy = $grantedBy ?? $_SESSION['user_id'] ?? null;
        return $stmt->execute([$userId, $dashboardType, $grantedBy, $grantedBy]);
        
    } catch (Exception $e) {
        error_log("Error granting dashboard permission: " . $e->getMessage());
        return false;
    }
}

/**
 * פונקציה להסרת הרשאה לדשבורד
 */
function revokeDashboardPermission($userId, $dashboardType) {
    $db = getDbConnection();
    
    try {
        $stmt = $db->prepare("
            UPDATE user_dashboard_permissions 
            SET has_permission = 0 
            WHERE user_id = ? AND dashboard_type = ?
        ");
        return $stmt->execute([$userId, $dashboardType]);
        
    } catch (Exception $e) {
        error_log("Error revoking dashboard permission: " . $e->getMessage());
        return false;
    }
}

/**
 * פונקציה לבדיקת הרשאה לדשבורד
 */
function hasDashboardPermission($userId, $dashboardType) {
    $db = getDbConnection();
    
    // מנהלים תמיד יכולים
    $stmt = $db->prepare("SELECT permission_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $permissionLevel = $stmt->fetchColumn();
    
    if ($permissionLevel >= 3) {
        return true;
    }
    
    // בדוק הרשאה ספציפית
    $stmt = $db->prepare("
        SELECT has_permission 
        FROM user_dashboard_permissions 
        WHERE user_id = ? AND dashboard_type = ? AND has_permission = 1
    ");
    $stmt->execute([$userId, $dashboardType]);
    
    return (bool) $stmt->fetchColumn();
}

/**
 * פונקציה לקבלת רשימת הדשבורדים המותרים למשתמש
 */
function getUserAllowedDashboards($userId = null) {
    $db = getDbConnection();
    
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    
    // קבל רמת הרשאה
    $stmt = $db->prepare("SELECT permission_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $permissionLevel = $stmt->fetchColumn();
    
    $dashboards = [];
    
    // מנהלים ועורכים מתקדמים - גישה לכל הדשבורדים
    if ($permissionLevel >= 3) {
        $dashboards = [
            ['type' => 'main', 'name' => 'דשבורד ראשי', 'url' => DASHBOARD_FULL_URL, 'icon' => 'fas fa-home'],
            ['type' => 'deceased', 'name' => 'דשבורד נפטרים', 'url' => DASHBOARD_DECEASED_URL, 'icon' => 'fas fa-user-alt-slash'],
            ['type' => 'purchases', 'name' => 'דשבורד רכישות', 'url' => DASHBOARD_PURCHASES_URL, 'icon' => 'fas fa-shopping-cart']
        ];
    } else {
        // בדוק הרשאות ספציפיות
        $stmt = $db->prepare("
            SELECT dashboard_type 
            FROM user_dashboard_permissions 
            WHERE user_id = ? AND has_permission = 1
        ");
        $stmt->execute([$userId]);
        $allowedTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($allowedTypes as $type) {
            switch($type) {
                case 'main':
                    $dashboards[] = ['type' => 'main', 'name' => 'דשבורד ראשי', 'url' => DASHBOARD_FULL_URL, 'icon' => 'fas fa-home'];
                    break;
                case 'deceased':
                    $dashboards[] = ['type' => 'deceased', 'name' => 'דשבורד נפטרים', 'url' => DASHBOARD_DECEASED_URL, 'icon' => 'fas fa-user-alt-slash'];
                    break;
                case 'purchases':
                    $dashboards[] = ['type' => 'purchases', 'name' => 'דשבורד רכישות', 'url' => DASHBOARD_PURCHASES_URL, 'icon' => 'fas fa-shopping-cart'];
                    break;
            }
        }
    }
    
    // תמיד הוסף דשבורד צפייה בלבד כאופציה
    $dashboards[] = ['type' => 'view_only', 'name' => 'צפייה בלבד', 'url' => SITE_URL . '/includes/dashboard_view_only.php', 'icon' => 'fas fa-eye'];
    
    return $dashboards;
}

// הוסף את הקבוע החדש
define('DASHBOARD_VIEW_ONLY_URL', SITE_URL . '/includes/dashboard_view_only.php');

// ----------

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

// פונקציה משופרת ליצירת UUID
function generateUUID() {
    // שיטה 1: שימוש ב-random_bytes (מומלץ)
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
        
        // הגדרת ביטים לפי תקן UUID v4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // גרסה 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant bits
        
        // המרה לפורמט UUID
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    } else {
        // שיטה 2: גיבוי עם mt_rand (פחות מאובטח)
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    // רישום בלוג
    error_log("Generated new UUID: " . $uuid);
    
    // וידוא שה-UUID ייחודי בבסיס הנתונים
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE form_uuid = ?");
    $stmt->execute([$uuid]);
    
    // אם כבר קיים, צור חדש (נדיר מאוד)
    if ($stmt->fetchColumn() > 0) {
        error_log("UUID collision detected, generating new one");
        return generateUUID();
    }
    
    return $uuid;
}

// פונקציה לבדיקת הרשאות
function hasPermission($fieldName, $permissionLevel, $action = 'view') {
    static $cache = [];
    
    $userId = $_SESSION['user_id'] ?? null;
    $cacheKey = "{$fieldName}_{$userId}_{$permissionLevel}_{$action}";
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT 
                view_permission_levels,
                edit_permission_levels,
                user_specific_view,
                user_specific_edit
            FROM field_permissions 
            WHERE field_name = ?
        ");
        $stmt->execute([$fieldName]);
        $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permissions) {
            // אם השדה לא קיים, ברירת מחדל - רק מנהלים
            $cache[$cacheKey] = ($permissionLevel >= 4);
            return $cache[$cacheKey];
        }
        
        // בחירת העמודה הנכונה
        $levelColumn = $action === 'view' ? 'view_permission_levels' : 'edit_permission_levels';
        $userColumn = $action === 'view' ? 'user_specific_view' : 'user_specific_edit';
        
        // בדיקת הרשאה ספציפית למשתמש (קדימות עליונה)
        if ($permissions[$userColumn] && $userId) {
            $userPermissions = json_decode($permissions[$userColumn], true);
            if (isset($userPermissions[$userId]) && $userPermissions[$userId] === true) {
                $cache[$cacheKey] = true;
                return true;
            }
        }
        
        // בדיקת הרשאה כללית לפי רמה
        if ($permissions[$levelColumn]) {
            $allowedLevels = json_decode($permissions[$levelColumn], true);
            if (in_array($permissionLevel, $allowedLevels)) {
                $cache[$cacheKey] = true;
                return true;
            }
        }
        
        $cache[$cacheKey] = false;
        return false;
        
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        // במקרה של שגיאה, ברירת מחדל - רק מנהלים
        $cache[$cacheKey] = ($permissionLevel >= 4);
        return $cache[$cacheKey];
    }
}
// פונקציה לבדיקת הרשאה ספציפית למשתמש
function userHasPermission($userId, $permissionName) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT has_permission 
        FROM user_permissions 
        WHERE user_id = ? AND permission_name = ? AND has_permission = 1
    ");
    $stmt->execute([$userId, $permissionName]);
    
    return $stmt->fetchColumn() == 1;
}

// פונקציה לבדיקה אם המשתמש יכול למחוק טפסים
function canDeleteForms($userId, $permissionLevel) {
    // מנהלים תמיד יכולים
    if ($permissionLevel >= 4) {
        return true;
    }
    
    // בדוק הרשאה ספציפית
    return userHasPermission($userId, 'delete_forms');
}


// -----

// הוספה לקובץ config.php
define('DEBUG_MODE', true); // שנה ל-false בייצור

// פונקציית סניטציה משופרת עם דיבוג
function sanitizeInput($data) {
    if (is_array($data)) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            // דיבוג של שדות בעייתיים
            if (in_array($key, ['cemetery_id', 'block_id', 'section_id', 'row_id', 'grave_id', 'plot_id'])) {
                error_log("Sanitizing field $key: " . print_r($value, true));
            }
            $sanitized[$key] = sanitizeInput($value);
        }
        return $sanitized;
    }
    
    // טיפול בערכים ריקים
    if ($data === '' || $data === null) {
        return null;
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


// -----

// פונקציה לסניטציה של קלט
function sanitizeInput2($input) {
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

// פונקציה לרישום פעילות
function logActivity($action, $details = [], $formId = null) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $formId,
            $action,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// פונקציה להצגת הודעות דיבוג בקונסול
function debugLog($message, $data = null) {
    if ($data !== null) {
        $message .= ' - ' . json_encode($data);
    }
    echo "<script>console.log('DEBUG: " . addslashes($message) . "');</script>";
}

// יצירת CSRF token אם לא קיים
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// הגדרת error reporting בהתאם לסביבה
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// וידוא שתיקיית uploads קיימת
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// וידוא שתיקיית logs קיימת
$logsPath = __DIR__ . '/logs/';
if (!is_dir($logsPath)) {
    mkdir($logsPath, 0777, true);
}

// הגדרת קובץ לוג
ini_set('error_log', $logsPath . 'php_errors.log');


