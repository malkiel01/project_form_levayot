<?php
// ajax/auto_save.php - שמירה אוטומטית עם עדכון סטטוס
require_once '../config.php';
require_once '../DeceasedForm.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// בדיקת CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$formUuid = $_POST['form_uuid'] ?? null;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

if (!$formUuid) {
    echo json_encode(['success' => false, 'message' => 'Form UUID required']);
    exit;
}

// סניטציה של הנתונים
$formData = sanitizeInput($_POST);
unset($formData['csrf_token'], $formData['form_uuid']);

try {
    $form = new DeceasedForm($formUuid, $userPermissionLevel);
    
    // בדוק אם הטופס קיים
    if (!$form->getFormData()) {
        // יצירת טופס חדש
        $formData['form_uuid'] = $formUuid;
        $form = new DeceasedForm(null, $userPermissionLevel);
        $form->createForm($formData);
        
        // טען מחדש את הטופס
        $form = new DeceasedForm($formUuid, $userPermissionLevel);
        $updatedData = $form->getFormData();
    } else {
        // עדכון טופס קיים
        $form->updateForm($formData);
        $updatedData = $form->getFormData();
    }
    
    echo json_encode([
        'success' => true,
        'status' => $updatedData['status'],
        'progress' => $updatedData['progress_percentage'],
        'message' => $updatedData['status'] === 'completed' ? 'הטופס הושלם!' : 'הטופס נשמר כטיוטה'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

<!-- // הוסף את הקוד הזה לקובץ form.php במקום הקוד הקיים של השמירה האוטומטית

// משתנים לשמירה אוטומטית
let autoSaveTimeout;
let lastSavedData = {};
let isSaving = false;
let formUuid = '<?= $formUuid ?>';

// פונקציה להשוואת נתונים
function hasDataChanged() {
    const currentData = $('#deceasedForm').serialize();
    return currentData !== lastSavedData;
}

// פונקציה לשמירה אוטומטית משופרת
function autoSave() {
    if (isSaving || !hasDataChanged()) {
        return;
    }
    
    isSaving = true;
    
    // הצג אינדיקטור שמירה
    const originalText = $('.btn-primary').html();
    $('.btn-primary').html('<i class="fas fa-spinner fa-spin"></i> שומר...');
    
    // אסוף את הנתונים
    const formData = $('#deceasedForm').serializeArray();
    const dataObject = {};
    
    // המר לאובייקט
    formData.forEach(item => {
        dataObject[item.name] = item.value;
    });
    
    // הוסף form_uuid
    dataObject.form_uuid = formUuid;
    
    // שלח בקשת AJAX עם התמודדות טובה יותר עם טעינה
    $.ajax({
        url: 'ajax/save_ajax.php',
        method: 'POST',
        data: dataObject,
        success: function(response) {
            if (response.success) {
                lastSavedData = $('#deceasedForm').serialize();
                
                // עדכן את הסטטוס בממשק
                updateFormStatus(response.status, response.progress);
                
                // הצג הודעת הצלחה קטנה
                showAutoSaveNotification(response.message);
                
                console.log('Form saved successfully:', response);
            } else {
                console.error('Save failed:', response.message);
                showAutoSaveNotification('שגיאה בשמירה: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('שגיאה בשמירה אוטומטית:', error);
            showAutoSaveNotification('שגיאה בשמירה אוטומטית', 'error');
        },
        complete: function() {
            isSaving = false;
            $('.btn-primary').html(originalText);
        }
    });
}

// פונקציה לעדכון סטטוס הטופס בממשק
function updateFormStatus(status, progress) {
    // עדכן את ה-progress bar
    $('.progress-bar')
        .css('width', progress + '%')
        .attr('aria-valuenow', progress)
        .text(progress + '% הושלם');
    
    // עדכן את תג הסטטוס
    const statusIndicator = $('.status-indicator');
    if (statusIndicator.length) {
        statusIndicator.removeClass('status-draft status-completed');
        if (status === 'completed') {
            statusIndicator.addClass('status-completed').text('הושלם');
        } else {
            statusIndicator.addClass('status-draft').text('טיוטה');
        }
    }
}

// פונקציה משופרת להצגת הודעת שמירה
function showAutoSaveNotification(message, type = 'success') {
    // הסר הודעות קודמות
    $('.auto-save-notification').remove();
    
    // צור אלמנט הודעה
    const bgColor = type === 'success' ? '#28a745' : '#dc3545';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    const notification = $('<div class="auto-save-notification">')
        .html('<i class="fas fa-' + icon + '"></i> ' + message)
        .css({
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            background: bgColor,
            color: 'white',
            padding: '10px 20px',
            borderRadius: '5px',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
            zIndex: 9999,
            display: 'none'
        });
    
    // הוסף לדף
    $('body').append(notification);
    
    // הצג והסתר
    notification.fadeIn(300).delay(2000).fadeOut(300, function() {
        $(this).remove();
    });
}

// הפעל שמירה אוטומטית בכל שינוי
$('#deceasedForm').on('input change', 'input, select, textarea', function() {
    // אל תפעיל שמירה אוטומטית על שדות מסוימים
    if ($(this).attr('id') === 'signaturePad') {
        return;
    }
    
    // נקה timeout קודם
    clearTimeout(autoSaveTimeout);
    
    // הגדר timeout חדש (3 שניות אחרי הפסקת ההקלדה)
    autoSaveTimeout = setTimeout(function() {
        autoSave();
    }, 3000);
});

// שמור את המצב הראשוני
$(document).ready(function() {
    lastSavedData = $('#deceasedForm').serialize();
    
    // הוסף מחוון שינויים לא שמורים
    $('input, select, textarea').on('change input', function() {
        if (hasDataChanged() && !isSaving) {
            $('#unsavedIndicator').show();
        }
    });
});

// התראה לפני יציאה אם יש שינויים לא שמורים
$(window).on('beforeunload', function() {
    if (hasDataChanged() && !isSaving) {
        return 'יש לך שינויים שלא נשמרו. האם אתה בטוח שברצונך לצאת?';
    }
});

// ביטול ההתראה בעת שליחת הטופס
$('#deceasedForm').on('submit', function(e) {
    // בדוק אם יש שמירה אוטומטית בתהליך
    if (isSaving) {
        e.preventDefault();
        showAutoSaveNotification('אנא המתן לסיום השמירה האוטומטית', 'warning');
        
        // נסה שוב אחרי שנייה
        setTimeout(function() {
            if (!isSaving) {
                $('#deceasedForm').submit();
            }
        }, 1000);
        
        return false;
    }
    
    // ביטול התראת יציאה
    $(window).off('beforeunload');
});

// הוסף כפתור לשמירה ידנית כטיוטה
if ($('#saveDraftBtn').length === 0) {
    $('<button type="button" class="btn btn-outline-primary btn-lg ms-2" id="saveDraftBtn">' +
      '<i class="fas fa-save"></i> שמור כטיוטה' +
      '</button>').insertAfter('.btn-primary');
}

$('#saveDraftBtn').on('click', function() {
    // ביטול timeout של שמירה אוטומטית
    clearTimeout(autoSaveTimeout);
    
    // שמירה מיידית
    autoSave();
});

// הוסף מחוון שינויים לא שמורים
if ($('#unsavedIndicator').length === 0) {
    $('<span id="unsavedIndicator" class="text-warning ms-3" style="display:none;">' +
      '<i class="fas fa-exclamation-circle"></i> יש שינויים שלא נשמרו' +
      '</span>').insertAfter('#saveDraftBtn');
}

// פונקציה לטיפול בשמירה רגילה של הטופס
$('#deceasedForm').on('submit', function(e) {
    // הוסף שדה נסתר לציון שזו שמירה סופית
    if (!$('#finalSave').length) {
        $(this).append('<input type="hidden" id="finalSave" name="final_save" value="1">');
    }
});

// שמירה אוטומטית כל 30 שניות אם יש שינויים
setInterval(function() {
    if (hasDataChanged() && !isSaving) {
        autoSave();
    }
}, 30000);

// הוסף אינדיקטור חיבור
function checkConnection() {
    $.ajax({
        url: 'ajax/ping.php',
        timeout: 5000,
        success: function() {
            $('#connectionStatus').removeClass('text-danger').addClass('text-success')
                .html('<i class="fas fa-wifi"></i> מחובר');
        },
        error: function() {
            $('#connectionStatus').removeClass('text-success').addClass('text-danger')
                .html('<i class="fas fa-wifi-slash"></i> אין חיבור');
        }
    });
}

// בדוק חיבור כל 10 שניות
setInterval(checkConnection, 10000);

// הוסף סטטוס חיבור לדף
if ($('#connectionStatus').length === 0) {
    $('<small id="connectionStatus" class="text-success position-fixed" style="top: 10px; left: 10px; z-index: 9999;">' +
      '<i class="fas fa-wifi"></i> מחובר' +
      '</small>').appendTo('body');
} -->