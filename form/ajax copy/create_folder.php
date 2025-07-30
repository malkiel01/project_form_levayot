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

// ניקוי שם התיקייה
$name = preg_replace('/[^a-zA-Z0-9א-ת_\-\s]/u', '', $name);
$name = trim($name);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'שם תיקייה לא תקין']);
    exit;
}

try {
    $db = getDbConnection();
    
    // בניית הנתיב המלא
    $fullPath = rtrim($path, '/') . '/' . $name;
    
    // בדיקה אם התיקייה כבר קיימת
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM form_files 
        WHERE form_uuid = ? AND full_path = ? AND is_folder = 1
    ");
    $stmt->execute([$formUuid, $fullPath]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'התיקייה כבר קיימת']);
        exit;
    }
    
    // יצירת תיקייה פיזית בשרת
    $physicalPath = UPLOAD_PATH . $formUuid . $fullPath;
    if (!is_dir($physicalPath)) {
        if (!mkdir($physicalPath, 0777, true)) {
            throw new Exception('Failed to create physical directory');
        }
    }
    
    // יצירת רשומה בבסיס הנתונים
    $stmt = $db->prepare("
        INSERT INTO form_files (
            form_uuid, file_uuid, original_name, stored_name,
            is_folder, folder_path, full_path, uploaded_by,
            upload_date
        ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $formUuid,
        generateUUID(),
        $name,
        $name,
        $path,
        $fullPath,
        $_SESSION['user_id'] ?? null
    ]);
    
    echo json_encode(['success' => true, 'message' => 'התיקייה נוצרה בהצלחה']);
    
} catch (Exception $e) {
    error_log("Create folder error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה ביצירת תיקייה: ' . $e->getMessage()]);
}