// js/views/blocks.js
if (!window.Views) window.Views = {};

Views.Blocks = {
    data: null,
    
    async load() {
        try {
            const response = await API.getBlocks();
            this.data = response;
            this.render(response);
        } catch (error) {
            console.error('Error loading blocks:', error);
        }
    },
    
    render(data) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול גושים</h2>
                <button class="btn btn-add" onclick="App.addItem('block')">
                    <i class="fas fa-plus"></i> הוסף גוש
                </button>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="hierarchy-card">
                        <h5>סינון לפי בית עלמין</h5>
                        <select class="form-select" id="filterCemetery" onchange="Views.Blocks.filter()">
                            <option value="">כל בתי העלמין</option>`;
        
        if (data.cemeteries) {
            data.cemeteries.forEach(cemetery => {
                html += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
        }
        
        html += `
                        </select>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="hierarchy-card">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>מס׳</th>
                                    <th>בית עלמין</th>
                                    <th>שם גוש</th>
                                    <th>קוד</th>
                                    <th>מספר חלקות</th>
                                    <th>סטטוס</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody id="blocksTable">`;
        
        if (data.blocks && data.blocks.length > 0) {
            data.blocks.forEach(block => {
                html += this.createBlockRow(block);
            });
        } else {
            html += `
                <tr>
                    <td colspan="7" class="text-center">אין נתונים להצגה</td>
                </tr>`;
        }
        
        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>`;
        
        $('#content-area').html(html);
    },
    
    createBlockRow(block) {
        return `
            <tr data-cemetery="${block.cemetery_id}">
                <td>${block.id}</td>
                <td>${block.cemetery_name}</td>
                <td>${block.name}</td>
                <td>${block.code || '-'}</td>
                <td>${block.plots_count || 0}</td>
                <td>${Utils.formatStatus(block.is_active)}</td>
                <td class="action-buttons">
                    ${Utils.createActionButtons('block', block.id)}
                </td>
            </tr>`;
    },
    
    filter() {
        const cemeteryId = $('#filterCemetery').val();
        if (cemeteryId) {
            $('#blocksTable tr').hide();
            $(`#blocksTable tr[data-cemetery="${cemeteryId}"]`).show();
        } else {
            $('#blocksTable tr').show();
        }
    }
};