<?php
// ajax/download_file.php - הורדת קובץ
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
    
    // הגדרת headers להורדה
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    die('Download failed');
}