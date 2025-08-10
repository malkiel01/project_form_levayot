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
                    <tr class="clickable-row" data-cemetery-id="${cemetery.id}" style="cursor: pointer;">
                        <td>${cemetery.id}</td>
                        <td>${cemetery.name}</td>
                        <td>${cemetery.code || '-'}</td>
                        <td>${cemetery.blocks_count || 0}</td>
                        <td>${Utils.formatStatus(cemetery.is_active)}</td>
                        <td class="action-buttons" onclick="event.stopPropagation();">
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
        
        // הוסף אירוע לחיצה על שורות
        $('.clickable-row').on('click', function() {
            const cemeteryId = $(this).data('cemetery-id');
            Views.Cemeteries.showDetails(cemeteryId);
        });
    },
    
    async showDetails(cemeteryId) {
        try {
            const response = await API.getCemeteryDetails(cemeteryId);
            
            // בדוק אם התשובה תקינה
            if (!response || !response.success) {
                Utils.showError(response?.message || 'שגיאה בטעינת פרטי בית העלמין');
                return;
            }
            
            // בדוק שיש נתונים
            if (!response.cemetery) {
                Utils.showError('לא נמצאו פרטי בית העלמין');
                return;
            }
            
            const stats = response.stats || {};
            
            let modalContent = `
                <div class="modal fade" id="cemeteryDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">פרטי בית עלמין: ${response.cemetery.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="cemetery-details">
                                    <!-- מידע כללי -->
                                    <div class="info-section mb-4">
                                        <h6 class="text-primary">מידע כללי</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p><strong>קוד:</strong> ${response.cemetery.code || 'לא הוגדר'}</p>
                                                <p><strong>סטטוס:</strong> 
                                                    <span class="status-badge ${response.cemetery.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                                        ${response.cemetery.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>גושים:</strong> ${response.blocks?.length || 0}</p>
                                                <p><strong>חלקות:</strong> ${response.plots?.length || 0}</p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>אחוזות קבר:</strong> ${stats.total_area_graves || 0}</p>
                                                <p><strong>קברים סה"כ:</strong> ${stats.total_graves || 0}</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- סטטיסטיקת קברים -->
                                    <div class="stats-section mb-4">
                                        <h6 class="text-primary">סטטיסטיקת קברים</h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="stat-card bg-success text-white p-3 rounded">
                                                    <h4>${stats.available_graves || 0}</h4>
                                                    <p class="mb-0">קברים פנויים</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-card bg-secondary text-white p-3 rounded">
                                                    <h4>${(stats.total_graves || 0) - (stats.available_graves || 0)}</h4>
                                                    <p class="mb-0">קברים תפוסים</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-card bg-info text-white p-3 rounded">
                                                    <h4>-</h4>
                                                    <p class="mb-0">נתון עתידי</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-card bg-warning text-white p-3 rounded">
                                                    <h4>-</h4>
                                                    <p class="mb-0">נתון עתידי</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- גושים -->
                                    <div class="blocks-section mb-4">
                                        <h6 class="text-primary">גושים</h6>
                                        ${response.blocks && response.blocks.length > 0 ? `
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>שם</th>
                                                            <th>חלקות</th>
                                                            <th>קברים</th>
                                                            <th>פנויים</th>
                                                            <th>תפוסה</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${response.blocks.map(block => {
                                                            const occupancy = block.total_graves > 0 
                                                                ? Math.round((block.total_graves - (block.available_graves || 0)) / block.total_graves * 100)
                                                                : 0;
                                                            return `
                                                                <tr>
                                                                    <td>${block.name}</td>
                                                                    <td>${block.plots_count || 0}</td>
                                                                    <td>${block.total_graves || 0}</td>
                                                                    <td>${block.available_graves || 0}</td>
                                                                    <td>
                                                                        <div class="progress" style="height: 20px;">
                                                                            <div class="progress-bar" style="width: ${occupancy}%">${occupancy}%</div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            `;
                                                        }).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ` : '<p class="text-muted">אין גושים</p>'}
                                    </div>
                                    
                                    <!-- חלקות -->
                                    <div class="plots-section">
                                        <h6 class="text-primary">חלקות</h6>
                                        ${response.plots && response.plots.length > 0 ? `
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>שם</th>
                                                            <th>גוש</th>
                                                            <th>שורות</th>
                                                            <th>קברים</th>
                                                            <th>פנויים</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${response.plots.map(plot => `
                                                            <tr>
                                                                <td>${plot.name}</td>
                                                                <td>${plot.block_name}</td>
                                                                <td>${plot.rows_count || 0}</td>
                                                                <td>${plot.total_graves || 0}</td>
                                                                <td>${plot.available_graves || 0}</td>
                                                            </tr>
                                                        `).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ` : '<p class="text-muted">אין חלקות</p>'}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="App.editItem('cemetery', ${cemeteryId})">
                                    <i class="fas fa-edit"></i> ערוך
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#cemeteryDetailsModal').remove();

            $('body').append(modalContent);
            
            const modal = new bootstrap.Modal(document.getElementById('cemeteryDetailsModal'));
            modal.show();

            $('#cemeteryDetailsModal').on('hidden.bs.modal', function () {
                $(this).remove();
                $('.modal-backdrop').remove(); // הוסף את השורה הזו!
                $('body').removeClass('modal-open'); // ואת השורה הזו!
                $('body').css('padding-right', ''); // ואת השורה הזו!
            });
            
        } catch (error) {
            console.error('Error loading cemetery details:', error);
            Utils.showError('שגיאה בטעינת פרטי בית העלמין');
        }
    }
};