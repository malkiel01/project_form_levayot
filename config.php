<?php
/**
 * config.php - קובץ הגדרות ראשי למערכת ניהול בית עלמין
 * 
 * קובץ זה טוען הגדרות מקובץ .env ומגדיר קבועים וחיבורים למערכת
 */

// מנע גישה ישירה
if (!defined('CEMETERY_SYSTEM')) {
    define('CEMETERY_SYSTEM', true);
}

// הגדרת דיווח על שגיאות בהתאם לסביבה
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// הגדרת אזור זמן
date_default_timezone_set('Asia/Jerusalem');

/**
 * טעינת קובץ ENV
 */
class EnvLoader {
    private static $loaded = false;
    private static $envPath;
    
    public static function load($path = null) {
        if (self::$loaded) {
            return true;
        }
        
        self::$envPath = $path ?: __DIR__ . '/.env';
        
        if (!file_exists(self::$envPath)) {
            // נסה לחפש בתיקיית האב
            self::$envPath = dirname(__DIR__) . '/.env';
            if (!file_exists(self::$envPath)) {
                throw new Exception("קובץ .env לא נמצא. האם העתקת את env-example.txt ל-.env?");
            }
        }
        
        if (!is_readable(self::$envPath)) {
            throw new Exception("אין הרשאות קריאה לקובץ .env");
        }
        
        $lines = file(self::$envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // דלג על הערות וקווים ריקים
            if (empty($line) || strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // חלק את השורה למפתח וערך
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // הסר גרשיים אם יש
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // החלף משתנים אם יש
                if (strpos($value, '${') !== false) {
                    $value = self::parseVariables($value);
                }
                
                // הגדר משתנה סביבה
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
        return true;
    }
    
    private static function parseVariables($value) {
        return preg_replace_callback('/\${([^}]+)}/', function($matches) {
            return getenv($matches[1]) ?: $matches[0];
        }, $value);
    }
}

// טען את קובץ ENV
try {
    EnvLoader::load();
} catch (Exception $e) {
    die("שגיאה קריטית: " . $e->getMessage());
}

/**
 * פונקציה לקבלת ערך מ-ENV עם ברירת מחדל
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    // המר ערכים בוליאניים
    if (strtolower($value) === 'true') return true;
    if (strtolower($value) === 'false') return false;
    if (strtolower($value) === 'null') return null;
    
    return $value;
}

/**
 * הגדרת קבועים למסד נתונים
 */
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// בדיקה שיש את כל הפרטים הנדרשים
if (!DB_NAME || !DB_USER) {
    die("שגיאה: חסרים פרטי חיבור למסד נתונים. בדוק את קובץ .env");
}

/**
 * מחלקת חיבור למסד נתונים
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // בסביבת פיתוח - הצג את השגיאה המלאה
            if (env('APP_DEBUG', false)) {
                die("שגיאת חיבור למסד נתונים: " . $e->getMessage());
            } else {
                // בסביבת ייצור - הצג הודעה כללית ותעד את השגיאה
                error_log("Database connection error: " . $e->getMessage());
                die("שגיאה בחיבור למערכת. אנא פנה למנהל המערכת.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

/**
 * פונקציה גלובלית לקבלת חיבור למסד נתונים
 */
function getDbConnection() {
    return Database::getInstance()->getConnection();
}

/**
 * הגדרות אתר
 */
define('SITE_URL', rtrim(env('SITE_URL', 'http://localhost'), '/'));
define('SITE_NAME', env('SITE_NAME', 'מערכת ניהול נפטרים'));
define('SITE_EMAIL', env('SITE_EMAIL', 'info@example.com'));
define('UPLOAD_PATH', env('UPLOAD_PATH', __DIR__ . '/uploads/'));
define('TIMEZONE', env('TIMEZONE', 'Asia/Jerusalem'));

/**
 * הגדרות אבטחה
 */
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', 'default-key-change-this'));
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 3600));
define('CSRF_TOKEN_LIFETIME', (int)env('CSRF_TOKEN_LIFETIME', 3600));

/**
 * הגדרות נתיבים
 */
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('AJAX_PATH', ROOT_PATH . '/ajax/');
define('ADMIN_PATH', ROOT_PATH . '/admin/');
define('LOGS_PATH', env('LOG_PATH', ROOT_PATH . '/logs/'));

/**
 * הגדרות URLs
 */
define('BASE_URL', SITE_URL);
define('LOGIN_URL', BASE_URL . '/login.php');
define('DASHBOARD_URL', BASE_URL . '/dashboard.php');
define('ADMIN_URL', BASE_URL . '/admin/');

/**
 * התחלת SESSION מאובטחת
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // הגדרות אבטחה ל-session
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        
        // אם האתר רץ על HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        
        // הגדר שם ייחודי ל-session
        session_name('CEMETERY_SESSION');
        
        // הגדר זמן חיים ל-cookie
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
        
        // חדש את session ID באופן תקופתי
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // כל 30 דקות
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// התחל session
startSecureSession();

/**
 * טען קבצים נדרשים אם הם קיימים
 */
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

/**
 * הגדר handler לשגיאות
 */
if (env('APP_ENV') !== 'production') {
    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    
    set_exception_handler(function($exception) {
        echo "<div style='border: 2px solid red; padding: 10px; margin: 10px; background: #ffe0e0;'>";
        echo "<h3>שגיאה במערכת</h3>";
        echo "<p><strong>הודעה:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>קובץ:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>שורה:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    });
}

// בדיקת חיבור למסד נתונים בעת טעינת הקובץ (אופציונלי)
if (env('CHECK_DB_ON_LOAD', false)) {
    try {
        $db = getDbConnection();
        $db->query("SELECT 1");
    } catch (Exception $e) {
        error_log("Database check failed: " . $e->getMessage());
    }
}
?>