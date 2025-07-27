<?php
// ajax/create_share_link.php - יצירת קישור שיתוף מתקדם

require_once '../config.php';
require_once '../DeceasedForm.php';

header('Content-Type: application/json; charset=utf-8');

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'לא מורשה']);
    exit;
}

// קבלת פרמטרים
$formUuid = $_POST['form_uuid'] ?? null;
$allowedUsers = $_POST['allowed_users'] ?? 'null';
$canEdit = ($_POST['can_edit'] ?? '0') === '1';
$permissionLevel = intval($_POST['permission_level'] ?? 4);
$expiresAt = $_POST['expires_at'] ?? 'null';
$description = $_POST['description'] ?? '';

// ולידציה
if (!$formUuid) {
    echo json_encode(['success' => false, 'message' => 'חסר מזהה טופס']);
    exit;
}

// בדיקה שהטופס קיים
$db = getDbConnection();
$checkStmt = $db->prepare("SELECT id FROM deceased_forms WHERE form_uuid = ?");
$checkStmt->execute([$formUuid]);
if (!$checkStmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'הטופס לא נמצא']);
    exit;
}

try {
    // יצירת UUID חדש לקישור
    $linkUuid = generateUUID();
    
    // עיבוד פרמטרים
    $allowedUserIds = null;
    $accessType = 'public';
    
    if ($allowedUsers !== 'null' && $allowedUsers !== '') {
        $allowedUserIds = json_decode($allowedUsers);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($allowedUserIds)) {
            throw new Exception('רשימת משתמשים לא תקינה');
        }
        $accessType = 'users';
        $allowedUserIds = json_encode($allowedUserIds);
    }
    
    $expiresAtDate = null;
    if ($expiresAt !== 'null' && $expiresAt !== '') {
        $expiresAtDate = date('Y-m-d H:i:s', strtotime($expiresAt));
        if ($expiresAtDate === false) {
            throw new Exception('תאריך תפוגה לא תקין');
        }
    }
    
    // הוספת הקישור לדטהבייס
    $stmt = $db->prepare("
        INSERT INTO form_links 
        (link_uuid, form_uuid, permission_level, allowed_user_ids, can_edit, expires_at, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $linkUuid,
        $formUuid,
        $permissionLevel,
        $allowedUserIds,
        $canEdit ? 1 : 0,
        $expiresAtDate,
        $_SESSION['user_id']
    ]);
    
    // רישום בלוג
    $logStmt = $db->prepare("
        INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
        VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'create_share_link', ?, ?, ?)
    ");
    
    $logDetails = [
        'link_uuid' => $linkUuid,
        'access_type' => $accessType,
        'can_edit' => $canEdit,
        'expires_at' => $expiresAtDate,
        'description' => $description
    ];
    
    $logStmt->execute([
        $_SESSION['user_id'],
        $formUuid,
        json_encode($logDetails),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // החזרת תוצאה
    $response = [
        'success' => true,
        'link' => SITE_URL . '/form.php?link=' . $linkUuid,
        'link_uuid' => $linkUuid,
        'access_type' => $accessType,
        'can_edit' => $canEdit,
        'expires_at' => $expiresAtDate,
        'description' => $description
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error creating share link: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה ביצירת הקישור: ' . $e->getMessage()
    ]);
}
?>