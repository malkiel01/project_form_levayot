// form/js/form-validation.js - ולידציה ובדיקת שדות

$(document).ready(function() {
    // פונקציה לבדיקת שלמות הטופס
    function checkFormCompleteness() {
        if (formConfig.isViewOnly) return true;
        
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
        if ((idType === 'tz' || idType === 'passport') && formConfig.requiredFields.includes('identification_number')) {
            const idNumber = $('#identification_number').val();
            if (!idNumber || idNumber === '') {
                isComplete = false;
                if (!missingFields.includes('מספר זיהוי')) {
                    missingFields.push('מספר זיהוי');
                }
                $('#identification_number').addClass('field-missing');
            }
            
            const birthDate = $('#birth_date').val();
            if (!birthDate || birthDate === '' && formConfig.requiredFields.includes('birth_date')) {
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
    if (!formConfig.isViewOnly) {
        $('input, select, textarea').on('change keyup', function() {
            checkFormCompleteness();
        });
    }
    
    // טיפול בסוג זיהוי
    $('#identification_type').on('change', function() {
        const type = $(this).val();
        if (type === 'tz' || type === 'passport') {
            $('#identificationNumberDiv, #birthDateDiv').show();
            if (formConfig.requiredFields.includes('identification_number')) {
                $('#identification_number').attr('data-required', 'true');
            }
            if (formConfig.requiredFields.includes('birth_date')) {
                $('#birth_date').attr('data-required', 'true');
            }
        } else {
            $('#identificationNumberDiv, #birthDateDiv').hide();
            $('#identification_number, #birth_date').attr('data-required', 'false');
            $('#identification_number, #birth_date').removeClass('field-missing');
        }
        checkFormCompleteness();
    }).trigger('change');
    
    // ולידציה לפני שליחה - רק בדיקות פורמט
    $('#deceasedForm').on('submit', function(e) {
        if (formConfig.isViewOnly) {
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
        
        // הצג אנימציית טעינה
        $('#loadingOverlay').css('display', 'flex');
        
        // השבת את כל הכפתורים
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
    
    // הסר את האנימציה אם הדף נטען עם שגיאות
    if (typeof errors !== 'undefined' && Object.keys(errors).length > 0) {
        $('#loadingOverlay').hide();
    }
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