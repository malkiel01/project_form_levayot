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
    $CLIENT_ID = GOOGLE_CLIENT_ID;
    
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
    
    // בדיקה אם המשתמש קיים לפי email או google_id
    $stmt = $db->prepare("
        SELECT id, username, email, name, google_id, profile_picture, is_active
        FROM users 
        WHERE email = ? OR google_id = ?
    ");
    $stmt->execute([$email, $google_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // משתמש קיים
        
        // בדיקה אם המשתמש פעיל
        if (!$user['is_active']) {
            throw new Exception('החשבון לא פעיל. פנה למנהל המערכת.');
        }
        
        // עדכון google_id ותמונה אם צריך
        $updates = [];
        $params = [];
        
        if (!$user['google_id']) {
            $updates[] = "google_id = ?";
            $params[] = $google_id;
        }
        
        if ($picture && $picture !== $user['profile_picture']) {
            $updates[] = "profile_picture = ?";
            $params[] = $picture;
        }
        
        $updates[] = "last_login = NOW()";
        $updates[] = "auth_type = 'google'";
        
        if (!empty($updates)) {
            $params[] = $user['id'];
            $updateStmt = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $updateStmt->execute($params);
        }
        
        error_log("User {$user['username']} logged in via Google");
        
    } else {
        // משתמש חדש - יצירת חשבון
        
        // יצירת username ייחודי מה-email
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;
        
        // בדיקה אם ה-username תפוס
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        
        while ($checkStmt->fetch()) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
            $checkStmt->execute([$username]);
        }
        
        // הוספת המשתמש החדש
        $stmt = $db->prepare("
            INSERT INTO users (
                username, 
                email,
                google_id,
                name,
                profile_picture,
                auth_type,
                is_active,
                created_at,
                last_login
            ) VALUES (?, ?, ?, ?, ?, 'google', 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            $username,
            $email,
            $google_id,
            $name,
            $picture
        ]);
        
        $user = [
            'id' => $db->lastInsertId(),
            'username' => $username,
            'email' => $email,
            'name' => $name,
            'google_id' => $google_id,
            'profile_picture' => $picture
        ];
        
        error_log("New user created via Google: $username");
    }
    
    // כעת צריך לבדוק מה רמת ההרשאה של המשתמש
    // כנראה יש טבלה נפרדת להרשאות או ערך ברירת מחדל
    $permission_level = 1; // ברירת מחדל
    
    // נסה לחפש בטבלת הרשאות אם קיימת
    try {
        $permStmt = $db->prepare("SELECT permission_level FROM user_permissions WHERE user_id = ?");
        $permStmt->execute([$user['id']]);
        $perm = $permStmt->fetch();
        if ($perm) {
            $permission_level = $perm['permission_level'];
        }
    } catch (Exception $e) {
        // אם אין טבלת הרשאות, השתמש בברירת מחדל
        error_log("No permissions table found, using default");
    }
    
    // הגדרת סשן
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['permission_level'] = $permission_level;
    $_SESSION['login_time'] = time();
    $_SESSION['login_method'] = 'google';
    $_SESSION['user_picture'] = $user['profile_picture'] ?? $picture;
    $_SESSION['google_id'] = $user['google_id'];

    // קבלת הדשבורד המתאים למשתמש
    $userDashboard = getUserDashboardUrl($user['id'], $permission_level);

    // בדיקה אם יש redirect ספציפי
    if ($redirect && $redirect !== DASHBOARD_FULL_URL) {
        $allowedDashboards = getUserAllowedDashboards($user['id']);
        $canAccessRedirect = false;
        
        foreach ($allowedDashboards as $dashboard) {
            if (strpos($redirect, basename($dashboard['url'])) !== false) {
                $canAccessRedirect = true;
                break;
            }
        }
        
        if (!$canAccessRedirect) {
            $redirect = $userDashboard;
        }
    } else {
        $redirect = $userDashboard;
    }
    
    // רישום בלוג
    if (function_exists('logActivity')) {
        logActivity('login_success', [
            'method' => 'google',
            'email' => $email,
            'redirect' => $redirect
        ]);
    }

    alert($redirect);

    // החזרת תגובה
    echo json_encode([
        'success' => true,
        'redirect' => $redirect,
        'message' => 'התחברת בהצלחה',
        'user' => [
            'name' => $user['name'],
            'email' => $user['email'],
            'picture' => $user['profile_picture'] ?? $picture
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Google Auth Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה בהתחברות עם Google: ' . $e->getMessage()
    ]);
}
?>