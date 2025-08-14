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
                                <option value="in_process">בהליך קבורה</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">חיפוש:</label>
                            <input type="text" class="form-control" id="searchGrave" 
                                placeholder="חפש לפי שם, מספר או נפטר..." 
                                onkeyup="Views.Graves.filterGraves()">
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-sm btn-secondary w-100" onclick="Views.Graves.clearFilters()">
                                <i class="fas fa-times"></i> נקה סינון
                            </button>
                        </div>
                    </div>
                    
                    <!-- סיכום סטטוסים -->
                    <div class="hierarchy-card mt-3">
                        <h6>סיכום סטטוסים</h6>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-1">
                                <span><span class="badge bg-success">פנוי</span></span>
                                <span id="countAvailable">0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span><span class="badge bg-warning text-dark">שמור</span></span>
                                <span id="countReserved">0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span><span class="badge bg-info">בהליך</span></span>
                                <span id="countInProcess">0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span><span class="badge bg-danger">תפוס</span></span>
                                <span id="countOccupied">0</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>סה"כ:</strong>
                                <strong id="countTotal">0</strong>
                            </div>
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
        
        // עדכן את הסטטיסטיקות
        this.updateStatusCounts();
    },
    
    createGraveRowDont(grave) {
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

    createGraveRow(grave) {
        // קביעת סטטוס וצבע לפי המצב
        let statusBadge = '';
        let statusText = '';
        let additionalInfo = '';
        
        switch(grave.grave_status) {
            case 'occupied':
                statusBadge = 'bg-danger';
                statusText = 'תפוס';
                if (grave.deceased_first_name || grave.deceased_last_name) {
                    additionalInfo = `<br><small>${grave.deceased_first_name || ''} ${grave.deceased_last_name || ''}</small>`;
                }
                break;
                
            case 'in_process':
                statusBadge = 'bg-info';
                statusText = 'קבורה בהליך';
                if (grave.deceased_first_name || grave.deceased_last_name) {
                    additionalInfo = `<br><small class="text-muted">
                        ${grave.deceased_first_name || ''} ${grave.deceased_last_name || ''}
                        <br>סטטוס: ${this.getStatusLabel(grave.burial_status)}
                    </small>`;
                }
                break;
                
            case 'reserved':
                statusBadge = 'bg-warning text-dark';
                statusText = 'שמור';
                if (grave.purchaser_name) {
                    additionalInfo = `<br><small>${grave.purchaser_name}</small>`;
                }
                break;
                
            case 'available':
            default:
                statusBadge = 'bg-success';
                statusText = 'פנוי';
                break;
        }
        
        return `
            <tr data-status="${grave.grave_status}" 
                data-search="${grave.name || ''} ${grave.grave_number || ''} ${grave.deceased_first_name || ''} ${grave.deceased_last_name || ''}">
                <td>${grave.id}</td>
                <td>${grave.cemetery_name || '-'}</td>
                <td>${grave.block_name || '-'}</td>
                <td>${grave.plot_name || '-'}</td>
                <td>${grave.row_name || '-'}</td>
                <td>${grave.area_grave_name || '-'}</td>
                <td><strong>${grave.name || `קבר ${grave.grave_number}`}</strong></td>
                <td>${grave.grave_number || '-'}</td>
                <td>${grave.code || '-'}</td>
                <td>
                    <span class="badge ${statusBadge}">${statusText}</span>
                    ${additionalInfo}
                </td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-info" 
                            onclick="Views.Graves.showDetails(${grave.id})"
                            title="הצג פרטים">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.getActionButtons(grave)}
                </td>
            </tr>`;
    },

    getStatusLabel(status) {
        const labels = {
            'draft': 'טיוטה',
            'in_progress': 'בתהליך',
            'completed': 'הושלם',
            'archived': 'ארכיון',
            'cancelled': 'בוטל'
        };
        return labels[status] || status;
    },

    getActionButtonsDont(grave) {
        // אם הקבר תפוס או בתהליך, הצג קישור לטופס
        let buttons = '';
        
        if (grave.burial_form_id && grave.grave_status !== 'available') {
            buttons += `
                <a href="../form/index_deceased.php?id=${grave.burial_form_id}" 
                class="btn btn-sm btn-primary" 
                target="_blank"
                title="צפה בטופס לוויה">
                    <i class="fas fa-file-alt"></i>
                </a>
            `;
        }
        
        if (grave.purchase_form_id && grave.grave_status === 'reserved') {
            buttons += `
                <a href="../form/index_purchase.php?id=${grave.purchase_form_id}" 
                class="btn btn-sm btn-warning" 
                target="_blank"
                title="צפה בטופס רכישה">
                    <i class="fas fa-shopping-cart"></i>
                </a>
            `;
        }
        
        // כפתורי עריכה/מחיקה רק לקברים פנויים
        if (grave.grave_status === 'available' && window.Utils && Utils.createActionButtons) {
            buttons += Utils.createActionButtons('grave', grave.id, true);
        }
        
        return buttons;
    },

    getActionButtons(grave) {
        let buttons = '';
        
        // קישורים לטפסים אם קיימים
        if (grave.burial_form_id && grave.grave_status !== 'available') {
            buttons += `
                <a href="../form/index_deceased.php?id=${grave.burial_form_id}" 
                class="btn btn-sm btn-primary" 
                target="_blank"
                title="צפה בטופס לוויה">
                    <i class="fas fa-file-alt"></i>
                </a>
            `;
        }
        
        if (grave.purchase_form_id && grave.grave_status === 'reserved') {
            buttons += `
                <a href="../form/index_purchase.php?id=${grave.purchase_form_id}" 
                class="btn btn-sm btn-warning" 
                target="_blank"
                title="צפה בטופס רכישה">
                    <i class="fas fa-shopping-cart"></i>
                </a>
            `;
        }
        
        // כפתור עריכה - תמיד תמיד תמיד מופיע, לכל סטטוס
        buttons += `
            <button class="btn btn-sm btn-warning" 
                    onclick="App.editItem('grave', ${grave.id})"
                    title="ערוך קבר">
                <i class="fas fa-edit"></i>
            </button>
        `;
        
        // כפתור מחיקה - רק לקברים פנויים לגמרי
        if (grave.grave_status === 'available') {
            buttons += `
                <button class="btn btn-sm btn-danger" 
                        onclick="App.deleteItem('grave', ${grave.id})"
                        title="מחק קבר">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }
        
        return buttons;
    },

    // עדכון הפילטר לתמוך בסטטוסים החדשים
    filterGraves() {
        const status = $('#filterStatus').val();
        const search = $('#searchGrave').val().toLowerCase();
        
        $('#gravesTable tbody tr').each(function() {
            const $row = $(this);
            const rowStatus = $row.data('status');
            const rowSearch = $row.data('search').toLowerCase();
            
            let showRow = true;
            
            // פילטר לפי סטטוס
            if (status) {
                if (status === 'available' && rowStatus !== 'available') showRow = false;
                if (status === 'reserved' && rowStatus !== 'reserved') showRow = false;
                if (status === 'occupied' && !['occupied', 'in_process'].includes(rowStatus)) showRow = false;
                if (status === 'in_process' && rowStatus !== 'in_process') showRow = false;
            }
            
            // פילטר לפי חיפוש
            if (search && !rowSearch.includes(search)) {
                showRow = false;
            }
            
            $row.toggle(showRow);
        });
    },
    
    filterGravesDont() {
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
    async showDetailsDont(graveId) {
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
    
    // עדכון הפונקציה showDetails ב-graves.js

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
            
            // קבע סטטוס וצבע - עדכן את הלוגיקה
            let statusInfo = {
                text: 'פנוי',
                class: 'success',
                icon: 'fa-check-circle'
            };
            
            // בדוק אם יש טופס קבורה כלשהו (לא רק מושלם)
            const hasBurialForm = grave.burial_form_id || data.has_burial;
            
            if (grave.grave_status === 'occupied') {
                statusInfo = {
                    text: 'תפוס',
                    class: 'danger',
                    icon: 'fa-times-circle'
                };
            } else if (grave.grave_status === 'in_process') {
                statusInfo = {
                    text: 'קבורה בהליך',
                    class: 'info',
                    icon: 'fa-clock'
                };
            } else if (grave.grave_status === 'reserved' || data.has_purchase) {
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
                                        <td><strong>${grave.name || '-'}</strong></td>
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
            
            // הצג פרטי קבורה אם יש טופס (גם בהליך)
            if (hasBurialForm || grave.burial_form_id) {
                // אם יש פרטים בתוך האובייקט grave
                if (grave.deceased_first_name || grave.deceased_last_name) {
                    detailsHtml += `
                        <h5><i class="fas fa-cross"></i> פרטי קבורה</h5>
                        <table class="table table-striped">
                            <tbody>`;
                    
                    if (grave.burial_form_id) {
                        detailsHtml += `
                            <tr>
                                <td><strong>מספר טופס:</strong></td>
                                <td>
                                    <a href="../form/index_deceased.php?id=${grave.burial_form_id}" 
                                    target="_blank" class="text-primary">
                                        ${grave.burial_form_id.substring(0, 8)}...
                                        <i class="fas fa-external-link-alt ms-1"></i>
                                    </a>
                                </td>
                            </tr>`;
                    }
                    
                    detailsHtml += `
                        <tr>
                            <td><strong>שם הנפטר:</strong></td>
                            <td>${grave.deceased_first_name || ''} ${grave.deceased_last_name || ''}</td>
                        </tr>`;
                    
                    if (grave.burial_date) {
                        detailsHtml += `
                            <tr>
                                <td><strong>תאריך קבורה:</strong></td>
                                <td>${grave.burial_date}</td>
                            </tr>`;
                    }
                    
                    if (grave.burial_status) {
                        detailsHtml += `
                            <tr>
                                <td><strong>סטטוס טופס:</strong></td>
                                <td>${this.getStatusBadge(grave.burial_status)}</td>
                            </tr>`;
                    }
                    
                    detailsHtml += `
                            </tbody>
                        </table>`;
                } else {
                    // נסה לטעון פרטים נוספים
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
                                            <td>
                                                <a href="../form/index_deceased.php?id=${burial.form_uuid}" 
                                                target="_blank" class="text-primary">
                                                    ${burial.form_uuid.substring(0, 8)}...
                                                    <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                            </td>
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
                                            <td><strong>סטטוס טופס:</strong></td>
                                            <td>${this.getStatusBadge(burial.status)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            `;
                        }
                    } catch(e) {
                        console.error('Error loading burial data:', e);
                    }
                }
            }
            
            // הצג פרטי רכישה אם יש
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
            
            // אם הקבר פנוי לגמרי
            if (!hasBurialForm && !data.has_purchase) {
                detailsHtml += `
                    <h5><i class="fas fa-plus-circle"></i> פעולות זמינות</h5>
                    <div class="alert alert-info">
                        <p>קבר זה פנוי וזמין לרכישה או קבורה.</p>
                        <div class="mt-3">
                            <a href="../form/index_purchase.php?grave_id=${graveId}" 
                            class="btn btn-primary" target="_blank">
                                <i class="fas fa-shopping-cart"></i> יצירת טופס רכישה
                            </a>
                            <a href="../form/index_deceased.php?grave_id=${graveId}" 
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
                title: `פרטי קבר: ${grave.name || 'קבר ' + grave.grave_number}`,
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
    },

    // פונקציה לניקוי הפילטרים
    clearFilters() {
        $('#filterStatus').val('');
        $('#searchGrave').val('');
        this.filterGraves();
    },

    // פונקציה לעדכון הסטטיסטיקות
    updateStatusCounts() {
        let counts = {
            available: 0,
            reserved: 0,
            in_process: 0,
            occupied: 0,
            total: 0
        };
        
        if (this.gravesData) {
            this.gravesData.forEach(grave => {
                counts.total++;
                switch(grave.grave_status) {
                    case 'available':
                        counts.available++;
                        break;
                    case 'reserved':
                        counts.reserved++;
                        break;
                    case 'in_process':
                        counts.in_process++;
                        break;
                    case 'occupied':
                        counts.occupied++;
                        break;
                }
            });
        }
        // עדכן את המספרים בתצוגה
        $('#countAvailable').text(counts.available);
        $('#countReserved').text(counts.reserved);
        $('#countInProcess').text(counts.in_process);
        $('#countOccupied').text(counts.occupied);
        $('#countTotal').text(counts.total);
    },

    // עדכן גם את filterGraves כדי לעדכן את הסטטיסטיקות
    filterGraves() {
        const status = $('#filterStatus').val();
        const search = $('#searchGrave').val().toLowerCase();
        
        let visibleCount = 0;
        
        $('#gravesTable tr').each(function() {
            const $row = $(this);
            const rowStatus = $row.data('status');
            const rowSearch = ($row.data('search') || '').toLowerCase();
            
            let showRow = true;
            
            // פילטר לפי סטטוס
            if (status) {
                if (status === 'available' && rowStatus !== 'available') showRow = false;
                if (status === 'reserved' && rowStatus !== 'reserved') showRow = false;
                if (status === 'occupied' && rowStatus !== 'occupied') showRow = false;
                if (status === 'in_process' && rowStatus !== 'in_process') showRow = false;
            }
            
            // פילטר לפי חיפוש
            if (search && !rowSearch.includes(search)) {
                showRow = false;
            }
            
            $row.toggle(showRow);
            if (showRow) visibleCount++;
        });
        
        // הצג הודעה אם אין תוצאות
        if (visibleCount === 0 && $('#gravesTable tr').length > 0) {
            if ($('#gravesTable .no-results').length === 0) {
                $('#gravesTable').append(`
                    <tr class="no-results">
                        <td colspan="11" class="text-center text-muted">
                            לא נמצאו תוצאות מתאימות לסינון
                        </td>
                    </tr>
                `);
            }
        } else {
            $('#gravesTable .no-results').remove();
        }
    }
};