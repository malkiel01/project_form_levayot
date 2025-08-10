<?php
// form/includes/form_auth.php - תיקון לתמיכה במבנה ההיררכיה הנכון

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

function handleFormData($formUuid, $userPermissionLevel) {
    $isNewForm = false;
    $formData = [];
    $form = null;
    $successMessage = null;
    $errorMessage = null;
    $errors = [];
    
    if (!$formUuid) {
        // טופס חדש - בדיקת הרשאה
        if ($userPermissionLevel < 2) {
            header('Location: ../' . LOGIN_URL);
            exit;
        }
        
        $formUuid = generateUUID();
        $isNewForm = true;
        header("Location: ../" . FORM_URL . "?id=" . $formUuid);
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
    
    // קבלת רשימת שדות חובה - המערכת משתמשת במבנה החדש עם JSON
    $requiredFields = [];
    if ($form) {
        // קבל את כל השדות שמוגדרים כחובה
        $stmt = $db->query("
            SELECT field_name, view_permission_levels, is_required 
            FROM field_permissions 
            WHERE is_required = 1
        ");
        $allRequiredFields = $stmt->fetchAll();
        
        foreach ($allRequiredFields as $field) {
            if ($field['view_permission_levels']) {
                $allowedLevels = json_decode($field['view_permission_levels'], true);
                if (is_array($allowedLevels) && in_array($userPermissionLevel, $allowedLevels)) {
                    $requiredFields[] = $field['field_name'];
                }
            }
        }
    }
    
    // אתחול כל המשתנים
    $cemeteries = $form ? $db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name")->fetchAll() : [];
    $blocks = [];
    $plots = [];
    $rows = [];
    $areaGraves = [];
    $graves = [];
    
    // טעינה היררכית לפי המבנה הנכון
    if (!empty($formData['cemetery_id'])) {
        // טען גושים לפי בית עלמין
        $blocksStmt = $db->prepare("SELECT id, name FROM blocks WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $blocksStmt->execute([$formData['cemetery_id']]);
        $blocks = $blocksStmt->fetchAll();
        
        if (!empty($formData['block_id'])) {
            // טען חלקות לפי גוש
            $plotsStmt = $db->prepare("SELECT id, name FROM plots WHERE block_id = ? AND is_active = 1 ORDER BY name");
            $plotsStmt->execute([$formData['block_id']]);
            $plots = $plotsStmt->fetchAll();
            
            if (!empty($formData['plot_id'])) {
                // טען שורות לפי חלקה
                $rowsStmt = $db->prepare("SELECT id, name FROM rows WHERE plot_id = ? AND is_active = 1 ORDER BY name");
                $rowsStmt->execute([$formData['plot_id']]);
                $rows = $rowsStmt->fetchAll();
                
                if (!empty($formData['row_id'])) {
                    // טען אחוזות קבר לפי שורה
                    $areaGravesStmt = $db->prepare("SELECT id, name FROM areaGraves WHERE row_id = ? AND is_active = 1 ORDER BY name");
                    $areaGravesStmt->execute([$formData['row_id']]);
                    $areaGraves = $areaGravesStmt->fetchAll();
                    
                    if (!empty($formData['areaGrave_id'])) {
                        // טען קברים לפי אחוזת קבר
                        $gravesStmt = $db->prepare("SELECT id, name FROM graves WHERE areaGrave_id = ? AND is_available = 1 ORDER BY name");
                        $gravesStmt->execute([$formData['areaGrave_id']]);
                        $graves = $gravesStmt->fetchAll();
                    }
                }
            }
        }
    }
    
    return compact('requiredFields', 'cemeteries', 'blocks', 'plots', 'rows', 'areaGraves', 'graves');
}

function handleFormSubmit($form, $formUuid, $isNewForm, $userPermissionLevel) {
    $result = ['success' => false, 'message' => '', 'redirect' => null];
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $result['message'] = 'שגיאת אבטחה. אנא רענן את הדף ונסה שוב.';
        return $result;
    }
    
    try {
        if ($isNewForm) {
            // הוסף את ה-UUID לנתונים
            $_POST['form_uuid'] = $formUuid;
            
            $newFormId = $form->createForm($_POST);
            
            if ($newFormId) {
                $_SESSION['form_saved_message'] = 'הטופס נוצר בהצלחה!';
                $result['success'] = true;
                $result['redirect'] = "index_deceased.php?id={$formUuid}&saved=1";
            } else {
                $result['message'] = 'שגיאה ביצירת הטופס';
            }
        } else {
            if ($form->updateForm($_POST)) {
                $result['success'] = true;
                $result['message'] = 'הטופס עודכן בהצלחה!';
                $result['formData'] = $form->getFormData();
            } else {
                $result['message'] = 'שגיאה בעדכון הטופס';
            }
        }
    } catch (Exception $e) {
        $result['message'] = 'שגיאה: ' . $e->getMessage();
        error_log("Form submit error: " . $e->getMessage());
    }
    
    return $result;
}

function showAccessDenied() {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <title>גישה נדחתה</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger text-center">
                <h4>גישה נדחתה</h4>
                <p>אין לך הרשאה לצפות בטופס זה.</p>
                <a href="<?= DASHBOARD_FULL_URL ?>" class="btn btn-primary">חזור לדשבורד</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function showInvalidLink() {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <title>קישור לא תקף</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-warning text-center">
                <h4>קישור לא תקף</h4>
                <p>הקישור שביקשת אינו תקף או שפג תוקפו.</p>
                <a href="<?= DASHBOARD_FULL_URL ?>" class="btn btn-primary">עבור לדשבורד</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function logLinkAccess($linkUuid, $formUuid) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            UPDATE form_links 
            SET access_count = access_count + 1, 
                last_accessed = CURRENT_TIMESTAMP 
            WHERE link_uuid = ?
        ");
        $stmt->execute([$linkUuid]);
        
        // רישום בלוג
        $logStmt = $db->prepare("
            INSERT INTO activity_log (user_id, form_uuid, action, details, ip_address) 
            VALUES (?, ?, 'link_access', ?, ?)
        ");
        $logStmt->execute([
            getSafeUserId(),
            $formUuid,
            json_encode(['link_uuid' => $linkUuid]),
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log link access: " . $e->getMessage());
    }
}
?>