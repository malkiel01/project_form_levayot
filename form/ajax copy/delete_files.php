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
    
    // מחיקת קבצים פיזיים ותיקיות
    foreach ($files as $file) {
        if ($file['is_folder']) {
            // מחיקת תיקייה פיזית
            $folderPath = UPLOAD_PATH . $file['form_uuid'] . $file['full_path'];
            
            // מחק תחילה את כל הקבצים בתיקייה ובתת-תיקיות
            $subStmt = $db->prepare("
                SELECT * FROM form_files 
                WHERE form_uuid = ? AND folder_path LIKE ?
            ");
            $subPath = $file['full_path'] . '%';
            $subStmt->execute([$file['form_uuid'], $subPath]);
            $subFiles = $subStmt->fetchAll();
            
            // מחק קבצים פיזיים
            foreach ($subFiles as $subFile) {
                if (!$subFile['is_folder']) {
                    $subFilePath = UPLOAD_PATH . $subFile['form_uuid'] . $subFile['folder_path'] . '/' . $subFile['stored_name'];
                    if (file_exists($subFilePath)) {
                        unlink($subFilePath);
                    }
                }
            }
            
            // מחק רשומות מהדטהבייס
            $delStmt = $db->prepare("
                DELETE FROM form_files 
                WHERE form_uuid = ? AND (full_path = ? OR folder_path LIKE ?)
            ");
            $delStmt->execute([$file['form_uuid'], $file['full_path'], $subPath]);
            
            // נסה למחוק את התיקייה הפיזית
            if (is_dir($folderPath)) {
                deleteDirectory($folderPath);
            }
        } else {
            // מחיקת קובץ רגיל
            $filePath = UPLOAD_PATH . $file['form_uuid'] . $file['folder_path'] . '/' . $file['stored_name'];
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