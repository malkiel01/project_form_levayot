<?php
// form.php - ממשק טופס נפטרים עם תיקונים

require_once 'config.php';
require_once 'DeceasedForm.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// קבלת רמת הרשאה
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

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
        // הטופס לא קיים - זה טופס חדש
        $isNewForm = true;
        $formData = []; // טופס ריק
        
        // יצירת אובייקט טופס חדש ללא UUID (נעביר אותו בשמירה)
        $form = new DeceasedForm(null, $userPermissionLevel);
    } else {
        // הטופס קיים - טען את הנתונים
        $isNewForm = false;
        $formData = $existingFormData;
    }
}

// הדפסה לקונסול לצורכי דיבוג
echo "<script>
console.log('=== FORM DEBUG INFO ===');
console.log('Form UUID: " . $formUuid . "');
console.log('Is New Form: " . ($isNewForm ? 'YES' : 'NO') . "');
console.log('Form Data Loaded: " . (!empty($formData) ? 'YES' : 'NO') . "');
console.log('======================');
</script>";

// רישום בלוג
error_log("FORM ACCESS - UUID: $formUuid, Is New: " . ($isNewForm ? 'YES' : 'NO') . ", User: " . $_SESSION['user_id']);

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // סניטציה של הנתונים
    $postData = sanitizeInput($_POST);
    unset($postData['csrf_token']);
    
    // ולידציה
    $errors = $form->validateForm($postData);
    
    if (empty($errors)) {
        try {
            if ($isNewForm) {
                // יצירת טופס חדש
                $postData['form_uuid'] = $formUuid; // וודא שה-UUID נשמר
                $form->createForm($postData);
                $successMessage = "הטופס נוצר בהצלחה";
                
                // לאחר יצירה מוצלחת, הטופס כבר לא חדש
                $isNewForm = false;
                
                // טען מחדש את הנתונים
                $form = new DeceasedForm($formUuid, $userPermissionLevel);
                $formData = $form->getFormData();
                
                echo "<script>console.log('Form created successfully with UUID: " . $formUuid . "');</script>";
            } else {
                // עדכון טופס קיים
                $form->updateForm($postData);
                $successMessage = "הטופס עודכן בהצלחה";
                
                // טען מחדש את הנתונים המעודכנים
                $formData = $form->getFormData();
                
                echo "<script>console.log('Form updated successfully');</script>";
            }
        } catch (Exception $e) {
            $errorMessage = "שגיאה בשמירת הטופס: " . $e->getMessage();
            error_log("Form save error: " . $e->getMessage());
        }
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
            </div>
            
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
            
            <form method="POST" id="deceasedForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- פרטי הנפטר -->
                <div class="section-title">פרטי הנפטר</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="identification_type" class="form-label <?= $form && $form->canEditField('identification_type') ? 'required' : '' ?>">
                            סוג זיהוי
                        </label>
                        <select class="form-select <?= isset($errors['identification_type']) ? 'is-invalid' : '' ?>" 
                                id="identification_type" name="identification_type" 
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
                        <label for="deceased_name" class="form-label <?= $form && $form->canEditField('deceased_name') ? 'required' : '' ?>">
                            שם הנפטר
                        </label>
                        <input type="text" class="form-control <?= isset($errors['deceased_name']) ? 'is-invalid' : '' ?>" 
                               id="deceased_name" name="deceased_name" 
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
                        <label for="death_date" class="form-label <?= $form && $form->canEditField('death_date') ? 'required' : '' ?>">
                            תאריך פטירה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['death_date']) ? 'is-invalid' : '' ?>" 
                               id="death_date" name="death_date" 
                               value="<?= $formData['death_date'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('death_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="death_time" class="form-label <?= $form && $form->canEditField('death_time') ? 'required' : '' ?>">
                            שעת פטירה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['death_time']) ? 'is-invalid' : '' ?>" 
                               id="death_time" name="death_time" 
                               value="<?= $formData['death_time'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('death_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_time'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_date" class="form-label <?= $form && $form->canEditField('burial_date') ? 'required' : '' ?>">
                            תאריך קבורה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['burial_date']) ? 'is-invalid' : '' ?>" 
                               id="burial_date" name="burial_date" 
                               value="<?= $formData['burial_date'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('burial_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_time" class="form-label <?= $form && $form->canEditField('burial_time') ? 'required' : '' ?>">
                            שעת קבורה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['burial_time']) ? 'is-invalid' : '' ?>" 
                               id="burial_time" name="burial_time" 
                               value="<?= $formData['burial_time'] ?? '' ?>"
                               <?= !$form || !$form->canEditField('burial_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_time'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="burial_license" class="form-label <?= $form && $form->canEditField('burial_license') ? 'required' : '' ?>">
                            רשיון קבורה
                        </label>
                        <input type="text" class="form-control <?= isset($errors['burial_license']) ? 'is-invalid' : '' ?>" 
                               id="burial_license" name="burial_license" 
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
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> שמור טופס
                        </button>
                        <?php if (!$isNewForm): ?>
                            <a href="view_form.php?id=<?= $formUuid ?>" class="btn btn-info btn-lg ms-2">
                                <i class="fas fa-eye"></i> צפייה בטופס
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-success btn-lg ms-2" onclick="shareForm()">
                            <i class="fas fa-share"></i> שתף טופס
                        </button>
                        <a href="forms_list.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    </script>
</body>
</html>
        $(document).ready(function() {
            // טיפול בסוג זיהוי
            $('#identification_type').on('change', function() {
                const type = $(this).val();
                if (type === 'tz' || type === 'passport') {
                    $('#identificationNumberDiv, #birthDateDiv').show();
                    $('#identification_number, #birth_date').prop('required', true);
                } else {
                    $('#identificationNumberDiv, #birthDateDiv').hide();
                    $('#identification_number, #birth_date').prop('required', false).val('');
                }
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
            
            // ולידציה לפני שליחה
            $('#deceasedForm').on('submit', function(e) {
                let hasError = false;
                $('.is-invalid').removeClass('is-invalid');
                
                // בדיקת שדות חובה
                const requiredFields = {
                    'identification_type': 'סוג זיהוי',
                    'deceased_name': 'שם הנפטר',
                    'death_date': 'תאריך פטירה',
                    'death_time': 'שעת פטירה',
                    'burial_date': 'תאריך קבורה',
                    'burial_time': 'שעת קבורה',
                    'burial_license': 'רשיון קבורה'
                };
                
                // בדיקה מותנית לסוג זיהוי
                const idType = $('#identification_type').val();
                if (idType === 'tz' || idType === 'passport') {
                    requiredFields['identification_number'] = 'מספר זיהוי';
                    requiredFields['birth_date'] = 'תאריך לידה';
                }
                
                for (let field in requiredFields) {
                    const value = $('[name="' + field + '"]').val();
                    if (!value || value === '') {
                        hasError = true;
                        $('[name="' + field + '"]').addClass('is-invalid');
                        
                        // הוסף הודעת שגיאה אם אין
                        if (!$('[name="' + field + '"]').next('.invalid-feedback').length) {
                            $('[name="' + field + '"]').after('<div class="invalid-feedback">' + requiredFields[field] + ' הוא שדה חובה</div>');
                        }
                    }
                }
                
                // בדיקת תאריכים
                const deathDate = $('#death_date').val();
                const burialDate = $('#burial_date').val();
                
                if (deathDate && burialDate && new Date(burialDate) < new Date(deathDate)) {
                    hasError = true;
                    $('#burial_date').addClass('is-invalid');
                    if (!$('#burial_date').next('.invalid-feedback').length) {
                        $('#burial_date').after('<div class="invalid-feedback">תאריך הקבורה לא יכול להיות לפני תאריך הפטירה</div>');
                    }
                }
                
                if (hasError) {
                    e.preventDefault();
                    
                    // גלול לשדה השגוי הראשון
                    const firstError = $('.is-invalid').first();
                    if (firstError.length) {
                        $('html, body').animate({
                            scrollTop: firstError.offset().top - 100
                        }, 500);
                    }
                    
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
        
        // שיתוף טופס
        function shareForm() {
            const formUrl = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: 'טופס הזנת נפטר',
                    url: formUrl
                }).catch(err => {
                    console.log('Error sharing:', err);
                    copyToClipboard(formUrl);
                });
            } else {
                copyToClipboard(formUrl);
            }
        }
        
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