<?php
/**
 * קונפיג חדש
 * config.php - קובץ הגדרות ראשי למערכת ניהול בית עלמין
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
        // נסה גם בתיקיית האב
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
        // דלג על הערות וקווים ריקים
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        // חלק למפתח וערך
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // הסר גרשיים
            $value = trim($value, '"\'');
            
            // הגדר משתנה סביבה
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

// בדיקה שיש את הפרטים הנדרשים
if (empty(DB_NAME)) {
    die("שגיאה: חסר שם מסד נתונים (DB_NAME) בקובץ .env");
}
if (empty(DB_USER)) {
    die("שגיאה: חסר שם משתמש (DB_USER) בקובץ .env");
}

// הגדרות אתר - חשוב לכלול את אלה!
define('SITE_URL', rtrim($_ENV['SITE_URL'] ?? 'https://mbe-plus.com/cemeteries/vaadma/project_form_levayot', '/'));
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'מערכת ניהול טפסי נפטרים');
define('SITE_EMAIL', $_ENV['SITE_EMAIL'] ?? 'info@example.com');

// הגדרות נתיבים
define('ROOT_PATH', dirname(__DIR__)); // תיקיית השורש של הפרויקט
define('AUTH_PATH', ROOT_PATH . '/auth/');
define('ADMIN_PATH', ROOT_PATH . '/admin/');
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? (ROOT_PATH . '/uploads/'));
define('LOGS_PATH', $_ENV['LOG_PATH'] ?? (ROOT_PATH . '/logs/'));

// הגדרות URLs - חשוב מאוד!
define('BASE_URL', SITE_URL);
define('AUTH_URL', BASE_URL . '/auth');
define('ADMIN_URL', BASE_URL . '/admin');
define('LOGIN_URL', AUTH_URL . '/login.php');
define('LOGOUT_URL', AUTH_URL . '/logout.php');
define('REGISTER_URL', AUTH_URL . '/register.php');

// הגדרות דשבורדים - מתוקן לכלול את תיקיית includes!
define('DASHBOARD_URL', BASE_URL . '/includes/dashboard.php');
define('DASHBOARD_FULL_URL', DASHBOARD_URL); 
define('CEMETERIES_DASHBOARD_URL', BASE_URL . '/includes/dashboard_cemeteries.php'); // או השם הנכון
define('ADMIN_DASHBOARD_URL', BASE_URL . '/includes/dashboard_admin.php'); // או השם הנכון

// אם יש לך דשבורדים נוספים בתיקיית includes:
define('DASHBOARD_DECEASED_URL', BASE_URL . '/includes/dashboard_deceased.php');
define('DASHBOARD_PURCHASES_URL', BASE_URL . '/includes/dashboard_purchases.php');
define('DASHBOARD_VIEW_ONLY_URL', BASE_URL . '/includes/dashboard_view_only.php');

// אם יש רשימות בתיקיית includes/lists:
define('DECEASED_LIST_URL', BASE_URL . '/includes/lists/deceased_list.php');
define('PURCHASE_LIST_URL', BASE_URL . '/includes/lists/purchase_list.php');

// ואם יש טפסים בתיקיית form:
define('FORM_URL', BASE_URL . '/form/index_deceased.php');
define('FORM_DECEASED_URL', BASE_URL . '/form/index_deceased.php');
define('FORM_PURCHASE_URL', BASE_URL . '/form/index_purchase.php');



// הגדרות Google Auth (אם בשימוש)
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
            // בסביבת פיתוח - הצג שגיאה מפורטת
            if (($_ENV['APP_ENV'] ?? 'development') !== 'production') {
                die("
                    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
                        <h2>שגיאת חיבור למסד נתונים</h2>
                        <p style='color: red;'>" . $e->getMessage() . "</p>
                    </div>
                ");
            } else {
                // בסביבת ייצור - הצג הודעה כללית
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
    }
    return $_SESSION['csrf_token'];
}

// בדיקת CSRF token
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// קבלת URL של דשבורד לפי הרשאות
function getUserDashboardUrl2($userId, $permissionLevel) {
    // ברירת מחדל
    $dashboard = DASHBOARD_FULL_URL;
    
    // לפי רמת הרשאה
    switch ($permissionLevel) {
        case 4: // מנהל ראשי
        case 3: // מנהל
            $dashboard = ADMIN_DASHBOARD_URL;
            break;
        case 2: // עורך
            $dashboard = CEMETERIES_DASHBOARD_URL;
            break;
        case 1: // צופה
        default:
            $dashboard = DASHBOARD_FULL_URL;
            break;
    }
    
    return $dashboard;
}

// קבלת רשימת דשבורדים מותרים למשתמש
function getUserAllowedDashboards2($userId) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT permission 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    $dashboards = [];
    
    if ($user) {
        // תמיד יש גישה לדשבורד הראשי
        $dashboards[] = ['name' => 'דשבורד ראשי', 'url' => DASHBOARD_FULL_URL];
        
        // לפי רמת הרשאה - שימוש ב-permission במקום permission_level
        if ($user['permission'] >= 2) {
            $dashboards[] = ['name' => 'דשבורד בתי עלמין', 'url' => CEMETERIES_DASHBOARD_URL];
        }
        
        if ($user['permission'] >= 3) {
            $dashboards[] = ['name' => 'דשבורד ניהול', 'url' => ADMIN_DASHBOARD_URL];
        }
    }
    
    return $dashboards;
}

// 6767676767676

// קבלת רשימת דשבורדים מותרים למשתמש
function getUserAllowedDashboards($userId) {
    // כיוון שאין עמודת permission בטבלת users
    // נניח שיש טבלה נפרדת להרשאות או שכולם מקבלים גישה בסיסית
    
    $dashboards = [];
    
    // ברירת מחדל - כולם יכולים לגשת לדשבורד הראשי
    $dashboards[] = ['name' => 'דשבורד ראשי', 'url' => DASHBOARD_FULL_URL];
    
    // בדוק אם יש טבלת הרשאות
    try {
        $db = getDbConnection();
        
        // נסה לבדוק אם יש טבלת user_permissions או משהו דומה
        $stmt = $db->prepare("
            SELECT permission_level 
            FROM user_permissions 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $perm = $stmt->fetch();
        
        if ($perm && $perm['permission_level'] >= 2) {
            $dashboards[] = ['name' => 'דשבורד בתי עלמין', 'url' => CEMETERIES_DASHBOARD_URL];
        }
        
        if ($perm && $perm['permission_level'] >= 3) {
            $dashboards[] = ['name' => 'דשבורד ניהול', 'url' => ADMIN_DASHBOARD_URL];
        }
        
    } catch (Exception $e) {
        // אם אין טבלת הרשאות, תן גישה בסיסית בלבד
        error_log("No permissions table found: " . $e->getMessage());
    }
    
    // הוסף דשבורדים נוספים אם קיימים
    if (defined('DASHBOARD_DECEASED_URL')) {
        $dashboards[] = ['name' => 'דשבורד נפטרים', 'url' => DASHBOARD_DECEASED_URL];
    }
    
    if (defined('DASHBOARD_PURCHASES_URL')) {
        $dashboards[] = ['name' => 'דשבורד רכישות', 'url' => DASHBOARD_PURCHASES_URL];
    }
    
    return $dashboards;
}

// קבלת URL של דשבורד לפי הרשאות
function getUserDashboardUrl($userId, $permissionLevel = null) {
    // אם לא נשלחה רמת הרשאה, נסה לקבל אותה
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
            $permissionLevel = 1; // ברירת מחדל
        }
    }
    
    // החזר דשבורד לפי רמת הרשאה
    switch ($permissionLevel) {
        case 4: // מנהל ראשי
        case 3: // מנהל
            return ADMIN_DASHBOARD_URL;
        case 2: // עורך
            return CEMETERIES_DASHBOARD_URL;
        case 1: // צופה
        default:
            return DASHBOARD_FULL_URL;
    }
}

// 6767676767676

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
        @mkdir($dir, 0777, true);
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