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

// // ולידציה בסיסית
// if (empty($formUuid)) {
//     echo json_encode(['success' => false, '