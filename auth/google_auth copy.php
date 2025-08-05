<?php
// auth/google_auth.php - טיפול באימות Google

require_once '../config.php';

// הגדר כותרות JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// בדיקה שזו בקשת POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// קבלת הנתונים
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['credential'])) {
    echo json_encode(['success' => false, 'message' => 'Missing credential']);
    exit;
}

try {
    // Google Client ID
    $CLIENT_ID = '453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com';
    
    // אימות הטוקן מול Google
    $id_token = $data['credential'];
    $redirect = $data['redirect'] ?? DASHBOARD_FULL_URL;
    
    // URL לאימות
    $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token;
    
    // שליחת בקשה לאימות
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to verify token');
    }
    
    $payload = json_decode($response, true);
    
    // וידוא שהטוקן תקין
    if (!$payload || $payload['aud'] !== $CLIENT_ID) {
        throw new Exception('Invalid token');
    }
    
    // קבלת פרטי המשתמש
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? '';
    $google_id = $payload['sub'] ?? '';
    $picture = $payload['picture'] ?? '';
    
    if (empty($email)) {
        throw new Exception('Email not provided');
    }
    
    // חיבור למסד הנתונים
    $db = getDbConnection();
    
    // בדיקה אם המשתמש קיים
    $stmt = $db->prepare("
        SELECT id, username, full_name, permission_level, is_active, locked_until
        FROM users 
        WHERE email = ? OR google_id = ?
    ");
    $stmt->execute([$email, $google_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // משתמש קיים - בדיקת סטטוס
        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'message' => 'החשבון לא פעיל']);
            exit;
        }
        
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            echo json_encode(['success' => false, 'message' => 'החשבון נעול זמנית']);
            exit;
        }
        
        // עדכון פרטי התחברות
        $stmt = $db->prepare("
            UPDATE users 
            SET google_id = ?, 
                last_login = NOW(),
                failed_login_attempts = 0,
                locked_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$google_id, $user['id']]);
        
    } else {
        // משתמש חדש - יצירת חשבון
        $username = explode('@', $email)[0] . '_' . rand(100, 999);
        
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, google_id, full_name, 
                permission_level, is_active, created_at, password
            ) VALUES (?, ?, ?, ?, 1, 1, NOW(), ?)
        ");
        
        // סיסמה רנדומלית (המשתמש ישתמש ב-Google)
        $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt->execute([
            $username,
            $email,
            $google_id,
            $name,
            $random_password
        ]);
        
        $user = [
            'id' => $db->lastInsertId(),
            'username' => $username,
            'full_name' => $name,
            'permission_level' => 1
        ];
    }
    
    // הגדרת סשן
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'] ?? $name;
    $_SESSION['permission_level'] = $user['permission_level'];
    $_SESSION['login_time'] = time();
    $_SESSION['login_method'] = 'google';
    $_SESSION['user_picture'] = $picture;
    
    // רישום בלוג
    logActivity('login_success', [
        'method' => 'google',
        'email' => $email,
        'redirect' => $redirect
    ]);
    
    // החזרת תגובה
    echo json_encode([
        'success' => true,
        'redirect' => $redirect,
        'message' => 'התחברת בהצלחה'
    ]);
    
} catch (Exception $e) {
    error_log('Google Auth Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה בהתחברות עם Google: ' . $e->getMessage()
    ]);
}