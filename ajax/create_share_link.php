<?php
require_once '../config.php';
require_once '../DeceasedForm.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$formUuid = $_POST['form_uuid'] ?? null;

if (!$formUuid) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing form_uuid']);
    exit;
}

$linkUuid = createFormLink(
    $formUuid,
    4,
    null,
    false,
    date('Y-m-d H:i:s', strtotime('+30 days')),
    $_SESSION['user_id']
);

echo json_encode([
    'link' => SITE_URL . '/form.php?link=' . $linkUuid
]);
