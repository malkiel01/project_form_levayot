<?php
// ajax/create_share_link.php - יצירת קישור שיתוף

require_once '../config.php';

header('Content-Type: application/json');

// בדיקת הרשאות בסיסיות - לא נדרש להיות מחובר
// משתמש לא מחובר יכול ליצור קישור אם יש לו גישה לטופס

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// קבלת פרמטרים
$formUuid = $_POST['form_uuid'] ?? '';
$allowedUsers = $_POST['allowed_users'] ?? 'null';
$canEdit = $_POST['can_edit'] ?? '0';
$permissionLevel = $_POST['permission_level'] ?? '1';
$expiresAt = $_POST['expires_at'] ?? 'null';
$description = $_POST['description'] ?? '';

// ולידציה בסיסית
if (empty($formUuid)) {
    echo json_encode(['success' => false, 'message' => 'חסר מזהה טופס']);
    exit;
}

try {
    $db = getDbConnection();
    
    // בדוק שהטופס קיים
    $checkStmt = $db->prepare("SELECT id FROM deceased_forms WHERE form_uuid = ?");
    $checkStmt->execute([$formUuid]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'טופס לא נמצא']);
        exit;
    }
    
    // יצירת UUID חדש לקישור
    $linkUuid = generateUUID();
    
    // עיבוד משתמשים מורשים
    $allowedUsersJson = null;
    if ($allowedUsers !== 'null' && $allowedUsers !== '') {
        $allowedUsersJson = $allowedUsers;
    }
    
    // עיבוד תאריך תפוגה
    $expiresAtValue = null;
    if ($expiresAt !== 'null' && $expiresAt !== '') {
        $expiresAtValue = $expiresAt;
    }
    
    // הכנסת הקישור לטבלה
    $stmt = $db->prepare("
        INSERT INTO form_links (
            link_uuid, 
            form_uuid, 
            created_by, 
            allowed_user_ids, 
            can_edit, 
            permission_level, 
            expires_at, 
            description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $linkUuid,
        $formUuid,
        $_SESSION['user_id'] ?? null,
        $allowedUsersJson,
        $canEdit,
        $permissionLevel,
        $expiresAtValue,
        $description
    ]);
    
    // בניית הקישור המלא
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $formUrl = $protocol . '://' . $host . $basePath . '/form/?link=' . $linkUuid;
    
    // רישום בלוג
    if (isset($_SESSION['user_id'])) {
        $logStmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
            VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'create_share_link', ?, ?, ?)
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            $formUuid,
            json_encode([
                'link_uuid' => $linkUuid,
                'can_edit' => $canEdit,
                'expires_at' => $expiresAtValue
            ]),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    // החזרת תגובה
    echo json_encode([
        'success' => true,
        'link' => $formUrl,
        'link_uuid' => $linkUuid,
        'access_type' => $allowedUsersJson ? 'restricted' : 'public',
        'can_edit' => $canEdit == '1',
        'expires_at' => $expiresAtValue,
        'message' => 'הקישור נוצר בהצלחה'
    ]);
    
} catch (Exception $e) {
    error_log("Error creating share link: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה ביצירת הקישור: ' . $e->getMessage()
    ]);
}