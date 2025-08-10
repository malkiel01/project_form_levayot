// js/views/cemeteries.js
if (!window.Views) window.Views = {};

Views.Cemeteries = {
    async load() {
        try {
            const cemeteries = await API.getCemeteries();
            this.render(cemeteries);
        } catch (error) {
            console.error('Error loading cemeteries:', error);
        }
    },
    
    render(cemeteries) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול בתי עלמין</h2>
                <button class="btn btn-add" onclick="App.addItem('cemetery')">
                    <i class="fas fa-plus"></i> הוסף בית עלמין
                </button>
            </div>
            
            <div class="hierarchy-card">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>מס׳</th>
                            <th>שם</th>
                            <th>קוד</th>
                            <th>מספר גושים</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>`;
        
        if (cemeteries && cemeteries.length > 0) {
            cemeteries.forEach(cemetery => {
                html += `
                    <tr>
                        <td>${cemetery.id}</td>
                        <td>${cemetery.name}</td>
                        <td>${cemetery.code || '-'}</td>
                        <td>${cemetery.blocks_count || 0}</td>
                        <td>${Utils.formatStatus(cemetery.is_active)}</td>
                        <td class="action-buttons">
                            ${Utils.createActionButtons('cemetery', cemetery.id)}
                        </td>
                    </tr>`;
            });
        } else {
            html += `
                <tr>
                    <td colspan="6" class="text-center">אין נתונים להצגה</td>
                </tr>`;
        }
        
        html += `
                    </tbody>
                </table>
            </div>`;
        
        $('#content-area').html(html);
    }
};