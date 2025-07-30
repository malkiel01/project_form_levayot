// form/js/form-fields.js - טיפול בשדות תלויים

$(document).ready(function() {
    // טיפול בשדות תלויים של מקום קבורה - רק אם לא במצב צפייה
    if (!formConfig.isViewOnly) {
        $('#cemetery_id').on('change', function() {
            const cemeteryId = $(this).val();
            
            // נקה את כל השדות התלויים
            $('#block_id').html('<option value="">בחר קודם בית עלמין</option>');
            $('#section_id').html('<option value="">בחר קודם גוש</option>');
            $('#row_id').html('<option value="">בחר קודם חלקה</option>');
            $('#grave_id').html('<option value="">בחר קודם שורה</option>');
            $('#plot_id').html('<option value="">בחר...</option>');
            
            if (cemeteryId) {
                $.get('../ajax/get_blocks.php', {cemetery_id: cemeteryId}, function(data) {
                    $('#block_id').html(data);
                    // אם יש ערך קיים, בחר אותו
                    if (formData.block_id) {
                        $('#block_id').val(formData.block_id).trigger('change');
                    }
                });
                $.get('../ajax/get_plots.php', {cemetery_id: cemeteryId}, function(data) {
                    $('#plot_id').html(data);
                    if (formData.plot_id) {
                        $('#plot_id').val(formData.plot_id);
                    }
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
                $.get('../ajax/get_sections.php', {block_id: blockId}, function(data) {
                    $('#section_id').html(data);
                    if (formData.section_id) {
                        $('#section_id').val(formData.section_id).trigger('change');
                    }
                });
            }
        });
        
        $('#section_id').on('change', function() {
            const sectionId = $(this).val();
            
            // נקה שדות תלויים
            $('#row_id').html('<option value="">בחר קודם חלקה</option>');
            $('#grave_id').html('<option value="">בחר קודם שורה</option>');
            
            if (sectionId) {
                $.get('../ajax/get_rows.php', {section_id: sectionId}, function(data) {
                    $('#row_id').html(data);
                    if (formData.row_id) {
                        $('#row_id').val(formData.row_id).trigger('change');
                    }
                });
            }
        });
        
        $('#row_id').on('change', function() {
            const rowId = $(this).val();
            
            // נקה שדה תלוי
            $('#grave_id').html('<option value="">בחר קודם שורה</option>');
            
            if (rowId) {
                $.get('../ajax/get_graves.php', {row_id: rowId}, function(data) {
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