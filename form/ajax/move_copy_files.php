<?php
// ajax/move_copy_files.php - העתקה והעברה של קבצים בין תיקיות

require_once '../../config.php';

header('Content-Type: application/json');

// קבלת נתונים
$data = json_decode(file_get_contents('php://input'), true);

$action = $data['action'] ?? ''; // copy או cut
$fileIds = $data['files'] ?? [];
$sourcePath = $data['source'] ?? '/';
$destinationPath = $data['destination'] ?? '/';
$formUuid = $data['form_uuid'] ?? '';

// ולידציה
if (empty($action) || empty($fileIds) || empty($formUuid)) {
    echo json_encode(['success' => false, 'message' => 'חסרים פרמטרים נדרשים']);
    exit;
}

if (!in_array($action, ['copy', 'cut'])) {
    echo json_encode(['success' => false, 'message' => 'פעולה לא תקינה']);
    exit;
}

// בדיקה שאין ניסיון להעתיק/להעביר תיקייה לתוך עצמה
foreach ($fileIds as $fileId) {
    if (!validateMoveOperation($fileId, $destinationPath, $formUuid)) {
        echo json_encode(['success' => false, 'message' => 'לא ניתן להעביר תיקייה לתוך עצמה']);
        exit;
    }
}

try {
    $db = getDbConnection();
    $db->beginTransaction();
    
    $successCount = 0;
    $errors = [];
    
    foreach ($fileIds as $fileId) {
        if ($action === 'copy') {
            $result = copyFile($fileId, $destinationPath, $formUuid, $db);
        } else {
            $result = moveFile($fileId, $destinationPath, $formUuid, $db);
        }
        
        if ($result['success']) {
            $successCount++;
        } else {
            $errors[] = $result['message'];
        }
    }
    
    if (count($errors) === 0) {
        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => $action === 'copy' ? 
                "$successCount קבצים הועתקו בהצלחה" : 
                "$successCount קבצים הועברו בהצלחה"
        ]);
    } else {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'אירעו שגיאות: ' . implode(', ', $errors)
        ]);
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Move/Copy error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'שגיאה בביצוע הפעולה']);
}

// פונקציה לולידציה של פעולת העברה
function validateMoveOperation($fileId, $destinationPath, $formUuid) {
    $db = getDbConnection();
    
    // קבל פרטי הקובץ/תיקייה
    $stmt = $db->prepare("
        SELECT * FROM form_files 
        WHERE id = ? AND form_uuid = ?
    ");
    $stmt->execute([$fileId, $formUuid]);
    $file = $stmt->fetch();
    
    if (!$file || !$file['is_folder']) {
        return true; // אם זה לא תיקייה, אין בעיה
    }
    
    // בדוק שהיעד לא נמצא בתוך התיקייה המועברת
    $folderPath = $file['full_path'];
    return strpos($destinationPath, $folderPath) !== 0;
}

// פונקציה להעתקת קובץ
function copyFile($fileId, $destinationPath, $formUuid, $db) {
    try {
        // קבל פרטי הקובץ
        $stmt = $db->prepare("
            SELECT * FROM form_files 
            WHERE id = ? AND form_uuid = ?
        ");
        $stmt->execute([$fileId, $formUuid]);
        $file = $stmt->fetch();
        
        if (!$file) {
            return ['success' => false, 'message' => 'קובץ לא נמצא'];
        }
        
        if ($file['is_folder']) {
            return copyFolder($file, $destinationPath, $formUuid, $db);
        } else {
            return copyRegularFile($file, $destinationPath, $formUuid, $db);
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// פונקציה להעתקת קובץ רגיל
function copyRegularFile($file, $destinationPath, $formUuid, $db) {
    // בדוק אם קובץ עם אותו שם קיים ביעד
    $newName = getUniqueFileName($file['original_name'], $destinationPath, $formUuid, $db);
    
    // העתק את הקובץ הפיזי
    $sourcePath = UPLOAD_PATH . $formUuid . $file['folder_path'] . '/' . $file['stored_name'];
    $newStoredName = generateUUID() . '.' . $file['file_extension'];
    $destPhysicalPath = UPLOAD_PATH . $formUuid . $destinationPath . '/' . $newStoredName;
    
    // צור תיקיית יעד אם לא קיימת
    $destDir = dirname($destPhysicalPath);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }
    
    if (!copy($sourcePath, $destPhysicalPath)) {
        return ['success' => false, 'message' => 'שגיאה בהעתקת הקובץ'];
    }
    
    // העתק תצוגה מקדימה אם קיימת
    $newThumbnailPath = null;
    if ($file['thumbnail_path']) {
        $thumbSource = UPLOAD_PATH . $formUuid . '/' . $file['thumbnail_path'];
        $thumbDest = UPLOAD_PATH . $formUuid . '/thumbs' . $destinationPath . '/' . $newStoredName;
        
        $thumbDir = dirname($thumbDest);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0777, true);
        }
        
        if (copy($thumbSource, $thumbDest)) {
            $newThumbnailPath = 'thumbs' . $destinationPath . '/' . $newStoredName;
        }
    }
    
    // צור רשומה חדשה בדטהבייס
    $stmt = $db->prepare("
        INSERT INTO form_files (
            form_uuid, file_uuid, original_name, stored_name,
            file_type, file_size, mime_type, file_extension,
            is_folder, folder_path, full_path, thumbnail_path,
            uploaded_by, upload_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())
    ");
    
    $newFullPath = rtrim($destinationPath, '/') . '/' . $newStoredName;
    
    $stmt->execute([
        $formUuid,
        generateUUID(),
        $newName,
        $newStoredName,
        $file['file_type'],
        $file['file_size'],
        $file['mime_type'],
        $file['file_extension'],
        $destinationPath,
        $newFullPath,
        $newThumbnailPath,
        $_SESSION['user_id'] ?? null
    ]);
    
    return ['success' => true];
}

// פונקציה להעתקת תיקייה
function copyFolder($folder, $destinationPath, $formUuid, $db) {
    // צור שם ייחודי לתיקייה ביעד
    $newFolderName = getUniqueFileName($folder['original_name'], $destinationPath, $formUuid, $db);
    $newFolderPath = rtrim($destinationPath, '/') . '/' . $newFolderName;
    
    // צור את התיקייה הפיזית
    $physicalPath = UPLOAD_PATH . $formUuid . $newFolderPath;
    if (!mkdir($physicalPath, 0777, true)) {
        return ['success' => false, 'message' => 'שגיאה ביצירת התיקייה'];
    }
    
    // צור רשומה לתיקייה החדשה
    $stmt = $db->prepare("
        INSERT INTO form_files (
            form_uuid, file_uuid, original_name, stored_name,
            is_folder, folder_path, full_path, uploaded_by, upload_date
        ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $formUuid,
        generateUUID(),
        $newFolderName,
        $newFolderName,
        $destinationPath,
        $newFolderPath,
        $_SESSION['user_id'] ?? null
    ]);
    
    // העתק את כל הקבצים והתיקיות שבתוך התיקייה
    $stmt = $db->prepare("
        SELECT id FROM form_files 
        WHERE form_uuid = ? AND folder_path = ?
    ");
    $stmt->execute([$formUuid, $folder['full_path']]);
    
    while ($subItem = $stmt->fetch()) {
        copyFile($subItem['id'], $newFolderPath, $formUuid, $db);
    }
    
    return ['success' => true];
}

// פונקציה להעברת קובץ
function moveFile($fileId, $destinationPath, $formUuid, $db) {
    try {
        // קבל פרטי הקובץ
        $stmt = $db->prepare("
            SELECT * FROM form_files 
            WHERE id = ? AND form_uuid = ?
        ");
        $stmt->execute([$fileId, $formUuid]);
        $file = $stmt->fetch();
        
        if (!$file) {
            return ['success' => false, 'message' => 'קובץ לא נמצא'];
        }
        
        if ($file['is_folder']) {
            return moveFolder($file, $destinationPath, $formUuid, $db);
        } else {
            return moveRegularFile($file, $destinationPath, $formUuid, $db);
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// פונקציה להעברת קובץ רגיל
function moveRegularFile($file, $destinationPath, $formUuid, $db) {
    // בדוק אם קובץ עם אותו שם קיים ביעד
    $newName = getUniqueFileName($file['original_name'], $destinationPath, $formUuid, $db);
    
    // העבר את הקובץ הפיזי
    $sourcePath = UPLOAD_PATH . $formUuid . $file['folder_path'] . '/' . $file['stored_name'];
    $destPath = UPLOAD_PATH . $formUuid . $destinationPath . '/' . $file['stored_name'];
    
    // צור תיקיית יעד אם לא קיימת
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }
    
    if (!rename($sourcePath, $destPath)) {
        return ['success' => false, 'message' => 'שגיאה בהעברת הקובץ'];
    }
    
    // העבר תצוגה מקדימה אם קיימת
    $newThumbnailPath = null;
    if ($file['thumbnail_path']) {
        $thumbSource = UPLOAD_PATH . $formUuid . '/' . $file['thumbnail_path'];
        $thumbDest = UPLOAD_PATH . $formUuid . '/thumbs' . $destinationPath . '/' . $file['stored_name'];
        
        $thumbDir = dirname($thumbDest);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0777, true);
        }
        
        if (rename($thumbSource, $thumbDest)) {
            $newThumbnailPath = 'thumbs' . $destinationPath . '/' . $file['stored_name'];
        }
    }
    
    // עדכן את הרשומה בדטהבייס
    $newFullPath = rtrim($destinationPath, '/') . '/' . $file['stored_name'];
    
    $stmt = $db->prepare("
        UPDATE form_files 
        SET original_name = ?, folder_path = ?, full_path = ?, thumbnail_path = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $newName,
        $destinationPath,
        $newFullPath,
        $newThumbnailPath,
        $file['id']
    ]);
    
    return ['success' => true];
}

// פונקציה להעברת תיקייה
function moveFolder($folder, $destinationPath, $formUuid, $db) {
    // בדוק אם תיקייה עם אותו שם קיימת ביעד
    $newFolderName = getUniqueFileName($folder['original_name'], $destinationPath, $formUuid, $db);
    $newFolderPath = rtrim($destinationPath, '/') . '/' . $newFolderName;
    
    // העבר את התיקייה הפיזית
    $sourcePhysical = UPLOAD_PATH . $formUuid . $folder['full_path'];
    $destPhysical = UPLOAD_PATH . $formUuid . $newFolderPath;
    
    // צור תיקיית אב אם לא קיימת
    $parentDir = dirname($destPhysical);
    if (!is_dir($parentDir)) {
        mkdir($parentDir, 0777, true);
    }
    
    if (!rename($sourcePhysical, $destPhysical)) {
        return ['success' => false, 'message' => 'שגיאה בהעברת התיקייה'];
    }
    
    // עדכן את התיקייה בדטהבייס
    $stmt = $db->prepare("
        UPDATE form_files 
        SET original_name = ?, folder_path = ?, full_path = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $newFolderName,
        $destinationPath,
        $newFolderPath,
        $folder['id']
    ]);
    
    // עדכן את כל הקבצים והתיקיות שבתוך התיקייה
    $oldPath = $folder['full_path'];
    $stmt = $db->prepare("
        SELECT * FROM form_files 
        WHERE form_uuid = ? AND (folder_path LIKE ? OR full_path LIKE ?)
    ");
    $searchPath = $oldPath . '%';
    $stmt->execute([$formUuid, $searchPath, $searchPath]);
    
    while ($subItem = $stmt->fetch()) {
        // חשב נתיב חדש
        if ($subItem['is_folder']) {
            $newSubPath = str_replace($oldPath, $newFolderPath, $subItem['full_path']);
            $newSubFolderPath = str_replace($oldPath, $newFolderPath, $subItem['folder_path']);
            
            $updateStmt = $db->prepare("
                UPDATE form_files 
                SET folder_path = ?, full_path = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newSubFolderPath, $newSubPath, $subItem['id']]);
        } else {
            $newSubFolderPath = str_replace($oldPath, $newFolderPath, $subItem['folder_path']);
            $newSubFullPath = str_replace($oldPath, $newFolderPath, $subItem['full_path']);
            
            // עדכן גם תצוגה מקדימה
            $newThumbPath = null;
            if ($subItem['thumbnail_path']) {
                $newThumbPath = str_replace('thumbs' . $oldPath, 'thumbs' . $newFolderPath, $subItem['thumbnail_path']);
            }
            
            $updateStmt = $db->prepare("
                UPDATE form_files 
                SET folder_path = ?, full_path = ?, thumbnail_path = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newSubFolderPath, $newSubFullPath, $newThumbPath, $subItem['id']]);
        }
    }
    
    return ['success' => true];
}

// פונקציה לקבלת שם ייחודי
function getUniqueFileName($originalName, $path, $formUuid, $db) {
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $newName = $originalName;
    $counter = 1;
    
    while (true) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM form_files 
            WHERE form_uuid = ? AND folder_path = ? AND original_name = ?
        ");
        $stmt->execute([$formUuid, $path, $newName]);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $newName = $baseName . ' (' . $counter . ')';
        if ($extension) {
            $newName .= '.' . $extension;
        }
        $counter++;
    }
    
    return $newName;
}