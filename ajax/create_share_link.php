<?php
require_once 'config.php';
require_once 'DeceasedForm.php';

// לוודא שהמשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// לקבל את ה־form_uuid מ־POST
$formUuid = $_POST['form_uuid'] ?? null;

if (!$formUuid) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing form_uuid']);
    exit;
}

// צור קישור חדש
$linkUuid = createFormLink(
    $formUuid,            // מזהה הטופס
    4,                    // הרשאה למשתמש לא רשום
    null,                 // פתוח לכולם
    false,                // צפייה בלבד (אפשר לשנות ל-true אם צריך עריכה)
    date('Y-m-d H:i:s', strtotime('+30 days')), // תוקף 30 יום מהיום
    $_SESSION['user_id']  // יוצר הקישור
);

// החזר את הקישור המלא
echo json_encode([
    'link' => SITE_URL . '/form.php?link=' . $linkUuid
]);
