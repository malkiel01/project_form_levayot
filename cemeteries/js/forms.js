// js/forms.js
const Forms = {
    async loadFields(type, id = null) {
        console.log('Loading fields for type:', type, 'id:', id);
        let fields = '';
        
        try {
            switch(type) {
                case 'cemetery':
                    fields = this.getCemeteryFields();
                    break;
                case 'block':
                    fields = await this.getBlockFields();
                    break;
                case 'plot':
                    fields = await this.getPlotFields();
                    break;
                case 'row':
                    fields = await this.getRowFields();
                    break;
                case 'areaGrave':
                    fields = await this.getAreaGraveFields();
                    break;
                case 'grave':
                    fields = await this.getGraveFields();
                    break;
                default:
                    console.error('Unknown type:', type);
                    fields = '<div class="alert alert-danger">סוג רשומה לא מזוהה</div>';
            }
            
            $('#formFields').html(fields);
            
            // Setup dynamic selects if needed
            if (type !== 'cemetery') {
                this.setupDynamicSelects();
            }
            
            // Load existing data if editing
            if (id) {
                await this.loadItemData(type, id);
            }
        } catch (error) {
            console.error('Error loading fields:', error);
            $('#formFields').html('<div class="alert alert-danger">שגיאה בטעינת הטופס</div>');
        }
    },
    
    getCemeteryFields() {
        return `
            <div class="mb-3">
                <label class="form-label">שם בית העלמין *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_active">
                    <option value="1">פעיל</option>
                    <option value="0">לא פעיל</option>
                </select>
            </div>`;
    },
    
    async getBlockFields() {
        const cemeteries = await API.getCemeteries();
        let options = '<option value="">בחר בית עלמין</option>';
        
        if (cemeteries) {
            cemeteries.forEach(cemetery => {
                options += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        return `
            <div class="mb-3">
                <label class="form-label">בית עלמין *</label>
                <select class="form-select" name="cemetery_id" required>
                    ${options}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">שם הגוש *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_active">
                    <option value="1">פעיל</option>
                    <option value="0">לא פעיל</option>
                </select>
            </div>`;
    },
    
    async getPlotFields() {
        const cemeteries = await API.getCemeteries();
        let cemeteryOptions = '<option value="">בחר בית עלמין</option>';
        
        if (cemeteries) {
            cemeteries.forEach(cemetery => {
                cemeteryOptions += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        return `
            <div class="mb-3">
                <label class="form-label">בית עלמין *</label>
                <select class="form-select" id="select_cemetery" required>
                    ${cemeteryOptions}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">גוש *</label>
                <select class="form-select" name="block_id" id="select_block" required>
                    <option value="">בחר קודם בית עלמין</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">שם החלקה *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_active">
                    <option value="1">פעיל</option>
                    <option value="0">לא פעיל</option>
                </select>
            </div>`;
    },
    
    async getRowFields() {
        const cemeteries = await API.getCemeteries();
        let cemeteryOptions = '<option value="">בחר בית עלמין</option>';
        
        if (cemeteries) {
            cemeteries.forEach(cemetery => {
                cemeteryOptions += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        return `
            <div class="mb-3">
                <label class="form-label">בית עלמין *</label>
                <select class="form-select" id="select_cemetery" required>
                    ${cemeteryOptions}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">גוש *</label>
                <select class="form-select" id="select_block" required>
                    <option value="">בחר קודם בית עלמין</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">חלקה *</label>
                <select class="form-select" name="plot_id" id="select_plot" required>
                    <option value="">בחר קודם גוש</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">שם השורה *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_active">
                    <option value="1">פעיל</option>
                    <option value="0">לא פעיל</option>
                </select>
            </div>`;
    },
    
    async getAreaGraveFields() {
        const cemeteries = await API.getCemeteries();
        let cemeteryOptions = '<option value="">בחר בית עלמין</option>';
        
        if (cemeteries) {
            cemeteries.forEach(cemetery => {
                cemeteryOptions += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        return `
            <div class="mb-3">
                <label class="form-label">בית עלמין *</label>
                <select class="form-select" id="select_cemetery" required>
                    ${cemeteryOptions}
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">גוש *</label>
                <select class="form-select" id="select_block" required>
                    <option value="">בחר קודם בית עלמין</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">חלקה *</label>
                <select class="form-select" id="select_plot" required>
                    <option value="">בחר קודם גוש</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">שורה *</label>
                <select class="form-select" name="row_id" id="select_row" required>
                    <option value="">בחר קודם חלקה</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">שם אחוזת הקבר *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_active">
                    <option value="1">פעיל</option>
                    <option value="0">לא פעיל</option>
                </select>
            </div>`;
    },
    
    // async getGraveFields() {
    //     const cemeteries = await API.getCemeteries();
    //     let cemeteryOptions = '<option value="">בחר בית עלמין</option>';
        
    //     if (cemeteries) {
    //         cemeteries.forEach(cemetery => {
    //             cemeteryOptions += `<option value="${cemetery.id}">${cemetery.name}</option>`;
    //         });
    //     }
        
    //     return `
    //         <div class="mb-3">
    //             <label class="form-label">בית עלמין *</label>
    //             <select class="form-select" id="select_cemetery" required>
    //                 ${cemeteryOptions}
    //             </select>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">גוש *</label>
    //             <select class="form-select" id="select_block" required>
    //                 <option value="">בחר קודם בית עלמין</option>
    //             </select>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">חלקה *</label>
    //             <select class="form-select" id="select_plot" required>
    //                 <option value="">בחר קודם גוש</option>
    //             </select>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">שורה *</label>
    //             <select class="form-select" id="select_row" required>
    //                 <option value="">בחר קודם חלקה</option>
    //             </select>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">אחוזת קבר *</label>
    //             <select class="form-select" name="areaGrave_id" id="select_areaGrave" required>
    //                 <option value="">בחר קודם שורה</option>
    //             </select>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">שם הקבר *</label>
    //             <input type="text" class="form-control" name="name" required>
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">מספר קבר</label>
    //             <input type="text" class="form-control" name="grave_number">
    //         </div>
    //         <div class="mb-3">
    //             <label class="form-label">קוד</label>
    //             <input type="text" class="form-control" name="code">
    //         </div>
    //         <!-- הסרנו את שדה הסטטוס - קברים חדשים תמיד פנויים -->
    //         <!-- אין צורך להציג את זה למשתמש -->
    //     `;
    // },

    setupDynamicSelects() {
        $('#select_cemetery').on('change', async function() {
            const cemeteryId = $(this).val();
            $('#select_block').html('<option value="">טוען...</option>');
            
            if (cemeteryId) {
                const blocks = await API.getBlocksByCemetery(cemeteryId);
                let options = '<option value="">בחר גוש</option>';
                blocks.forEach(block => {
                    options += `<option value="${block.id}">${block.name}</option>`;
                });
                $('#select_block').html(options);
            } else {
                $('#select_block').html('<option value="">בחר קודם בית עלמין</option>');
                $('#select_plot').html('<option value="">בחר קודם גוש</option>');
                $('#select_row').html('<option value="">בחר קודם חלקה</option>');
                $('#select_areaGrave').html('<option value="">בחר קודם שורה</option>');
            }
        });
        
        $('#select_block').on('change', async function() {
            const blockId = $(this).val();
            $('#select_plot').html('<option value="">טוען...</option>');
            
            if (blockId) {
                const plots = await API.getPlotsByBlock(blockId);
                let options = '<option value="">בחר חלקה</option>';
                plots.forEach(plot => {
                    options += `<option value="${plot.id}">${plot.name}</option>`;
                });
                $('#select_plot').html(options);
            } else {
                $('#select_plot').html('<option value="">בחר קודם גוש</option>');
                $('#select_row').html('<option value="">בחר קודם חלקה</option>');
                $('#select_areaGrave').html('<option value="">בחר קודם שורה</option>');
            }
        });
        
        $('#select_plot').on('change', async function() {
            const plotId = $(this).val();
            $('#select_row').html('<option value="">טוען...</option>');
            
            if (plotId) {
                const rows = await API.getRowsByPlot(plotId);
                let options = '<option value="">בחר שורה</option>';
                rows.forEach(row => {
                    options += `<option value="${row.id}">${row.name}</option>`;
                });
                $('#select_row').html(options);
            } else {
                $('#select_row').html('<option value="">בחר קודם חלקה</option>');
                $('#select_areaGrave').html('<option value="">בחר קודם שורה</option>');
            }
        });
        
        $('#select_row').on('change', async function() {
            const rowId = $(this).val();
            $('#select_areaGrave').html('<option value="">טוען...</option>');
            
            if (rowId) {
                const areaGraves = await API.getAreaGravesByRow(rowId);
                let options = '<option value="">בחר אחוזת קבר</option>';
                areaGraves.forEach(areaGrave => {
                    options += `<option value="${areaGrave.id}">${areaGrave.name}</option>`;
                });
                $('#select_areaGrave').html(options);
            } else {
                $('#select_areaGrave').html('<option value="">בחר קודם שורה</option>');
            }
        });
    },
    
    async loadItemData(type, id) {
        const data = await API.getItem(type, id);
        
        if (!data) return;
        
        // Handle hierarchical data loading
        switch(type) {
            case 'block':
                // Simple case - just set the values
                $('[name="cemetery_id"]').val(data.cemetery_id);
                $('[name="name"]').val(data.name);
                $('[name="code"]').val(data.code);
                $('[name="is_active"]').val(data.is_active);
                break;
                
            case 'plot':
                if (data.block_id) {
                    const block = await API.getItem('block', data.block_id);
                    if (block && block.cemetery_id) {
                        $('#select_cemetery').val(block.cemetery_id);
                        await $('#select_cemetery').trigger('change');
                        
                        setTimeout(async () => {
                            $('#select_block').val(data.block_id);
                            $('[name="name"]').val(data.name);
                            $('[name="code"]').val(data.code);
                            $('[name="is_active"]').val(data.is_active);
                        }, 300);
                    }
                }
                break;
                
            case 'row':
                if (data.plot_id) {
                    const plot = await API.getItem('plot', data.plot_id);
                    if (plot && plot.block_id) {
                        const block = await API.getItem('block', plot.block_id);
                        if (block && block.cemetery_id) {
                            $('#select_cemetery').val(block.cemetery_id);
                            await $('#select_cemetery').trigger('change');
                            
                            setTimeout(async () => {
                                $('#select_block').val(plot.block_id);
                                await $('#select_block').trigger('change');
                                
                                setTimeout(() => {
                                    $('#select_plot').val(data.plot_id);
                                    $('[name="name"]').val(data.name);
                                    $('[name="code"]').val(data.code);
                                    $('[name="is_active"]').val(data.is_active);
                                }, 300);
                            }, 300);
                        }
                    }
                }
                break;
                
            case 'areaGrave':
                if (data.row_id) {
                    const row = await API.getItem('row', data.row_id);
                    if (row && row.plot_id) {
                        const plot = await API.getItem('plot', row.plot_id);
                        if (plot && plot.block_id) {
                            const block = await API.getItem('block', plot.block_id);
                            if (block && block.cemetery_id) {
                                $('#select_cemetery').val(block.cemetery_id);
                                await $('#select_cemetery').trigger('change');
                                
                                setTimeout(async () => {
                                    $('#select_block').val(plot.block_id);
                                    await $('#select_block').trigger('change');
                                    
                                    setTimeout(async () => {
                                        $('#select_plot').val(row.plot_id);
                                        await $('#select_plot').trigger('change');
                                        
                                        setTimeout(() => {
                                            $('#select_row').val(data.row_id);
                                            $('[name="name"]').val(data.name);
                                            $('[name="code"]').val(data.code);
                                            $('[name="is_active"]').val(data.is_active);
                                        }, 300);
                                    }, 300);
                                }, 300);
                            }
                        }
                    }
                }
                break;
                
            case 'grave2':
                if (data.areaGrave_id) {
                    const areaGrave = await API.getItem('areaGrave', data.areaGrave_id);
                    if (areaGrave && areaGrave.row_id) {
                        const row = await API.getItem('row', areaGrave.row_id);
                        if (row && row.plot_id) {
                            const plot = await API.getItem('plot', row.plot_id);
                            if (plot && plot.block_id) {
                                const block = await API.getItem('block', plot.block_id);
                                if (block && block.cemetery_id) {
                                    $('#select_cemetery').val(block.cemetery_id);
                                    await $('#select_cemetery').trigger('change');
                                    
                                    setTimeout(async () => {
                                        $('#select_block').val(plot.block_id);
                                        await $('#select_block').trigger('change');
                                        
                                        setTimeout(async () => {
                                            $('#select_plot').val(row.plot_id);
                                            await $('#select_plot').trigger('change');
                                            
                                            setTimeout(async () => {
                                                $('#select_row').val(areaGrave.row_id);
                                                await $('#select_row').trigger('change');
                                                
                                                setTimeout(() => {
                                                    $('#select_areaGrave').val(data.areaGrave_id);
                                                    $('[name="name"]').val(data.name);
                                                    $('[name="grave_number"]').val(data.grave_number);
                                                    $('[name="code"]').val(data.code);
                                                    $('[name="is_available"]').val(data.is_available);
                                                }, 300);
                                            }, 300);
                                        }, 300);
                                    }, 300);
                                }
                            }
                        }
                    }
                }
                break;

            case 'grave':
                // בעריכת קבר - טען את כל ההיררכיה בצורה מלאה
                if (data.areaGrave_id) {
                    console.log('Loading grave hierarchy...');
                    
                    try {
                        // שלב 1: טען את אחוזת הקבר
                        const areaGrave = await API.getItem('areaGrave', data.areaGrave_id);
                        if (!areaGrave || !areaGrave.row_id) {
                            throw new Error('Failed to load areaGrave');
                        }
                        console.log('AreaGrave loaded:', areaGrave);
                        
                        // שלב 2: טען את השורה
                        const row = await API.getItem('row', areaGrave.row_id);
                        if (!row || !row.plot_id) {
                            throw new Error('Failed to load row');
                        }
                        console.log('Row loaded:', row);
                        
                        // שלב 3: טען את החלקה
                        const plot = await API.getItem('plot', row.plot_id);
                        if (!plot || !plot.block_id) {
                            throw new Error('Failed to load plot');
                        }
                        console.log('Plot loaded:', plot);
                        
                        // שלב 4: טען את הגוש
                        const block = await API.getItem('block', plot.block_id);
                        if (!block || !block.cemetery_id) {
                            throw new Error('Failed to load block');
                        }
                        console.log('Block loaded:', block);
                        
                        // שלב 5: הגדר את בית העלמין
                        $('#select_cemetery').val(block.cemetery_id);
                        await $('#select_cemetery').trigger('change');
                        
                        // המתן לטעינת הגושים
                        await this.waitForOptions('#select_block', 3000);
                        $('#select_block').val(block.id);
                        await $('#select_block').trigger('change');
                        
                        // המתן לטעינת החלקות
                        await this.waitForOptions('#select_plot', 3000);
                        $('#select_plot').val(plot.id);
                        await $('#select_plot').trigger('change');
                        
                        // המתן לטעינת השורות
                        await this.waitForOptions('#select_row', 3000);
                        $('#select_row').val(row.id);
                        await $('#select_row').trigger('change');
                        
                        // המתן לטעינת אחוזות הקבר
                        await this.waitForOptions('#select_areaGrave', 3000);
                        $('#select_areaGrave').val(data.areaGrave_id);
                        
                        // מלא את שאר השדות
                        $('[name="name"]').val(data.name);
                        $('[name="grave_number"]').val(data.grave_number);
                        $('[name="code"]').val(data.code);
                        
                        // נעל את כל שדות ההיררכיה בעריכה
                        $('#select_cemetery').prop('disabled', true);
                        $('#select_block').prop('disabled', true);
                        $('#select_plot').prop('disabled', true);
                        $('#select_row').prop('disabled', true);
                        $('#select_areaGrave').prop('disabled', true);
                        
                        // הוסף הודעה למשתמש
                        $('#formFields').prepend(`
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>שים לב:</strong> לא ניתן לשנות את מיקום הקבר (בית עלמין, גוש, חלקה, שורה ואחוזת קבר) לאחר יצירתו.
                                למחיקה והעברה, יש למחוק את הקבר וליצור חדש במיקום הרצוי.
                            </div>
                        `);
                        
                    } catch (error) {
                        console.error('Error loading grave hierarchy:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'שגיאה בטעינת נתונים',
                            text: 'לא ניתן לטעון את כל הנתונים הנדרשים. אנא נסה שנית.',
                            confirmButtonText: 'סגור'
                        });
                    }
                }
                break;
                
            default:
                // For cemetery or any other simple type
                for (let key in data) {
                    const $field = $(`[name="${key}"]`);
                    if ($field.length) {
                        $field.val(data[key]);
                    }
                }
        }
    },

    // פונקציית עזר להמתנה לטעינת אופציות
    async waitForOptions(selector, timeout = 5000) {
        const startTime = Date.now();
        
        while (Date.now() - startTime < timeout) {
            const $select = $(selector);
            const options = $select.find('option').length;
            
            // בדוק אם יש יותר מאופציה אחת (שהיא ה"בחר...")
            if (options > 1) {
                console.log(`Options loaded for ${selector}: ${options} options`);
                return true;
            }
            
            // המתן 100ms לפני הבדיקה הבאה
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        throw new Error(`Timeout waiting for options in ${selector}`);
    },

    // עדכון הפונקציה getGraveFields כדי לזהות מצב עריכה:
    async getGraveFields(isEdit = false) {
        const cemeteries = await API.getCemeteries();
        let cemeteryOptions = '<option value="">בחר בית עלמין</option>';
        
        if (cemeteries) {
            cemeteries.forEach(cemetery => {
                cemeteryOptions += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        return `
            ${isEdit ? '' : '<!-- מצב יצירה -->'}
            <div class="mb-3">
                <label class="form-label">בית עלמין *</label>
                <select class="form-select" id="select_cemetery" required ${isEdit ? 'disabled' : ''}>
                    ${cemeteryOptions}
                </select>
                ${isEdit ? '<small class="text-muted">לא ניתן לשנות בעריכה</small>' : ''}
            </div>
            <div class="mb-3">
                <label class="form-label">גוש *</label>
                <select class="form-select" id="select_block" required ${isEdit ? 'disabled' : ''}>
                    <option value="">בחר קודם בית עלמין</option>
                </select>
                ${isEdit ? '<small class="text-muted">לא ניתן לשנות בעריכה</small>' : ''}
            </div>
            <div class="mb-3">
                <label class="form-label">חלקה *</label>
                <select class="form-select" id="select_plot" required ${isEdit ? 'disabled' : ''}>
                    <option value="">בחר קודם גוש</option>
                </select>
                ${isEdit ? '<small class="text-muted">לא ניתן לשנות בעריכה</small>' : ''}
            </div>
            <div class="mb-3">
                <label class="form-label">שורה *</label>
                <select class="form-select" id="select_row" required ${isEdit ? 'disabled' : ''}>
                    <option value="">בחר קודם חלקה</option>
                </select>
                ${isEdit ? '<small class="text-muted">לא ניתן לשנות בעריכה</small>' : ''}
            </div>
            <div class="mb-3">
                <label class="form-label">אחוזת קבר *</label>
                <select class="form-select" name="areaGrave_id" id="select_areaGrave" required ${isEdit ? 'disabled' : ''}>
                    <option value="">בחר קודם שורה</option>
                </select>
                ${isEdit ? '<small class="text-muted">לא ניתן לשנות בעריכה</small>' : ''}
            </div>
            <div class="mb-3">
                <label class="form-label">שם הקבר *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">מספר קבר</label>
                <input type="text" class="form-control" name="grave_number">
            </div>
            <div class="mb-3">
                <label class="form-label">קוד</label>
                <input type="text" class="form-control" name="code">
            </div>
        `;
    },
    
    async save() {
        // אסוף את הנתונים מהטופס
        const formElement = document.getElementById('editForm');
        const formData = new FormData(formElement);
        const data = Object.fromEntries(formData);
        
        // בצע וולידציה
        const type = $('#itemType').val();
        const validation = Validation.validateForm(type, data);
        
        if (!validation.valid) {
            Validation.showFormErrors(validation.errors);
            return;
        }
        
        // בדוק ולידציה HTML5
        if (!formElement.checkValidity()) {
            formElement.reportValidity();
            return;
        }
        
        // Show loading
        $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> שומר...');
        
        // בנה את הנתונים לשליחה
        const isUpdate = $('#itemId').val() ? true : false;
        const params = new URLSearchParams();
        
        // הוסף את הנתונים המסוננים
        for (let key in validation.data) {
            params.append(key, validation.data[key]);
        }
        
        // הוסף מטא-נתונים
        params.append('type', type);
        if (isUpdate) {
            params.append('id', $('#itemId').val());
        }
        
        console.log('Saving validated data:', validation.data);
        
        try {
            const response = await API.saveItem(params.toString(), isUpdate);
            
            console.log('Save response:', response);
            
            if (response.success) {
                $('#editModal').modal('hide');
                Utils.showSuccess(response.message || 'הפעולה בוצעה בהצלחה');
                App.loadPage(App.currentPage);
            } else {
                Utils.showError(response.message || 'שגיאה בשמירה');
            }
        } catch (error) {
            console.error('Save error:', error);
            Utils.showError('אירעה שגיאה בשמירת הנתונים');
        } finally {
            $('#saveBtn').prop('disabled', false).html('שמירה');
        }
    }
};