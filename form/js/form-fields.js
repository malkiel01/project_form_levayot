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
                $.get('../../ajax/get_plots.php', {block_id: blockId}, function(data) {  // ✅ תוקן הנתיב
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
                $.get('../../ajax/get_rows.php', {plot_id: plotId}, function(data) {  // ✅ תוקן הנתיב
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
                $.get('../../ajax/get_area_graves.php', {row_id: rowId}, function(data) {  // ✅ תוקן הנתיב
                    $('#areaGrave_id').html(data);
                    if (formData.areaGrave_id) {
                        $('#areaGrave_id').val(formData.areaGrave_id).trigger('change');
                    }
                });
            }
        });
        
        // אחוזת קבר → קברים
        $('#areaGrave_id').on('change', function() {
            const areaGraveId = $(this).val();
            
            // נקה שדה תלוי
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (areaGraveId) {
                $.get('../../ajax/get_graves.php', {area_grave_id: areaGraveId}, function(data) {  // ✅ תוקן הנתיב
                    $('#grave_id').html(data);
                    if (formData.grave_id) {
                        $('#grave_id').val(formData.grave_id);
                    }
                });
            }
        });
        
        // אם יש ערכים קיימים, הפעל את השדות הרלוונטיים
        if (formData.cemetery_id) {
            $('#cemetery_id').trigger('change');
        }
    }
});