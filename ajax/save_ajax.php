<?php
// ajax/auto_save.php - שמירה אוטומטית עם עדכון סטטוס

require_once '../config.php';
require_once '../DeceasedForm.php';

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
    echo json_encode(['success' => false, 'message' => 'Form UUID required']);
    exit;
}

// סניטציה של הנתונים
$formData = sanitizeInput($_POST);
unset($formData['csrf_token'], $formData['form_uuid']);

try {
    $form = new DeceasedForm($formUuid, $userPermissionLevel);
    
    // בדוק אם הטופס קיים
    if (!$form->getFormData()) {
        // יצירת טופס חדש
        $formData['form_uuid'] = $formUuid;
        $form = new DeceasedForm(null, $userPermissionLevel);
        $form->createForm($formData);
        
        // טען מחדש את הטופס
        $form = new DeceasedForm($formUuid, $userPermissionLevel);
        $updatedData = $form->getFormData();
    } else {
        // עדכון טופס קיים
        $form->updateForm($formData);
        $updatedData = $form->getFormData();
    }
    
    echo json_encode([
        'success' => true,
        'status' => $updatedData['status'],
        'progress' => $updatedData['progress_percentage'],
        'message' => $updatedData['status'] === 'completed' ? 'הטופס הושלם!' : 'הטופס נשמר כטיוטה'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>