<?php
// share-target.php - טיפול בקבצים משותפים מאפליקציות אחרות
require_once 'config.php';

session_start();

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    // שמירת הנתונים ב-session להמשך טיפול אחרי התחברות
    $_SESSION['pending_share'] = [
        'files' => $_FILES,
        'title' => $_POST['title'] ?? '',
        'text' => $_POST['text'] ?? '',
        'url' => $_POST['url'] ?? '',
        'timestamp' => time()
    ];
    
    header('Location: /auth/login.php?redirect=share');
    exit;
}

// טיפול בקבצים שהתקבלו
if (isset($_FILES['files'])) {
    $uploadedFiles = [];
    
    foreach ($_FILES['files']['name'] as $key => $name) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['files']['tmp_name'][$key];
            $fileType = $_FILES['files']['type'][$key];
            $fileSize = $_FILES['files']['size'][$key];
            
            // בדיקת סוג וגודל קובץ
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                           'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (!in_array($fileType, $allowedTypes)) {
                continue;
            }
            
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                continue;
            }
            
            // יצירת שם קובץ ייחודי
            $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
            $newFileName = uniqid('shared_') . '.' . $fileExtension;
            $uploadPath = UPLOAD_PATH . '/temp/' . $newFileName;
            
            // יצירת תיקיית temp אם לא קיימת
            if (!is_dir(UPLOAD_PATH . '/temp/')) {
                mkdir(UPLOAD_PATH . '/temp/', 0777, true);
            }
            
            if (move_uploaded_file($tmpName, $uploadPath)) {
                $uploadedFiles[] = [
                    'original_name' => $name,
                    'stored_name' => $newFileName,
                    'file_type' => $fileType,
                    'file_size' => $fileSize,
                    'upload_path' => $uploadPath
                ];
            }
        }
    }
    
    // שמירת הקבצים ב-session
    $_SESSION['shared_files'] = $uploadedFiles;
}

// הפניה לדף בחירת טופס
header('Location: /select-form-for-share.php');
exit;