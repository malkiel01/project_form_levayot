// js/views/plots.js
if (!window.Views) window.Views = {};

Views.Plots = {
    async load() {
        try {
            const plots = await API.getPlots();
            this.render(plots);
        } catch (error) {
            console.error('Error loading plots:', error);
        }
    },
    
    render(plots) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול חלקות</h2>
                <button class="btn btn-add" onclick="App.addItem('plot')">
                    <i class="fas fa-plus"></i> הוסף חלקה
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
                                <th>שם חלקה</th>
                                <th>קוד</th>
                                <th>מספר שורות</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        if (plots && plots.length > 0) {
            plots.forEach(plot => {
                html += `
                    <tr>
                        <td>${plot.id}</td>
                        <td>${plot.cemetery_name || '-'}</td>
                        <td>${plot.block_name || '-'}</td>
                        <td>${plot.name}</td>
                        <td>${plot.code || '-'}</td>
                        <td>${plot.rows_count || 0}</td>
                        <td>${Utils.formatStatus(plot.is_active)}</td>
                        <td class="action-buttons">
                            ${Utils.createActionButtons('plot', plot.id)}
                        </td>
                    </tr>`;
            });
        } else {
            html += `
                <tr>
                    <td colspan="8" class="text-center">אין נתונים להצגה</td>
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