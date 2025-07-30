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
    
    // קבלת קבצים ותיקיות בנתיב הנוכחי בלבד
    $stmt = $db->prepare("
        SELECT 
            id, file_uuid, original_name as name, stored_name, 
            file_size as size, upload_date, file_extension as extension,
            is_folder, folder_path as path, full_path, thumbnail_path as thumbnail,
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
        
        // עבור תיקיות, הוסף מידע על תוכן
        if ($file['is_folder']) {
            $subPath = $path === '/' ? '/' . $file['name'] : $path . '/' . $file['name'];
            $countStmt = $db->prepare("
                SELECT COUNT(*) as item_count 
                FROM form_files 
                WHERE form_uuid = ? AND folder_path = ?
            ");
            $countStmt->execute([$formUuid, $subPath]);
            $file['item_count'] = $countStmt->fetchColumn();
        }
    }
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'path' => $path,
        'current_path' => $path
    ]);
    
} catch (Exception $e) {
    error_log("Error getting files: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load files: ' . $e->getMessage()]);
}