<?php
// ajax/create_folder.php - יצירת תיקייה
require_once '../../config.php';

header('Content-Type: application/json');

$formUuid = $_POST['form_uuid'] ?? '';
$path = $_POST['path'] ?? '/';
$name = $_POST['name'] ?? '';

if (empty($formUuid) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $db = getDbConnection();
    
    // בדיקה אם התיקייה כבר קיימת
    $fullPath = rtrim($path, '/') . '/' . $name;
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM form_files 
        WHERE form_uuid = ? AND folder_path = ? AND original_name = ? AND is_folder = 1
    ");
    $stmt->execute([$formUuid, $path, $name]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'התיקייה כבר קיימת']);
        exit;
    }
    
    // יצירת התיקייה
    $stmt = $db->prepare("
        INSERT INTO form_files (
            form_uuid, file_uuid, original_name, stored_name,
            is_folder, folder_path, uploaded_by
        ) VALUES (?, ?, ?, ?, 1, ?, ?)
    ");
    
    $stmt->execute([
        $formUuid,
        generateUUID(),
        $name,
        $name,
        $path,
        $_SESSION['user_id'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'התיקייה נוצרה בהצלחה']);
    
} catch (Exception $e) {
    error_log("Create folder error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת תיקייה: ' . $e->getMessage()]);
}