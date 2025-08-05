<?php
// includes/purchase_form_scripts.php - סקריפטים לטופס רכישות

/**
 * רנדור סקריפטים לטופס
 */
function renderPurchaseFormScripts() {
    ?>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/he.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <!-- jQuery Validation -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/localization/messages_he.js"></script>
    
    <!-- Form Scripts -->
    <script>
    $(document).ready(function() {
        // אתחול Select2
        $('.form-select').select2({
            theme: 'bootstrap-5',
            language: 'he',
            dir: 'rtl',
            placeholder: 'בחר...',
            allowClear: true
        });
        
        // טיפול בבחירת מיקום דינמית
        $('#cemetery_id').on('change', function() {
            const cemeteryId = $(this).val();
            loadBlocks(cemeteryId);
        });
        
        $('#block_id').on('change', function() {
            const blockId = $(this).val();
            loadSections(blockId);
        });
        
        $('#section_id').on('change', function() {
            const sectionId = $(this).val();
            loadRows(sectionId);
        });
        
        $('#row_id').on('change', function() {
            const rowId = $(this).val();
            loadGraves(rowId);
        });
        
        $('#grave_id').on('change', function() {
            const graveId = $(this).val();
            loadPlots(graveId);
        });
        
        // טיפול בחישובי תשלומים
        $('#purchase_price, #payment_amount').on('input', function() {
            calculateRemainingBalance();
        });
        
        // הצגת/הסתרת שדה תשלומים
        $('#payment_method').on('change', function() {
            if ($(this).val() === 'installments') {
                $('#installmentsGroup').show();
            } else {
                $('#installmentsGroup').hide();
            }
        });
        
        // הוספת הנהנה
        let beneficiaryIndex = $('.beneficiary-row').length;
        
        $('#addBeneficiary').on('click', function() {
            const newRow = `
                <div class="beneficiary-row" data-index="${beneficiaryIndex}">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>שם מלא</label>
                            <input type="text" 
                                   name="beneficiaries[${beneficiaryIndex}][name]" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label>תעודת זהות</label>
                            <input type="text" 
                                   name="beneficiaries[${beneficiaryIndex}][id_number]" 
                                   class="form-control"
                                   maxlength="9">
                        </div>
                        <div class="form-group">
                            <label>קרבה לרוכש</label>
                            <input type="text" 
                                   name="beneficiaries[${beneficiaryIndex}][relation]" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-danger btn-sm remove-beneficiary" data-index="${beneficiaryIndex}">
                                <i class="fas fa-trash"></i> הסר
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#beneficiariesContainer').append(newRow);
            beneficiaryIndex++;
        });
        
        // הסרת הנהנה
        $(document).on('click', '.remove-beneficiary', function() {
            $(this).closest('.beneficiary-row').remove();
        });
        
        // אתחול חתימה דיגיטלית
        if (document.getElementById('signaturePad') && !formConfig.isViewOnly) {
            const canvas = document.getElementById('signaturePad');
            const signaturePad = new SignaturePad(canvas);
            
            // התאמת גודל הקנבס
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }
            
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();
            
            // ניקוי חתימה
            $('#clearSignature').on('click', function() {
                signaturePad.clear();
                $('#signatureData').val('');
            });
            
            // שמירת חתימה בשליחת הטופס
            $('#purchaseForm').on('submit', function() {
                if (!signaturePad.isEmpty()) {
                    $('#signatureData').val(signaturePad.toDataURL());
                }
            });
        }
        
        // ולידציה של הטופס
        $('#purchaseForm').validate({
            rules: {
                purchaser_first_name: { required: true },
                purchaser_last_name: { required: true },
                purchaser_id: { 
                    required: true,
                    digits: true,
                    minlength: 9,
                    maxlength: 9
                },
                purchaser_phone: { required: true },
                purchaser_email: { email: true },
                purchase_date: { required: true },
                purchase_type: { required: true },
                cemetery_id: { required: true },
                block_id: { required: true },
                payment_method: { required: true },
                payment_amount: { 
                    required: true,
                    number: true,
                    min: 0
                }
            },
            messages: {
                purchaser_id: {
                    digits: "יש להזין ספרות בלבד",
                    minlength: "ת.ז. חייבת להכיל 9 ספרות",
                    maxlength: "ת.ז. חייבת להכיל 9 ספרות"
                }
            },
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            }
        });
        
        // בדיקת שדות חסרים
        function checkMissingFields() {
            const missing = [];
            formConfig.requiredFields.forEach(field => {
                const value = $(`[name="${field}"]`).val();
                if (!value || value.trim() === '') {
                    const label = $(`[for="${field}"]`).text().replace(' *', '');
                    missing.push(label || fieldTranslations[field] || field);
                }
            });
            
            if (missing.length > 0) {
                $('#missingFieldsList').html(missing.map(field => `<li>${field}</li>`).join(''));
                $('#missingFieldsAlert').show();
            } else {
                $('#missingFieldsAlert').hide();
            }
        }
        
        // בדיקה בשינוי שדה
        $('input, select, textarea').on('change', checkMissingFields);
        
        // בדיקה ראשונית
        if (!formConfig.isNewForm) {
            checkMissingFields();
        }
        
        // תצוגה מקדימה
        $('#previewForm').on('click', function() {
            window.open(`index_purchase.php?uuid=${formConfig.formUuid}&preview=1`, '_blank');
        });
        
        // שיתוף טופס
        $('#shareForm').on('click', function() {
            generateShareLink();
        });
        
        // העתקת קישור שיתוף
        $('#copyShareLink').on('click', function() {
            const shareLink = $('#shareLink');
            shareLink.select();
            document.execCommand('copy');
            
            Swal.fire({
                icon: 'success',
                title: 'הקישור הועתק!',
                showConfirmButton: false,
                timer: 1500
            });
        });
        
        // אישור לפני שליחה
        $('button[value="submit"]').on('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'האם לשלוח את הטופס לאישור?',
                text: 'לאחר השליחה לא תוכל לערוך את הטופס',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'כן, שלח',
                cancelButtonText: 'ביטול'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#purchaseForm').append('<input type="hidden" name="action" value="submit">').submit();
                }
            });
        });
    });
    
    // פונקציות עזר
    function showLoading() {
        $('#loadingOverlay').show();
    }
    
    function hideLoading() {
        $('#loadingOverlay').hide();
    }
    
    // טעינת נתונים דינמית
    function loadBlocks(cemeteryId) {
        if (!cemeteryId) {
            $('#block_id').html('<option value="">בחר גוש</option>').prop('disabled', true).trigger('change');
            return;
        }
        
        showLoading();
        $.ajax({
            url: '../api/get_locations.php',
            method: 'GET',
            data: { action: 'get_blocks', cemetery_id: cemeteryId },
            success: function(response) {
                let options = '<option value="">בחר גוש</option>';
                response.forEach(block => {
                    options += `<option value="${block.id}">${block.name}</option>`;
                });
                $('#block_id').html(options).prop('disabled', false).trigger('change');
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן לטעון את רשימת הגושים', 'error');
            },
            complete: hideLoading
        });
    }
    
    function loadSections(blockId) {
        if (!blockId) {
            $('#section_id').html('<option value="">בחר חלקה</option>').prop('disabled', true).trigger('change');
            return;
        }
        
        showLoading();
        $.ajax({
            url: '../api/get_locations.php',
            method: 'GET',
            data: { action: 'get_sections', block_id: blockId },
            success: function(response) {
                let options = '<option value="">בחר חלקה</option>';
                response.forEach(section => {
                    options += `<option value="${section.id}">${section.name}</option>`;
                });
                $('#section_id').html(options).prop('disabled', false).trigger('change');
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן לטעון את רשימת החלקות', 'error');
            },
            complete: hideLoading
        });
    }
    
    function loadRows(sectionId) {
        if (!sectionId) {
            $('#row_id').html('<option value="">בחר שורה</option>').prop('disabled', true).trigger('change');
            return;
        }
        
        showLoading();
        $.ajax({
            url: '../api/get_locations.php',
            method: 'GET',
            data: { action: 'get_rows', section_id: sectionId },
            success: function(response) {
                let options = '<option value="">בחר שורה</option>';
                response.forEach(row => {
                    options += `<option value="${row.id}">${row.row_number}</option>`;
                });
                $('#row_id').html(options).prop('disabled', false).trigger('change');
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן לטעון את רשימת השורות', 'error');
            },
            complete: hideLoading
        });
    }
    
    function loadGraves(rowId) {
        if (!rowId) {
            $('#grave_id').html('<option value="">בחר קבר</option>').prop('disabled', true).trigger('change');
            return;
        }
        
        showLoading();
        $.ajax({
            url: '../api/get_locations.php',
            method: 'GET',
            data: { action: 'get_graves', row_id: rowId },
            success: function(response) {
                let options = '<option value="">בחר קבר</option>';
                response.forEach(grave => {
                    options += `<option value="${grave.id}">${grave.grave_number}</option>`;
                });
                $('#grave_id').html(options).prop('disabled', false).trigger('change');
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן לטעון את רשימת הקברים', 'error');
            },
            complete: hideLoading
        });
    }
    
    function loadPlots(graveId) {
        if (!graveId) {
            $('#plot_id').html('<option value="">בחר חלקת קבר</option>').prop('disabled', true);
            return;
        }
        
        showLoading();
        $.ajax({
            url: '../api/get_locations.php',
            method: 'GET',
            data: { action: 'get_plots', grave_id: graveId },
            success: function(response) {
                let options = '<option value="">בחר חלקת קבר</option>';
                response.forEach(plot => {
                    const status = plot.status === 'available' ? 'פנוי' : 'תפוס';
                    const disabled = plot.status !== 'available' ? 'disabled' : '';
                    options += `<option value="${plot.id}" ${disabled}>${plot.plot_number} (${status})</option>`;
                });
                $('#plot_id').html(options).prop('disabled', false);
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן לטעון את רשימת החלקות', 'error');
            },
            complete: hideLoading
        });
    }
    
    // חישוב יתרה
    function calculateRemainingBalance() {
        const price = parseFloat($('#purchase_price').val()) || 0;
        const paid = parseFloat($('#payment_amount').val()) || 0;
        const remaining = Math.max(0, price - paid);
        
        $('#remaining_balance').val(remaining.toFixed(2));
        $('#summaryPurchasePrice').text(price.toFixed(2));
        $('#summaryPaidAmount').text(paid.toFixed(2));
        $('#summaryRemainingBalance').text(remaining.toFixed(2));
    }
    
    // יצירת קישור שיתוף
    function generateShareLink() {
        const permission = $('#sharePermission').val();
        
        showLoading();
        $.ajax({
            url: '../api/purchase_form_share.php',
            method: 'POST',
            data: {
                form_uuid: formConfig.formUuid,
                permission: permission,
                csrf_token: formConfig.csrfToken
            },
            success: function(response) {
                if (response.success) {
                    $('#shareLink').val(response.link);
                } else {
                    Swal.fire('שגיאה', response.message || 'לא ניתן ליצור קישור שיתוף', 'error');
                }
            },
            error: function() {
                Swal.fire('שגיאה', 'לא ניתן ליצור קישור שיתוף', 'error');
            },
            complete: hideLoading
        });
    }
    
    // אתחול תאריכים
    function initializeDatePickers() {
        // הגדר תאריך מקסימלי (היום)
        const today = new Date().toISOString().split('T')[0];
        $('input[type="date"]').attr('max', today);
        
        // אם תאריך תשלום ריק, הגדר להיום
        if (!$('#payment_date').val()) {
            $('#payment_date').val(today);
        }
    }
    
    // אתחול חישובי תשלומים
    function initializePaymentCalculations() {
        calculateRemainingBalance();
    }
    
    // אתחול בחירת חלקות
    function initializePlotSelection() {
        // אם יש ערכים קיימים, וודא שהרשימות הדינמיות נטענות
        if (formData.cemetery_id && formData.block_id) {
            // הפעל שרשרת טעינה
            $('#cemetery_id').trigger('change');
        }
    }
    </script>
    <?php
}