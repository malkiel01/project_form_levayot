<?php
// form.php - גרסה מתוקנת עם טיפול בשגיאות

require_once 'config.php';
require_once 'DeceasedForm.php';

// בדיקת כניסה מקישור שיתוף
$isLinkAccess = false;
$linkPermissions = null;
$viewOnly = false;
$formUuid = null;

// פונקציה לבדיקת משתמש מחובר
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// פונקציה לקבלת ID משתמש בטוח
function getSafeUserId() {
    return isUserLoggedIn() ? $_SESSION['user_id'] : null;
}

// פונקציה לקבלת רמת הרשאה בטוחה
function getSafePermissionLevel() {
    return isset($_SESSION['permission_level']) ? $_SESSION['permission_level'] : 1;
}

if (isset($_GET['link'])) {
    $linkUuid = $_GET['link'];
    
    // בדוק תקינות הקישור
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
        
        // בדוק אם יש הגבלה על משתמשים ספציפיים
        $accessGranted = true;
        if ($linkData['allowed_user_ids']) {
            $allowedUsers = json_decode($linkData['allowed_user_ids'], true);
            $currentUserId = getSafeUserId();
            if (!$currentUserId || !in_array($currentUserId, $allowedUsers)) {
                $accessGranted = false;
            }
        }
        
        if ($accessGranted) {
            // הגדר הרשאות זמניות
            $linkPermissions = [
                'form_uuid' => $linkData['form_uuid'],
                'permission_level' => isUserLoggedIn() ? 
                    getSafePermissionLevel() : $linkData['permission_level'],
                'can_edit' => $linkData['can_edit'],
                'link_uuid' => $linkUuid
            ];
            
            // אם אין הרשאת עריכה
            if (!$linkData['can_edit']) {
                $viewOnly = true;
            }
            
            // הגדר את ה-UUID של הטופס
            $formUuid = $linkData['form_uuid'];
            
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
            
            // עדכן זמן שימוש אחרון בקישור
            $updateStmt = $db->prepare("
                UPDATE form_links 
                SET last_used = NOW(), use_count = use_count + 1 
                WHERE link_uuid = ?
            ");
            $updateStmt->execute([$linkUuid]);
            
        } else {
            // הצג הודעת הרשאה ידידותית יותר
            $loginUrl = 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
            die('
                <!DOCTYPE html>
                <html dir="rtl" lang="he">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>נדרשת התחברות</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                                <a href="forms_list.php" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> רשימת טפסים
                                </a>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            ');
        }
    } else {
        // קישור לא תקף - הודעה ידידותית יותר
        die('
            <!DOCTYPE html>
            <html dir="rtl" lang="he">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>קישור לא תקף</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> עבור לדף הכניסה
                            </a>
                            <a href="forms_list.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i> רשימת טפסים
                            </a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ');
    }
} else {
    // כניסה רגילה - דורשת התחברות
    if (!isUserLoggedIn()) {
        header('Location: login.php');
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

// משתנים לשימוש בהמשך
$isNewForm = false;
$formData = [];
$form = null;

// אם אין ID בכתובת - יצירת טופס חדש (רק למשתמשים מחוברים)
if (!$formUuid) {
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    // יצירת UUID חדש
    $formUuid = generateUUID();
    $isNewForm = true;
    
    // הפניה לכתובת עם ה-UUID החדש
    header("Location: form.php?id=" . $formUuid);
    exit;
} else {
    // יש UUID - בדוק אם הטופס קיים
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

    // אם יש סימן ששמרנו הרגע, טען את הנתונים מחדש באופן מפורש
    if (isset($_GET['saved'])) {
        $formData = $form->getFormData();
    }
}

// רישום בלוג - רק ללוג, לא לפלט
error_log("FORM ACCESS - UUID: $formUuid, Is New: " . ($isNewForm ? 'YES' : 'NO') . ", User: " . getSafeUserId());

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקה שהמשתמש מורשה לערוך
    if ($viewOnly) {
        die('אין לך הרשאה לערוך טופס זה');
    }
    
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
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
    
    if (empty($formatErrors)) {
        try {
            if ($isNewForm) {
                // יצירת טופס חדש
                $postData['form_uuid'] = $formUuid;
                $postData['status'] = 'draft';
                $form->createForm($postData);
                
                // חשוב! וודא שהשמירה הסתיימה על ידי טעינת הנתונים חזרה
                $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
                $verifyData = $verifyForm->getFormData();
                
                // המתן עד 5 שניות לשמירה
                $attempts = 0;
                while (!$verifyData && $attempts < 10) {
                    usleep(500000); // חצי שנייה
                    $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
                    $verifyData = $verifyForm->getFormData();
                    $attempts++;
                }
                
                if ($verifyData) {
                    // השמירה הצליחה
                    $_SESSION['form_saved_message'] = "הטופס נוצר בהצלחה";
                    
                    if ($saveAndView) {
                        header("Location: view_form.php?id=" . $formUuid);
                        exit;
                    } else {
                        header("Location: form.php?id=" . $formUuid . "&saved=1");
                        exit;
                    }
                } else {
                    // השמירה נכשלה
                    $errorMessage = "שגיאה בשמירת הטופס. אנא נסה שוב.";
                }
                
            } else {
                // עדכון טופס קיים
                $form->updateForm($postData);
                
                // וודא שהעדכון הסתיים
                $verifyForm = new DeceasedForm($formUuid, $userPermissionLevel);
                $verifyData = $verifyForm->getFormData();
                
                if ($saveAndView) {
                    $_SESSION['form_saved_message'] = "הטופס עודכן בהצלחה";
                    header("Location: view_form.php?id=" . $formUuid);
                    exit;
                } else {
                    // טען מחדש את הנתונים המעודכנים
                    $formData = $verifyData;
                    $successMessage = "הטופס עודכן בהצלחה";
                }
            }
        } catch (Exception $e) {
            $errorMessage = "שגיאה בשמירת הטופס: " . $e->getMessage();
            error_log("Form save error: " . $e->getMessage());
        }
    } else {
        $errors = $formatErrors;
    }
}

// בדוק אם יש הודעת הצלחה מהסשן
if (isset($_SESSION['form_saved_message'])) {
    $successMessage = $_SESSION['form_saved_message'];
    unset($_SESSION['form_saved_message']);
}

// קבלת רשימות לשדות תלויים (אם יש נתונים)
$cemeteries = $form ? $form->getCemeteries() : [];
$blocks = [];
$sections = [];
$rows = [];
$graves = [];
$plots = [];

// אם יש נתונים קיימים, טען את הרשימות הרלוונטיות
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

// קבלת רשימת שדות חובה
$requiredFields = [];
if ($form) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT field_name 
        FROM field_permissions 
        WHERE permission_level = ? AND is_required = 1
    ");
    $stmt->execute([$userPermissionLevel]);
    $requiredFields = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>טופס הזנת נפטר <?= $isNewForm ? '- חדש' : '- עריכה' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            max-width: 900px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin: 20px -15px 15px -15px;
            border-right: 4px solid #007bff;
            font-weight: bold;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .progress {
            height: 30px;
            margin-bottom: 20px;
        }
        .signature-pad {
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
            height: 200px;
            cursor: crosshair;
        }
        .field-disabled {
            background-color: #e9ecef !important;
            cursor: not-allowed !important;
        }
        .form-uuid-display {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
        }
        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status-draft {
            background-color: #6c757d;
            color: white;
        }
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        .field-missing {
            border-color: #ffc107 !important;
        }
        .missing-fields-alert {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .spinner-container {
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .loading-overlay .progress {
            height: 25px;
        }
        .user-status {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1000;
            font-size: 0.8rem;
        }
        .link-access-notice {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- מחוון סטטוס משתמש -->
    <div class="user-status">
        <?php if (isUserLoggedIn()): ?>
            <span class="badge bg-success">
                <i class="fas fa-user"></i> מחובר: <?= htmlspecialchars($_SESSION['username'] ?? 'משתמש') ?>
            </span>
        <?php else: ?>
            <span class="badge bg-warning">
                <i class="fas fa-user-slash"></i> אורח
            </span>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay משופר -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-container">
                <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                    <span class="visually-hidden">טוען...</span>
                </div>
            </div>
            <h3 class="text-white mt-3">שומר נתונים...</h3>
            <div class="progress mt-3" style="width: 300px; margin: 0 auto;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                    role="progressbar" style="width: 100%"></div>
            </div>
            <p class="text-white mt-2">אנא המתן, מעדכן את הטופס במערכת...</p>
        </div>
    </div>

    <div class="container">
        <div class="form-container">
            <!-- הודעה על גישה בקישור -->
            <?php if ($isLinkAccess): ?>
            <div class="link-access-notice">
                <i class="fas fa-link"></i>
                <strong>גישה בקישור שיתוף</strong> - 
                <?php if (isUserLoggedIn()): ?>
                    אתה נכנס דרך קישור שיתוף כמשתמש מחובר
                <?php else: ?>
                    אתה נכנס דרך קישור שיתוף כאורח
                <?php endif; ?>
                <?php if ($viewOnly): ?>
                    <br><small><i class="fas fa-eye"></i> צפייה בלבד - לא ניתן לערוך</small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <h2 class="text-center mb-4">
                <i class="fas fa-file-alt"></i> טופס הזנת נפטר
                <?php if ($isNewForm): ?>
                    <span class="badge bg-success">חדש</span>
                <?php else: ?>
                    <span class="badge bg-info">עריכה</span>
                <?php endif; ?>
            </h2>
            
            <!-- הצגת UUID -->
            <div class="form-uuid-display text-center">
                <small>מספר טופס: <strong><?= $formUuid ?></strong></small>
                <small>גירסה: <strong>7.0</strong></small>
            </div>
            
            <!-- הצגת סטטוס -->
            <?php if (!$isNewForm): ?>
            <div class="text-center mb-3">
                <span class="status-indicator <?= ($formData['status'] ?? 'draft') === 'completed' ? 'status-completed' : 'status-draft' ?>">
                    <?= ($formData['status'] ?? 'draft') === 'completed' ? 'הושלם' : 'טיוטה' ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $successMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $errorMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Progress Bar -->
            <div class="progress">
                <div class="progress-bar" role="progressbar" 
                     style="width: <?= $formData['progress_percentage'] ?? 0 ?>%">
                    <?= $formData['progress_percentage'] ?? 0 ?>% הושלם
                </div>
            </div>
            
            <!-- התראה על שדות חסרים -->
            <div id="missingFieldsAlert" class="missing-fields-alert" style="display: none;">
                <i class="fas fa-info-circle"></i>
                <span id="missingFieldsText"></span>
            </div>
            
            <form method="POST" id="deceasedForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- פרטי הנפטר -->
                <div class="section-title">פרטי הנפטר</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="identification_type" class="form-label <?= in_array('identification_type', $requiredFields) ? 'required' : '' ?>">
                            סוג זיהוי
                        </label>
                        <select class="form-select <?= isset($errors['identification_type']) ? 'is-invalid' : '' ?>" 
                                id="identification_type" name="identification_type" 
                                data-required="<?= in_array('identification_type', $requiredFields) ? 'true' : 'false' ?>"
                                <?= (!$form || !$form->canEditField('identification_type') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
                            <option value="tz" <?= ($formData['identification_type'] ?? '') === 'tz' ? 'selected' : '' ?>>תעודת זהות</option>
                            <option value="passport" <?= ($formData['identification_type'] ?? '') === 'passport' ? 'selected' : '' ?>>דרכון</option>
                            <option value="anonymous" <?= ($formData['identification_type'] ?? '') === 'anonymous' ? 'selected' : '' ?>>אלמוני</option>
                            <option value="baby" <?= ($formData['identification_type'] ?? '') === 'baby' ? 'selected' : '' ?>>תינוק</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6" id="identificationNumberDiv">
                        <label for="identification_number" class="form-label">מספר זיהוי</label>
                        <input type="text" class="form-control <?= isset($errors['identification_number']) ? 'is-invalid' : '' ?>" 
                               id="identification_number" name="identification_number" 
                               value="<?= $formData['identification_number'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('identification_number') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="deceased_name" class="form-label <?= in_array('deceased_name', $requiredFields) ? 'required' : '' ?>">
                            שם הנפטר
                        </label>
                        <input type="text" class="form-control" 
                               id="deceased_name" name="deceased_name" 
                               data-required="<?= in_array('deceased_name', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['deceased_name'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('deceased_name') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="father_name" class="form-label">שם האב</label>
                        <input type="text" class="form-control" id="father_name" name="father_name" 
                               value="<?= $formData['father_name'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('father_name') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="mother_name" class="form-label">שם האם</label>
                        <input type="text" class="form-control" id="mother_name" name="mother_name" 
                               value="<?= $formData['mother_name'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('mother_name') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6" id="birthDateDiv">
                        <label for="birth_date" class="form-label">תאריך לידה</label>
                        <input type="date" class="form-control" 
                               id="birth_date" name="birth_date" 
                               value="<?= $formData['birth_date'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('birth_date') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <!-- פרטי הפטירה -->
                <div class="section-title">פרטי הפטירה</div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="death_date" class="form-label <?= in_array('death_date', $requiredFields) ? 'required' : '' ?>">
                            תאריך פטירה
                        </label>
                        <input type="date" class="form-control" 
                               id="death_date" name="death_date" 
                               data-required="<?= in_array('death_date', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['death_date'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('death_date') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="death_time" class="form-label <?= in_array('death_time', $requiredFields) ? 'required' : '' ?>">
                            שעת פטירה
                        </label>
                        <input type="time" class="form-control" 
                               id="death_time" name="death_time" 
                               data-required="<?= in_array('death_time', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['death_time'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('death_time') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_date" class="form-label <?= in_array('burial_date', $requiredFields) ? 'required' : '' ?>">
                            תאריך קבורה
                        </label>
                        <input type="date" class="form-control" 
                               id="burial_date" name="burial_date" 
                               data-required="<?= in_array('burial_date', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_date'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('burial_date') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_time" class="form-label <?= in_array('burial_time', $requiredFields) ? 'required' : '' ?>">
                            שעת קבורה
                        </label>
                        <input type="time" class="form-control" 
                               id="burial_time" name="burial_time" 
                               data-required="<?= in_array('burial_time', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_time'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('burial_time') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="burial_license" class="form-label <?= in_array('burial_license', $requiredFields) ? 'required' : '' ?>">
                            רשיון קבורה
                        </label>
                        <input type="text" class="form-control" 
                               id="burial_license" name="burial_license" 
                               data-required="<?= in_array('burial_license', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_license'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('burial_license') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="death_location" class="form-label">מקום הפטירה</label>
                        <input type="text" class="form-control" id="death_location" name="death_location" 
                               value="<?= $formData['death_location'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('death_location') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <!-- מקום הקבורה - רק למנהלים -->
                <?php if ($form && $form->canViewField('cemetery_id')): ?>
                <div class="section-title">מקום הקבורה</div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="cemetery_id" class="form-label">בית עלמין</label>
                        <select class="form-select" id="cemetery_id" name="cemetery_id" 
                                <?= (!$form->canEditField('cemetery_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
                            <?php foreach ($cemeteries as $cemetery): ?>
                                <option value="<?= $cemetery['id'] ?>" 
                                        <?= ($formData['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cemetery['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="block_id" class="form-label">גוש</label>
                        <select class="form-select" id="block_id" name="block_id" 
                                <?= (!$form->canEditField('block_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר קודם בית עלמין</option>
                            <?php foreach ($blocks as $block): ?>
                                <option value="<?= $block['id'] ?>" 
                                        <?= ($formData['block_id'] ?? '') == $block['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($block['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="section_id" class="form-label">חלקה</label>
                        <select class="form-select" id="section_id" name="section_id" 
                                <?= (!$form->canEditField('section_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר קודם גוש</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>" 
                                        <?= ($formData['section_id'] ?? '') == $section['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="row_id" class="form-label">שורה</label>
                        <select class="form-select" id="row_id" name="row_id" 
                                <?= (!$form->canEditField('row_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר קודם חלקה</option>
                            <?php foreach ($rows as $row): ?>
                                <option value="<?= $row['id'] ?>" 
                                        <?= ($formData['row_id'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="grave_id" class="form-label">קבר</label>
                        <select class="form-select" id="grave_id" name="grave_id" 
                                <?= (!$form->canEditField('grave_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר קודם שורה</option>
                            <?php foreach ($graves as $grave): ?>
                                <option value="<?= $grave['id'] ?>" 
                                        <?= ($formData['grave_id'] ?? '') == $grave['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($grave['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="plot_id" class="form-label">אחוזת קבר</label>
                        <select class="form-select" id="plot_id" name="plot_id" 
                                <?= (!$form->canEditField('plot_id') || $viewOnly) ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
                            <?php foreach ($plots as $plot): ?>
                                <option value="<?= $plot['id'] ?>" 
                                        <?= ($formData['plot_id'] ?? '') == $plot['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plot['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- פרטי המודיע -->
                <div class="section-title">פרטי המודיע</div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="informant_name" class="form-label">שם המודיע</label>
                        <input type="text" class="form-control" id="informant_name" name="informant_name" 
                               value="<?= $formData['informant_name'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('informant_name') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_phone" class="form-label">טלפון</label>
                        <input type="tel" class="form-control" id="informant_phone" name="informant_phone" 
                               value="<?= $formData['informant_phone'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('informant_phone') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_relationship" class="form-label">קרבה משפחתית</label>
                        <input type="text" class="form-control" id="informant_relationship" name="informant_relationship" 
                               value="<?= $formData['informant_relationship'] ?? '' ?>"
                               <?= (!$form || !$form->canEditField('informant_relationship') || $viewOnly) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="notes" class="form-label">הערות</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  <?= (!$form || !$form->canEditField('notes') || $viewOnly) ? 'disabled' : '' ?>><?= $formData['notes'] ?? '' ?></textarea>
                    </div>
                </div>
                
                <!-- חתימת לקוח -->
                <div class="section-title">חתימת לקוח</div>
                <div class="row mb-3">
                    <div class="col-12">
                        <?php if ($viewOnly): ?>
                            <!-- הצגת חתימה קיימת בלבד -->
                            <?php if (!empty($formData['client_signature'])): ?>
                                <div class="signature-display" style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; text-align: center; background: #f8f9fa;">
                                    <img src="<?= htmlspecialchars($formData['client_signature']) ?>" alt="חתימת לקוח" style="max-width: 100%; max-height: 180px;">
                                </div>
                            <?php else: ?>
                                <div class="text-muted text-center" style="padding: 50px; border: 1px dashed #ddd;">
                                    אין חתימה
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- לוח חתימה -->
                            <canvas id="signaturePad" class="signature-pad"></canvas>
                            <input type="hidden" id="client_signature" name="client_signature" 
                                   value="<?= $formData['client_signature'] ?? '' ?>">
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="clearSignature()">
                                    <i class="fas fa-eraser"></i> נקה חתימה
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- כפתורי פעולה -->
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <?php if (!$viewOnly): ?>
                            <?php if ($isNewForm): ?>
                                <!-- כפתורים לטופס חדש -->
                                <button type="submit" name="save" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> צור טופס
                                </button>
                                <button type="submit" name="save_and_view" value="1" class="btn btn-success btn-lg ms-2">
                                    <i class="fas fa-save"></i> צור וצפה בטופס
                                </button>
                            <?php else: ?>
                                <!-- כפתורים לטופס קיים -->
                                <button type="submit" name="save" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> שמור שינויים
                                </button>
                                <button type="submit" name="save_and_view" value="1" class="btn btn-success btn-lg ms-2">
                                    <i class="fas fa-save"></i> שמור וצפה בטופס
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (!$isNewForm): ?>
                            <a href="view_form.php?id=<?= $formUuid ?>" class="btn btn-info btn-lg ms-2">
                                <i class="fas fa-eye"></i> צפייה בטופס
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isUserLoggedIn()): ?>
                            <a href="forms_list.php" class="btn btn-secondary btn-lg ms-2">
                                <i class="fas fa-list"></i> רשימת טפסים
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline-primary btn-lg ms-2">
                                <i class="fas fa-sign-in-alt"></i> התחבר למערכת
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // משתנה לציון אם זה טופס חדש
        const isNewForm = <?= $isNewForm ? 'true' : 'false' ?>;
        const isViewOnly = <?= $viewOnly ? 'true' : 'false' ?>;
        const isUserLoggedIn = <?= isUserLoggedIn() ? 'true' : 'false' ?>;
        
        $(document).ready(function() {
            // רשימת שדות חובה
            const requiredFields = <?= json_encode($requiredFields) ?>;
            
            // פונקציה לבדיקת שלמות הטופס
            function checkFormCompleteness() {
                if (isViewOnly) return true;
                
                let missingFields = [];
                let isComplete = true;
                
                // עבור על כל שדות החובה
                $('[data-required="true"]:not(:disabled)').each(function() {
                    const field = $(this);
                    const fieldLabel = field.closest('.col-md-3, .col-md-4, .col-md-6').find('label').text().replace('*', '').trim();
                    const value = field.val();
                    
                    if (!value || value === '') {
                        isComplete = false;
                        missingFields.push(fieldLabel);
                        field.addClass('field-missing');
                    } else {
                        field.removeClass('field-missing');
                    }
                });
                
                // בדיקת שדות מותנים
                const idType = $('#identification_type').val();
                if ((idType === 'tz' || idType === 'passport') && requiredFields.includes('identification_number')) {
                    const idNumber = $('#identification_number').val();
                    if (!idNumber || idNumber === '') {
                        isComplete = false;
                        if (!missingFields.includes('מספר זיהוי')) {
                            missingFields.push('מספר זיהוי');
                        }
                        $('#identification_number').addClass('field-missing');
                    }
                    
                    const birthDate = $('#birth_date').val();
                    if (!birthDate || birthDate === '' && requiredFields.includes('birth_date')) {
                        isComplete = false;
                        if (!missingFields.includes('תאריך לידה')) {
                            missingFields.push('תאריך לידה');
                        }
                        $('#birth_date').addClass('field-missing');
                    }
                }
                
                // עדכון התראה
                if (missingFields.length > 0) {
                    $('#missingFieldsText').html(
                        '<strong>שדות חובה חסרים:</strong> ' + missingFields.join(', ') + 
                        '<br><small>הטופס יישמר כטיוטה עד להשלמת כל השדות החובה</small>'
                    );
                    $('#missingFieldsAlert').show();
                } else {
                    $('#missingFieldsAlert').hide();
                }
                
                return isComplete;
            }
            
            // בדיקה ראשונית
            checkFormCompleteness();
            
            // בדיקה בכל שינוי - רק אם לא במצב צפייה
            if (!isViewOnly) {
                $('input, select, textarea').on('change keyup', function() {
                    checkFormCompleteness();
                });
            }
            
            // טיפול בסוג זיהוי
            $('#identification_type').on('change', function() {
                const type = $(this).val();
                if (type === 'tz' || type === 'passport') {
                    $('#identificationNumberDiv, #birthDateDiv').show();
                    if (requiredFields.includes('identification_number')) {
                        $('#identification_number').attr('data-required', 'true');
                    }
                    if (requiredFields.includes('birth_date')) {
                        $('#birth_date').attr('data-required', 'true');
                    }
                } else {
                    $('#identificationNumberDiv, #birthDateDiv').hide();
                    $('#identification_number, #birth_date').attr('data-required', 'false');
                    $('#identification_number, #birth_date').removeClass('field-missing');
                }
                checkFormCompleteness();
            }).trigger('change');
            
            // טיפול בשדות תלויים של מקום קבורה - רק אם לא במצב צפייה
            if (!isViewOnly) {
                $('#cemetery_id').on('change', function() {
                    const cemeteryId = $(this).val();
                    
                    // נקה את כל השדות התלויים
                    $('#block_id').html('<option value="">בחר קודם בית עלמין</option>');
                    $('#section_id').html('<option value="">בחר קודם גוש</option>');
                    $('#row_id').html('<option value="">בחר קודם חלקה</option>');
                    $('#grave_id').html('<option value="">בחר קודם שורה</option>');
                    $('#plot_id').html('<option value="">בחר...</option>');
                    
                    if (cemeteryId) {
                        $.get('ajax/get_blocks.php', {cemetery_id: cemeteryId}, function(data) {
                            $('#block_id').html(data);
                            <?php if (!empty($formData['block_id'])): ?>
                            $('#block_id').val('<?= $formData['block_id'] ?>').trigger('change');
                            <?php endif; ?>
                        });
                        $.get('ajax/get_plots.php', {cemetery_id: cemeteryId}, function(data) {
                            $('#plot_id').html(data);
                            <?php if (!empty($formData['plot_id'])): ?>
                            $('#plot_id').val('<?= $formData['plot_id'] ?>');
                            <?php endif; ?>
                        });
                    }
                });
                
                $('#block_id').on('change', function() {
                    const blockId = $(this).val();
                    
                    // נקה שדות תלויים
                    $('#section_id').html('<option value="">בחר קודם גוש</option>');
                    $('#row_id').html('<option value="">בחר קודם חלקה</option>');
                    $('#grave_id').html('<option value="">בחר קודם שורה</option>');
                    
                    if (blockId) {
                        $.get('ajax/get_sections.php', {block_id: blockId}, function(data) {
                            $('#section_id').html(data);
                            <?php if (!empty($formData['section_id'])): ?>
                            $('#section_id').val('<?= $formData['section_id'] ?>').trigger('change');
                            <?php endif; ?>
                        });
                    }
                });
                
                $('#section_id').on('change', function() {
                    const sectionId = $(this).val();
                    
                    // נקה שדות תלויים
                    $('#row_id').html('<option value="">בחר קודם חלקה</option>');
                    $('#grave_id').html('<option value="">בחר קודם שורה</option>');
                    
                    if (sectionId) {
                        $.get('ajax/get_rows.php', {section_id: sectionId}, function(data) {
                            $('#row_id').html(data);
                            <?php if (!empty($formData['row_id'])): ?>
                            $('#row_id').val('<?= $formData['row_id'] ?>').trigger('change');
                            <?php endif; ?>
                        });
                    }
                });
                
                $('#row_id').on('change', function() {
                    const rowId = $(this).val();
                    
                    // נקה שדה תלוי
                    $('#grave_id').html('<option value="">בחר קודם שורה</option>');
                    
                    if (rowId) {
                        $.get('ajax/get_graves.php', {row_id: rowId}, function(data) {
                            $('#grave_id').html(data);
                            <?php if (!empty($formData['grave_id'])): ?>
                            $('#grave_id').val('<?= $formData['grave_id'] ?>');
                            <?php endif; ?>
                        });
                    }
                });
                
                // אם יש ערכים קיימים, הפעל את השדות הרלוונטיים
                <?php if (!empty($formData['cemetery_id'])): ?>
                $('#cemetery_id').trigger('change');
                <?php endif; ?>
            }
            
            // ולידציה לפני שליחה - רק בדיקות פורמט
            $('#deceasedForm').on('submit', function(e) {
                if (isViewOnly) {
                    e.preventDefault();
                    return false;
                }
                
                $('.is-invalid').removeClass('is-invalid');
                
                // בדיקת תאריכים
                const deathDate = $('#death_date').val();
                const burialDate = $('#burial_date').val();
                
                if (deathDate && burialDate && new Date(burialDate) < new Date(deathDate)) {
                    e.preventDefault();
                    $('#burial_date').addClass('is-invalid');
                    if (!$('#burial_date').next('.invalid-feedback').length) {
                        $('#burial_date').after('<div class="invalid-feedback">תאריך הקבורה לא יכול להיות לפני תאריך הפטירה</div>');
                    }
                    
                    $('html, body').animate({
                        scrollTop: $('#burial_date').offset().top - 100
                    }, 500);
                    
                    return false;
                }
                
                // בדיקת תעודת זהות
                const idType = $('#identification_type').val();
                const idNumber = $('#identification_number').val();
                
                if (idType === 'tz' && idNumber && !validateIsraeliId(idNumber)) {
                    e.preventDefault();
                    $('#identification_number').addClass('is-invalid');
                    if (!$('#identification_number').next('.invalid-feedback').length) {
                        $('#identification_number').after('<div class="invalid-feedback">מספר תעודת זהות לא תקין</div>');
                    }
                    
                    $('html, body').animate({
                        scrollTop: $('#identification_number').offset().top - 100
                    }, 500);
                    
                    return false;
                }
                
                // נקה שדות ריקים של select לפני שליחה
                $('select').each(function() {
                    if ($(this).val() === '' || $(this).val() === null) {
                        $(this).removeAttr('name');
                    }
                });
            });
        });
        
        // פונקציה לבדיקת תעודת זהות ישראלית
        function validateIsraeliId(id) {
            id = String(id).trim();
            if (id.length !== 9 || isNaN(id)) {
                return false;
            }
            
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                const digit = parseInt(id[i]);
                const step = digit * ((i % 2) + 1);
                sum += step > 9 ? step - 9 : step;
            }
            
            return sum % 10 === 0;
        }
        
        // חתימה דיגיטלית - רק אם לא במצב צפייה
        if (!isViewOnly) {
            const canvas = document.getElementById('signaturePad');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                let isDrawing = false;
                
                // התאמת גודל הקנבס
                function resizeCanvas() {
                    const rect = canvas.getBoundingClientRect();
                    canvas.width = rect.width;
                    canvas.height = 200;
                    
                    // טען מחדש חתימה קיימת אם יש
                    loadExistingSignature();
                }
                
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
                
                // אירועי ציור
                canvas.addEventListener('mousedown', startDrawing);
                canvas.addEventListener('mousemove', draw);
                canvas.addEventListener('mouseup', stopDrawing);
                canvas.addEventListener('mouseout', stopDrawing);
                
                // תמיכה במכשירי מגע
                canvas.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousedown', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                
                canvas.addEventListener('touchmove', function(e) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousemove', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                
                canvas.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    const mouseEvent = new MouseEvent('mouseup', {});
                    canvas.dispatchEvent(mouseEvent);
                });
                
                function startDrawing(e) {
                    isDrawing = true;
                    const rect = canvas.getBoundingClientRect();
                    ctx.beginPath();
                    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
                }
                
                function draw(e) {
                    if (!isDrawing) return;
                    const rect = canvas.getBoundingClientRect();
                    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                    ctx.stroke();
                }
                
                function stopDrawing() {
                    if (isDrawing) {
                        isDrawing = false;
                        // שמירת החתימה כ-base64
                        document.getElementById('client_signature').value = canvas.toDataURL();
                    }
                }
                
                window.clearSignature = function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    document.getElementById('client_signature').value = '';
                };
                
                // טעינת חתימה קיימת
                function loadExistingSignature() {
                    const existingSignature = document.getElementById('client_signature').value;
                    if (existingSignature && existingSignature.startsWith('data:image')) {
                        const img = new Image();
                        img.onload = function() {
                            ctx.drawImage(img, 0, 0);
                        };
                        img.src = existingSignature;
                    }
                }
                
                // טען חתימה קיימת בטעינה ראשונה
                loadExistingSignature();
            }
        }
        
        // הצג אנימציה בעת שמירה
        $('#deceasedForm').on('submit', function(e) {
            // אם יש שגיאות ולידציה, אל תציג את האנימציה
            if ($(this).find('.is-invalid').length > 0) {
                return;
            }
            
            // הצג את האנימציה
            $('#loadingOverlay').css('display', 'flex');
            
            // השבת את כל הכפתורים
            $(this).find('button[type="submit"]').prop('disabled', true);
        });

        // הסר את האנימציה אם הדף נטען עם שגיאות
        $(document).ready(function() {
            <?php if (isset($errors) && !empty($errors)): ?>
            $('#loadingOverlay').hide();
            <?php endif; ?>
        });
        
        // בדיקה אם המשתמש התחבר בלשונית אחרת
        function checkUserLoginStatus() {
            // רק אם המשתמש לא מחובר כרגע
            if (!isUserLoggedIn) {
                $.ajax({
                    url: 'ajax/check_login_status.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.logged_in) {
                            // המשתמש התחבר - הצג הודעה ורענן
                            showLoginNotification(response.username);
                        }
                    },
                    error: function() {
                        // שגיאה - לא נעשה כלום
                    }
                });
            }
        }
        
        // בדוק סטטוס התחברות כל 30 שניות
        setInterval(checkUserLoginStatus, 30000);
        
        function showLoginNotification(username) {
            const notification = $('<div class="alert alert-success alert-dismissible position-fixed" style="top: 70px; right: 20px; z-index: 9999; max-width: 400px;">')
                .html(`
                    <i class="fas fa-user-check"></i>
                    <strong>התחברת בהצלחה!</strong><br>
                    שלום ${username}, אתה יכול כעת לערוך את הטופס.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh"></i> רענן דף
                        </button>
                    </div>
                `);
            
            $('body').append(notification);
            
            // הסר אוטומטית אחרי 10 שניות
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 10000);
        }
    </script>
</body>
</html>