<?php
// includes/purchase_form_helpers.php - פונקציות עזר לטופס רכישות

// כלול את קובץ form_auth.php אם לא נכלל כבר
if (!function_exists('handleFormAuth')) {
    require_once 'form_auth.php';
}

/**
 * טיפול באימות והרשאות
 */
function handlePurchaseFormAuth() {
    // אתחול משתנים
    $isLinkAccess = false;
    $linkPermissions = null;
    $viewOnly = false;
    $formUuid = $_GET['uuid'] ?? null;
    $userPermissionLevel = $_SESSION['permission_level'] ?? 0;
    
    // בדיקת גישה דרך קישור
    if (isset($_GET['token']) && !isset($_SESSION['user_id'])) {
        $linkResult = validateShareLink($_GET['token']);
        if ($linkResult['valid']) {
            $isLinkAccess = true;
            $linkPermissions = $linkResult['permissions'];
            $viewOnly = ($linkPermissions === 'view');
            $formUuid = $linkResult['form_uuid'];
        } else {
            header("Location: ../login.php?error=invalid_link");
            exit;
        }
    } else {
        // בדיקת הרשאות משתמש רגיל
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../login.php");
            exit;
        }
        
        // בדיקת הרשאת צפייה בלבד לפי רמת הרשאה
        if ($userPermissionLevel < 2) {
            $viewOnly = true;
        }
    }
    
    return [
        'isLinkAccess' => $isLinkAccess,
        'linkPermissions' => $linkPermissions,
        'viewOnly' => $viewOnly,
        'formUuid' => $formUuid,
        'userPermissionLevel' => $userPermissionLevel
    ];
}

/**
 * טיפול בנתוני הטופס
 */
function handlePurchaseFormData($formUuid, $userPermissionLevel) {
    global $pdo;
    
    // בדיקה שיש חיבור למסד
    if (!isset($pdo) || $pdo === null) {
        die('שגיאה: אין חיבור למסד הנתונים. אנא בדוק את קובץ config.php');
    }
    
    $form = new PurchaseForm($pdo);
    $isNewForm = empty($formUuid);
    $formData = [];
    $successMessage = null;
    $errorMessage = null;
    $errors = [];
    
    if ($isNewForm) {
        // יצירת טופס חדש
        $result = $form->createForm($_SESSION['user_id'] ?? null);
        if ($result['success']) {
            $formUuid = $result['formUuid'];
            // הפניה לעמוד הטופס עם ה-UUID החדש
            header("Location: purchase_form.php?uuid=" . $formUuid);
            exit;
        } else {
            $errorMessage = $result['error'];
        }
    } else {
        // טעינת טופס קיים
        $result = $form->loadForm($formUuid);
        if ($result['success']) {
            $formData = $result['formData'];
        } else {
            $errorMessage = $result['error'];
        }
    }
    
    return [
        'isNewForm' => $isNewForm,
        'formData' => $formData,
        'form' => $form,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
        'errors' => $errors
    ];
}

/**
 * קבלת נתוני עזר לטופס
 */
function getPurchaseFormHelpers($form, $formData, $userPermissionLevel) {
    global $pdo;
    
    // שדות חובה
    $requiredFields = $form->getRequiredFields();
    
    // רשימת בתי עלמין
    $cemeteries = getCemeteries($pdo);
    
    // רשימות דינמיות לפי הבחירות
    $blocks = [];
    $sections = [];
    $rows = [];
    $graves = [];
    $plots = [];
    
    if (!empty($formData['cemetery_id'])) {
        $blocks = getBlocks($pdo, $formData['cemetery_id']);
    }
    if (!empty($formData['block_id'])) {
        $sections = getSections($pdo, $formData['block_id']);
    }
    if (!empty($formData['section_id'])) {
        $rows = getRows($pdo, $formData['section_id']);
    }
    if (!empty($formData['row_id'])) {
        $graves = getGraves($pdo, $formData['row_id']);
    }
    if (!empty($formData['grave_id'])) {
        $plots = getPlots($pdo, $formData['grave_id']);
    }
    
    // סוגי רכישה ואמצעי תשלום
    $purchaseTypes = PurchaseForm::getPurchaseTypes();
    $paymentMethods = PurchaseForm::getPaymentMethods();
    
    return [
        'requiredFields' => $requiredFields,
        'cemeteries' => $cemeteries,
        'blocks' => $blocks,
        'sections' => $sections,
        'rows' => $rows,
        'graves' => $graves,
        'plots' => $plots,
        'purchaseTypes' => $purchaseTypes,
        'paymentMethods' => $paymentMethods
    ];
}

/**
 * טיפול בשליחת הטופס
 */
function handlePurchaseFormSubmit($form, $formUuid, $isNewForm, $userPermissionLevel) {
    // בדיקת CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return [
            'success' => false,
            'message' => 'שגיאת אבטחה. נא לרענן את הדף ולנסות שוב.'
        ];
    }
    
    // קבלת הפעולה
    $action = $_POST['action'] ?? 'save';
    
    // הכנת הנתונים
    $data = $_POST;
    $data['submit_action'] = $action;
    
    // שמירת הטופס
    $result = $form->saveForm($data, $_SESSION['user_id'] ?? null);
    
    if ($result['success']) {
        // טיפול לפי סוג הפעולה
        switch ($action) {
            case 'submit':
                // שליחת התראות למנהלים
                sendPurchaseFormNotifications($formUuid, 'submitted');
                $_SESSION['purchase_form_saved_message'] = 'הטופס נשלח לאישור בהצלחה';
                break;
                
            case 'save':
            default:
                $_SESSION['purchase_form_saved_message'] = 'הטופס נשמר בהצלחה';
                break;
        }
        
        return [
            'success' => true,
            'message' => $result['message'],
            'redirect' => "purchase_form.php?uuid=$formUuid"
        ];
    }
    
    return [
        'success' => false,
        'message' => $result['error'] ?? 'שגיאה בשמירת הטופס',
        'errors' => $result['errors'] ?? []
    ];
}

/**
 * בדיקת תוקף קישור שיתוף
 */
function validateShareLink($token) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM purchase_form_share_links 
            WHERE token = ? 
            AND expires_at > NOW()
            AND used_count < max_uses
        ");
        $stmt->execute([$token]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            // עדכון מספר השימושים
            $stmt = $pdo->prepare("
                UPDATE purchase_form_share_links 
                SET used_count = used_count + 1,
                    last_used_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$link['id']]);
            
            return [
                'valid' => true,
                'form_uuid' => $link['form_uuid'],
                'permissions' => $link['permissions']
            ];
        }
    } catch (Exception $e) {
        error_log("Error validating share link: " . $e->getMessage());
    }
    
    return ['valid' => false];
}

/**
 * שליחת התראות
 */
function sendPurchaseFormNotifications($formUuid, $event) {
    global $pdo;
    
    try {
        // קבלת פרטי הטופס
        $stmt = $pdo->prepare("
            SELECT pf.*, u.email as submitter_email, u.username as submitter_name
            FROM purchase_forms pf
            LEFT JOIN users u ON pf.user_id = u.id
            WHERE pf.form_uuid = ?
        ");
        $stmt->execute([$formUuid]);
        $formData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$formData) return;
        
        // קבלת רשימת מנהלים
        $stmt = $pdo->prepare("
            SELECT email, username 
            FROM users 
            WHERE permission_level >= 4 
            AND email IS NOT NULL
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // הכנת תוכן ההתראה
        $subject = "טופס רכישה חדש ממתין לאישור";
        $purchaserName = $formData['purchaser_first_name'] . ' ' . $formData['purchaser_last_name'];
        
        $message = "
            <h3>טופס רכישה חדש הוגש</h3>
            <p><strong>שם הרוכש:</strong> $purchaserName</p>
            <p><strong>ת.ז.:</strong> {$formData['purchaser_id']}</p>
            <p><strong>תאריך הגשה:</strong> " . date('d/m/Y H:i') . "</p>
            <p><strong>הוגש על ידי:</strong> {$formData['submitter_name']}</p>
            <br>
            <p><a href='" . SITE_URL . "/form/purchase_form.php?uuid=$formUuid'>לחץ כאן לצפייה בטופס</a></p>
        ";
        
        // שליחת אימיילים למנהלים
        foreach ($admins as $admin) {
            sendEmail($admin['email'], $subject, $message);
        }
        
        // יצירת התראה במערכת
        createSystemNotification(
            'purchase_form_submitted',
            "טופס רכישה חדש הוגש על ידי {$formData['submitter_name']}",
            ['form_uuid' => $formUuid]
        );
        
    } catch (Exception $e) {
        error_log("Error sending purchase form notifications: " . $e->getMessage());
    }
}

/**
 * פונקציות עזר לקבלת נתונים
 */
function getCemeteries($pdo) {
    $stmt = $pdo->prepare("SELECT id, name FROM cemeteries WHERE active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBlocks($pdo, $cemeteryId) {
    $stmt = $pdo->prepare("SELECT id, name FROM blocks WHERE cemetery_id = ? AND active = 1 ORDER BY name");
    $stmt->execute([$cemeteryId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSections($pdo, $blockId) {
    $stmt = $pdo->prepare("SELECT id, name FROM sections WHERE block_id = ? AND active = 1 ORDER BY name");
    $stmt->execute([$blockId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRows($pdo, $sectionId) {
    $stmt = $pdo->prepare("SELECT id, row_number FROM rows WHERE section_id = ? ORDER BY row_number");
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGraves($pdo, $rowId) {
    $stmt = $pdo->prepare("SELECT id, grave_number FROM graves WHERE row_id = ? ORDER BY grave_number");
    $stmt->execute([$rowId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPlots($pdo, $graveId) {
    $stmt = $pdo->prepare("
        SELECT id, plot_number, status 
        FROM plots 
        WHERE grave_id = ? 
        ORDER BY plot_number
    ");
    $stmt->execute([$graveId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * יצירת CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// יצירת הטוקן בעת טעינת הקובץ
generateCSRFToken();