<?php
/**
 * config.php - קובץ הגדרות ראשי למערכת ניהול בית עלמין
 * תיקון בעיית הניתובים!
 */

// הגדרת דיווח על שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// הגדרת אזור זמן
date_default_timezone_set('Asia/Jerusalem');

/**
 * טעינת קובץ ENV
 */
function loadEnvFile($path) {
    if (!file_exists($path)) {
        $parentPath = dirname($path) . '/.env';
        if (file_exists($parentPath)) {
            $path = $parentPath;
        } else {
            die("
                <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
                    <h2>שגיאה: קובץ .env לא נמצא</h2>
                    <p>יש ליצור קובץ .env בתיקיית השורש של הפרויקט</p>
                </div>
            ");
        }
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $loaded = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $loaded++;
        }
    }
    
    return $loaded;
}

// טען את קובץ ENV
$envPath = __DIR__ . '/.env';
$loadedVars = loadEnvFile($envPath);

// הגדרות מסד נתונים
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// בדיקת פרטים נדרשים
if (empty(DB_NAME)) {
    die("שגיאה: חסר שם מסד נתונים (DB_NAME) בקובץ .env");
}
if (empty(DB_USER)) {
    die("שגיאה: חסר שם משתמש (DB_USER) בקובץ .env");
}

// הגדרת ה-URL הבסיסי של האתר
define('SITE_URL', 'https://vaadma.cemeteries.mbe-plus.com/project_form_levayot');

// הגדרות URLs - כולם חייבים להיות מלאים!
define('LOGIN_URL', SITE_URL . '/auth/login.php');
define('LOGOUT_URL', SITE_URL . '/auth/logout.php');
define('REGISTER_URL', SITE_URL . '/auth/register.php');



define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'מערכת ניהול בית עלמין');
define('SITE_EMAIL', $_ENV['SITE_EMAIL'] ?? 'info@example.com');

// הגדרות נתיבים בשרת
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('AUTH_PATH', ROOT_PATH . '/auth/');
define('ADMIN_PATH', ROOT_PATH . '/admin/');
define('FORM_PATH', ROOT_PATH . '/form/');
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? (ROOT_PATH . '/uploads/'));
define('LOGS_PATH', $_ENV['LOG_PATH'] ?? (ROOT_PATH . '/logs/'));

// ****** תיקון קריטי - כל הניתובים חייבים להיות URL מלא! ******
// דשבורדים - URLs מלאים!
define('DASHBOARD_URL', SITE_URL . '/includes/dashboard.php');
define('DASHBOARD_FULL_URL', SITE_URL . '/includes/dashboard.php');
define('ADMIN_DASHBOARD_URL', SITE_URL . '/includes/dashboard_admin.php');
define('DASHBOARD_DECEASED_URL', SITE_URL . '/includes/dashboard_deceased.php');
define('DASHBOARD_PURCHASES_URL', SITE_URL . '/includes/dashboard_purchases.php');
define('DASHBOARD_VIEW_ONLY_URL', SITE_URL . '/includes/dashboard_view_only.php');
define('CEMETERIES_DASHBOARD_URL', SITE_URL . '/includes/dashboard_cemeteries.php');



// טפסים - URL מלא!
define('FORM_DECEASED_URL', SITE_URL . '/form/index_deceased.php');
define('FORM_PURCHASE_URL', SITE_URL . '/form/index_purchase.php');

// רשימות - URL מלא!
define('DECEASED_LIST_URL', SITE_URL . '/includes/lists/deceased_list.php');
define('PURCHASE_LIST_URL', SITE_URL . '/includes/lists/purchase_list.php');

// הגדרות Google Auth
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// הגדרות אבטחה
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 3600));
define('CSRF_TOKEN_LIFETIME', (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600));
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-this');


// משתנה גלובלי לחיבור
$pdo = null;

/**
 * פונקציה לקבלת חיבור למסד נתונים
 */
function getDbConnection() {
    global $pdo;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            if (($_ENV['APP_ENV'] ?? 'development') !== 'production') {
                die("
                    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
                        <h2>שגיאת חיבור למסד נתונים</h2>
                        <p style='color: red;'>" . $e->getMessage() . "</p>
                    </div>
                ");
            } else {
                error_log("Database connection error: " . $e->getMessage());
                die("שגיאה בחיבור למערכת. אנא פנה למנהל המערכת.");
            }
        }
    }
    
    return $pdo;
}

/**
 * פונקציות עזר
 */

// ניקוי קלט
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// יצירת CSRF token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// בדיקת CSRF token
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// קבלת רשימת דשבורדים מותרים למשתמש
function getUserAllowedDashboards($userId) {
    $dashboards = [];
    
    // ברירת מחדל - דשבורד ראשי (URL מלא!)
    $dashboards[] = [
        'name' => 'דשבורד ראשי', 
        'url' => DASHBOARD_FULL_URL,
        'type' => 'main',
        'icon' => 'fas fa-home'
    ];
    
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT permission_level 
            FROM user_permissions 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $perm = $stmt->fetch();
        
        if ($perm) {
            $permissionLevel = $perm['permission_level'];
            
            if ($permissionLevel >= 2) {
                $dashboards[] = [
                    'name' => 'דשבורד נפטרים',
                    'url' => DASHBOARD_DECEASED_URL,
                    'type' => 'deceased',
                    'icon' => 'fas fa-user-alt-slash'
                ];
                
                $dashboards[] = [
                    'name' => 'דשבורד רכישות',
                    'url' => DASHBOARD_PURCHASES_URL,
                    'type' => 'purchases',
                    'icon' => 'fas fa-shopping-cart'
                ];
            }
            
            if ($permissionLevel >= 3) {
                $dashboards[] = [
                    'name' => 'דשבורד ניהול',
                    'url' => ADMIN_DASHBOARD_URL,
                    'type' => 'admin',
                    'icon' => 'fas fa-cog'
                ];
                
                $dashboards[] = [
                    'name' => 'ניהול בתי עלמין',
                    'url' => CEMETERIES_DASHBOARD_URL,
                    'type' => 'cemeteries',
                    'icon' => 'fas fa-monument'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
    }
    
    return $dashboards;
}

// קבלת URL של דשבורד לפי הרשאות (URL מלא!)
function getUserDashboardUrl($userId, $permissionLevel = null) {
    if ($permissionLevel === null) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("
                SELECT permission_level 
                FROM user_permissions 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $perm = $stmt->fetch();
            $permissionLevel = $perm ? $perm['permission_level'] : 1;
        } catch (Exception $e) {
            $permissionLevel = 1;
        }
    }
    
    // החזר דשבורד לפי רמת הרשאה (URL מלא!)
    switch ($permissionLevel) {
        case 4: // מנהל ראשי
        case 3: // מנהל
            return ADMIN_DASHBOARD_URL;
        case 2: // עורך
            return DASHBOARD_DECEASED_URL;
        case 1: // צופה
        default:
            return DASHBOARD_FULL_URL;
    }
}

// פונקציה למעבר לדף (URL מלא!)
function redirectTo($url) {
    // אם זה לא URL מלא, הוסף את SITE_URL
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        // אם זה מתחיל ב-/, הסר אותו
        $url = ltrim($url, '/');
        $url = SITE_URL . '/' . $url;
    }
    
    header('Location: ' . $url);
    exit;
}

// רישום פעילות
function logActivity($action, $details = [], $userId = null) {
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $userId = $userId ?: ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->execute([
            $userId,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            $ip,
            $userAgent
        ]);
        
    } catch (Exception $e) {
        error_log("Activity log error: $action - " . json_encode($details));
    }
}

// התחלת SESSION
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_name('CEMETERY_SESSION');
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// טען קבצים נוספים אם קיימים
$includeFiles = [
    'functions.php',
    'auth_functions.php',
    'validation_functions.php'
];

foreach ($includeFiles as $file) {
    $filePath = INCLUDES_PATH . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

// וודא שתיקיות נדרשות קיימות
$requiredDirs = [UPLOAD_PATH, LOGS_PATH];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// הגדר קובץ לוג
ini_set('error_log', LOGS_PATH . 'php_errors.log');
?>