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
        
        // // אחוזת קבר → קברים
        // $('#areaGrave_id').on('change', function() {
        //     const areaGraveId = $(this).val();
            
        //     // נקה שדה תלוי
        //     $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
        //     if (areaGraveId) {
        //         $.get('../ajax/get_graves.php', {area_grave_id: areaGraveId}, function(data) {  // ✅ תוקן הנתיב
        //             $('#grave_id').html(data);
        //             if (formData.grave_id) {
        //                 $('#grave_id').val(formData.grave_id);
        //             }
        //         });
        //     }
        // });


        // // אחוזת קבר → קברים - תיקון לטופס לוויה
        // $('#areaGrave_id').on('change', function() {
        //     const areaGraveId = $(this).val();
            
        //     // נקה שדה קברים
        //     $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
        //     if (areaGraveId) {
        //         // הכן את הנתונים לשליחה
        //         const requestData = {
        //             areaGrave_id: areaGraveId
        //         };
                
        //         // אם זה לא טופס חדש, שלח את ה-UUID של הטופס
        //         // formConfig.formUuid מוגדר ב-index_deceased.php
        //         if (!formConfig.isNewForm && formConfig.formUuid) {
        //             requestData.current_form_uuid = formConfig.formUuid;
        //             console.log('Sending current form UUID:', formConfig.formUuid);
        //         }
                
        //         $.get('../ajax/get_graves.php', requestData, function(html) {
        //             $('#grave_id').html(html);
                    
        //             // אם יש ערך קיים שמור ב-data attribute, בחר אותו
        //             const currentGraveId = $('#grave_id').data('current-value');
        //             if (currentGraveId) {
        //                 $('#grave_id').val(currentGraveId);
                        
        //                 // אם הערך לא נמצא ברשימה, זה אומר שהקבר תפוס והטופס הנוכחי לא משויך אליו
        //                 if ($('#grave_id').val() != currentGraveId) {
        //                     console.log('Current grave not in list, might be occupied by another form');
        //                 }
        //             }
        //         }).fail(function() {
        //             console.error('Failed to load graves');
        //             $('#grave_id').html('<option value="">שגיאה בטעינת קברים</option>');
        //         });
        //     }
        // });

        $('#areaGrave_id').on('change', function() {
            const areaGraveId = $(this).val();
            
            // שמור את הערך הנוכחי לפני שמנקים
            const currentGraveId = $('#grave_id').data('current-value') || $('#grave_id').val();
            
            // נקה שדה קברים
            $('#grave_id').html('<option value="">בחר קודם אחוזת קבר</option>');
            
            if (areaGraveId) {
                // הכן את הנתונים לשליחה
                const requestData = {
                    areaGrave_id: areaGraveId
                };
                
                // אם זה לא טופס חדש, שלח את ה-UUID של הטופס
                if (!formConfig.isNewForm && formConfig.formUuid) {
                    requestData.current_form_uuid = formConfig.formUuid;
                    console.log('Sending current form UUID:', formConfig.formUuid);
                }
                
                $.get('../ajax/get_graves.php', requestData, function(html) {
                    $('#grave_id').html(html);
                    
                    // שחזר את ה-data attribute
                    $('#grave_id').data('current-value', currentGraveId);
                    
                    // נסה לבחור את הקבר הנוכחי
                    if (currentGraveId) {
                        $('#grave_id').val(currentGraveId);
                        
                        // בדוק אם הבחירה הצליחה
                        if ($('#grave_id').val() == currentGraveId) {
                            console.log('Current grave selected successfully:', currentGraveId);
                        } else {
                            console.log('Current grave not in list - might be occupied by another form');
                        }
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Failed to load graves:', error);
                    $('#grave_id').html('<option value="">שגיאה בטעינת קברים</option>');
                });
            }
        });

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