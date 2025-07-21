<?php
// ajax/save_signature.php - שמירת חתימה

require_once '../config.php';
require_once '../DeceasedForm.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// בדיקת CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$formId = $_POST['form_id'] ?? null;
$signature = $_POST['signature'] ?? null;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

if (!$formId || !$signature) {
    echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
    exit;
}

$form = new DeceasedForm($formId, $userPermissionLevel);

try {
    $result = $form->saveSignature($signature);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'החתימה נשמרה בהצלחה' : 'שגיאה בשמירת החתימה'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}