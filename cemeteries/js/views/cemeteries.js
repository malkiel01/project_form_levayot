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
    
    // פונקציה להצגת פרטי בית עלמין
    async showDetails(cemeteryId) {
        try {
            const response = await API.getCemeteryDetails(cemeteryId);
            
            let modalContent = `
                <div class="modal fade" id="cemeteryDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">פרטי בית עלמין: ${response.cemetery.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="cemetery-details">
                                    <div class="info-section mb-4">
                                        <h6 class="text-primary">מידע כללי</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>קוד:</strong> ${response.cemetery.code || 'לא הוגדר'}</p>
                                                <p><strong>סטטוס:</strong> 
                                                    <span class="status-badge ${response.cemetery.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                                        ${response.cemetery.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>מספר גושים:</strong> ${response.blocks.length}</p>
                                                <p><strong>מספר חלקות:</strong> ${response.plots.length}</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="blocks-section mb-4">
                                        <h6 class="text-primary">גושים</h6>
                                        ${response.blocks.length > 0 ? `
                                            <div class="list-group">
                                                ${response.blocks.map(block => `
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span>${block.name}</span>
                                                            <span class="badge bg-secondary">${block.sections_count || 0} חלקות</span>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        ` : '<p class="text-muted">אין גושים</p>'}
                                    </div>
                                    
                                    <div class="plots-section">
                                        <h6 class="text-primary">חלקות</h6>
                                        ${response.plots.length > 0 ? `
                                            <div class="list-group">
                                                ${response.plots.map(plot => `
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span>${plot.name} ${plot.block_name ? `(${plot.block_name})` : ''}</span>
                                                            <span class="badge bg-secondary">${plot.rows_count || 0} שורות</span>
                                                        </div>
                                                    </div>
                                                `).join('')}
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
            });
            
        } catch (error) {
            console.error('Error loading cemetery details:', error);
            Utils.showError('שגיאה בטעינת פרטי בית העלמין');
        }
    }
};