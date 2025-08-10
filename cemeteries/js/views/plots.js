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
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>מס׳</th>
                            <th>בית עלמין</th>
                            <th>גוש</th>
                            <th>שם החלקה</th>
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
                    <tr class="clickable-row" data-plot-id="${plot.id}" style="cursor: pointer;">
                        <td>${plot.id}</td>
                        <td>${plot.cemetery_name}</td>
                        <td>${plot.block_name}</td>
                        <td>${plot.name}</td>
                        <td>${plot.code || '-'}</td>
                        <td>${plot.rows_count || 0}</td>
                        <td>${Utils.formatStatus(plot.is_active)}</td>
                        <td class="action-buttons" onclick="event.stopPropagation();">
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
            </div>`;
        
        $('#content-area').html(html);
        
        // הוסף אירוע לחיצה על שורות
        $('.clickable-row').on('click', function() {
            const plotId = $(this).data('plot-id');
            Views.Plots.showDetails(plotId);
        });
    },
    
    async showDetails(plotId) {
        try {
            const response = await API.getPlotDetails(plotId);
            
            // בדוק אם התשובה תקינה
            if (!response || !response.success) {
                Utils.showError(response?.message || 'שגיאה בטעינת פרטי חלקה');
                return;
            }
            
            // בדוק שיש נתונים
            if (!response.plot) {
                Utils.showError('לא נמצאו פרטי חלקה');
                return;
            }
            
            const stats = response.stats || {};
            
            let modalContent = `
                <div class="modal fade" id="plotDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">פרטי חלקה: ${response.plot.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="plot-details">
                                    <!-- מידע כללי -->
                                    <div class="info-section mb-4">
                                        <h6 class="text-primary">מידע כללי</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p><strong>בית עלמין:</strong> ${response.plot.cemetery_name}</p>
                                                <p><strong>גוש:</strong> ${response.plot.block_name}</p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>קוד:</strong> ${response.plot.code || 'לא הוגדר'}</p>
                                                <p><strong>סטטוס:</strong> 
                                                    <span class="status-badge ${response.plot.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                                        ${response.plot.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>שורות:</strong> ${stats.total_rows || 0}</p>
                                                <p><strong>אחוזות קבר:</strong> ${stats.total_area_graves || 0}</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- סטטיסטיקת קברים -->
                                    <div class="stats-section mb-4">
                                        <h6 class="text-primary">סטטיסטיקת קברים</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="stat-card bg-primary text-white p-3 rounded">
                                                    <h4>${stats.total_graves || 0}</h4>
                                                    <p class="mb-0">סה"כ קברים</p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="stat-card bg-success text-white p-3 rounded">
                                                    <h4>${stats.available_graves || 0}</h4>
                                                    <p class="mb-0">קברים פנויים</p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="stat-card bg-secondary text-white p-3 rounded">
                                                    <h4>${(stats.total_graves || 0) - (stats.available_graves || 0)}</h4>
                                                    <p class="mb-0">קברים תפוסים</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- שורות -->
                                    <div class="rows-section mb-4">
                                        <h6 class="text-primary">שורות בחלקה</h6>
                                        ${response.rows && response.rows.length > 0 ? `
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>שם</th>
                                                            <th>קוד</th>
                                                            <th>אחוזות קבר</th>
                                                            <th>קברים</th>
                                                            <th>פנויים</th>
                                                            <th>תפוסה</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${response.rows.map(row => {
                                                            const total = row.total_graves || 0;
                                                            const available = row.available_graves || 0;
                                                            const occupancy = total > 0 
                                                                ? Math.round((total - available) / total * 100)
                                                                : 0;
                                                            return `
                                                                <tr>
                                                                    <td>${row.name}</td>
                                                                    <td>${row.code || '-'}</td>
                                                                    <td>${row.area_graves_count || 0}</td>
                                                                    <td>${total}</td>
                                                                    <td>${available}</td>
                                                                    <td>
                                                                        ${total > 0 ? `
                                                                            <div class="progress" style="height: 20px;">
                                                                                <div class="progress-bar ${occupancy > 90 ? 'bg-danger' : occupancy > 70 ? 'bg-warning' : 'bg-success'}" 
                                                                                     style="width: ${occupancy}%">${occupancy}%</div>
                                                                            </div>
                                                                        ` : '-'}
                                                                    </td>
                                                                </tr>
                                                            `;
                                                        }).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ` : '<p class="text-muted">אין שורות</p>'}
                                    </div>
                                    
                                    <!-- אחוזות קבר -->
                                    <div class="area-graves-section">
                                        <h6 class="text-primary">אחוזות קבר בחלקה</h6>
                                        ${response.areaGraves && response.areaGraves.length > 0 ? `
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>שם</th>
                                                            <th>שורה</th>
                                                            <th>קוד</th>
                                                            <th>קברים</th>
                                                            <th>פנויים</th>
                                                            <th>תפוסה</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${response.areaGraves.map(ag => {
                                                            const total = ag.graves_count || 0;
                                                            const available = ag.available_count || 0;
                                                            const occupancy = total > 0 
                                                                ? Math.round((total - available) / total * 100)
                                                                : 0;
                                                            return `
                                                                <tr>
                                                                    <td>${ag.name}</td>
                                                                    <td>${ag.row_name}</td>
                                                                    <td>${ag.code || '-'}</td>
                                                                    <td>${total}</td>
                                                                    <td>${available}</td>
                                                                    <td>
                                                                        ${total > 0 ? `
                                                                            <div class="progress" style="height: 20px;">
                                                                                <div class="progress-bar ${occupancy > 90 ? 'bg-danger' : occupancy > 70 ? 'bg-warning' : 'bg-success'}" 
                                                                                     style="width: ${occupancy}%">${occupancy}%</div>
                                                                            </div>
                                                                        ` : '-'}
                                                                    </td>
                                                                </tr>
                                                            `;
                                                        }).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ` : '<p class="text-muted">אין אחוזות קבר</p>'}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="App.editItem('plot', ${plotId})">
                                    <i class="fas fa-edit"></i> ערוך
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#plotDetailsModal').remove();
            $('body').append(modalContent);
            
            const modal = new bootstrap.Modal(document.getElementById('plotDetailsModal'));
            modal.show();

            $('#plotDetailsModal').on('hidden.bs.modal', function () {
                $(this).remove();
                $('.modal-backdrop').remove(); // הוסף את השורה הזו!
                $('body').removeClass('modal-open'); // ואת השורה הזו!
                $('body').css('padding-right', ''); // ואת השורה הזו!
            });
            
        } catch (error) {
            console.error('Error loading plot details:', error);
            Utils.showError('שגיאה בטעינת פרטי חלקה');
        }
    }
};