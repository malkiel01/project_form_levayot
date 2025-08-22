<?php
/**
 * config.php - קובץ הגדרות ראשי למערכת ניהול בית עלמין
 * כל הניתובים מנוהלים מכאן באופן מרכזי
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
                    <p>נתיב: $path</p>
                </div>
            ");
        }
    }
    
    if (!is_readable($path)) {
        die("שגיאה: אין הרשאות קריאה לקובץ .env");
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

if ($loadedVars == 0) {
    die("שגיאה: קובץ .env ריק או לא תקין");
}

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

// הגדרות אתר
define('SITE_URL', rtrim($_ENV['SITE_URL'] ?? 'https://vaadma.cemeteries.mbe-plus.com/project_form_levayot', '/'));
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'מערכת ניהול בית עלמין');
define('SITE_EMAIL', $_ENV['SITE_EMAIL'] ?? 'info@example.com');

// חילוץ הנתיב הבסיסי
$parsed_url = parse_url(SITE_URL);
$path_parts = explode('/', trim($parsed_url['path'] ?? '', '/'));
define('BASE_PATH', '/' . implode('/', $path_parts));

// הגדרות נתיבים בשרת (תיקיות פיזיות)
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app/');
define('PUBLIC_PATH', ROOT_PATH . '/public/');
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? (ROOT_PATH . '/uploads/'));
define('LOGS_PATH', $_ENV['LOG_PATH'] ?? (ROOT_PATH . '/logs/'));

// תיקיות האפליקציה
define('CONTROLLERS_PATH', APP_PATH . 'controllers/');
define('MODELS_PATH', APP_PATH . 'models/');
define('VIEWS_PATH', APP_PATH . 'views/');
define('INCLUDES_PATH', APP_PATH . 'includes/');
define('HELPERS_PATH', APP_PATH . 'helpers/');
define('MIDDLEWARE_PATH', APP_PATH . 'middleware/');

// תיקיות העיצוב
define('ASSETS_PATH', PUBLIC_PATH . 'assets/');
define('CSS_PATH', ASSETS_PATH . 'css/');
define('JS_PATH', ASSETS_PATH . 'js/');
define('IMAGES_PATH', ASSETS_PATH . 'images/');
define('FONTS_PATH', ASSETS_PATH . 'fonts/');

/**
 * מערכת ניתוב מרכזית
 * כל הניתובים מוגדרים כאן ומנוהלים באופן מרכזי
 */
class Routes {
    // ניתובי בסיס
    const BASE_URL = SITE_URL;
    const BASE_PATH = BASE_PATH;
    
    // ניתובי אימות
    const AUTH = [
        'LOGIN' => BASE_PATH . '/auth/login.php',
        'LOGOUT' => BASE_PATH . '/auth/logout.php',
        'REGISTER' => BASE_PATH . '/auth/register.php',
        'FORGOT_PASSWORD' => BASE_PATH . '/auth/forgot_password.php',
        'RESET_PASSWORD' => BASE_PATH . '/auth/reset_password.php',
        'GOOGLE_AUTH' => BASE_PATH . '/auth/google_auth.php',
        'VERIFY_EMAIL' => BASE_PATH . '/auth/verify_email.php'
    ];
    
    // ניתובי דשבורדים
    const DASHBOARDS = [
        'MAIN' => BASE_PATH . '/dashboard/index.php',
        'ADMIN' => BASE_PATH . '/dashboard/admin.php',
        'CEMETERIES' => BASE_PATH . '/dashboard/cemeteries.php',
        'DECEASED' => BASE_PATH . '/dashboard/deceased.php',
        'PURCHASES' => BASE_PATH . '/dashboard/purchases.php',
        'REPORTS' => BASE_PATH . '/dashboard/reports.php',
        'SETTINGS' => BASE_PATH . '/dashboard/settings.php',
        'VIEW_ONLY' => BASE_PATH . '/dashboard/view_only.php'
    ];
    
    // ניתובי טפסים
    const FORMS = [
        'DECEASED' => BASE_PATH . '/forms/deceased.php',
        'PURCHASE' => BASE_PATH . '/forms/purchase.php',
        'PLOT' => BASE_PATH . '/forms/plot.php',
        'CONTACT' => BASE_PATH . '/forms/contact.php',
        'PAYMENT' => BASE_PATH . '/forms/payment.php'
    ];
    
    // ניתובי רשימות
    const LISTS = [
        'DECEASED' => BASE_PATH . '/lists/deceased.php',
        'PURCHASES' => BASE_PATH . '/lists/purchases.php',
        'PLOTS' => BASE_PATH . '/lists/plots.php',
        'USERS' => BASE_PATH . '/lists/users.php',
        'CEMETERIES' => BASE_PATH . '/lists/cemeteries.php',
        'ACTIVITY' => BASE_PATH . '/lists/activity_log.php'
    ];
    
    // ניתובי API
    const API = [
        'BASE' => BASE_PATH . '/api/',
        'AUTH' => BASE_PATH . '/api/auth/',
        'DECEASED' => BASE_PATH . '/api/deceased/',
        'PURCHASES' => BASE_PATH . '/api/purchases/',
        'PLOTS' => BASE_PATH . '/api/plots/',
        'USERS' => BASE_PATH . '/api/users/',
        'SEARCH' => BASE_PATH . '/api/search/',
        'REPORTS' => BASE_PATH . '/api/reports/',
        'UPLOAD' => BASE_PATH . '/api/upload/'
    ];
    
    // ניתובי ניהול
    const ADMIN = [
        'USERS' => BASE_PATH . '/admin/users.php',
        'PERMISSIONS' => BASE_PATH . '/admin/permissions.php',
        'CEMETERIES' => BASE_PATH . '/admin/cemeteries.php',
        'SETTINGS' => BASE_PATH . '/admin/settings.php',
        'BACKUP' => BASE_PATH . '/admin/backup.php',
        'LOGS' => BASE_PATH . '/admin/logs.php',
        'IMPORT' => BASE_PATH . '/admin/import.php',
        'EXPORT' => BASE_PATH . '/admin/export.php'
    ];
    
    // ניתובי משאבים (Assets)
    const ASSETS = [
        'CSS' => BASE_PATH . '/assets/css/',
        'JS' => BASE_PATH . '/assets/js/',
        'IMAGES' => BASE_PATH . '/assets/images/',
        'FONTS' => BASE_PATH . '/assets/fonts/',
        'VENDORS' => BASE_PATH . '/assets/vendors/',
        'UPLOADS' => BASE_PATH . '/uploads/'
    ];
    
    /**
     * קבלת ניתוב לפי מפתח
     */
    public static function get($section, $key = null) {
        $section = strtoupper($section);
        
        if ($key === null) {
            return constant("self::$section") ?? null;
        }
        
        $key = strtoupper($key);
        $routes = constant("self::$section") ?? [];
        
        return $routes[$key] ?? null;
    }
    
    /**
     * בניית URL מלא
     */
    public static function url($path) {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        
        if (strpos($path, '/') === 0) {
            return SITE_URL . $path;
        }
        
        return SITE_URL . '/' . $path;
    }
    
    /**
     * ניתוב לדף
     */
    public static function redirect($path, $params = []) {
        $url = self::url($path);
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * בדיקת הרשאה לניתוב
     */
    public static function canAccess($route, $userId = null) {
        $userId = $userId ?: ($_SESSION['user_id'] ?? null);
        
        if (!$userId) {
            return false;
        }
        
        // כאן תוכל להוסיף לוגיקה לבדיקת הרשאות
        // לפי הניתוב והמשתמש
        
        return true;
    }
}

// הגדרות קבועות לתאימות אחורה
define('LOGIN_URL', Routes::AUTH['LOGIN']);
define('LOGOUT_URL', Routes::AUTH['LOGOUT']);
define('DASHBOARD_URL', Routes::DASHBOARDS['MAIN']);
define('DASHBOARD_FULL_URL', Routes::DASHBOARDS['MAIN']);
define('ADMIN_DASHBOARD_URL', Routes::DASHBOARDS['ADMIN']);
define('DASHBOARD_DECEASED_URL', Routes::DASHBOARDS['DECEASED']);
define('DASHBOARD_PURCHASES_URL', Routes::DASHBOARDS['PURCHASES']);
define('FORM_DECEASED_URL', Routes::FORMS['DECEASED']);
define('FORM_PURCHASE_URL', Routes::FORMS['PURCHASE']);

// הגדרות Google Auth
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// הגדרות אבטחה
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 3600));
define('CSRF_TOKEN_LIFETIME', (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600));
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-this');
define('MAX_LOGIN_ATTEMPTS', (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOCKOUT_TIME', (int)($_ENV['LOCKOUT_TIME'] ?? 900)); // 15 דקות

// הגדרות העלאת קבצים
define('MAX_FILE_SIZE', (int)($_ENV['MAX_FILE_SIZE'] ?? 5242880)); // 5MB
define('ALLOWED_FILE_TYPES', explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'jpg,jpeg,png,pdf,doc,docx'));

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
 * פונקציות עזר נדרשות
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
    
    // בדיקת תוקף זמן
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
    
    // ברירת מחדל - דשבורד ראשי
    $dashboards[] = [
        'name' => 'דשבורד ראשי', 
        'url' => Routes::DASHBOARDS['MAIN'],
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
            
            // הוסף דשבורדים לפי רמת הרשאה
            if ($permissionLevel >= 2) {
                $dashboards[] = [
                    'name' => 'דשבורד נפטרים',
                    'url' => Routes::DASHBOARDS['DECEASED'],
                    'type' => 'deceased',
                    'icon' => 'fas fa-user-alt-slash'
                ];
                
                $dashboards[] = [
                    'name' => 'דשבורד רכישות',
                    'url' => Routes::DASHBOARDS['PURCHASES'],
                    'type' => 'purchases',
                    'icon' => 'fas fa-shopping-cart'
                ];
            }
            
            if ($permissionLevel >= 3) {
                $dashboards[] = [
                    'name' => 'דשבורד ניהול',
                    'url' => Routes::DASHBOARDS['ADMIN'],
                    'type' => 'admin',
                    'icon' => 'fas fa-cog'
                ];
                
                $dashboards[] = [
                    'name' => 'ניהול בתי עלמין',
                    'url' => Routes::DASHBOARDS['CEMETERIES'],
                    'type' => 'cemeteries',
                    'icon' => 'fas fa-monument'
                ];
            }
            
            if ($permissionLevel >= 4) {
                $dashboards[] = [
                    'name' => 'דוחות',
                    'url' => Routes::DASHBOARDS['REPORTS'],
                    'type' => 'reports',
                    'icon' => 'fas fa-chart-bar'
                ];
                
                $dashboards[] = [
                    'name' => 'הגדרות מערכת',
                    'url' => Routes::DASHBOARDS['SETTINGS'],
                    'type' => 'settings',
                    'icon' => 'fas fa-cogs'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
    }
    
    return $dashboards;
}

// קבלת URL של דשבורד לפי הרשאות
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
    
    // החזר דשבורד לפי רמת הרשאה
    switch ($permissionLevel) {
        case 4: // מנהל ראשי
        case 3: // מנהל
            return Routes::DASHBOARDS['ADMIN'];
        case 2: // עורך
            return Routes::DASHBOARDS['DECEASED'];
        case 1: // צופה
        default:
            return Routes::DASHBOARDS['MAIN'];
    }
}

// פונקציה למעבר לדף (תאימות אחורה)
function redirectTo($path) {
    Routes::redirect($path);
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
    // הגדרות אבטחה ל-session
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // אם באתר מאובטח
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    
    // הגדר שם ייחודי ל-session
    session_name('CEMETERY_SESSION');
    
    // הגדר זמן חיים
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // התחל את ה-session
    session_start();
    
    // חדש session ID כל 30 דקות
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
    'validation_functions.php',
    'security_functions.php',
    'database_functions.php'
];

foreach ($includeFiles as $file) {
    $filePath = HELPERS_PATH . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

// וודא שתיקיות נדרשות קיימות
$requiredDirs = [
    UPLOAD_PATH,
    LOGS_PATH,
    APP_PATH,
    PUBLIC_PATH,
    CONTROLLERS_PATH,
    MODELS_PATH,
    VIEWS_PATH,
    INCLUDES_PATH,
    HELPERS_PATH,
    MIDDLEWARE_PATH,
    ASSETS_PATH,
    CSS_PATH,
    JS_PATH,
    IMAGES_PATH,
    FONTS_PATH
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// הגדר קובץ לוג
ini_set('error_log', LOGS_PATH . 'php_errors.log');

// בדיקה בסיסית של החיבור (אופציונלי)
if (($_ENV['CHECK_DB_ON_LOAD'] ?? false) == 'true') {
    try {
        $db = getDbConnection();
        $db->query("SELECT 1");
    } catch (Exception $e) {
        error_log("Database check failed: " . $e->getMessage());
    }
}
?>