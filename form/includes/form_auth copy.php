<?php
// form/includes/form_auth.php - ניהול אימות והרשאות מתוקן

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getSafeUserId() {
    return isUserLoggedIn() ? $_SESSION['user_id'] : null;
}

function getSafePermissionLevel() {
    return isset($_SESSION['permission_level']) ? $_SESSION['permission_level'] : 1;
}

function handleFormAuth() {
    $isLinkAccess = false;
    $linkPermissions = null;
    $viewOnly = false;
    $formUuid = null;
    
    if (isset($_GET['link'])) {
        $linkUuid = $_GET['link'];
        
        $db = getDbConnection();
        $linkStmt = $db->prepare("
            SELECT * FROM form_links 
            WHERE link_uuid = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $linkStmt->execute([$linkUuid]);
        $linkData = $linkStmt->fetch();
        
        if ($linkData) {
            $isLinkAccess = true;
            
            // בדיקת הרשאות
            $accessGranted = true;
            if ($linkData['allowed_user_ids']) {
                $allowedUsers = json_decode($linkData['allowed_user_ids'], true);
                $currentUserId = getSafeUserId();
                if (!$currentUserId || !in_array($currentUserId, $allowedUsers)) {
                    $accessGranted = false;
                }
            }
            
            if ($accessGranted) {
                $linkPermissions = [
                    'form_uuid' => $linkData['form_uuid'],
                    'permission_level' => isUserLoggedIn() ? 
                        getSafePermissionLevel() : $linkData['permission_level'],
                    'can_edit' => $linkData['can_edit'],
                    'link_uuid' => $linkUuid
                ];
                
                if (!$linkData['can_edit']) {
                    $viewOnly = true;
                }
                
                $formUuid = $linkData['form_uuid'];
                
                // רישום גישה
                logLinkAccess($linkUuid, $formUuid);
                
            } else {
                showAccessDenied();
            }
        } else {
            showInvalidLink();
        }
    } else {
        // כניסה רגילה - דורשת התחברות
        if (!isUserLoggedIn()) {
            header('Location: ../' . LOGIN_URL);
            exit;
        }
    }
    
    // קביעת רמת הרשאה
    if ($isLinkAccess && $linkPermissions) {
        $userPermissionLevel = $linkPermissions['permission_level'];
    } else {
        $userPermissionLevel = getSafePermissionLevel();
    }
    
    // בדיקה אם יש ID של טופס ב-URL
    if (!$formUuid) {
        $formUuid = $_GET['id'] ?? null;
    }
    
    return compact('isLinkAccess', 'linkPermissions', 'viewOnly', 'formUuid', 'userPermissionLevel');
}

function logLinkAccess($linkUuid, $formUuid) {
    $db = getDbConnection();
    
    // רישום גישה בלוג - רק אם המשתמש מחובר
    if (isUserLoggedIn()) {
        $logStmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
            VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'access_via_link', ?, ?, ?)
        ");
        $logStmt->execute([
            getSafeUserId(),
            $formUuid,
            json_encode(['link_uuid' => $linkUuid]),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    $updateStmt = $db->prepare("
        UPDATE form_links 
        SET last_used = NOW(), use_count = use_count + 1 
        WHERE link_uuid = ?
    ");
    $updateStmt->execute([$linkUuid]);
}

function showAccessDenied() {
    $loginUrl = '../' . LOGIN_URL . '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>נדרשת התחברות</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
            <style>
                body { background-color: #f8f9fa; }
                .auth-container { 
                    max-width: 500px; 
                    margin: 100px auto; 
                    padding: 30px; 
                    background: white; 
                    border-radius: 10px; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.1); 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="auth-container text-center">
                    <i class="fas fa-lock fa-3x text-warning mb-3"></i>
                    <h3>נדרשת התחברות</h3>
                    <p class="text-muted">הקישור הזה מוגבל למשתמשים ספציפיים בלבד.</p>
                    <p>אנא התחבר למערכת כדי לגשת לטופס.</p>
                    <div class="mt-4">
                        <a href="' . $loginUrl . '" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> התחבר למערכת
                        </a>
                        <a href="../forms_list.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
    ');
}

function showInvalidLink() {
    $loginUrl = LOGIN_URL; // אם זה קבוע
    die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>קישור לא תקף</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
            <style>
                body { background-color: #f8f9fa; }
                .error-container { 
                    max-width: 500px; 
                    margin: 100px auto; 
                    padding: 30px; 
                    background: white; 
                    border-radius: 10px; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.1); 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-container text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h3>קישור לא תקף</h3>
                    <p class="text-muted">הקישור אינו קיים, פג תוקפו, או שאינו תקין.</p>
                    <div class="mt-4">
                        <a href="../' . $loginUrl . '" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> עבור לדף הכניסה
                        </a>
                        <a href="../forms_list.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
    ');
}

function handleFormData($formUuid, $userPermissionLevel) {
    $isNewForm = false;
    $formData = [];
    $form = null;
    $successMessage = null;
    $errorMessage = null;
    $errors = [];
    
    if (!$formUuid) {
        // יצירת טופס חדש - רק למשתמשים מחוברים
        if (!isUserLoggedIn()) {
            header('Location: ../' . LOGIN_URL);
            exit;
        }
        
        $formUuid = generateUUID();
        $isNewForm = true;
        header("Location: index.php?id=" . $formUuid);
        exit;
    } else {
        // טעינת טופס קיים
        $form = new DeceasedForm($formUuid, $userPermissionLevel);
        $existingFormData = $form->getFormData();

        if (!$existingFormData) {
            $isNewForm = true;
            $formData = [];
            $form = new DeceasedForm(null, $userPermissionLevel);
        } else {
            $isNewForm = false;
            $formData = $existingFormData;
        }

        if (isset($_GET['saved'])) {
            $formData = $form->getFormData();
        }
    }
    
    error_log("FORM ACCESS - UUID: $formUuid, Is New: " . ($isNewForm ? 'YES' : 'NO') . ", User: " . getSafeUserId());
    
    return compact('isNewForm', 'formData', 'form', 'successMessage', 'errorMessage', 'errors');
}

function getFormHelpers($form, $formData, $userPermissionLevel) {
    $db = getDbConnection();
    
    // קבלת רשימת שדות חובה
    $requiredFields = [];
    if ($form) {
        $stmt = $db->prepare("
            SELECT field_name 
            FROM field_permissions 
            WHERE permission_level = ? AND is_required = 1
        ");
        $stmt->execute([$userPermissionLevel]);
        $requiredFields = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // קבלת רשימות לשדות תלויים
    $cemeteries = $form ? $form->getCemeteries() : [];
    $blocks = [];
    $sections = [];
    $rows = [];
    $graves = [];
    $plots = [];
    
    if (!empty($formData)) {
        if (!empty($formData['cemetery_id'])) {
            $blocks = $form->getBlocks($formData['cemetery_id']);
            $plots = $form->getPlots($formData['cemetery_id']);
        }
        if (!empty($formData['block_id'])) {
            $sections = $form->getSections($formData['block_id']);
        }
        if (!empty($formData['section_id'])) {
            $rows = $form->getRows($formData['section_id']);
        }
        if (!empty($formData['row_id'])) {
            $graves = $form->getGraves($formData['row_id']);
        }
    }
    
    return compact('requiredFields', 'cemeteries', 'blocks', 'sections', 'rows', 'graves', 'plots');
}

function handleFormSubmit($form, $formUuid, $isNewForm, $userPermissionLevel) {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return ['success' => false, 'message' => 'Invalid CSRF token'];
    }
    
    // סניטציה של הנתונים
    $postData = sanitizeInput($_POST);
    unset($postData['csrf_token']);
    
    // בדיקה אם לחצו על כפתור "שמור וצפה"
    $saveAndView = isset($_POST['save_and_view']);
    
    // ולידציה של פורמט
    $formatErrors = [];
    
    // בדיקת פורמט תעודת זהות אם קיימת
    if (!empty($postData['identification_type']) && $postData['identification_type'] === 'tz' && 
        !empty($postData['identification_number']) && !validateIsraeliId($postData['identification_number'])) {
        $formatErrors['identification_number'] = "מספר תעודת זהות לא תקין";
    }
    
    // בדיקת תאריכים
    if (!empty($postData['death_date']) && !empty($postData['burial_date'])) {
        if (strtotime($postData['burial_date']) < strtotime($postData['death_date'])) {
            $formatErrors['burial_date'] = "תאריך הקבורה לא יכול להיות לפני תאריך הפטירה";
        }
    }
    
    if (!empty($formatErrors)) {
        return ['success' => false, 'errors' => $formatErrors];
    }
    
    try {
        if ($isNewForm) {
            $postData['form_uuid'] = $formUuid;
            $postData['status'] = 'draft';
            $form->createForm($postData);
            
            // וידוא שהשמירה הסתיימה
            $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
            $verifyData = $verifyForm->getFormData();
            
            $attempts = 0;
            while (!$verifyData && $attempts < 10) {
                usleep(500000);
                $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
                $verifyData = $verifyForm->getFormData();
                $attempts++;
            }
            
            if ($verifyData) {
                $_SESSION['form_saved_message'] = "הטופס נוצר בהצלחה";
                
                if ($saveAndView) {
                    return ['success' => true, 'redirect' => "view_form.php?id=" . $formUuid];
                } else {
                    return ['success' => true, 'redirect' => "index.php?id=" . $formUuid . "&saved=1"];
                }
            } else {
                return ['success' => false, 'message' => "שגיאה בשמירת הטופס. אנא נסה שוב."];
            }
            
        } else {
            $form->updateForm($postData);
            
            $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
            $verifyData = $verifyForm->getFormData();
            
            if ($saveAndView) {
                $_SESSION['form_saved_message'] = "הטופס עודכן בהצלחה";
                return ['success' => true, 'redirect' => "view_form.php?id=" . $formUuid];
            } else {
                return [
                    'success' => true, 
                    'message' => "הטופס עודכן בהצלחה",
                    'formData' => $verifyData
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Form save error: " . $e->getMessage());
        return ['success' => false, 'message' => "שגיאה בשמירת הטופס: " . $e->getMessage()];
    }
}