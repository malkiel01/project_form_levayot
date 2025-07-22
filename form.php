<?php
// form.php - ממשק טופס נפטרים

require_once 'config.php';
require_once 'DeceasedForm.php';

// בדיקת התחברות - הוסף את זה!
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


// קבלת רמת הרשאה (לצורך הדגמה, בפועל תקבל מהמשתמש המחובר)
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;


// בדיקה אם יש ID של טופס ב-URL
$formUuid = $_GET['id'] ?? null;
$form = new DeceasedForm($formUuid, $userPermissionLevel);

// הוסף את זה:
echo "<!-- DEBUG: Permission Level = $userPermissionLevel -->";
try {
    $test = $form->canEditField('identification_type');
    echo "<!-- DEBUG: canEditField test = " . ($test ? 'true' : 'false') . " -->";
} catch (Exception $e) {
    echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
}

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // סניטציה של הנתונים
    $formData = sanitizeInput($_POST);
    unset($formData['csrf_token']);
    
    // ולידציה
    $errors = $form->validateForm($formData);
    
    if (empty($errors)) {
        try {
            if ($formUuid) {
                // עדכון טופס קיים
                $form->updateForm($formData);
                $successMessage = "הטופס עודכן בהצלחה";
            } else {
                // יצירת טופס חדש
                $newFormId = $form->createForm($formData);
                header("Location: form.php?id=" . $newFormId);
                exit;
            }
        } catch (Exception $e) {
            $errorMessage = "שגיאה בשמירת הטופס: " . $e->getMessage();
        }
    }
}

// קבלת נתוני הטופס
$formData = $form->getFormData() ?? [];

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>טופס הזנת נפטר</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="text-center mb-4">
                <i class="fas fa-file-alt"></i> טופס הזנת נפטר
            </h2>
            
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
                        <label for="identification_type" class="form-label <?= $form->canEditField('identification_type') ? 'required' : '' ?>">
                            סוג זיהוי
                        </label>
                        <select class="form-select <?= isset($errors['identification_type']) ? 'is-invalid' : '' ?>" 
                                id="identification_type" name="identification_type" 
                                <?= !$form->canEditField('identification_type') ? 'disabled' : '' ?>>
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
                               <?= !$form->canEditField('identification_number') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['identification_number'])): ?>
                            <div class="invalid-feedback"><?= $errors['identification_number'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="deceased_name" class="form-label <?= $form->canEditField('deceased_name') ? 'required' : '' ?>">
                            שם הנפטר
                        </label>
                        <input type="text" class="form-control <?= isset($errors['deceased_name']) ? 'is-invalid' : '' ?>" 
                               id="deceased_name" name="deceased_name" 
                               value="<?= $formData['deceased_name'] ?? '' ?>"
                               <?= !$form->canEditField('deceased_name') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['deceased_name'])): ?>
                            <div class="invalid-feedback"><?= $errors['deceased_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="father_name" class="form-label">שם האב</label>
                        <input type="text" class="form-control" id="father_name" name="father_name" 
                               value="<?= $formData['father_name'] ?? '' ?>"
                               <?= !$form->canEditField('father_name') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="mother_name" class="form-label">שם האם</label>
                        <input type="text" class="form-control" id="mother_name" name="mother_name" 
                               value="<?= $formData['mother_name'] ?? '' ?>"
                               <?= !$form->canEditField('mother_name') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6" id="birthDateDiv">
                        <label for="birth_date" class="form-label">תאריך לידה</label>
                        <input type="date" class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>" 
                               id="birth_date" name="birth_date" 
                               value="<?= $formData['birth_date'] ?? '' ?>"
                               <?= !$form->canEditField('birth_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['birth_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['birth_date'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- פרטי הפטירה -->
                <div class="section-title">פרטי הפטירה</div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="death_date" class="form-label <?= $form->canEditField('death_date') ? 'required' : '' ?>">
                            תאריך פטירה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['death_date']) ? 'is-invalid' : '' ?>" 
                               id="death_date" name="death_date" 
                               value="<?= $formData['death_date'] ?? '' ?>"
                               <?= !$form->canEditField('death_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="death_time" class="form-label <?= $form->canEditField('death_time') ? 'required' : '' ?>">
                            שעת פטירה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['death_time']) ? 'is-invalid' : '' ?>" 
                               id="death_time" name="death_time" 
                               value="<?= $formData['death_time'] ?? '' ?>"
                               <?= !$form->canEditField('death_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['death_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['death_time'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_date" class="form-label <?= $form->canEditField('burial_date') ? 'required' : '' ?>">
                            תאריך קבורה
                        </label>
                        <input type="date" class="form-control <?= isset($errors['burial_date']) ? 'is-invalid' : '' ?>" 
                               id="burial_date" name="burial_date" 
                               value="<?= $formData['burial_date'] ?? '' ?>"
                               <?= !$form->canEditField('burial_date') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_date'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="burial_time" class="form-label <?= $form->canEditField('burial_time') ? 'required' : '' ?>">
                            שעת קבורה
                        </label>
                        <input type="time" class="form-control <?= isset($errors['burial_time']) ? 'is-invalid' : '' ?>" 
                               id="burial_time" name="burial_time" 
                               value="<?= $formData['burial_time'] ?? '' ?>"
                               <?= !$form->canEditField('burial_time') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_time'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_time'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="burial_license" class="form-label <?= $form->canEditField('burial_license') ? 'required' : '' ?>">
                            רשיון קבורה
                        </label>
                        <input type="text" class="form-control <?= isset($errors['burial_license']) ? 'is-invalid' : '' ?>" 
                               id="burial_license" name="burial_license" 
                               value="<?= $formData['burial_license'] ?? '' ?>"
                               <?= !$form->canEditField('burial_license') ? 'disabled' : '' ?>>
                        <?php if (isset($errors['burial_license'])): ?>
                            <div class="invalid-feedback"><?= $errors['burial_license'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="death_location" class="form-label">מקום הפטירה</label>
                        <input type="text" class="form-control" id="death_location" name="death_location" 
                               value="<?= $formData['death_location'] ?? '' ?>"
                               <?= !$form->canEditField('death_location') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <!-- מקום הקבורה - רק למנהלים -->
                <?php if ($form->canViewField('cemetery_id')): ?>
                <div class="section-title">מקום הקבורה</div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="cemetery_id" class="form-label">בית עלמין</label>
                        <select class="form-select" id="cemetery_id" name="cemetery_id" 
                                <?= !$form->canEditField('cemetery_id') ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
                            <?php foreach ($form->getCemeteries() as $cemetery): ?>
                                <option value="<?= $cemetery['id'] ?>" 
                                        <?= ($formData['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                                    <?= $cemetery['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="block_id" class="form-label">גוש</label>
                        <select class="form-select" id="block_id" name="block_id" 
                                <?= !$form->canEditField('block_id') ? 'disabled' : '' ?>>
                            <option value="">בחר קודם בית עלמין</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="section_id" class="form-label">חלקה</label>
                        <select class="form-select" id="section_id" name="section_id" 
                                <?= !$form->canEditField('section_id') ? 'disabled' : '' ?>>
                            <option value="">בחר קודם גוש</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="row_id" class="form-label">שורה</label>
                        <select class="form-select" id="row_id" name="row_id" 
                                <?= !$form->canEditField('row_id') ? 'disabled' : '' ?>>
                            <option value="">בחר קודם חלקה</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="grave_id" class="form-label">קבר</label>
                        <select class="form-select" id="grave_id" name="grave_id" 
                                <?= !$form->canEditField('grave_id') ? 'disabled' : '' ?>>
                            <option value="">בחר קודם שורה</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="plot_id" class="form-label">אחוזת קבר</label>
                        <select class="form-select" id="plot_id" name="plot_id" 
                                <?= !$form->canEditField('plot_id') ? 'disabled' : '' ?>>
                            <option value="">בחר...</option>
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
                               <?= !$form->canEditField('informant_name') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_phone" class="form-label">טלפון</label>
                        <input type="tel" class="form-control" id="informant_phone" name="informant_phone" 
                               value="<?= $formData['informant_phone'] ?? '' ?>"
                               <?= !$form->canEditField('informant_phone') ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="informant_relationship" class="form-label">קרבה משפחתית</label>
                        <input type="text" class="form-control" id="informant_relationship" name="informant_relationship" 
                               value="<?= $formData['informant_relationship'] ?? '' ?>"
                               <?= !$form->canEditField('informant_relationship') ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="notes" class="form-label">הערות</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  <?= !$form->canEditField('notes') ? 'disabled' : '' ?>><?= $formData['notes'] ?? '' ?></textarea>
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
                        <?php if ($formUuid): ?>
                            <button type="button" class="btn btn-success btn-lg ms-2" onclick="shareForm()">
                                <i class="fas fa-share"></i> שתף טופס
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // טיפול בתצוגת שדות מותנים
        $(document).ready(function() {
            $('#identification_type').on('change', function() {
                const type = $(this).val();
                if (type === 'tz' || type === 'passport') {
                    $('#identificationNumberDiv, #birthDateDiv').show();
                    $('#identification_number, #birth_date').prop('required', true);
                } else {
                    $('#identificationNumberDiv, #birthDateDiv').hide();
                    $('#identification_number, #birth_date').prop('required', false);
                }
            }).trigger('change');
            
            
            // --------------------------------
            // הוסף את הקוד הזה ל-form.php בתוך תג <script> בסוף הדף

            // ניקוי שדות תלויים כשמשנים שדה הורה
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
                    });
                    $.get('ajax/get_plots.php', {cemetery_id: cemeteryId}, function(data) {
                        $('#plot_id').html(data);
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
                    });
                }
            });
            
            // וודא שרק ערכים תקינים נשלחים
            $('#deceasedForm').on('submit', function(e) {
                // אם השדה ריק, הסר אותו מהטופס
                $('select').each(function() {
                    if ($(this).val() === '' || $(this).val() === null) {
                        $(this).removeAttr('name');
                    }
                });
                
                // וודא שיש לפחות את השדות הנדרשים
                const requiredFields = ['identification_type', 'deceased_name', 'death_date', 'death_time', 'burial_date', 'burial_time', 'burial_license'];
                let hasError = false;
                
                requiredFields.forEach(function(field) {
                    const value = $('[name="' + field + '"]').val();
                    if (!value || value === '') {
                        hasError = true;
                        $('[name="' + field + '"]').addClass('is-invalid');
                    }
                });
                
                if (hasError) {
                    e.preventDefault();
                    alert('יש למלא את כל השדות החובה');
                    return false;
                }
            });
            // --------------------------------
            
            
            // // טעינת רשימות תלויות למקום קבורה
            // $('#cemetery_id').on('change', function() {
            //     const cemeteryId = $(this).val();
            //     if (cemeteryId) {
            //         $.get('ajax/get_blocks.php', {cemetery_id: cemeteryId}, function(data) {
            //             $('#block_id').html(data);
            //         });
            //         $.get('ajax/get_plots.php', {cemetery_id: cemeteryId}, function(data) {
            //             $('#plot_id').html(data);
            //         });
            //     }
            // });
            
            // $('#block_id').on('change', function() {
            //     const blockId = $(this).val();
            //     if (blockId) {
            //         $.get('ajax/get_sections.php', {block_id: blockId}, function(data) {
            //             $('#section_id').html(data);
            //         });
            //     }
            // });
            
            // $('#section_id').on('change', function() {
            //     const sectionId = $(this).val();
            //     if (sectionId) {
            //         $.get('ajax/get_rows.php', {section_id: sectionId}, function(data) {
            //             $('#row_id').html(data);
            //         });
            //     }
            // });
            
            // $('#row_id').on('change', function() {
            //     const rowId = $(this).val();
            //     if (rowId) {
            //         $.get('ajax/get_graves.php', {row_id: rowId}, function(data) {
            //             $('#grave_id').html(data);
            //         });
            //     }
            // });
        });
        
        // חתימה דיגיטלית
        const canvas = document.getElementById('signaturePad');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        
        // התאמת גודל הקנבס
        canvas.width = canvas.offsetWidth;
        canvas.height = 200;
        
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
        const existingSignature = document.getElementById('client_signature').value;
        if (existingSignature) {
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0);
            };
            img.src = existingSignature;
        }
        
        // שיתוף טופס
        function shareForm() {
            const formUrl = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: 'טופס הזנת נפטר',
                    url: formUrl
                });
            } else {
                // העתקה ללוח
                navigator.clipboard.writeText(formUrl);
                alert('הקישור הועתק ללוח');
            }
        }
    </script>
</body>
</html>