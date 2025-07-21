<?php
// ajax/upload_document.php - העלאת מסמך

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
$documentType = $_POST['document_type'] ?? 'general';
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

if (!$formId || !isset($_FILES['document'])) {
    echo json_encode(['success' => false, 'message' => 'נתונים חסרים']);
    exit;
}

$form = new DeceasedForm($formId, $userPermissionLevel);

try {
    $documentId = $form->uploadDocument($_FILES['document'], $documentType);
    echo json_encode([
        'success' => true, 
        'document_id' => $documentId,
        'message' => 'המסמך הועלה בהצלחה'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}