<?php
// ajax/rename_file.php - שינוי שם קובץ
require_once '../../config.php';

header('Content-Type: application/json');

$fileId = $_POST['file_id'] ?? '';
$newName = $_POST['new_name'] ?? '';

if (empty($fileId) || empty($newName)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $db = getDbConnection();
    
    // בדיקת קובץ קיים
    $stmt = $db->prepare("SELECT * FROM form_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'הקובץ לא נמצא']);
        exit;
    }
    
    // עדכון השם
    $stmt = $db->prepare("UPDATE form_files SET original_name = ? WHERE id = ?");
    $stmt->execute([$newName, $fileId]);
    
    echo json_encode(['success' => true, 'message' => 'השם שונה בהצלחה']);
    
} catch (Exception $e) {
    error_log("Rename error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה בשינוי השם']);
}