<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // אם משתמש ב-Composer

header('Content-Type: application/json');

// קבלת הנתונים
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['credential'])) {
    echo json_encode(['success' => false, 'message' => 'חסר אסימון אימות']);
    exit;
}

// אימות הטוקן של Google
$client = new Google_Client(['client_id' => '453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com']);
$payload = $client->verifyIdToken($data['credential']);

if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'אסימון לא תקף']);
    exit;
}

// קבלת פרטי המשתמש מ-Google
$googleId = $payload['sub'];
$email = $payload['email'];
$name = $payload['name'];
$picture = $payload['picture'] ?? '';
$emailVerified = $payload['email_verified'];

if (!$emailVerified) {
    echo json_encode(['success' => false, 'message' => 'האימייל לא אומת על ידי Google']);
    exit;
}

try {
    $db = getDbConnection();
    
    // בדיקה אם המשתמש קיים
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
    $stmt->execute([$email, $googleId]);
    $user = $stmt->fetch();
    
    if ($data['action'] === 'register') {
        // רישום משתמש חדש
        if ($user) {
            echo json_encode(['success' => false, 'message' => 'משתמש עם אימייל זה כבר קיים במערכת']);
            exit;
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
        
        $insertStmt->execute([
            $username, $email, $name, $googleId, $picture
        ]);
        
        $userId = $db->lastInsertId();
        
        // רישום בלוג
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
        
        // הודעה למנהלים
        notifyAdminsAboutNewUser($userId, $username, $email, $name, 'Google');
        
        echo json_encode([
            'success' => true, 
            'message' => 'הרישום הושלם בהצלחה! החשבון שלך ממתין לאישור מנהל.'
        ]);
        
    } else {
        // התחברות
        if (!$user) {
            echo json_encode([
                'success' => false, 
                'message' => 'לא נמצא חשבון עם אימייל זה. אנא הרשם תחילה.'
            ]);
            exit;
        }
        
        // בדיקת סטטוס החשבון
        if (!$user['is_active']) {
            echo json_encode([
                'success' => false, 
                'message' => 'החשבון שלך ממתין לאישור מנהל'
            ]);
            exit;
        }
        
        // עדכון פרטי Google אם צריך
        if (!$user['google_id']) {
            $updateStmt = $db->prepare("
                UPDATE users 
                SET google_id = ?, profile_picture = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$googleId, $picture, $user['id']]);
        }
        
        // עדכון זמן כניסה אחרון
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
           ->execute([$user['id']]);
        
        // הגדרת סשן
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['permission_level'] = $user['permission_level'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // רישום בלוג
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
        
        // קביעת כתובת להפניה
        $redirect = $data['redirect'] ?? DASHBOARD_URL;
        
        echo json_encode([
            'success' => true,
            'redirect' => $redirect
        ]);
    }
    
} catch (Exception $e) {
    error_log("Google auth error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'שגיאה במערכת. נסה שוב מאוחר יותר.'
    ]);
}

// פונקציה ליצירת שם משתמש ייחודי
function generateUniqueUsername($email, $db) {
    $baseUsername = explode('@', $email)[0];
    $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', $baseUsername);
    
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if (!$stmt->fetch()) {
            return $username;
        }
        
        $username = $baseUsername . $counter;
        $counter++;
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