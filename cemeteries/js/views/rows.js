// js/views/rows.js
if (!window.Views) window.Views = {};

Views.Rows = {
    async load() {
        try {
            const rows = await API.getRows();
            this.render(rows);
        } catch (error) {
            console.error('Error loading rows:', error);
        }
    },
    
    render(rows) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול שורות</h2>
                <button class="btn btn-add" onclick="App.addItem('row')">
                    <i class="fas fa-plus"></i> הוסף שורה
                </button>
            </div>
            
            <div class="hierarchy-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>מס׳</th>
                                <th>בית עלמין</th>
                                <th>גוש</th>
                                <th>חלקה</th>
                                <th>שם שורה</th>
                                <th>קוד</th>
                                <th>אחוזות קבר</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        if (rows && rows.length > 0) {
            rows.forEach(row => {
                html += `
                    <tr>
                        <td>${row.id}</td>
                        <td>${row.cemetery_name || '-'}</td>
                        <td>${row.block_name || '-'}</td>
                        <td>${row.plot_name || '-'}</td>
                        <td>${row.name}</td>
                        <td>${row.code || '-'}</td>
                        <td>${row.area_graves_count || 0}</td>
                        <td>${Utils.formatStatus(row.is_active)}</td>
                        <td class="action-buttons">
                            ${Utils.createActionButtons('row', row.id)}
                        </td>
                    </tr>`;
            });
        } else {
            html += `
                <tr>
                    <td colspan="9" class="text-center">אין נתונים להצגה</td>
                </tr>`;
        }
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>`;
        
        $('#content-area').html(html);
    }
};