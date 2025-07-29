<?php
// admin/delete_form.php - מחיקת טופס
require_once '../config.php';

header('Content-Type: application/json');

// בדיקת הרשאות מנהל
if (($_SESSION['permission_level'] ?? 0) < 4) {
    echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$formId = $_POST['form_id'] ?? null;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID missing']);
    exit;
}

try {
    $db = getDbConnection();
    
    // מחיקת הטופס (המסמכים יימחקו אוטומטית בגלל ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM deceased_forms WHERE form_uuid = ?");
    $result = $stmt->execute([$formId]);
    
    if ($result) {
        // מחיקת תיקיית המסמכים
        $uploadPath = UPLOAD_PATH . $formId . '/';
        if (is_dir($uploadPath)) {
            // מחיקת כל הקבצים בתיקייה
            $files = glob($uploadPath . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($uploadPath);
        }
        
        echo json_encode(['success' => true, 'message' => 'הטופס נמחק בהצלחה']);
    } else {
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת הטופס']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}