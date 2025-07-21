<?php
// login.php - דף התחברות משופר למערכת

require_once 'config.php';

// אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttemptTime = $_SESSION['last_attempt_time'] ?? 0;

// בדיקת נעילה עקב ניסיונות כושלים
$lockoutTime = 300; // 5 דקות
$maxAttempts = 5;
$isLockedOut = false;

if ($loginAttempts >= $maxAttempts && (time() - $lastAttemptTime) < $lockoutTime) {
    $remainingTime = $lockoutTime - (time() - $lastAttemptTime);
    $isLockedOut = true;
    $error = "החשבון נעול עקב ניסיונות כושלים רבים. נסה שוב בעוד " . ceil($remainingTime / 60) . " דקות.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'יש למלא שם משתמש וסיסמה';
    } else {
        $db = getDbConnection();
        
        // שליפת פרטי המשתמש
        $stmt = $db->prepare("
            SELECT u.*, p.name as permission_name 
            FROM users u 
            JOIN permissions p ON u.permission_level = p.permission_level 
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // התחברות מוצלחת
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['permission_level'] = $user['permission_level'];
            $_SESSION['permission_name'] = $user['permission_name'];
            
            // איפוס ניסיונות כושלים
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt_time']);
            
            // עדכון זמן כניסה אחרון
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // רישום בלוג
            $logStmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'login', ?, ?, ?)
            ");
            $logStmt->execute([
                $user['id'],
                json_encode(['success' => true]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // זכור אותי - הגדרת cookie לשבוע
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (7 * 24 * 60 * 60), '/', '', true, true);
                // כאן תוכל לשמור את הטוקן בדטהבייס לאימות עתידי
            }
            
            // הפניה לדף הבית
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
            
        } else {
            // כישלון בהתחברות
            $loginAttempts++;
            $_SESSION['login_attempts'] = $loginAttempts;
            $_SESSION['last_attempt_time'] = time();
            
            if ($loginAttempts >= $maxAttempts) {
                $error = "החשבון נעול עקב ניסיונות כושלים רבים. נסה שוב בעוד 5 דקות.";
            } else {
                $remainingAttempts = $maxAttempts - $loginAttempts;
                $error = "שם משתמש או סיסמה שגויים. נותרו $remainingAttempts ניסיונות.";
            }
            
            // רישום ניסיון כושל בלוג
            if ($user) {
                $logStmt = $db->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                    VALUES (?, 'login_failed', ?, ?, ?)
                ");
                $logStmt->execute([
                    $user['id'],
                    json_encode(['username' => $username, 'attempts' => $loginAttempts]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }