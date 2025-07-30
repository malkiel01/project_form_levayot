<?php
// ajax/delete_files.php - מחיקת קבצים
require_once '../../config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$fileIds = $data['files'] ?? [];

if (empty($fileIds)) {
    echo json_encode(['success' => false, 'message' => 'No files selected']);
    exit;
}

try {
    $db = getDbConnection();
    
    // קבלת פרטי הקבצים
    $placeholders = str_repeat('?,', count($fileIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT id, form_uuid, stored_name, is_folder, thumbnail_path 
        FROM form_files 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($fileIds);
    $files = $stmt->fetchAll();
    
    // מחיקת קבצים פיזיים
    foreach ($files as $file) {
        if (!$file['is_folder']) {
            $filePath = UPLOAD_PATH . $file['form_uuid'] . '/' . $file['stored_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // מחיקת תצוגה מקדימה
            if ($file['thumbnail_path']) {
                $thumbPath = UPLOAD_PATH . $file['form_uuid'] . '/' . $file['thumbnail_path'];
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }
        }
    }
    
    // מחיקה מהדטהבייס
    $stmt = $db->prepare("DELETE FROM form_files WHERE id IN ($placeholders)");
    $stmt->execute($fileIds);
    
    echo json_encode(['success' => true, 'message' => 'Files deleted successfully']);
    
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete files']);
}