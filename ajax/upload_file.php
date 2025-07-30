<?php
// ajax/upload_file.php - העלאת קובץ
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$formUuid = $_POST['form_uuid'] ?? '';
$path = $_POST['path'] ?? '/';

if (empty($formUuid)) {
    echo json_encode(['success' => false, 'message' => 'Missing form UUID']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

// בדיקות
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit;
}

// בדיקת גודל
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

// בדיקת סוג קובץ
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ALLOWED_FILE_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']);
    exit;
}

try {
    $db = getDbConnection();
    
    // יצירת תיקיית הטופס אם לא קיימת
    $formDir = UPLOAD_PATH . $formUuid;
    if (!is_dir($formDir)) {
        mkdir($formDir, 0777, true);
    }
    
    // יצירת שם קובץ ייחודי
    $fileUuid = generateUUID();
    $storedName = $fileUuid . '.' . $extension;
    $filePath = $formDir . '/' . $storedName;
    
    // העלאת הקובץ
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save file');
    }
    
    // יצירת תצוגה מקדימה לתמונות
    $thumbnailPath = null;
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
        $thumbnailPath = createThumbnail($filePath, $formDir . '/thumbs/', $storedName);
    }
    
    // שמירה בדטהבייס
    $stmt = $db->prepare("
        INSERT INTO form_files (
            form_uuid, file_uuid, original_name, stored_name, 
            file_type, file_size, mime_type, uploaded_by, 
            folder_path, file_extension, thumbnail_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $formUuid,
        $fileUuid,
        $file['name'],
        $storedName,
        $file['type'],
        $file['size'],
        $file['type'],
        $_SESSION['user_id'] ?? null,
        $path,
        $extension,
        $thumbnailPath
    ]);
    
    echo json_encode([
        'success' => true,
        'file_id' => $db->lastInsertId(),
        'file_uuid' => $fileUuid,
        'message' => 'File uploaded successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
}

// פונקציה ליצירת תצוגה מקדימה
function createThumbnail($source, $thumbDir, $filename) {
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }
    
    $thumbPath = $thumbDir . $filename;
    
    // קבל מידע על התמונה
    $info = getimagesize($source);
    if (!$info) return null;
    
    // יצירת תמונה לפי הסוג
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return null;
    }
    
    // חישוב מימדים
    $width = $info[0];
    $height = $info[1];
    $thumbSize = 200;
    
    if ($width > $height) {
        $newWidth = $thumbSize;
        $newHeight = ($height / $width) * $thumbSize;
    } else {
        $newHeight = $thumbSize;
        $newWidth = ($width / $height) * $thumbSize;
    }
    
    // יצירת תמונה מוקטנת
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // שמירת שקיפות ל-PNG
    if ($info['mime'] == 'image/png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // שמירה
    switch ($info['mime']) {
        case 'image/jpeg':
            imagejpeg($thumb, $thumbPath, 80);
            break;
        case 'image/png':
            imagepng($thumb, $thumbPath, 8);
            break;
        case 'image/gif':
            imagegif($thumb, $thumbPath);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($thumb);
    
    return 'thumbs/' . $filename;
}

// ======================================

// ajax/get_form_files.php - קבלת רשימת קבצים
require_once '../config.php';

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
            (SELECT username FROM users WHERE id = uploaded_by) as uploaded_by
        FROM form_files 
        WHERE form_uuid = ? AND folder_path = ?
        ORDER BY is_folder DESC, name ASC
    ");
    
    $stmt->execute([$formUuid, $path]);
    $files = $stmt->fetchAll();
    
    // התאמת נתיבי תמונות
    foreach ($files as &$file) {
        if ($file['thumbnail']) {
            $file['thumbnail'] = '../uploads/' . $formUuid . '/' . $file['thumbnail'];
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

// ======================================

// ajax/delete_files.php - מחיקת קבצים
require_once '../config.php';

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

// ======================================

// ajax/rename_file.php - שינוי שם קובץ
require_once '../config.php';

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
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    
    // עדכון השם
    $stmt = $db->prepare("UPDATE form_files SET original_name = ? WHERE id = ?");
    $stmt->execute([$newName, $fileId]);
    
    echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
    
} catch (Exception $e) {
    error_log("Rename error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to rename file']);
}

// ======================================

// ajax/create_folder.php - יצירת תיקייה
require_once '../config.php';

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
        echo json_encode(['success' => false, 'message' => 'Folder already exists']);
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
    
    echo json_encode(['success' => true, 'message' => 'Folder created successfully']);
    
} catch (Exception $e) {
    error_log("Create folder error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
}

// ======================================

// ajax/download_file.php - הורדת קובץ
require_once '../config.php';

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

// ======================================

// ajax/preview_file.php - תצוגה מקדימה
require_once '../config.php';

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

// ======================================

// ajax/paste_files.php - העתקה/גזירה של קבצים
require_once '../config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$fileIds = $data['files'] ?? [];
$destination = $data['destination'] ?? '/';
$formUuid = $data['form_uuid'] ?? '';

if (empty($action) || empty($fileIds) || empty($formUuid)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $db = getDbConnection();
    
    if ($action === 'copy') {
        // העתקת קבצים
        foreach ($fileIds as $fileId) {
            $stmt = $db->prepare("SELECT * FROM form_files WHERE id = ?");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();
            
            if (!$file) continue;
            
            // יצירת עותק בדטהבייס
            $newUuid = generateUUID();
            $newName = getCopyName($file['original_name'], $destination, $formUuid, $db);
            
            if (!$file['is_folder']) {
                // העתקת קובץ פיזי
                $sourcePath = UPLOAD_PATH . $file['form_uuid'] . '/' . $file['stored_name'];
                $newStoredName = $newUuid . '.' . $file['file_extension'];
                $destPath = UPLOAD_PATH . $formUuid . '/' . $newStoredName;
                
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $destPath);
                }
                
                // העתקת תצוגה מקדימה
                if ($file['thumbnail_path']) {
                    $sourceThumb = UPLOAD_PATH . $file['form_uuid'] . '/' . $file['thumbnail_path'];
                    $destThumb = UPLOAD_PATH . $formUuid . '/thumbs/' . $newStoredName;
                    if (file_exists($sourceThumb)) {
                        copy($sourceThumb, $destThumb);
                    }
                }
            }
            
            // הכנסה לדטהבייס
            $stmt = $db->prepare("
                INSERT INTO form_files (
                    form_uuid, file_uuid, original_name, stored_name,
                    file_type, file_size, mime_type, uploaded_by,
                    folder_path, is_folder, file_extension, thumbnail_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $formUuid,
                $newUuid,
                $newName,
                $file['is_folder'] ? $newName : ($newUuid . '.' . $file['file_extension']),
                $file['file_type'],
                $file['file_size'],
                $file['mime_type'],
                $_SESSION['user_id'] ?? null,
                $destination,
                $file['is_folder'],
                $file['file_extension'],
                $file['is_folder'] ? null : ('thumbs/' . $newUuid . '.' . $file['file_extension'])
            ]);
        }
        
    } else if ($action === 'cut') {
        // העברת קבצים
        foreach ($fileIds as $fileId) {
            $stmt = $db->prepare("UPDATE form_files SET folder_path = ? WHERE id = ?");
            $stmt->execute([$destination, $fileId]);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Operation completed successfully']);
    
} catch (Exception $e) {
    error_log("Paste error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Operation failed']);
}

// פונקציה לקבלת שם עותק ייחודי
function getCopyName($originalName, $path, $formUuid, $db) {
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $counter = 1;
    $newName = $originalName;
    
    while (true) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM form_files 
            WHERE form_uuid = ? AND folder_path = ? AND original_name = ?
        ");
        $stmt->execute([$formUuid, $path, $newName]);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $newName = $baseName . ' (' . $counter . ')' . ($extension ? '.' . $extension : '');
        $counter++;
    }
    
    return $newName;
}