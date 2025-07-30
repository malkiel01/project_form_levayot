<?php
// ajax/get_form_files.php - קבלת רשימת קבצים
require_once '../../config.php';

header('Content-Type: application/json');

$formUuid = $_POST['form_uuid'] ?? '';
$path = $_POST['path'] ?? '/';

if (empty($formUuid)) {
    echo json_encode(['success' => false, 'message' => 'Missing form UUID']);
    exit;
}

try {
    $db = getDbConnection();
    
    $stmt = $db->prepare("
        SELECT 
            id, file_uuid, original_name as name, stored_name, 
            file_size as size, upload_date, file_extension as extension,
            is_folder, folder_path as path, thumbnail_path as thumbnail,
            (SELECT username FROM users WHERE id = f.uploaded_by) as uploaded_by
        FROM form_files f
        WHERE form_uuid = ? AND folder_path = ?
        ORDER BY is_folder DESC, name ASC
    ");
    
    $stmt->execute([$formUuid, $path]);
    $files = $stmt->fetchAll();
    
    // התאמת נתיבי תמונות
    foreach ($files as &$file) {
        if ($file['thumbnail']) {
            $file['thumbnail'] = '../../uploads/' . $formUuid . '/' . $file['thumbnail'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'path' => $path
    ]);
    
} catch (Exception $e) {
    error_log("Error getting files: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load files']);
}