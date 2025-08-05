// form-autosave.js - מערכת שמירה אוטומטית לטופס נפטרים

// משתנים גלובליים לשמירה אוטומטית
let autoSaveTimeout;
let lastSavedData = {};
let isSaving = false;
let formUuid = '';

// אתחול בטעינת הדף
$(document).ready(function() {
    // קבלת ה-UUID מה-URL
    const urlParams = new URLSearchParams(window.location.search);
    formUuid = urlParams.get('id') || urlParams.get('uuid') || '';
    
    // אם אין UUID, אל תפעיל שמירה אוטומטית
    if (!formUuid) {
        console.warn('No form UUID found - autosave disabled');
        return;
    }
    
    // שמור את המצב הראשוני
    lastSavedData = $('#deceasedForm').serialize();
    
    // הוסף listeners לשינויים
    initializeAutoSave();
    
    // הוסף כפתור שמירה ידנית
    addSaveDraftButton();
    
    // הוסף מחוון שינויים
    addUnsavedIndicator();
    
    // הוסף בדיקת חיבור
    initializeConnectionCheck();
});

// פונקציה להשוואת נתונים
function hasDataChanged() {
    const currentData = $('#deceasedForm').serialize();
    return currentData !== lastSavedData;
}

// פונקציה לשמירה אוטומטית
function autoSave() {
    if (isSaving || !hasDataChanged() || !formUuid) {
        return;
    }
    
    isSaving = true;
    
    // הצג אינדיקטור שמירה
    const $submitBtn = $('.btn-primary[type="submit"]');
    const originalText = $submitBtn.html();
    $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> שומר...');
    
    // אסוף את הנתונים
    const formData = $('#deceasedForm').serializeArray();
    const dataObject = {};
    
    // המר לאובייקט
    formData.forEach(item => {
        dataObject[item.name] = item.value;
    });
    
    // הוסף form_uuid ו-csrf_token
    dataObject.form_uuid = formUuid;
    dataObject.csrf_token = $('meta[name="csrf-token"]').attr('content') || 
                           $('input[name="csrf_token"]').val() || 
                           '';
    
    // שלח בקשת AJAX
    $.ajax({
        url: 'ajax/save_ajax.php',
        method: 'POST',
        data: dataObject,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                lastSavedData = $('#deceasedForm').serialize();
                
                // עדכן את הסטטוס בממשק
                updateFormStatus(response.status, response.progress);
                
                // הצג הודעת הצלחה
                showAutoSaveNotification(response.message);
                
                // הסתר מחוון שינויים
                $('#unsavedIndicator').hide();
                
                console.log('Form auto-saved successfully');
            } else {
                console.error('Save failed:', response.message);
                showAutoSaveNotification('שגיאה בשמירה: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Auto-save error:', error);
            showAutoSaveNotification('שגיאה בחיבור לשרת', 'error');
        },
        complete: function() {
            isSaving = false;
            $submitBtn.html(originalText);
        }
    });
}

// עדכון סטטוס הטופס בממשק
function updateFormStatus(status, progress) {
    // עדכן progress bar
    const $progressBar = $('.progress-bar');
    if ($progressBar.length) {
        $progressBar
            .css('width', progress + '%')
            .attr('aria-valuenow', progress)
            .text(progress + '% הושלם');
    }
    
    // עדכן תג סטטוס
    const $statusIndicator = $('.status-indicator');
    if ($statusIndicator.length) {
        $statusIndicator.removeClass('status-draft status-completed status-in-progress');
        
        switch(status) {
            case 'completed':
                $statusIndicator.addClass('status-completed').text('הושלם');
                break;
            case 'in_progress':
                $statusIndicator.addClass('status-in-progress').text('בתהליך');
                break;
            default:
                $statusIndicator.addClass('status-draft').text('טיוטה');
        }
    }
}

// הצגת הודעת שמירה
function showAutoSaveNotification(message, type = 'success') {
    // הסר הודעות קודמות
    $('.auto-save-notification').remove();
    
    // צור אלמנט הודעה
    const bgColor = type === 'success' ? '#28a745' : '#dc3545';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    const $notification = $('<div class="auto-save-notification">')
        .html(`<i class="fas fa-${icon}"></i> ${message}`)
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
            display: 'none',
            fontSize: '14px'
        });
    
    // הוסף לדף
    $('body').append($notification);
    
    // הצג והסתר
    $notification.fadeIn(300).delay(2000).fadeOut(300, function() {
        $(this).remove();
    });
}

// אתחול מערכת השמירה האוטומטית
function initializeAutoSave() {
    // שמירה אוטומטית בכל שינוי
    $('#deceasedForm').on('input change', 'input, select, textarea', function() {
        // אל תפעיל על שדות מסוימים
        if ($(this).attr('id') === 'signaturePad' || $(this).attr('type') === 'file') {
            return;
        }
        
        // הצג מחוון שינויים
        if (hasDataChanged()) {
            $('#unsavedIndicator').show();
        }
        
        // נקה timeout קודם
        clearTimeout(autoSaveTimeout);
        
        // הגדר timeout חדש (3 שניות אחרי הפסקת ההקלדה)
        autoSaveTimeout = setTimeout(function() {
            autoSave();
        }, 3000);
    });
    
    // שמירה אוטומטית כל 30 שניות
    setInterval(function() {
        if (hasDataChanged() && !isSaving) {
            autoSave();
        }
    }, 30000);
    
    // התראה לפני יציאה
    $(window).on('beforeunload', function() {
        if (hasDataChanged() && !isSaving) {
            return 'יש לך שינויים שלא נשמרו. האם אתה בטוח שברצונך לצאת?';
        }
    });
    
    // ביטול התראה בשליחת הטופס
    $('#deceasedForm').on('submit', function(e) {
        // בדוק אם יש שמירה בתהליך
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
}

// הוספת כפתור שמירה ידנית
function addSaveDraftButton() {
    if ($('#saveDraftBtn').length === 0) {
        const $saveDraftBtn = $('<button type="button" class="btn btn-outline-primary btn-lg ms-2" id="saveDraftBtn">' +
                               '<i class="fas fa-save"></i> שמור כטיוטה' +
                               '</button>');
        
        // הוסף אחרי כפתור השליחה
        $('.btn-primary[type="submit"]').after($saveDraftBtn);
        
        // הוסף פונקציונליות
        $saveDraftBtn.on('click', function() {
            clearTimeout(autoSaveTimeout);
            autoSave();
        });
    }
}

// הוספת מחוון שינויים
function addUnsavedIndicator() {
    if ($('#unsavedIndicator').length === 0) {
        const $indicator = $('<span id="unsavedIndicator" class="text-warning ms-3" style="display:none;">' +
                           '<i class="fas fa-exclamation-circle"></i> יש שינויים שלא נשמרו' +
                           '</span>');
        
        $('#saveDraftBtn').after($indicator);
    }
}

// בדיקת חיבור לשרת
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

// אתחול בדיקת חיבור
function initializeConnectionCheck() {
    // הוסף סטטוס חיבור
    if ($('#connectionStatus').length === 0) {
        $('<small id="connectionStatus" class="text-success position-fixed" style="top: 10px; left: 10px; z-index: 9999;">' +
          '<i class="fas fa-wifi"></i> מחובר' +
          '</small>').appendTo('body');
    }
    
    // בדוק חיבור כל 10 שניות
    setInterval(checkConnection, 10000);
    
    // בדיקה ראשונית
    checkConnection();
}

// חשיפת פונקציות לשימוש חיצוני
window.FormAutoSave = {
    save: autoSave,
    hasChanges: hasDataChanged,
    reset: function() {
        lastSavedData = $('#deceasedForm').serialize();
        $('#unsavedIndicator').hide();
    }
};