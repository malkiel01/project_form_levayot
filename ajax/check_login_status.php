<?php
// ajax/check_login_status.php - בדיקת סטטוס התחברות של המשתמש
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// אל תדרוש התחברות - זה בדיוק מה שאנחנו בודקים

try {
    $response = [
        'logged_in' => false,
        'username' => null,
        'user_id' => null,
        'permission_level' => null,
        'timestamp' => time()
    ];

    // בדוק אם המשתמש מחובר
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $response['logged_in'] = true;
        $response['username'] = $_SESSION['username'] ?? '';
        $response['user_id'] = $_SESSION['user_id'];
        $response['permission_level'] = $_SESSION['permission_level'] ?? 1;
        
        // עדכן זמן פעילות אחרון
        $_SESSION['last_activity'] = time();
        
        // רישום בלוג (אופציונלי - רק אם רוצים לעקוב)
        if (rand(1, 100) == 1) { // רק 1% מהפעמים כדי לא להעמיס על הDB
            $db = getDbConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'check_login_status', ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                json_encode(['method' => 'ajax']),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    // במקרה של שגיאה, החזר תשובה ברירת מחדל
    echo json_encode([
        'logged_in' => false,
        'username' => null,
        'user_id' => null,
        'permission_level' => null,
        'error' => 'Server error',
        'timestamp' => time()
    ]);
}