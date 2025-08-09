// js/forms.js
const Forms = {
    async loadFields(type, id = null) {
        let fields = '';
        
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
    
    async getGraveFields() {
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
                <select class="form-select" id="select_row" required>
                    <option value="">בחר קודם חלקה</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">אחוזת קבר *</label>
                <select class="form-select" name="areaGrave_id" id="select_areaGrave" required>
                    <option value="">בחר קודם שורה</option>
                </select>
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
            <div class="mb-3">
                <label class="form-label">סטטוס</label>
                <select class="form-select" name="is_available">
                    <option value="1">פנוי</option>
                    <option value="0">תפוס</option>
                </select>
            </div>`;
    },
    
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
                
            case 'grave':
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
    
    async save() {
        // Validate form
        const form = document.getElementById('editForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Show loading
        $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> שומר...');
        
        const formData = $('#editForm').serialize();
        const isUpdate = $('#itemId').val() ? true : false;
        
        try {
            const response = await API.saveItem(formData, isUpdate);
            
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