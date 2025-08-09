// js/views/areaGraves.js
if (!window.Views) window.Views = {};

Views.AreaGraves = {
    async load() {
        try {
            const areaGraves = await API.getAreaGraves();
            this.render(areaGraves);
        } catch (error) {
            console.error('Error loading area graves:', error);
        }
    },
    
    render(areaGraves) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול אחוזות קבר</h2>
                <button class="btn btn-add" onclick="App.addItem('areaGrave')">
                    <i class="fas fa-plus"></i> הוסף אחוזת קבר
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
                                <th>שורה</th>
                                <th>שם אחוזה</th>
                                <th>קוד</th>
                                <th>סה"כ קברים</th>
                                <th>פנויים</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        if (areaGraves && areaGraves.length > 0) {
            areaGraves.forEach(ag => {
                const occupancyRate = ag.graves_count > 0 ? 
                    Math.round(((ag.graves_count - ag.available_count) / ag.graves_count) * 100) : 0;
                
                html += `
                    <tr>
                        <td>${ag.id}</td>
                        <td>${ag.cemetery_name || '-'}</td>
                        <td>${ag.block_name || '-'}</td>
                        <td>${ag.plot_name || '-'}</td>
                        <td>${ag.row_name || '-'}</td>
                        <td>${ag.name}</td>
                        <td>${ag.code || '-'}</td>
                        <td>${ag.graves_count || 0}</td>
                        <td>
                            <span class="badge bg-success">${ag.available_count || 0}</span>
                            ${ag.graves_count > 0 ? `<small class="text-muted">(${occupancyRate}% תפוס)</small>` : ''}
                        </td>
                        <td>${Utils.formatStatus(ag.is_active)}</td>
                        <td class="action-buttons">
                            ${Utils.createActionButtons('areaGrave', ag.id)}
                        </td>
                    </tr>`;
            });
        } else {
            html += `
                <tr>
                    <td colspan="11" class="text-center">אין נתונים להצגה</td>
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