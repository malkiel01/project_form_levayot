<?php
// מניעת הצגת שגיאות PHP (חשוב מאוד!)
ini_set('display_errors', 0);
error_reporting(0);

// אם יש שגיאה, נשמור אותה בלוג ולא נציג אותה
function handleError($message, $debugInfo = null) {
    error_log("Google Auth Error: " . $message . ($debugInfo ? " | Debug: " . json_encode($debugInfo) : ""));
    http_response_code(200); // חשוב לא לשלוח קוד שגיאה HTTP
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    require_once '../config.php';
    
    // ודא שיש לנו Content-Type נכון מההתחלה
    header('Content-Type: application/json');
    
    // קריאה ל־input RAW
    $rawInput = file_get_contents('php://input');
    
    // ולידציה בסיסית של הקלט
    if (empty($rawInput)) {
        handleError('לא התקבל מידע מGoogle');
    }
    
    // לוג לדיבוג (אופציונלי)
    error_log("Google Auth Raw Input: " . $rawInput);
    
    // נסה לפענח JSON
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        handleError('פורמט מידע לא תקין מGoogle', ['json_error' => json_last_error_msg()]);
    }
    
    if (!isset($data['credential']) || empty($data['credential'])) {
        handleError('חסר אסימון אימות מGoogle');
    }
    
    // בדיקה אם יש לנו את ה-Google Client
    if (!class_exists('Google_Client')) {
        handleError('שירות Google לא זמין במערכת');
    }
    
    // אימות הטוקן של Google
    $client = new Google_Client(['client_id' => '453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com']);
    
    try {
        $payload = $client->verifyIdToken($data['credential']);
    } catch (Exception $e) {
        handleError('שגיאה באימות טוקן Google', ['error' => $e->getMessage()]);
    }
    
    if (!$payload) {
        handleError('אסימון Google לא תקף');
    }
    
    // קבלת פרטי המשתמש מ-Google
    $googleId = $payload['sub'] ?? '';
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? '';
    $picture = $payload['picture'] ?? '';
    $emailVerified = $payload['email_verified'] ?? false;
    
    if (!$emailVerified) {
        handleError('האימייל לא אומת על ידי Google');
    }
    
    if (empty($email) || empty($googleId)) {
        handleError('חסרים פרטים חיוניים מGoogle');
    }
    
    try {
        $db = getDbConnection();
    } catch (Exception $e) {
        handleError('שגיאה בחיבור לבסיס הנתונים', ['db_error' => $e->getMessage()]);
    }
    
    // בדיקה אם המשתמש קיים
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
    $stmt->execute([$email, $googleId]);
    $user = $stmt->fetch();
    
    $action = $data['action'] ?? 'login'; // ברירת מחדל התחברות
    
    if ($action === 'register') {
        // רישום משתמש חדש
        if ($user) {
            handleError('משתמש עם אימייל זה כבר קיים במערכת');
        }
        
        // יצירת שם משתמש ייחודי
        $username = generateUniqueUsername($email, $db);
        
        // יצירת משתמש חדש
        $insertStmt = $db->prepare("
            INSERT INTO users (
                username, email, full_name, google_id, 
                profile_picture, permission_level, is_active, 
                email_verified, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, 0, 1, NOW())
        ");
        
        $result = $insertStmt->execute([
            $username, $email, $name, $googleId, $picture
        ]);
        
        if (!$result) {
            handleError('שגיאה ביצירת החשבון');
        }
        
        $userId = $db->lastInsertId();
        
        // רישום בלוג
        try {
            $logStmt = $db->prepare("
                INSERT INTO activity_log 
                (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'user_registered', ?, ?, ?)
            ");
            
            $logStmt->execute([
                $userId,
                json_encode([
                    'registration_type' => 'google',
                    'google_id' => $googleId
                ]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log registration: " . $e->getMessage());
        }
        
        // הודעה למנהלים (אופציונלי)
        try {
            notifyAdminsAboutNewUser($userId, $username, $email, $name, 'Google');
        } catch (Exception $e) {
            error_log("Failed to notify admins: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'הרישום הושלם בהצלחה! החשבון שלך ממתין לאישור מנהל.'
        ]);
        
    } else {
        // התחברות
        if (!$user) {
            handleError('לא נמצא חשבון עם אימייל זה. אנא הרשם תחילה.');
        }
        
        // בדיקת סטטוס החשבון
        if (!$user['is_active']) {
            handleError('החשבון שלך ממתין לאישור מנהל');
        }
        
        // עדכון פרטי Google אם צריך
        if (!$user['google_id']) {
            try {
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET google_id = ?, profile_picture = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$googleId, $picture, $user['id']]);
            } catch (Exception $e) {
                error_log("Failed to update Google info: " . $e->getMessage());
            }
        }
        
        // עדכון זמן כניסה אחרון
        try {
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
               ->execute([$user['id']]);
        } catch (Exception $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
        
        // הגדרת סשן
        session_regenerate_id(true); // אבטחה נוספת
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['permission_level'] = $user['permission_level'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // רישום בלוג
        try {
            $logStmt = $db->prepare("
                INSERT INTO activity_log 
                (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'login_success', ?, ?, ?)
            ");
            
            $logStmt->execute([
                $user['id'],
                json_encode(['method' => 'google']),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log login: " . $e->getMessage());
        }
        
        // קביעת כתובת להפניה
        $redirect = $data['redirect'] ?? DASHBOARD_URL;
        
        // ולידציה בסיסית של ה-redirect
        if (!filter_var($redirect, FILTER_VALIDATE_URL)) {
            $redirect = DASHBOARD_URL;
        }
        
        echo json_encode([
            'success' => true,
            'redirect' => $redirect,
            'message' => 'התחברות הצליחה!'
        ]);
    }
    
} catch (Exception $e) {
    handleError('שגיאה במערכת. נסה שוב מאוחר יותר.', ['exception' => $e->getMessage()]);
}

// פונקציה ליצירת שם משתמש ייחודי
function generateUniqueUsername($email, $db) {
    $baseUsername = explode('@', $email)[0];
    $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $baseUsername);
    
    if (empty($baseUsername)) {
        $baseUsername = 'user';
    }
    
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                return $username;
            }
            
            $username = $baseUsername . $counter;
            $counter++;
            
            // מניעת לולאה אינסופית
            if ($counter > 1000) {
                return $baseUsername . '_' . time();
            }
        } catch (Exception $e) {
            error_log("Error checking username uniqueness: " . $e->getMessage());
            return $baseUsername . '_' . time();
        }
    }
}

// פונקציה לשליחת הודעה למנהלים
function notifyAdminsAboutNewUser($userId, $username, $email, $fullName, $method = 'manual') {
    try {
        $db = getDbConnection();
        
        // קבלת כל המנהלים
        $stmt = $db->prepare("
            SELECT email, full_name 
            FROM users 
            WHERE permission_level = 4 AND is_active = 1
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        // הכנת הודעה
        $subject = "משתמש חדש ממתין לאישור - $fullName";
        $message = "
            <h3>משתמש חדש נרשם למערכת</h3>
            <p><strong>שם:</strong> $fullName</p>
            <p><strong>שם משתמש:</strong> $username</p>
            <p><strong>אימייל:</strong> $email</p>
            <p><strong>שיטת רישום:</strong> $method</p>
            <p><a href='" . SITE_URL . "/admin/users.php?action=approve&id=$userId'>אשר משתמש</a></p>
        ";
        
        // שליחת מייל לכל מנהל
        foreach ($admins as $admin) {
            // כאן תוסיף את פונקציית שליחת המייל שלך
            // mail($admin['email'], $subject, $message, $headers);
        }
        
    } catch (Exception $e) {
        error_log("Error notifying admins: " . $e->getMessage());
    }
}
?>