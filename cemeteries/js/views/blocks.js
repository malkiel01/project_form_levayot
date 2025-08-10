// js/views/blocks.js
if (!window.Views) window.Views = {};

Views.Blocks = {
    async load() {
        try {
            const response = await API.getBlocks();
            this.render(response);
        } catch (error) {
            console.error('Error loading blocks:', error);
        }
    },
    
    render(data) {
        const blocks = data.blocks || data;
        const cemeteries = data.cemeteries || [];
        
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול גושים</h2>
                <button class="btn btn-add" onclick="App.addItem('block')">
                    <i class="fas fa-plus"></i> הוסף גוש
                </button>
            </div>`;
        
        // Add filter if we have cemeteries
        if (cemeteries.length > 0) {
            html += `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-select" id="filterCemetery" onchange="Views.Blocks.filterByCemetery()">
                            <option value="">כל בתי העלמין</option>
                            ${cemeteries.map(c => `<option value="${c.id}">${c.name}</option>`).join('')}
                        </select>
                    </div>
                </div>`;
        }
        
        html += `
            <div class="hierarchy-card">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>מס׳</th>
                            <th>בית עלמין</th>
                            <th>שם הגוש</th>
                            <th>קוד</th>
                            <th>מספר חלקות</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody id="blocksTableBody">`;
        
        if (blocks && blocks.length > 0) {
            blocks.forEach(block => {
                html += `
                    <tr class="clickable-row block-row" data-block-id="${block.id}" data-cemetery="${block.cemetery_id}" style="cursor: pointer;">
                        <td>${block.id}</td>
                        <td>${block.cemetery_name}</td>
                        <td>${block.name}</td>
                        <td>${block.code || '-'}</td>
                        <td>${block.plots_count || 0}</td>
                        <td>${Utils.formatStatus(block.is_active)}</td>
                        <td class="action-buttons" onclick="event.stopPropagation();">
                            ${Utils.createActionButtons('block', block.id)}
                        </td>
                    </tr>`;
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
            </div>`;
        
        $('#content-area').html(html);
        
        // הוסף אירוע לחיצה על שורות
        $('.clickable-row').on('click', function() {
            const blockId = $(this).data('block-id');
            Views.Blocks.showDetails(blockId);
        });
    },
    
    filterByCemetery() {
        const cemeteryId = $('#filterCemetery').val();
        if (cemeteryId) {
            $('.block-row').hide();
            $(`.block-row[data-cemetery="${cemeteryId}"]`).show();
        } else {
            $('.block-row').show();
        }
    },
    
    async showDetails(blockId) {
        try {
            const response = await API.getBlockDetails(blockId);
            
            // בדוק אם התשובה תקינה
            if (!response || !response.success) {
                Utils.showError(response?.message || 'שגיאה בטעינת פרטי גוש');
                return;
            }
            
            // בדוק שיש נתונים
            if (!response.block) {
                Utils.showError('לא נמצאו פרטי גוש');
                return;
            }
            
            const stats = response.stats || {};
            
            let modalContent = `
                <div class="modal fade" id="blockDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">פרטי גוש: ${response.block.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="block-details">
                                    <!-- מידע כללי -->
                                    <div class="info-section mb-4">
                                        <h6 class="text-primary">מידע כללי</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p><strong>בית עלמין:</strong> ${response.block.cemetery_name}</p>
                                                <p><strong>קוד:</strong> ${response.block.code || 'לא הוגדר'}</p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>סטטוס:</strong> 
                                                    <span class="status-badge ${response.block.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                                        ${response.block.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                                    </span>
                                                </p>
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
                                            <div class="col-md-4">
                                                <div class="stat-card bg-info text-white p-3 rounded">
                                                    <h4>${stats.total_graves || 0}</h4>
                                                    <p class="mb-0">סה"כ קברים</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- חלקות -->
                                    <div class="plots-section">
                                        <h6 class="text-primary">חלקות בגוש</h6>
                                        ${response.plots && response.plots.length > 0 ? `
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>שם</th>
                                                            <th>קוד</th>
                                                            <th>שורות</th>
                                                            <th>קברים</th>
                                                            <th>פנויים</th>
                                                            <th>תפוסה</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${response.plots.map(plot => {
                                                            const total = plot.total_graves || 0;
                                                            const available = plot.available_graves || 0;
                                                            const occupancy = total > 0 
                                                                ? Math.round((total - available) / total * 100)
                                                                : 0;
                                                            return `
                                                                <tr>
                                                                    <td>${plot.name}</td>
                                                                    <td>${plot.code || '-'}</td>
                                                                    <td>${plot.rows_count || 0}</td>
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
                                        ` : '<p class="text-muted">אין חלקות</p>'}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="App.editItem('block', ${blockId})">
                                    <i class="fas fa-edit"></i> ערוך
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#blockDetailsModal').remove();
            $('body').append(modalContent);
            
            const modal = new bootstrap.Modal(document.getElementById('blockDetailsModal'));
            modal.show();
            
            $('#blockDetailsModal').on('hidden.bs.modal', function () {
                $(this).remove();
            });
            
        } catch (error) {
            console.error('Error loading block details:', error);
            Utils.showError('שגיאה בטעינת פרטי גוש');
        }
    }
};