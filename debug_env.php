<?php
/**
 * debug_env.php - בדיקת קובץ ENV וחיבור למסד נתונים
 * 
 * הרץ קובץ זה כדי לבדוק:
 * 1. האם קובץ .env נמצא ונקרא כהלכה
 * 2. האם המשתנים נטענים בצורה תקינה
 * 3. האם החיבור למסד הנתונים עובד
 */

// הגדרת דיווח על שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// הגדרת נתיב לקובץ ENV
$envPath = __DIR__ . '/.env';

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת ENV וחיבור למסד נתונים</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>בדיקת ENV וחיבור למסד נתונים</h1>
        
        <h2>1. בדיקת קובץ .env</h2>
        <?php
        if (file_exists($envPath)) {
            echo '<div class="success">✓ קובץ .env נמצא בנתיב: ' . $envPath . '</div>';
            
            // בדיקת הרשאות קריאה
            if (is_readable($envPath)) {
                echo '<div class="success">✓ קובץ .env ניתן לקריאה</div>';
            } else {
                echo '<div class="error">✗ קובץ .env לא ניתן לקריאה - בדוק הרשאות</div>';
            }
            
            // בדיקת גודל הקובץ
            $fileSize = filesize($envPath);
            echo '<div class="info">גודל הקובץ: ' . $fileSize . ' bytes</div>';
            
        } else {
            echo '<div class="error">✗ קובץ .env לא נמצא בנתיב: ' . $envPath . '</div>';
            echo '<div class="warning">האם העתקת את env-example.txt ל-.env?</div>';
        }
        ?>
        
        <h2>2. טעינת משתני ENV</h2>
        <?php
        // פונקציה לטעינת ENV
        function loadEnv($path) {
            if (!file_exists($path)) {
                return false;
            }
            
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $envVars = [];
            
            foreach ($lines as $line) {
                // דלג על הערות
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // חלק את השורה למפתח וערך
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // הסר גרשיים אם יש
                    $value = trim($value, '"\'');
                    
                    $envVars[$key] = $value;
                    
                    // הגדר כמשתנה סביבה
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
            
            return $envVars;
        }
        
        if (file_exists($envPath)) {
            $envVars = loadEnv($envPath);
            
            if ($envVars) {
                echo '<div class="success">✓ משתני ENV נטענו בהצלחה</div>';
                echo '<h3>משתנים שנמצאו:</h3>';
                echo '<table>';
                echo '<tr><th>משתנה</th><th>ערך</th></tr>';
                
                $dbVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
                foreach ($dbVars as $var) {
                    $value = isset($envVars[$var]) ? $envVars[$var] : 'לא נמצא';
                    $displayValue = $var === 'DB_PASS' ? str_repeat('*', strlen($value)) : $value;
                    echo '<tr>';
                    echo '<td>' . $var . '</td>';
                    echo '<td>' . htmlspecialchars($displayValue) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="error">✗ לא הצלחתי לטעון משתנים מקובץ ENV</div>';
            }
        }
        ?>
        
        <h2>3. בדיקת חיבור למסד נתונים</h2>
        <?php
        if (isset($envVars) && $envVars) {
            $host = $envVars['DB_HOST'] ?? 'localhost';
            $dbname = $envVars['DB_NAME'] ?? '';
            $username = $envVars['DB_USER'] ?? '';
            $password = $envVars['DB_PASS'] ?? '';
            $charset = $envVars['DB_CHARSET'] ?? 'utf8mb4';
            
            if (empty($dbname) || empty($username)) {
                echo '<div class="error">✗ חסרים פרטי חיבור: DB_NAME או DB_USER</div>';
            } else {
                try {
                    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset"
                    ];
                    
                    echo '<div class="info">מנסה להתחבר ל: ' . $dsn . '</div>';
                    
                    $pdo = new PDO($dsn, $username, $password, $options);
                    
                    echo '<div class="success">✓ החיבור למסד הנתונים הצליח!</div>';
                    
                    // בדיקת גרסת MySQL
                    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                    echo '<div class="info">גרסת MySQL: ' . $version . '</div>';
                    
                    // בדיקת charset
                    $charset = $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch();
                    echo '<div class="info">Charset: ' . $charset['Value'] . '</div>';
                    
                    // בדיקת טבלאות
                    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                    echo '<div class="info">מספר טבלאות במסד הנתונים: ' . count($tables) . '</div>';
                    
                } catch (PDOException $e) {
                    echo '<div class="error">✗ שגיאה בחיבור למסד הנתונים:</div>';
                    echo '<div class="error">' . $e->getMessage() . '</div>';
                    
                    // הצעות לפתרון
                    echo '<h3>הצעות לפתרון:</h3>';
                    if (strpos($e->getMessage(), 'Access denied') !== false) {
                        echo '<div class="warning">• בדוק שם משתמש וסיסמה</div>';
                    }
                    if (strpos($e->getMessage(), 'Unknown database') !== false) {
                        echo '<div class="warning">• בדוק ששם מסד הנתונים נכון</div>';
                        echo '<div class="warning">• האם יצרת את מסד הנתונים?</div>';
                    }
                    if (strpos($e->getMessage(), 'Connection refused') !== false) {
                        echo '<div class="warning">• בדוק ששרת MySQL פועל</div>';
                        echo '<div class="warning">• בדוק כתובת השרת (host)</div>';
                    }
                }
            }
        }
        ?>
        
        <h2>4. בדיקת הגדרות PHP</h2>
        <?php
        $requiredExtensions = ['pdo', 'pdo_mysql', 'session'];
        echo '<table>';
        echo '<tr><th>הרחבה</th><th>סטטוס</th></tr>';
        
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            echo '<tr>';
            echo '<td>' . $ext . '</td>';
            echo '<td>' . ($loaded ? '<span style="color: green">✓ מותקן</span>' : '<span style="color: red">✗ חסר</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        ?>
        
        <h2>5. דוגמה לקובץ config.php מתוקן</h2>
        <div class="info">
            <p>השתמש בקוד הבא בקובץ config.php שלך:</p>
        </div>
        <pre><code>&lt;?php
// config.php - קובץ הגדרות ראשי

// טעינת קובץ ENV
function loadEnvFile($path) {
    if (!file_exists($path)) {
        throw new Exception("קובץ .env לא נמצא");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// טען את קובץ ENV
try {
    loadEnvFile(__DIR__ . '/.env');
} catch (Exception $e) {
    die("שגיאה בטעינת הגדרות: " . $e->getMessage());
}

// הגדרות מסד נתונים מ-ENV
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// פונקציה לקבלת חיבור למסד נתונים
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("שגיאת חיבור למסד נתונים: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// הגדרות נוספות מ-ENV
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');
define('SITE_NAME', getenv('SITE_NAME') ?: 'מערכת ניהול נפטרים');
// ... הגדרות נוספות

// התחל session
session_start();
?&gt;</code></pre>
    </div>
</body>
</html>