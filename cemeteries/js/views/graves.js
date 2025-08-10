// js/views/graves.js
if (!window.Views) window.Views = {};

Views.Graves = {
    async load() {
        try {
            const graves = await API.getGraves();
            this.render(graves);
        } catch (error) {
            console.error('Error loading graves:', error);
        }
    },
    
    render(graves) {
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>ניהול קברים</h2>
                <button class="btn btn-add" onclick="App.addItem('grave')">
                    <i class="fas fa-plus"></i> הוסף קבר
                </button>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="hierarchy-card">
                        <h5>סינון מהיר</h5>
                        <div class="mb-3">
                            <label class="form-label">סטטוס:</label>
                            <select class="form-select" id="filterStatus" onchange="Views.Graves.filterGraves()">
                                <option value="">הכל</option>
                                <option value="available">פנויים בלבד</option>
                                <option value="reserved">שמורים בלבד</option>
                                <option value="occupied">תפוסים בלבד</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">חיפוש:</label>
                            <input type="text" class="form-control" id="searchGrave" 
                                   placeholder="חפש לפי שם או מספר..." 
                                   onkeyup="Views.Graves.filterGraves()">
                        </div>
                    </div>
                </div>
                <div class="col-md-9">
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
                                        <th>אחוזה</th>
                                        <th>שם קבר</th>
                                        <th>מספר</th>
                                        <th>קוד</th>
                                        <th>סטטוס</th>
                                        <th>פעולות</th>
                                    </tr>
                                </thead>
                                <tbody id="gravesTable">`;
        
        if (graves && graves.length > 0) {
            graves.forEach(grave => {
                html += this.createGraveRow(grave);
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
                    </div>
                </div>
            </div>`;
        
        $('#content-area').html(html);
        
        // Store data for filtering
        this.gravesData = graves;
    },
    
    createGraveRow(grave) {
        // קביעת סטטוס הקבר
        let status = 'available';
        let statusBadge = '<span class="badge bg-success">פנוי</span>';
        
        if (grave.has_burial == 1) {
            status = 'occupied';
            statusBadge = '<span class="badge bg-danger">תפוס</span>';
        } else if (grave.has_purchase == 1) {
            status = 'reserved';
            statusBadge = '<span class="badge bg-warning">שמור</span>';
        }
        
        return `
            <tr data-status="${status}" 
                data-search="${grave.name} ${grave.grave_number || ''} ${grave.code || ''}">
                <td>${grave.id}</td>
                <td>${grave.cemetery_name || '-'}</td>
                <td>${grave.block_name || '-'}</td>
                <td>${grave.plot_name || '-'}</td>
                <td>${grave.row_name || '-'}</td>
                <td>${grave.area_grave_name || '-'}</td>
                <td><strong>${grave.name}</strong></td>
                <td>${grave.grave_number || '-'}</td>
                <td>${grave.code || '-'}</td>
                <td>${statusBadge}</td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-info" 
                            onclick="Views.Graves.showDetails(${grave.id})"
                            title="הצג פרטים">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${window.Utils && Utils.createActionButtons ? Utils.createActionButtons('grave', grave.id) : `
                        <button class="btn btn-sm btn-warning" onclick="App.editItem('grave', ${grave.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="App.deleteItem('grave', ${grave.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `}
                </td>
            </tr>`;
    },
    
    filterGraves() {
        const status = $('#filterStatus').val();
        const search = $('#searchGrave').val().toLowerCase();
        
        $('#gravesTable tr').each(function() {
            const $row = $(this);
            let show = true;
            
            // Filter by status
            if (status && $row.data('status') != status) {
                show = false;
            }
            
            // Filter by search
            if (search && !$row.data('search').toLowerCase().includes(search)) {
                show = false;
            }
            
            $row.toggle(show);
        });
        
        // Update count
        const visibleRows = $('#gravesTable tr:visible').length;
        if (visibleRows === 0 && $('#gravesTable tr').length > 0) {
            $('#gravesTable').html(`
                <tr>
                    <td colspan="11" class="text-center">לא נמצאו תוצאות מתאימות</td>
                </tr>
            `);
        }
    },
    
    // פונקציות עזר פנימיות
    showLoading() {
        // הצג אינדיקטור טעינה
        const loadingHtml = `
            <div class="loading-overlay" id="loadingOverlay" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">טוען...</span>
                </div>
            </div>
        `;
        $('body').append(loadingHtml);
    },
    
    hideLoading() {
        $('#loadingOverlay').remove();
    },
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'שגיאה',
            text: message,
            confirmButtonText: 'סגור'
        });
    },
    
    // פונקציה חדשה להצגת פרטי קבר
    async showDetails(graveId) {
        try {
            this.showLoading();
            
            // טען פרטי קבר מלאים
            const response = await $.ajax({
                url: 'api/check_grave_status.php',
                method: 'GET',
                data: { 
                    action: 'get_full_status',
                    grave_id: graveId 
                }
            });
            
            if (!response.success) {
                throw new Error(response.error || 'Failed to load grave details');
            }
            
            const data = response.data;
            const grave = data.grave;
            
            // קבע סטטוס וצבע
            let statusInfo = {
                text: 'פנוי',
                class: 'success',
                icon: 'fa-check-circle'
            };
            
            if (data.has_burial) {
                statusInfo = {
                    text: 'תפוס',
                    class: 'danger',
                    icon: 'fa-times-circle'
                };
            } else if (data.has_purchase) {
                statusInfo = {
                    text: 'שמור',
                    class: 'warning',
                    icon: 'fa-lock'
                };
            }
            
            // בנה HTML לתצוגה
            let detailsHtml = `
                <div class="grave-details-container">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-${statusInfo.class} d-flex align-items-center">
                                <i class="fas ${statusInfo.icon} me-2"></i>
                                <strong>סטטוס הקבר: ${statusInfo.text}</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-map-marker-alt"></i> פרטי מיקום</h5>
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <td><strong>בית עלמין:</strong></td>
                                        <td>${grave.cemetery_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>גוש:</strong></td>
                                        <td>${grave.block_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>חלקה:</strong></td>
                                        <td>${grave.plot_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>שורה:</strong></td>
                                        <td>${grave.row_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>אחוזת קבר:</strong></td>
                                        <td>${grave.areaGrave_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>שם הקבר:</strong></td>
                                        <td><strong>${grave.name}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>מספר קבר:</strong></td>
                                        <td>${grave.grave_number || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>קוד:</strong></td>
                                        <td>${grave.code || '-'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
            `;
            
            // הוסף פרטי רכישה או קבורה אם קיימים
            if (data.has_purchase || data.has_burial) {
                // טען פרטים נוספים
                if (data.has_purchase) {
                    try {
                        const purchaseResponse = await $.ajax({
                            url: 'api/check_grave_status.php',
                            method: 'GET',
                            data: { 
                                action: 'check_purchase',
                                grave_id: graveId 
                            }
                        });
                        
                        if (purchaseResponse.success && purchaseResponse.data) {
                            const purchase = purchaseResponse.data;
                            detailsHtml += `
                                <h5><i class="fas fa-shopping-cart"></i> פרטי רכישה</h5>
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <td><strong>מספר טופס:</strong></td>
                                            <td>${purchase.form_uuid || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>שם הרוכש:</strong></td>
                                            <td>${purchase.buyer_name || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>תאריך רכישה:</strong></td>
                                            <td>${purchase.purchase_date || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>טלפון:</strong></td>
                                            <td>${purchase.buyer_phone || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>סטטוס טופס:</strong></td>
                                            <td>${this.getStatusBadge(purchase.status)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            `;
                        }
                    } catch(e) {
                        console.error('Error loading purchase data:', e);
                    }
                }
                
                if (data.has_burial) {
                    try {
                        const burialResponse = await $.ajax({
                            url: 'api/check_grave_status.php',
                            method: 'GET',
                            data: { 
                                action: 'check_burial',
                                grave_id: graveId 
                            }
                        });
                        
                        if (burialResponse.success && burialResponse.data) {
                            const burial = burialResponse.data;
                            detailsHtml += `
                                <h5><i class="fas fa-cross"></i> פרטי קבורה</h5>
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <td><strong>מספר טופס:</strong></td>
                                            <td>${burial.form_uuid || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>שם הנפטר:</strong></td>
                                            <td>${burial.deceased_name || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>תאריך פטירה:</strong></td>
                                            <td>${burial.death_date || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>תאריך קבורה:</strong></td>
                                            <td>${burial.burial_date || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>רשיון קבורה:</strong></td>
                                            <td>${burial.burial_license || '-'}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            `;
                        }
                    } catch(e) {
                        console.error('Error loading burial data:', e);
                    }
                }
            } else {
                // הקבר פנוי - הצג אפשרויות
                detailsHtml += `
                    <h5><i class="fas fa-plus-circle"></i> פעולות זמינות</h5>
                    <div class="alert alert-info">
                        <p>קבר זה פנוי וזמין לרכישה או קבורה.</p>
                        <div class="mt-3">
                            <a href="../form/index_purchase.php?grave_id=${graveId}" 
                               class="btn btn-primary" target="_blank">
                                <i class="fas fa-shopping-cart"></i> יצירת טופס רכישה
                            </a>
                            <a href="../form/index.php?grave_id=${graveId}" 
                               class="btn btn-secondary ms-2" target="_blank">
                                <i class="fas fa-cross"></i> יצירת טופס קבורה
                            </a>
                        </div>
                    </div>
                `;
            }
            
            detailsHtml += `
                        </div>
                    </div>
                </div>
            `;
            
            // הצג במודל
            Swal.fire({
                title: `פרטי קבר: ${grave.name}`,
                html: detailsHtml,
                width: '900px',
                showCloseButton: true,
                showCancelButton: false,
                confirmButtonText: 'סגור',
                customClass: {
                    popup: 'rtl-popup'
                }
            });
            
        } catch (error) {
            console.error('Error loading grave details:', error);
            this.showError(error.message || 'שגיאה בטעינת פרטי הקבר');
        } finally {
            this.hideLoading();
        }
    },
    
    // פונקציית עזר לתצוגת סטטוס
    getStatusBadge(status) {
        const badges = {
            'draft': '<span class="badge bg-secondary">טיוטה</span>',
            'in_progress': '<span class="badge bg-info">בתהליך</span>',
            'completed': '<span class="badge bg-success">הושלם</span>',
            'archived': '<span class="badge bg-dark">ארכיון</span>',
            'cancelled': '<span class="badge bg-danger">בוטל</span>'
        };
        return badges[status] || '<span class="badge bg-light text-dark">לא ידוע</span>';
    }
};