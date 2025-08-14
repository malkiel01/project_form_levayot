// form/js/form-fields.js - טיפול בשדות תלויים

$(document).ready(function() { 
    // טיפול בשדות תלויים של מקום קבורה - רק אם לא במצב צפייה
    if (!formConfig.isViewOnly) {
        // בית עלמין → גושים
        $('#cemetery_id').on('change', function() {
            const cemeteryId = $(this).val();
            
            // נקה את כל השדות התלויים
            $('#block_id').html('<option value="">בחר קודם בית עלמין</option>');
            $('#plot_id').html('<option value="">בחר קודם גוש</option>');
            $('#row_id').html('<option value="">בחר קודם חלקה</option>');
            $('#areaGrave_id').html('<option value="">בחר קודם שורה</option>');
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (cemeteryId) {
                $.get('../ajax/get_blocks.php', {cemetery_id: cemeteryId}, function(data) {  // ✅ תוקן הנתיב
                    $('#block_id').html(data);
                    // אם יש ערך קיים, בחר אותו
                    if (formData.block_id) {
                        $('#block_id').val(formData.block_id).trigger('change');
                    }
                });
            }
        });
        
        // גוש → חלקות
        $('#block_id').on('change', function() {
            const blockId = $(this).val();
            
            // נקה שדות תלויים
            $('#plot_id').html('<option value="">בחר קודם גוש</option>');
            $('#row_id').html('<option value="">בחר קודם חלקה</option>');
            $('#areaGrave_id').html('<option value="">בחר קודם שורה</option>');
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (blockId) {
                $.get('../ajax/get_plots.php', {block_id: blockId}, function(data) {  // ✅ תוקן הנתיב
                    $('#plot_id').html(data);
                    if (formData.plot_id) {
                        $('#plot_id').val(formData.plot_id).trigger('change');
                    }
                });
            }
        });
        
        // חלקה → שורות
        $('#plot_id').on('change', function() {
            const plotId = $(this).val();
            
            // נקה שדות תלויים
            $('#row_id').html('<option value="">בחר קודם חלקה</option>');
            $('#areaGrave_id').html('<option value="">בחר קודם שורה</option>');
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (plotId) {
                $.get('../ajax/get_rows.php', {plot_id: plotId}, function(data) {  // ✅ תוקן הנתיב
                    $('#row_id').html(data);
                    if (formData.row_id) {
                        $('#row_id').val(formData.row_id).trigger('change');
                    }
                });
            }
        });
        
        // שורה → אחוזות קבר
        $('#row_id').on('change', function() {
            const rowId = $(this).val();
            
            // נקה שדות תלויים
            $('#areaGrave_id').html('<option value="">בחר קודם שורה</option>');
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (rowId) {
                $.get('../ajax/get_area_graves.php', {row_id: rowId}, function(data) {  // ✅ תוקן הנתיב
                    $('#areaGrave_id').html(data);
                    if (formData.areaGrave_id) {
                        $('#areaGrave_id').val(formData.areaGrave_id).trigger('change');
                    }
                });
            }
        });
        
        // אחוזת קבר → קברים
        $('#areaGrave_id').off('change').on('change', function() {
            console.log('=== AREAGRAVE CHANGE EVENT ===');
            const areaGraveId = $(this).val();
            console.log('areaGraveId:', areaGraveId);
            
            // שמור את הערך הנוכחי
            const currentGraveId = $('#grave_id').data('current-value') || $('#grave_id').val();
            console.log('currentGraveId before clear:', currentGraveId);
            
            // נקה שדה קברים
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (areaGraveId) {
                const requestData = {
                    areaGrave_id: areaGraveId
                };
                
                if (!formConfig.isNewForm && formConfig.formUuid) {
                    requestData.current_form_uuid = formConfig.formUuid;
                    console.log('Sending form UUID:', formConfig.formUuid);
                }
                
                console.log('Request data:', requestData);
                
                $.get('../ajax/get_graves.php', requestData)
                    .done(function(html) {
                        console.log('Response received, length:', html.length);
                        console.log('Response HTML:', html);
                        
                        $('#grave_id').html(html);
                        $('#grave_id').data('current-value', currentGraveId);
                        
                        if (currentGraveId) {
                            $('#grave_id').val(currentGraveId);
                            console.log('Set grave value to:', currentGraveId);
                            console.log('Actual value after set:', $('#grave_id').val());
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('AJAX Failed!');
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('Response:', xhr.responseText);
                    });
            }
        });

        // טריגר ידני לבדיקה
        console.log('Triggering areaGrave change manually...');
        $('#areaGrave_id').trigger('change');

        // בטעינה הראשונית - אם יש אחוזת קבר נבחרת, טען את הקברים
        setTimeout(function() {
            if (!formConfig.isNewForm) {
                const areaGraveId = $('#areaGrave_id').val();
                const graveId = $('#grave_id').data('current-value');
                
                if (areaGraveId && graveId) {
                    console.log('Initial load - have areaGrave and grave, triggering reload');
                    $('#areaGrave_id').trigger('change');
                }
            }
        }, 500); // דיליי קטן כדי לוודא שהכל נטען


        // בטעינה הראשונית - אם יש אחוזת קבר נבחרת, טען את הקברים
        $(window).on('load', function() {
            if (!formConfig.isNewForm) {
                const areaGraveId = $('#areaGrave_id').val();
                if (areaGraveId) {
                    console.log('Initial load - triggering areaGrave change');
                    $('#areaGrave_id').trigger('change');
                }
            }
        });

        
        // אם יש ערכים קיימים, הפעל את השדות הרלוונטיים
        if (formData.cemetery_id) {
            $('#cemetery_id').trigger('change');
        }
    }
});