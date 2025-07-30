<?php
// ajax/preview_file.php - תצוגה מקדימה
require_once '../../config.php';

$fileId = $_GET['id'] ?? '';

if (empty($fileId)) {
    die('File not found');
}

try {
    $db = getDbConnection();
    
    $stmt = $db->prepare("SELECT * FROM form_files WHERE id = ? AND is_folder = 0");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        die('File not found');
    }
    
    $filePath = UPLOAD_PATH . $file['form_uuid'] . '/' . $file['stored_name'];
    
    if (!file_exists($filePath)) {
        die('File not found on server');
    }
    
    // הגדרת headers לתצוגה
    $extension = strtolower($file['file_extension']);
    
    // סוגי קבצים שניתן להציג בדפדפן
    $previewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'txt'];
    
    if (in_array($extension, $previewableTypes)) {
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
        readfile($filePath);
    } else {
        // להורדה עבור סוגים אחרים
        header('Location: download_file.php?id=' . $fileId);
    }
    exit;
    
} catch (Exception $e) {
    error_log("Preview error: " . $e->getMessage());
    die('Preview failed');
}