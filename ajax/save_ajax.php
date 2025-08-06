<?php
// ajax/save_ajax.php - שמירה אוטומטית עם עדכון סטטוס ודיבוג מפורט
require_once '../config.php';
require_once '../DeceasedForm.php';

// הפעלת דיבוג מפורט
error_reporting(E_ALL);
ini_set('display_errors', 1);

// יצירת לוג ייעודי לדיבוג
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    
    error_log($logMessage);
    
    // כתיבה גם לקובץ לוג ייעודי
    $logFile = '../logs/ajax_save_' . date('Y-m-d') . '.log';
    if (!is_dir('../logs')) {
        mkdir('../logs', 0777, true);
    }
    file_put_contents($logFile, $logMessage . "\n\n", FILE_APPEND);
}

debugLog("=== START AJAX SAVE REQUEST ===");
debugLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("User IP: " . $_SERVER['REMOTE_ADDR']);

header('Content-Type: application/json');

// בדיקת משתמש
if (!isset($_SESSION['user_id'])) {
    debugLog("ERROR: User not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

debugLog("User authenticated: ID = {$_SESSION['user_id']}, Username = " . ($_SESSION['username'] ?? 'N/A'));

// בדיקת CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    debugLog("ERROR: CSRF token mismatch");
    debugLog("Received token: " . ($_POST['csrf_token'] ?? 'none'));
    debugLog("Session token: " . ($_SESSION['csrf_token'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$formUuid = $_POST['form_uuid'] ?? null;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

debugLog("Form UUID: " . ($formUuid ?? 'NULL'));
debugLog("User Permission Level: $userPermissionLevel");

if (!$formUuid) {
    debugLog("ERROR: Form UUID missing");
    echo json_encode(['success' => false, 'message' => 'Form UUID required']);
    exit;
}

// דיבוג של כל הנתונים שהתקבלו (לפני סניטציה)
debugLog("RAW POST DATA:", $_POST);

// סניטציה של הנתונים
$formData = sanitizeInput($_POST);
unset($formData['csrf_token']);

// חשוב! וודא שה-UUID קיים בנתונים
$formData['form_uuid'] = $formUuid;

// דיבוג של הנתונים אחרי סניטציה
debugLog("SANITIZED DATA:", $formData);

// בדיקת שדות קריטיים
$criticalFields = [
    'deceased_name' => $formData['deceased_name'] ?? '',
    'identification_type' => $formData['identification_type'] ?? '',
    'identification_number' => $formData['identification_number'] ?? '',
    'death_date' => $formData['death_date'] ?? '',
    'burial_date' => $formData['burial_date'] ?? ''
];
debugLog("CRITICAL FIELDS:", $criticalFields);

try {
    debugLog("Creating DeceasedForm object for UUID: $formUuid");
    $form = new DeceasedForm($formUuid, $userPermissionLevel);
    
    // בדוק אם הטופס קיים
    $existingData = $form->getFormData();
    
    if (!$existingData) {
        debugLog("Form does not exist - creating new form");
        debugLog("Data to be created:", $formData);
        
        // יצירת טופס חדש
        $form = new DeceasedForm(null, $userPermissionLevel);
        
        // דיבוג לפני יצירה
        debugLog("BEFORE CREATE - Form data structure:", array_keys($formData));
        debugLog("BEFORE CREATE - Required fields check:");
        
        $requiredFields = ['deceased_name', 'death_date', 'burial_date'];
        foreach ($requiredFields as $field) {
            $value = $formData[$field] ?? 'NOT SET';
            debugLog("  - $field: $value");
        }
        
        // ביצוע היצירה
        $createResult = $form->createForm($formData);
        debugLog("Create result: " . ($createResult ? "SUCCESS - UUID: $createResult" : "FAILED"));
        
        // טען מחדש את הטופס
        $form = new DeceasedForm($formUuid, $userPermissionLevel);
        $updatedData = $form->getFormData();
        
        debugLog("Form created successfully");
        debugLog("New form data:", [
            'id' => $updatedData['id'] ?? 'N/A',
            'status' => $updatedData['status'] ?? 'N/A',
            'progress' => $updatedData['progress_percentage'] ?? 'N/A'
        ]);
        
    } else {
        debugLog("Form exists - updating");
        debugLog("Existing form ID: " . ($existingData['id'] ?? 'N/A'));
        debugLog("Current status: " . ($existingData['status'] ?? 'N/A'));
        
        // השוואת שינויים
        $changes = [];
        foreach ($formData as $key => $value) {
            if (isset($existingData[$key]) && $existingData[$key] != $value) {
                $changes[$key] = [
                    'old' => $existingData[$key],
                    'new' => $value
                ];
            }
        }
        
        if (!empty($changes)) {
            debugLog("CHANGES DETECTED:", $changes);
        } else {
            debugLog("No changes detected in form data");
        }
        
        // עדכון טופס קיים
        $updateResult = $form->updateForm($formData);
        debugLog("Update result: " . ($updateResult ? "SUCCESS" : "FAILED"));
        
        $updatedData = $form->getFormData();
    }
    
    // דיבוג של התוצאה הסופית
    debugLog("FINAL FORM STATE:", [
        'id' => $updatedData['id'] ?? 'N/A',
        'uuid' => $updatedData['form_uuid'] ?? 'N/A',
        'status' => $updatedData['status'] ?? 'N/A',
        'progress_percentage' => $updatedData['progress_percentage'] ?? 'N/A',
        'created_at' => $updatedData['created_at'] ?? 'N/A',
        'updated_at' => $updatedData['updated_at'] ?? 'N/A'
    ]);
    
    $response = [
        'success' => true,
        'status' => $updatedData['status'],
        'progress' => $updatedData['progress_percentage'],
        'message' => $updatedData['status'] === 'completed' ? 'הטופס הושלם!' : 'הטופס נשמר כטיוטה',
        // הוסף מידע דיבוג בסביבת פיתוח
        'debug' => DEBUG_MODE ? [
            'form_id' => $updatedData['id'] ?? null,
            'form_uuid' => $formUuid,
            'fields_count' => count($formData),
            'user_id' => $_SESSION['user_id']
        ] : null
    ];
    
    debugLog("RESPONSE:", $response);
    echo json_encode($response);
    
} catch (Exception $e) {
    debugLog("ERROR: Exception caught");
    debugLog("Exception message: " . $e->getMessage());
    debugLog("Exception trace: " . $e->getTraceAsString());
    
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        // בסביבת פיתוח, הוסף מידע מפורט
        'debug' => DEBUG_MODE ? [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace()
        ] : null
    ];
    
    echo json_encode($errorResponse);
}

debugLog("=== END AJAX SAVE REQUEST ===\n");
?>