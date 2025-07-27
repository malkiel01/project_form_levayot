<?php
// form.php - גרסה משופרת עם שמירה חלקית ועדכון סטטוס אוטומטי

require_once 'config.php';
require_once 'DeceasedForm.php';

// בדיקת כניסה מקישור שיתוף
$isLinkAccess = false;
$linkPermissions = null;
$viewOnly = false;

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
            if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], $allowedUsers)) {
                $accessGranted = false;
            }
        }
        
        if ($accessGranted) {
            // הגדר הרשאות זמניות
            $linkPermissions = [
                'form_uuid' => $linkData['form_uuid'],
                'permission_level' => isset($_SESSION['permission_level']) ? 
                    $_SESSION['permission_level'] : $linkData['permission_level'],
                'can_edit' => $linkData['can_edit'],
                'link_uuid' => $linkUuid
            ];
            
            // אם אין הרשאת עריכה
            if (!$linkData['can_edit']) {
                $viewOnly = true;
            }
            
            // הגדר את ה-UUID של הטופס
            $formUuid = $linkData['form_uuid'];
            
            // רישום גישה בלוג
            $logStmt = $db->prepare("
                INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
                VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'access_via_link', ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'] ?? null,
                $formUuid,
                json_encode(['link_uuid' => $linkUuid]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // עדכן זמן שימוש אחרון בקישור
            $updateStmt = $db->prepare("
                UPDATE form_links 
                SET last_used = NOW(), use_count = use_count + 1 
                WHERE link_uuid = ?
            ");
            $updateStmt->execute([$linkUuid]);
            
        } else {
            // אין הרשאה
            die('
                <div style="text-align: center; margin-top: 50px; font-family: Arial;">
                    <h2>אין לך הרשאה לצפות בטופס זה</h2>
                    <p>הקישור מוגבל למשתמשים ספציפיים בלבד.</p>
                    <a href="login.php">התחבר למערכת</a>
                </div>
            ');
        }
    } else {
        // קישור לא תקף
        die('
            <div style="text-align: center; margin-top: 50px; font-family: Arial;">
                <h2>קישור לא תקף</h2>
                <p>הקישור אינו קיים או שפג תוקפו.</p>
                <a href="login.php">עבור לדף הכניסה</a>
            </div>
        ');
    }
} else {
    // כניסה רגילה - דורשת התחברות
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// קביעת רמת הרשאה
if ($isLinkAccess && $linkPermissions) {
    $userPermissionLevel = $linkPermissions['permission_level'];
} else {
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;
}

// דוגמה לנטרול כפתור שמירה במצב צפייה בלבד
$viewOnly = isset($_SESSION['view_only']) && $_SESSION['view_only'];

// // קבלת רמת הרשאה
// $userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// בדיקה אם יש ID של טופס ב-URL
$formUuid = $_GET['id'] ?? null;

// משתנים לשימוש בהמשך
$isNewForm = false;
$formData = [];
$form = null;

// אם אין ID בכתובת - יצירת טופס חדש
if (!$formUuid) {
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
error_log("FORM ACCESS - UUID: $formUuid, Is New: " . ($isNewForm ? 'YES' : 'NO') . ", User: " . $_SESSION['user_id']);

// אחסן את מידע הדיבוג למטה בדף
$debugInfo = [
    'formUuid' => $formUuid,
    'isNewForm' => $isNewForm,
    'formDataLoaded' => !empty($formData)
];

// משתנה לזיהוי אם צריך להפנות לתצוגה
$shouldRedirectToView = false;

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // סניטציה של הנתונים
    $postData = sanitizeInput($_POST);
    unset($postData['csrf_token']);
    
    // בדיקה אם לחצו על כפתור "שמור וצפה"
    $saveAndView = isset($_POST['save_and_view']);
    
    // אל תבצע ולידציה של שדות חובה - רק ולידציה של פורמט
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
                $postData['form_uuid'] = $formUuid; // וודא שה-UUID נשמר
                $postData['status'] = 'draft'; // תמיד התחל כטיוטה
                $form->createForm($postData);
                $successMessage = "הטופס נוצר בהצלחה";
                
                // אם יצירת הטופס הצליחה והמשתמש לחץ על "שמור וצפה"
                if ($saveAndView) {
                    header("Location: view_form.php?id=" . $formUuid);
                    exit;
                }
                
                // אחרת, טען מחדש את הטופס לעריכה
                header("Location: form.php?id=" . $formUuid);
                exit;
                
            } else {
                // עדכון טופס קיים
                $form->updateForm($postData);
                $successMessage = "הטופס עודכן בהצלחה";
                
                // אם המשתמש לחץ על "שמור וצפה"
                if ($saveAndView) {
                    header("Location: view_form.php?id=" . $formUuid);
                    exit;
                }
                
                // טען מחדש את הנתונים המעודכנים
                $formData = $form->getFormData();
            }
        } catch (Exception $e) {
            $errorMessage = "שגיאה בשמירת הטופס: " . $e->getMessage();
            error_log("Form save error: " . $e->getMessage());
        }
    } else {
        $errors = $formatErrors;
    }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
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
                <small>גירסה: <strong>6.1</strong></small>
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
                                <?= !$form || !$form->canEditField('identification_type') ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
                            <option value="tz" <?= ($formData['identification_type'] ?? '') === 'tz' ? 'selected' : '' ?>>תעודת זהות</option>
                            <option value="passport" <?= ($formData['identification_type'] ?? '') === 'passport' ? 'selected' : '' ?>>דרכון</option>
                            <option value="anonymous" <?= ($formData['identification_type'] ?? '') === 'anonymous' ? 'selected' : '' ?>>אלמוני</option>
                            <option value="baby" <?= ($formData['identification_type'] ?? '') === 'baby' ? 'selected' : '' ?>>תינוק</option>
                        </select>
                        <?php if (isset($errors['identification_type'])): ?>
                            <div class="invalid-feedback"><?= $errors['identification_type'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6" id="identificationNumberDiv">
                        <label for="identification_number" class="form-label">מספר זיהוי</label>
                        <input type="text" class="form-control <?= isset($errors['identification_number']) ? 'is-invalid' : '' ?>" 
                               id="identification_number" name="identification_number" 
                               value="<?= $formData['identification_number'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('identification_number') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['identification_number'])): ?>
                            <div class="invalid-feedback"><?= $errors['identification_number'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="deceased_name" class="form-label <?= in_array('deceased_name', $requiredFields) ? 'required' : '' ?>">
                            שם הנפטר
                        </label>
                        <input type="text" class="form-control <?= isset($errors['deceased_name']) ? 'is-invalid' : '' ?>" 
                               id="deceased_name" name="deceased_name" 
                               data-required="<?= in_array('deceased_name', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['deceased_name'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('deceased_name') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['deceased_name'])): ?>
                            <div class="invalid-feedback"><?= $errors['deceased_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="father_name" class="form-label">שם האב</label>
                        <input type="text" class="form-control" id="father_name" name="father_name" 
                               value="<?= $formData['father_name'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('father_name') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="mother_name" class="form-label">שם האם</label>
                        <input type="text" class="form-control" id="mother_name" name="mother_name" 
                               value="<?= $formData['mother_name'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('mother_name') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6" id="birthDateDiv">
                        <label for="birth_date" class="form-label">תאריך לידה</label>
                        <input type="date" class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>" 
                               id="birth_date" name="birth_date" 
                               value="<?= $formData['birth_date'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('birth_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['birth_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['birth_date'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- פרטי הפטירה -->
                <div class="section-title">פרטי הפטירה</div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="death_date" class="form-label <?= in_array('death_date', $requiredFields) ? 'required' : '' ?>">
                            תאריך פטירה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['death_date']) ? 'is-invalid' : '' ?>" 
                               id="death_date" name="death_date" 
                               data-required="<?= in_array('death_date', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['death_date'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('death_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="death_time" class="form-label <?= in_array('death_time', $requiredFields) ? 'required' : '' ?>">
                            שעת פטירה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['death_time']) ? 'is-invalid' : '' ?>" 
                               id="death_time" name="death_time" 
                               data-required="<?= in_array('death_time', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['death_time'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('death_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_time'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_date" class="form-label <?= in_array('burial_date', $requiredFields) ? 'required' : '' ?>">
                            תאריך קבורה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['burial_date']) ? 'is-invalid' : '' ?>" 
                               id="burial_date" name="burial_date" 
                               data-required="<?= in_array('burial_date', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_date'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('burial_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_time" class="form-label <?= in_array('burial_time', $requiredFields) ? 'required' : '' ?>">
                            שעת קבורה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['burial_time']) ? 'is-invalid' : '' ?>" 
                               id="burial_time" name="burial_time" 
                               data-required="<?= in_array('burial_time', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_time'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('burial_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_time'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="burial_license" class="form-label <?= in_array('burial_license', $requiredFields) ? 'required' : '' ?>">
                            רשיון קבורה
                        </label>
                        <input type="text" class="form-control <?= isset($errors['burial_license']) ? 'is-invalid' : '' ?>" 
                               id="burial_license" name="burial_license" 
                               data-required="<?= in_array('burial_license', $requiredFields) ? 'true' : 'false' ?>"
                               value="<?= $formData['burial_license'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('burial_license') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_license'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_license'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="death_location" class="form-label">מקום הפטירה</label>
                        <input type="text" class="form-control" id="death_location" name="death_location" 
                               value="<?= $formData['death_location'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('death_location') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <!-- מקום הקבורה - רק למנהלים -->
                <?php if ($form && $form->canViewField('cemetery_id')): ?>
                <div class="section-title">מקום הקבורה</div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="cemetery_id" class="form-label">בית עלמין</label>
                        <select class="form-select" id="cemetery_id" name="cemetery_id" 
                                <?= !$form->canEditField('cemetery_id') ? 'disabled' : '' ?>>
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
                                <?= !$form->canEditField('block_id') ? 'disabled' : '' ?>>
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
                                <?= !$form->canEditField('section_id') ? 'disabled' : '' ?>>
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
                                <?= !$form->canEditField('row_id') ? 'disabled' : '' ?>>
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
                                <?= !$form->canEditField('grave_id') ? 'disabled' : '' ?>>
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
                                <?= !$form->canEditField('plot_id') ? 'disabled' : '' ?>>
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
                               <?= !$form || !$form->canEditField('informant_name') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_phone" class="form-label">טלפון</label>
                        <input type="tel" class="form-control" id="informant_phone" name="informant_phone" 
                               value="<?= $formData['informant_phone'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('informant_phone') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_relationship" class="form-label">קרבה משפחתית</label>
                        <input type="text" class="form-control" id="informant_relationship" name="informant_relationship" 
                               value="<?= $formData['informant_relationship'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('informant_relationship') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="notes" class="form-label">הערות</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  <?= !$form || !$form->canEditField('notes') ? 'disabled' : '' ?>><?= $formData['notes'] ?? '' ?></textarea>
                    </div>
                </div>
                
                <!-- חתימת לקוח -->
                <div class="section-title">חתימת לקוח</div>
                <div class="row mb-3">
                    <div class="col-12">
                        <canvas id="signaturePad" class="signature-pad"></canvas>
                        <input type="hidden" id="client_signature" name="client_signature" 
                               value="<?= $formData['client_signature'] ?? '' ?>">
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearSignature()">
                                <i class="fas fa-eraser"></i> נקה חתימה
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- כפתורי פעולה -->
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <?php if ($isNewForm): ?>
                            <!-- כפתורים לטופס חדש -->
                            <button type="submit" name="save" class="btn btn-primary btn-lg" <?= $viewOnly ? 'disabled' : '' ?>>
                                <i class="fas fa-save"></i> צור טופס
                            </button>
                            <button type="submit" name="save_and_view" value="1" class="btn btn-success btn-lg ms-2" <?= $viewOnly ? 'disabled' : '' ?>>
                                <i class="fas fa-save"></i> צור וצפה בטופס
                            </button>
                        <?php else: ?>
                            <!-- כפתורים לטופס קיים -->
                            <button type="submit" name="save" class="btn btn-primary btn-lg" <?= $viewOnly ? 'disabled' : '' ?>>
                                <i class="fas fa-save"></i> שמור שינויים
                            </button>
                            <button type="submit" name="save_and_view" value="1" class="btn btn-success btn-lg ms-2" <?= $viewOnly ? 'disabled' : '' ?>>
                                <i class="fas fa-save"></i> שמור וצפה בטופס
                            </button>
                            <a href="view_form.php?id=<?= $formUuid ?>" class="btn btn-info btn-lg ms-2">
                                <i class="fas fa-eye"></i> צפייה בטופס
                            </a>
                            <button type="button" class="btn btn-warning btn-lg ms-2" onclick="shareForm()">
                                <i class="fas fa-share"></i> שתף טופס
                            </button>
                        <?php endif; ?>
                        <a href="forms_list.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- תוספת ל-form.php - הוסיפי בסוף הקובץ לפני תג ה-</body> -->

<!-- Modal יצירת קישור שיתוף -->
<div class="modal fade" id="shareFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">יצירת קישור שיתוף</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shareLinkForm">
                    <!-- סוג גישה -->
                    <div class="mb-3">
                        <label class="form-label">סוג גישה</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="access_type" id="access_public" value="public" checked>
                            <label class="form-check-label" for="access_public">
                                <i class="fas fa-globe"></i> פתוח לכולם
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="access_type" id="access_users" value="users">
                            <label class="form-check-label" for="access_users">
                                <i class="fas fa-users"></i> משתמשים ספציפיים
                            </label>
                        </div>
                    </div>

                    <!-- בחירת משתמשים -->
                    <div class="mb-3" id="usersSelectDiv" style="display: none;">
                        <label for="allowed_users" class="form-label">בחר משתמשים מורשים</label>
                        <select class="form-select" id="allowed_users" name="allowed_users[]" multiple size="5">
                            <!-- יטען באמצעות AJAX -->
                        </select>
                        <small class="form-text text-muted">החזק Ctrl לבחירה מרובה</small>
                    </div>

                    <!-- הרשאות -->
                    <div class="mb-3">
                        <label class="form-label">הרשאות</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="permission_mode" id="view_only" value="view" checked>
                            <label class="form-check-label" for="view_only">
                                <i class="fas fa-eye"></i> צפייה בלבד
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="permission_mode" id="can_edit" value="edit">
                            <label class="form-check-label" for="can_edit">
                                <i class="fas fa-edit"></i> צפייה ועריכה
                            </label>
                        </div>
                    </div>

                    <!-- רמת הרשאה -->
                    <div class="mb-3">
                        <label for="permission_level" class="form-label">רמת הרשאה למשתמשים לא רשומים</label>
                        <select class="form-select" id="permission_level" name="permission_level">
                            <option value="1">צופה (רמה 1)</option>
                            <option value="2">עורך בסיסי (רמה 2)</option>
                            <option value="3">עורך מתקדם (רמה 3)</option>
                            <option value="4" selected>מנהל (רמה 4)</option>
                        </select>
                    </div>

                    <!-- תוקף -->
                    <div class="mb-3">
                        <label class="form-label">תוקף הקישור</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="expiry_type" id="no_expiry" value="never" checked>
                            <label class="form-check-label" for="no_expiry">
                                <i class="fas fa-infinity"></i> ללא הגבלת זמן
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="expiry_type" id="custom_expiry" value="custom">
                            <label class="form-check-label" for="custom_expiry">
                                <i class="fas fa-clock"></i> הגדרת תוקף
                            </label>
                        </div>
                    </div>

                    <!-- בחירת תאריך תפוגה -->
                    <div class="mb-3" id="expiryDateDiv" style="display: none;">
                        <label for="expiry_date" class="form-label">תאריך תפוגה</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            </div>
                            <div class="col-md-6">
                                <input type="time" class="form-control" id="expiry_time" name="expiry_time" value="23:59">
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(1)">מחר</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(7)">שבוע</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(30)">חודש</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(90)">3 חודשים</button>
                        </div>
                    </div>

                    <!-- הערות -->
                    <div class="mb-3">
                        <label for="link_description" class="form-label">תיאור/הערות (אופציונלי)</label>
                        <input type="text" class="form-control" id="link_description" name="link_description" 
                               placeholder="לדוגמה: קישור למשפחה">
                    </div>
                </form>

                <!-- הודעות -->
                <div id="shareLinkAlert" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                <button type="button" class="btn btn-primary" onclick="createShareLink()">
                    <i class="fas fa-link"></i> צור קישור
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal הצגת קישור שנוצר -->
<div class="modal fade" id="showLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">קישור השיתוף נוצר בהצלחה</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">קישור לשיתוף:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="generatedLink" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">
                            <i class="fas fa-copy"></i> העתק
                        </button>
                    </div>
                </div>
                <div id="linkDetails" class="alert alert-info">
                    <!-- פרטי הקישור יוצגו כאן -->
                </div>
                <div class="text-center">
                    <div id="qrcode"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                <button type="button" class="btn btn-primary" onclick="shareViaWhatsApp()">
                    <i class="fab fa-whatsapp"></i> שתף בוואטסאפ
                </button>
                <button type="button" class="btn btn-info" onclick="shareViaEmail()">
                    <i class="fas fa-envelope"></i> שלח במייל
                </button>
            </div>
        </div>
    </div>
</div>

<!-- הוסף את ספריית QR Code (אופציונלי) -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <script>
        // עדכון הפונקציה shareForm
        function shareForm() {
            if (isNewForm) {
                alert('יש לשמור את הטופס לפני השיתוף');
                return;
            }
            
            // טען רשימת משתמשים
            loadUsersList();
            
            // פתח את המודל
            $('#shareFormModal').modal('show');
        }

        // אם אתה רוצה לשמור גם את האפשרות הישנה של שיתוף מהיר, 
        // תוכל להוסיף פונקציה נוספת:
        function quickShareForm() {
            if (isNewForm) {
                alert('יש לשמור את הטופס לפני השיתוף');
                return;
            }

            const formUuid = '<?= $formUuid ?>';
            
            // יצירת קישור מהיר עם הגדרות ברירת מחדל
            const formData = new FormData();
            formData.append('form_uuid', formUuid);
            formData.append('allowed_users', 'null'); // פתוח לכולם
            formData.append('can_edit', '0'); // צפייה בלבד
            formData.append('permission_level', '4'); // רמת הרשאה 4
            formData.append('expires_at', 'null'); // ללא תפוגה
            formData.append('description', 'קישור מהיר');
            
            $.ajax({
                url: 'ajax/create_share_link.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const formUrl = response.link;
                        
                        if (navigator.share) {
                            navigator.share({
                                title: 'טופס הזנת נפטר',
                                url: formUrl
                            }).catch((err) => {
                                console.log('Error sharing:', err);
                                copyToClipboard(formUrl);
                            });
                        } else {
                            copyToClipboard(formUrl);
                        }
                    } else {
                        alert('שגיאה ביצירת קישור: ' + (response.message || 'שגיאה לא ידועה'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('שגיאה ביצירת הקישור');
                }
            });
        }

        // ואם תרצה, תוכל להוסיף שני כפתורים בממשק:
        // 1. כפתור "שיתוף מתקדם" שקורא ל-shareForm()
        // 2. כפתור "שיתוף מהיר" שקורא ל-quickShareForm()

        // טעינת רשימת משתמשים
        function loadUsersList() {
            $.get('ajax/get_users_list.php', function(data) {
                $('#allowed_users').html(data);
            });
        }

        // הצגה/הסתרה של שדות לפי הבחירה
        $(document).ready(function() {
            // גישה למשתמשים ספציפיים
            $('input[name="access_type"]').change(function() {
                if ($(this).val() === 'users') {
                    $('#usersSelectDiv').show();
                } else {
                    $('#usersSelectDiv').hide();
                }
            });
            
            // תוקף מותאם אישית
            $('input[name="expiry_type"]').change(function() {
                if ($(this).val() === 'custom') {
                    $('#expiryDateDiv').show();
                    // הגדר ברירת מחדל לעוד שבוע
                    setExpiryDays(7);
                } else {
                    $('#expiryDateDiv').hide();
                }
            });
        });

        // הגדרת תאריך תפוגה
        function setExpiryDays(days) {
            const date = new Date();
            date.setDate(date.getDate() + days);
            $('#expiry_date').val(date.toISOString().split('T')[0]);
        }

        // יצירת קישור השיתוף
        function createShareLink() {
            const formData = new FormData();
            formData.append('form_uuid', '<?= $formUuid ?>');
            
            // סוג גישה
            const accessType = $('input[name="access_type"]:checked').val();
            if (accessType === 'users') {
                const selectedUsers = $('#allowed_users').val();
                if (!selectedUsers || selectedUsers.length === 0) {
                    showAlert('shareLinkAlert', 'danger', 'יש לבחור לפחות משתמש אחד');
                    return;
                }
                formData.append('allowed_users', JSON.stringify(selectedUsers));
            } else {
                formData.append('allowed_users', 'null');
            }
            
            // הרשאות
            const permissionMode = $('input[name="permission_mode"]:checked').val();
            formData.append('can_edit', permissionMode === 'edit' ? '1' : '0');
            
            // רמת הרשאה
            formData.append('permission_level', $('#permission_level').val());
            
            // תוקף
            const expiryType = $('input[name="expiry_type"]:checked').val();
            if (expiryType === 'custom') {
                const expiryDate = $('#expiry_date').val();
                const expiryTime = $('#expiry_time').val();
                if (!expiryDate) {
                    showAlert('shareLinkAlert', 'danger', 'יש לבחור תאריך תפוגה');
                    return;
                }
                formData.append('expires_at', expiryDate + ' ' + expiryTime + ':00');
            } else {
                formData.append('expires_at', 'null');
            }
            
            // תיאור
            formData.append('description', $('#link_description').val());
            
            // שלח בקשה
            showAlert('shareLinkAlert', 'info', 'יוצר קישור...');
            
            $.ajax({
                url: 'ajax/create_share_link.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#shareFormModal').modal('hide');
                        showGeneratedLink(response);
                    } else {
                        showAlert('shareLinkAlert', 'danger', response.message || 'שגיאה ביצירת הקישור');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error creating link:', error);
                    showAlert('shareLinkAlert', 'danger', 'שגיאת תקשורת: ' + error);
                }
            });
        }

        // הצגת הקישור שנוצר
        function showGeneratedLink(data) {
            $('#generatedLink').val(data.link);
            
            // הצג פרטי קישור
            let details = '<strong>פרטי הקישור:</strong><br>';
            details += '• סוג גישה: ' + (data.access_type === 'public' ? 'פתוח לכולם' : 'משתמשים ספציפיים') + '<br>';
            details += '• הרשאות: ' + (data.can_edit ? 'צפייה ועריכה' : 'צפייה בלבד') + '<br>';
            if (data.expires_at) {
                details += '• תוקף עד: ' + new Date(data.expires_at).toLocaleDateString('he-IL') + '<br>';
            } else {
                details += '• תוקף: ללא הגבלה<br>';
            }
            $('#linkDetails').html(details);
            
            // צור QR Code
            $('#qrcode').empty();
            new QRCode(document.getElementById("qrcode"), {
                text: data.link,
                width: 200,
                height: 200
            });
            
            $('#showLinkModal').modal('show');
        }

        // העתקת קישור
        function copyLink() {
            const linkInput = document.getElementById('generatedLink');
            linkInput.select();
            document.execCommand('copy');
            
            // הצג הודעה
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> הועתק!';
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        }

        // שיתוף בוואטסאפ
        function shareViaWhatsApp() {
            const link = $('#generatedLink').val();
            const text = 'טופס נפטר - <?= htmlspecialchars($formData['deceased_name'] ?? 'טופס חדש') ?>\n' + link;
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
        }

        // שיתוף במייל
        function shareViaEmail() {
            const link = $('#generatedLink').val();
            const subject = 'טופס נפטר - <?= htmlspecialchars($formData['deceased_name'] ?? 'טופס חדש') ?>';
            const body = 'שלום,\n\nמצורף קישור לטופס נפטר:\n' + link + '\n\nבברכה';
            window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        }

        // פונקציית עזר להצגת הודעות
        function showAlert(elementId, type, message) {
            const alert = $('#' + elementId);
            alert.removeClass('alert-success alert-danger alert-info alert-warning');
            alert.addClass('alert-' + type);
            alert.html(message);
            alert.show();
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // משתנה לציון אם זה טופס חדש
        const isNewForm = <?= $isNewForm ? 'true' : 'false' ?>;
        
        $(document).ready(function() {
            // רשימת שדות חובה
            const requiredFields = <?= json_encode($requiredFields) ?>;
            
            // פונקציה לבדיקת שלמות הטופס
            function checkFormCompleteness() {
                let missingFields = [];
                let isComplete = true;
                
                // עבור על כל שדות החובה
                $('[data-required="true"]').each(function() {
                    const field = $(this);
                    const fieldName = field.attr('name');
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
            
            // בדיקה בכל שינוי
            $('input, select, textarea').on('change keyup', function() {
                checkFormCompleteness();
            });
            
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
            
            // טיפול בשדות תלויים של מקום קבורה
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
            
            // ולידציה לפני שליחה - רק בדיקות פורמט, לא שדות חובה
            $('#deceasedForm').on('submit', function(e) {
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
        
        // חתימה דיגיטלית
        const canvas = document.getElementById('signaturePad');
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
        
        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('client_signature').value = '';
        }
        
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
        
        
        function copyToClipboard(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('הקישור הועתק ללוח');
                }).catch(() => {
                    fallbackCopyToClipboard(text);
                });
            } else {
                fallbackCopyToClipboard(text);
            }
        }
        
        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                alert('הקישור הועתק ללוח');
            } catch (err) {
                alert('לא ניתן להעתיק את הקישור. הקישור הוא: ' + text);
            }
            
            document.body.removeChild(textArea);
        }
    </script>
    
    <?php if (!empty($debugInfo)): ?>
    <script>
        // הדפסת מידע דיבוג לקונסול
        console.log('=== FORM DEBUG INFO ===');
        console.log('Form UUID: <?= $debugInfo['formUuid'] ?>');
        console.log('Is New Form: <?= $debugInfo['isNewForm'] ? 'YES' : 'NO' ?>');
        console.log('Form Data Loaded: <?= $debugInfo['formDataLoaded'] ? 'YES' : 'NO' ?>');
        console.log('======================');
    </script>
    <?php endif; ?>
</body>
</html>

