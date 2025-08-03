<?php
    // form/ajax/share_link.php - טיפול ביצירת קישורי שיתוף עם בדיקת הרשאות

    require_once '../../config.php';
    require_once '../../DeceasedForm.php';

    // בדיקת CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }

    // בדיקה בסיסית - האם המשתמש מחובר
    if (!isset($_SESSION['user_id'])) {
        die(json_encode([
            'success' => false, 
            'message' => 'עליך להתחבר למערכת כדי לשתף טפסים'
        ]));
    }

    // בדיקת הרשאות - רק עורך (3) ומנהל (4) יכולים לשתף
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;
    if ($userPermissionLevel < 3) {
        die(json_encode([
            'success' => false, 
            'message' => 'אין לך הרשאה לשתף טפסים. פעולה זו מוגבלת לעורכים ומנהלים בלבד.'
        ]));
    }

    // קבלת נתוני הבקשה
    $formUuid = $_POST['form_uuid'] ?? '';
    $accessType = $_POST['access_type'] ?? 'public';
    $permissionMode = $_POST['permission_mode'] ?? 'view';
    $permissionLevel = $_POST['permission_level'] ?? 1;
    $allowedUsers = $_POST['allowed_users'] ?? [];
    $expiryType = $_POST['expiry_type'] ?? 'never';
    $expiryDate = $_POST['expiry_date'] ?? null;
    $expiryTime = $_POST['expiry_time'] ?? '23:59';
    $description = $_POST['description'] ?? '';

    // ולידציה של form_uuid
    if (empty($formUuid)) {
        die(json_encode(['success' => false, 'message' => 'מזהה טופס חסר']));
    }

    // בדיקה שהמשתמש הוא הבעלים של הטופס או מנהל
    $db = getDbConnection();
    $checkStmt = $db->prepare("
        SELECT created_by 
        FROM deceased_forms 
        WHERE form_uuid = ?
    ");
    $checkStmt->execute([$formUuid]);
    $formData = $checkStmt->fetch();

    if (!$formData) {
        die(json_encode(['success' => false, 'message' => 'טופס לא נמצא']));
    }

    // רק הבעלים של הטופס או מנהל יכולים לשתף
    if ($formData['created_by'] != $_SESSION['user_id'] && $userPermissionLevel < 4) {
        die(json_encode([
            'success' => false, 
            'message' => 'אין לך הרשאה לשתף טופס זה. רק היוצר או מנהל יכולים לשתף אותו.'
        ]));
    }

    // יצירת UUID לקישור
    $linkUuid = generateUUID();

    // חישוב תאריך תפוגה
    $expiresAt = null;
    if ($expiryType === 'custom' && $expiryDate) {
        $expiresAt = $expiryDate . ' ' . $expiryTime;
    }

    // הכנת נתונים לשמירה
    $allowedUserIds = null;
    if ($accessType === 'users' && !empty($allowedUsers)) {
        $allowedUserIds = json_encode(array_map('intval', $allowedUsers));
    }

    try {
        // יצירת הקישור
        $stmt = $db->prepare("
            INSERT INTO form_links 
            (link_uuid, form_uuid, created_by, permission_level, can_edit, 
            allowed_user_ids, expires_at, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $linkUuid,
            $formUuid,
            $_SESSION['user_id'],
            $permissionLevel,
            $permissionMode === 'edit' ? 1 : 0,
            $allowedUserIds,
            $expiresAt,
            $description
        ]);
        
        // רישום בלוג
        $logStmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
            VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'create_share_link', ?, ?, ?)
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            $formUuid,
            json_encode([
                'link_uuid' => $linkUuid,
                'access_type' => $accessType,
                'permission_mode' => $permissionMode,
                'expires_at' => $expiresAt
            ]),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // יצירת URL מלא
        $shareUrl = SITE_URL . '/' . FORM_URL . '?link=' . $linkUuid;
        
        echo json_encode([
            'success' => true,
            'link_uuid' => $linkUuid,
            'share_url' => $shareUrl,
            'expires_at' => $expiresAt,
            'can_edit' => $permissionMode === 'edit'
        ]);
        
    } catch (Exception $e) {
        error_log("Error creating share link: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'שגיאה ביצירת קישור השיתוף'
        ]);
    }

    // פונקציה ליצירת שיתוף מהיר - עם בדיקת הרשאות
    function quickShare() {
        // בדיקה בסיסית - האם המשתמש מחובר
        if (!isset($_SESSION['user_id'])) {
            return [
                'success' => false, 
                'message' => 'עליך להתחבר למערכת כדי לשתף טפסים'
            ];
        }
        
        // בדיקת הרשאות - רק עורך (3) ומנהל (4) יכולים לשתף
        $userPermissionLevel = $_SESSION['permission_level'] ?? 1;
        if ($userPermissionLevel < 3) {
            return [
                'success' => false, 
                'message' => 'אין לך הרשאה לשתף טפסים'
            ];
        }
        
        // המשך עם יצירת השיתוף המהיר...
    }
?>