<?php
// ajax/upload_file.php - העלאת קובץ
require_once '../../config.php';

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