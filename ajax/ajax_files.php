<?php
    // ajax/search_forms.php - חיפוש טפסים
    require_once '../config.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $search = $_POST['search'] ?? '';
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;

    if (strlen($search) < 2) {
        echo json_encode(['results' => [], 'message' => 'יש להזין לפחות 2 תווים']);
        exit;
    }

    $db = getDbConnection();
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
    $whereClause = "(
        CONCAT(IFNULL(deceased_first_name, ''), ' ', IFNULL(deceased_last_name, '')) LIKE ? 
        OR deceased_first_name LIKE ? 
        OR deceased_last_name LIKE ? 
        OR identification_number LIKE ? 
        OR form_uuid LIKE ?
    )";
    $params[] = "%$search%"; // הוספת פרמטר נוסף עבור form_uuid

    if ($userPermissionLevel < 4) {
        $whereClause .= " AND created_by = ?";
        $params[] = $_SESSION['user_id'];
    }

    $stmt = $db->prepare("
        SELECT 
            form_uuid, 
            CONCAT(IFNULL(deceased_first_name, ''), ' ', IFNULL(deceased_last_name, '')) as deceased_name,
            identification_number, 
            death_date, 
            status
        FROM deceased_forms
        WHERE $whereClause
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    echo json_encode(['results' => $results]);

?>

    <!-- // ajax/save_draft.php - שמירה אוטומטית כטיוטה -->
<?php
    // // ajax/save_draft.php - שמירה אוטומטית כטיוטה
    // require_once '../config.php';
    // require_once '../DeceasedForm.php';

    // header('Content-Type: application/json');

    // if (!isset($_SESSION['user_id'])) {
    //     http_response_code(401);
    //     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    //     exit;
    // }

    // // בדיקת CSRF token
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    //     exit;
    // }

    // $formUuid = $_POST['form_uuid'] ?? null;
    // $userPermissionLevel = $_SESSION['permission_level'] ?? 1;

    // // סניטציה של הנתונים
    // $formData = sanitizeInput($_POST);
    // unset($formData['csrf_token'], $formData['form_uuid']);

    // // הגדרת סטטוס כטיוטה אם לא הוגדר אחרת
    // if (!isset($formData['status']) || $formData['status'] === '') {
    //     $formData['status'] = 'draft';
    // }

    // try {
    //     if ($formUuid) {
    //         // עדכון טופס קיים
    //         $form = new DeceasedForm($formUuid, $userPermissionLevel);
    //         $form->updateForm($formData);
    //         echo json_encode(['success' => true, 'message' => 'הטופס נשמר כטיוטה']);
    //     } else {
    //         // יצירת טופס חדש
    //         $form = new DeceasedForm(null, $userPermissionLevel);
    //         $newFormUuid = $form->createForm($formData);
    //         echo json_encode([
    //             'success' => true, 
    //             'message' => 'הטופס נשמר כטיוטה',
    //             'form_uuid' => $newFormUuid
    //         ]);
    //     }
    // } catch (Exception $e) {
    //     echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    // }
?>
<?php

// ajax/save_draft.php - שמירה אוטומטית כטיוטה
require_once '../config.php';
require_once '../DeceasedForm.php';

    $formType = $_POST['form_type'] ?? 'deceased';
    $formTypeData = $db->prepare("SELECT * FROM form_types WHERE type_key = ?");
    $formTypeData->execute([$formType]);
    $typeInfo = $formTypeData->fetch();

    require_once "../{$typeInfo['form_class']}.php";
    $formClass = $typeInfo['form_class'];
    $form = new $formClass($formUuid, $userPermissionLevel);


    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $formUuid = $_POST['form_uuid'] ?? null;
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;

    // סניטציה של הנתונים
    $formData = sanitizeInput($_POST);
    unset($formData['csrf_token'], $formData['form_uuid']);

    // הגדרת סטטוס כטיוטה אם לא הוגדר אחרת
    if (!isset($formData['status']) || $formData['status'] === '') {
        $formData['status'] = 'draft';
    }

    try {
        if ($formUuid) {
            // עדכון טופס קיים
            $form = new DeceasedForm($formUuid, $userPermissionLevel);
            $form->updateForm($formData);
            echo json_encode(['success' => true, 'message' => 'הטופס נשמר כטיוטה']);
        } else {
            // יצירת טופס חדש
            $form = new DeceasedForm(null, $userPermissionLevel);
            $newFormUuid = $form->createForm($formData);
            echo json_encode([
                'success' => true, 
                'message' => 'הטופס נשמר כטיוטה',
                'form_uuid' => $newFormUuid
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
?>

<?php
    // ajax/delete_form.php - מחיקת טופס
    require_once '../config.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $formUuid = $_POST['form_uuid'] ?? null;
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;

    if (!$formUuid) {
        echo json_encode(['success' => false, 'message' => 'Form ID required']);
        exit;
    }

    // רק מנהלים יכולים למחוק טפסים
    if ($userPermissionLevel < 3) {
        echo json_encode(['success' => false, 'message' => 'אין לך הרשאה למחוק טפסים']);
        exit;
    }

    $db = getDbConnection();

    try {
        // בדיקה אם הטופס קיים ושייך למשתמש (אם לא מנהל)
        $checkStmt = $db->prepare("SELECT id, created_by FROM deceased_forms WHERE form_uuid = ?");
        $checkStmt->execute([$formUuid]);
        $form = $checkStmt->fetch();
        
        if (!$form) {
            echo json_encode(['success' => false, 'message' => 'הטופס לא נמצא']);
            exit;
        }
        
        // אם לא מנהל, בדוק שהטופס שייך למשתמש
        if ($userPermissionLevel < 4 && $form['created_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'אין לך הרשאה למחוק טופס זה']);
            exit;
        }
        
        // מחיקת הטופס
        $deleteStmt = $db->prepare("DELETE FROM deceased_forms WHERE form_uuid = ?");
        $deleteStmt->execute([$formUuid]);
        
        // רישום בלוג
        $logStmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, 'delete_form', ?, ?, ?)
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            $form['id'],
            json_encode(['form_uuid' => $formUuid]),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode(['success' => true, 'message' => 'הטופס נמחק בהצלחה']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת הטופס']);
    }

?>

<?php
    // ajax/mark_notification_read.php - סימון התראה כנקראה
    require_once '../config.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $notificationId = intval($_POST['notification_id'] ?? 0);

    if (!$notificationId) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        exit;
    }

    $db = getDbConnection();

    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error marking notification as read']);
    }

?>