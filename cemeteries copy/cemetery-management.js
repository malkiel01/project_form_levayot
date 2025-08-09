// cemetery-management.js
$(document).ready(function() {
    let currentPage = 'overview';
    let currentData = {};
    
    // Navigation
    $('.nav-link').on('click', function(e) {
        e.preventDefault();
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        
        currentPage = $(this).data('page');
        $('#current-page').text($(this).text().trim());
        loadPage(currentPage);
    });
    
    // Load initial page
    loadPage('overview');
    
    // Load page content
    function loadPage(page) {
        showLoader();
        
        switch(page) {
            case 'overview':
                loadOverview();
                break;
            case 'cemeteries':
                loadCemeteries();
                break;
            case 'blocks':
                loadBlocks();
                break;
            case 'plots':
                loadPlots();
                break;
            case 'rows':
                loadRows();
                break;
            case 'areaGraves':
                loadAreaGraves();
                break;
            case 'graves':
                loadGraves();
                break;
            case 'reports':
                loadReports();
                break;
            case 'import':
                loadImport();
                break;
        }
    }
    
    // Load Overview
    function loadOverview() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getStats' },
            success: function(response) {
                let html = `
                    <h2>סקירה כללית</h2>
                    <div class="row mt-4">
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.cemeteries || 0}</h3>
                                <p>בתי עלמין</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.blocks || 0}</h3>
                                <p>גושים</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.plots || 0}</h3>
                                <p>חלקות</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.rows || 0}</h3>
                                <p>שורות</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.areaGraves || 0}</h3>
                                <p>אחוזות קבר</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <h3>${response.graves || 0}</h3>
                                <p>קברים</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-5">
                        <div class="col-md-6">
                            <div class="hierarchy-card">
                                <h4>קברים פנויים לפי בית עלמין</h4>
                                <canvas id="availableGravesChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="hierarchy-card">
                                <h4>התפלגות לפי סטטוס</h4>
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                `;
                $('#content-area').html(html);
                hideLoader();
                
                // Load charts (if using Chart.js)
                // loadCharts(response.chartData);
            },
            error: function() {
                showError('שגיאה בטעינת הנתונים');
            }
        });
    }
    
    // Load Cemeteries
    function loadCemeteries() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getCemeteries' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול בתי עלמין</h2>
                        <button class="btn btn-add" onclick="addItem('cemetery')">
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
                
                response.forEach(cemetery => {
                    html += `
                        <tr>
                            <td>${cemetery.id}</td>
                            <td>${cemetery.name}</td>
                            <td>${cemetery.code || '-'}</td>
                            <td>${cemetery.blocks_count || 0}</td>
                            <td>
                                <span class="status-badge ${cemetery.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                    ${cemetery.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editItem('cemetery', ${cemetery.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewDetails('cemetery', ${cemetery.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem('cemetery', ${cemetery.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                $('#content-area').html(html);
                hideLoader();
            }
        });
    }
    
    // Load Blocks
    function loadBlocks() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getBlocks' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול גושים</h2>
                        <button class="btn btn-add" onclick="addItem('block')">
                            <i class="fas fa-plus"></i> הוסף גוש
                        </button>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="hierarchy-card">
                                <h5>סינון לפי בית עלמין</h5>
                                <select class="form-select" id="filterCemetery" onchange="filterBlocks()">
                                    <option value="">כל בתי העלמין</option>`;
                
                // Add cemetery options
                response.cemeteries?.forEach(cemetery => {
                    html += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                });
                
                html += `
                                </select>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="hierarchy-card">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>מס׳</th>
                                            <th>בית עלמין</th>
                                            <th>שם גוש</th>
                                            <th>קוד</th>
                                            <th>מספר חלקות</th>
                                            <th>סטטוס</th>
                                            <th>פעולות</th>
                                        </tr>
                                    </thead>
                                    <tbody id="blocksTable">`;
                
                response.blocks?.forEach(block => {
                    html += createBlockRow(block);
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>`;
                
                $('#content-area').html(html);
                currentData.blocks = response.blocks;
                hideLoader();
            }
        });
    }
    
    // Helper function to create block row
    function createBlockRow(block) {
        return `
            <tr data-cemetery="${block.cemetery_id}">
                <td>${block.id}</td>
                <td>${block.cemetery_name}</td>
                <td>${block.name}</td>
                <td>${block.code || '-'}</td>
                <td>${block.plots_count || 0}</td>
                <td>
                    <span class="status-badge ${block.is_active == 1 ? 'status-active' : 'status-inactive'}">
                        ${block.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                    </span>
                </td>
                <td class="action-buttons">
                    <button class="btn btn-sm btn-primary" onclick="editItem('block', ${block.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem('block', ${block.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    }
    
    // Load Plots
    function loadPlots() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getPlots' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול חלקות</h2>
                        <button class="btn btn-add" onclick="addItem('plot')">
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
                                    <th>שם חלקה</th>
                                    <th>קוד</th>
                                    <th>מספר שורות</th>
                                    <th>סטטוס</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                response.forEach(plot => {
                    html += `
                        <tr>
                            <td>${plot.id}</td>
                            <td>${plot.cemetery_name}</td>
                            <td>${plot.block_name}</td>
                            <td>${plot.name}</td>
                            <td>${plot.code || '-'}</td>
                            <td>${plot.rows_count || 0}</td>
                            <td>
                                <span class="status-badge ${plot.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                    ${plot.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editItem('plot', ${plot.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem('plot', ${plot.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                $('#content-area').html(html);
                hideLoader();
            }
        });
    }
    
    // Load Rows
    function loadRows() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getRows' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול שורות</h2>
                        <button class="btn btn-add" onclick="addItem('row')">
                            <i class="fas fa-plus"></i> הוסף שורה
                        </button>
                    </div>
                    
                    <div class="hierarchy-card">
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
                
                response.forEach(row => {
                    html += `
                        <tr>
                            <td>${row.id}</td>
                            <td>${row.cemetery_name}</td>
                            <td>${row.block_name}</td>
                            <td>${row.plot_name}</td>
                            <td>${row.name}</td>
                            <td>${row.code || '-'}</td>
                            <td>${row.area_graves_count || 0}</td>
                            <td>
                                <span class="status-badge ${row.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                    ${row.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editItem('row', ${row.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem('row', ${row.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                $('#content-area').html(html);
                hideLoader();
            }
        });
    }
    
    // Load Area Graves
    function loadAreaGraves() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getAreaGraves' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול אחוזות קבר</h2>
                        <button class="btn btn-add" onclick="addItem('areaGrave')">
                            <i class="fas fa-plus"></i> הוסף אחוזת קבר
                        </button>
                    </div>
                    
                    <div class="hierarchy-card">
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
                
                response.forEach(ag => {
                    html += `
                        <tr>
                            <td>${ag.id}</td>
                            <td>${ag.cemetery_name}</td>
                            <td>${ag.block_name}</td>
                            <td>${ag.plot_name}</td>
                            <td>${ag.row_name}</td>
                            <td>${ag.name}</td>
                            <td>${ag.code || '-'}</td>
                            <td>${ag.graves_count || 0}</td>
                            <td>${ag.available_count || 0}</td>
                            <td>
                                <span class="status-badge ${ag.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                    ${ag.is_active == 1 ? 'פעיל' : 'לא פעיל'}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editItem('areaGrave', ${ag.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem('areaGrave', ${ag.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                $('#content-area').html(html);
                hideLoader();
            }
        });
    }
    
    // Load Graves
    function loadGraves() {
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'GET',
            data: { action: 'getGraves' },
            success: function(response) {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>ניהול קברים</h2>
                        <button class="btn btn-add" onclick="addItem('grave')">
                            <i class="fas fa-plus"></i> הוסף קבר
                        </button>
                    </div>
                    
                    <div class="hierarchy-card">
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
                            <tbody>`;
                
                response.forEach(grave => {
                    html += `
                        <tr>
                            <td>${grave.id}</td>
                            <td>${grave.cemetery_name}</td>
                            <td>${grave.block_name}</td>
                            <td>${grave.plot_name}</td>
                            <td>${grave.row_name}</td>
                            <td>${grave.area_grave_name}</td>
                            <td>${grave.name}</td>
                            <td>${grave.grave_number || '-'}</td>
                            <td>${grave.code || '-'}</td>
                            <td>
                                <span class="status-badge ${grave.is_available == 1 ? 'status-active' : 'status-inactive'}">
                                    ${grave.is_available == 1 ? 'פנוי' : 'תפוס'}
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editItem('grave', ${grave.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem('grave', ${grave.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                $('#content-area').html(html);
                hideLoader();
            }
        });
    }
    
    // Load Reports
    function loadReports() {
        let html = `
            <h2>דוחות</h2>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="hierarchy-card">
                        <h4>דוח קברים פנויים</h4>
                        <p>הצג את כל הקברים הפנויים במערכת</p>
                        <button class="btn btn-primary" onclick="generateReport('available_graves')">
                            <i class="fas fa-file-pdf"></i> הפק דוח
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hierarchy-card">
                        <h4>דוח מלאי לפי בית עלמין</h4>
                        <p>סיכום מצב המלאי בכל בית עלמין</p>
                        <button class="btn btn-primary" onclick="generateReport('inventory')">
                            <i class="fas fa-file-excel"></i> הפק דוח
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hierarchy-card">
                        <h4>דוח היררכיה מלא</h4>
                        <p>הצג את כל ההיררכיה של המערכת</p>
                        <button class="btn btn-primary" onclick="generateReport('hierarchy')">
                            <i class="fas fa-sitemap"></i> הפק דוח
                        </button>
                    </div>
                </div>
            </div>`;
        
        $('#content-area').html(html);
        hideLoader();
    }
    
    // Load Import
    function loadImport() {
        let html = `
            <h2>ייבוא נתונים</h2>
            <div class="hierarchy-card">
                <h4>ייבוא מקובץ Excel</h4>
                <p>ניתן לייבא נתונים של בתי עלמין, גושים, חלקות, שורות, אחוזות קבר וקברים מקובץ Excel</p>
                
                <div class="mb-3">
                    <label class="form-label">בחר סוג נתונים לייבוא:</label>
                    <select class="form-select" id="importType">
                        <option value="">בחר סוג</option>
                        <option value="cemeteries">בתי עלמין</option>
                        <option value="blocks">גושים</option>
                        <option value="plots">חלקות</option>
                        <option value="rows">שורות</option>
                        <option value="areaGraves">אחוזות קבר</option>
                        <option value="graves">קברים</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">בחר קובץ:</label>
                    <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                </div>
                
                <button class="btn btn-primary" onclick="importData()">
                    <i class="fas fa-upload"></i> התחל ייבוא
                </button>
                
                <hr class="my-4">
                
                <h5>הורד תבניות לדוגמה:</h5>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('cemeteries')">
                        <i class="fas fa-download"></i> בתי עלמין
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('blocks')">
                        <i class="fas fa-download"></i> גושים
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('plots')">
                        <i class="fas fa-download"></i> חלקות
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('rows')">
                        <i class="fas fa-download"></i> שורות
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('areaGraves')">
                        <i class="fas fa-download"></i> אחוזות קבר
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadTemplate('graves')">
                        <i class="fas fa-download"></i> קברים
                    </button>
                </div>
            </div>`;
        
        $('#content-area').html(html);
        hideLoader();
    }
    
    // Additional global functions
    window.generateReport = function(type) {
        window.open(`api/cemetery-api.php?action=generateReport&type=${type}`, '_blank');
    };
    
    window.downloadTemplate = function(type) {
        window.open(`api/cemetery-api.php?action=downloadTemplate&type=${type}`, '_blank');
    };
    
    window.importData = function() {
        const type = $('#importType').val();
        const file = $('#importFile')[0].files[0];
        
        if (!type || !file) {
            showError('יש לבחור סוג נתונים וקובץ');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('type', type);
        formData.append('file', file);
        
        showLoader();
        
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoader();
                if (response.success) {
                    Swal.fire('הצלחה', response.message, 'success');
                } else {
                    showError(response.message || 'שגיאה בייבוא');
                }
            },
            error: function() {
                showError('שגיאה בייבוא הקובץ');
            }
        });
    };
    
    // Global functions for actions
    window.addItem = function(type) {
        $('#itemId').val('');
        $('#itemType').val(type);
        loadFormFields(type);
        $('#modalTitle').text(`הוספת ${getTypeName(type)}`);
        $('#editModal').modal('show');
    };
    
    window.editItem = function(type, id) {
        $('#itemId').val(id);
        $('#itemType').val(type);
        loadFormFields(type, id);
        $('#modalTitle').text(`עריכת ${getTypeName(type)}`);
        $('#editModal').modal('show');
    };
    
    window.deleteItem = function(type, id) {
        Swal.fire({
            title: 'האם אתה בטוח?',
            text: 'לא ניתן לשחזר פעולה זו!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'כן, מחק',
            cancelButtonText: 'ביטול'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'POST',
                    data: {
                        action: 'delete',
                        type: type,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('נמחק!', 'הרשומה נמחקה בהצלחה.', 'success');
                            loadPage(currentPage);
                        } else {
                            showError(response.message || 'שגיאה במחיקה');
                        }
                    }
                });
            }
        });
    };
    
    window.filterBlocks = function() {
        const cemeteryId = $('#filterCemetery').val();
        if (cemeteryId) {
            $('#blocksTable tr').hide();
            $(`#blocksTable tr[data-cemetery="${cemeteryId}"]`).show();
        } else {
            $('#blocksTable tr').show();
        }
    };
    
    // Get type name in Hebrew
    function getTypeName(type) {
        const types = {
            cemetery: 'בית עלמין',
            block: 'גוש',
            plot: 'חלקה',
            row: 'שורה',
            areaGrave: 'אחוזת קבר',
            grave: 'קבר'
        };
        return types[type] || type;
    }
    
    // Load form fields based on type
    function loadFormFields(type, id = null) {
        let fields = '';
        
        switch(type) {
            case 'cemetery':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">שם בית העלמין *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_active">
                            <option value="1">פעיל</option>
                            <option value="0">לא פעיל</option>
                        </select>
                    </div>`;
                break;
                
            case 'block':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">בית עלמין *</label>
                        <select class="form-select" name="cemetery_id" required>
                            <option value="">בחר בית עלמין</option>`;
                
                // Load cemeteries
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { action: 'getCemeteries' },
                    async: false,
                    success: function(cemeteries) {
                        cemeteries.forEach(cemetery => {
                            fields += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                        });
                    }
                });
                
                fields += `
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שם הגוש *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_active">
                            <option value="1">פעיל</option>
                            <option value="0">לא פעיל</option>
                        </select>
                    </div>`;
                break;
                
            case 'plot':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">בית עלמין *</label>
                        <select class="form-select" id="select_cemetery" required>
                            <option value="">בחר בית עלמין</option>`;
                
                // Load cemeteries
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { action: 'getCemeteries' },
                    async: false,
                    success: function(cemeteries) {
                        cemeteries.forEach(cemetery => {
                            fields += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                        });
                    }
                });
                
                fields += `
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">גוש *</label>
                        <select class="form-select" name="block_id" id="select_block" required>
                            <option value="">בחר קודם בית עלמין</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שם החלקה *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_active">
                            <option value="1">פעיל</option>
                            <option value="0">לא פעיל</option>
                        </select>
                    </div>`;
                break;
                
            case 'row':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">בית עלמין *</label>
                        <select class="form-select" id="select_cemetery" required>
                            <option value="">בחר בית עלמין</option>`;
                
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { action: 'getCemeteries' },
                    async: false,
                    success: function(cemeteries) {
                        cemeteries.forEach(cemetery => {
                            fields += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                        });
                    }
                });
                
                fields += `
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">גוש *</label>
                        <select class="form-select" id="select_block" required>
                            <option value="">בחר קודם בית עלמין</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">חלקה *</label>
                        <select class="form-select" name="plot_id" id="select_plot" required>
                            <option value="">בחר קודם גוש</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שם השורה *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_active">
                            <option value="1">פעיל</option>
                            <option value="0">לא פעיל</option>
                        </select>
                    </div>`;
                break;
                
            case 'areaGrave':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">בית עלמין *</label>
                        <select class="form-select" id="select_cemetery" required>
                            <option value="">בחר בית עלמין</option>`;
                
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { action: 'getCemeteries' },
                    async: false,
                    success: function(cemeteries) {
                        cemeteries.forEach(cemetery => {
                            fields += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                        });
                    }
                });
                
                fields += `
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">גוש *</label>
                        <select class="form-select" id="select_block" required>
                            <option value="">בחר קודם בית עלמין</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">חלקה *</label>
                        <select class="form-select" id="select_plot" required>
                            <option value="">בחר קודם גוש</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שורה *</label>
                        <select class="form-select" name="row_id" id="select_row" required>
                            <option value="">בחר קודם חלקה</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שם אחוזת הקבר *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_active">
                            <option value="1">פעיל</option>
                            <option value="0">לא פעיל</option>
                        </select>
                    </div>`;
                break;
                
            case 'grave':
                fields = `
                    <div class="mb-3">
                        <label class="form-label">בית עלמין *</label>
                        <select class="form-select" id="select_cemetery" required>
                            <option value="">בחר בית עלמין</option>`;
                
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { action: 'getCemeteries' },
                    async: false,
                    success: function(cemeteries) {
                        cemeteries.forEach(cemetery => {
                            fields += `<option value="${cemetery.id}">${cemetery.name}</option>`;
                        });
                    }
                });
                
                fields += `
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">גוש *</label>
                        <select class="form-select" id="select_block" required>
                            <option value="">בחר קודם בית עלמין</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">חלקה *</label>
                        <select class="form-select" id="select_plot" required>
                            <option value="">בחר קודם גוש</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שורה *</label>
                        <select class="form-select" id="select_row" required>
                            <option value="">בחר קודם חלקה</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">אחוזת קבר *</label>
                        <select class="form-select" name="areaGrave_id" id="select_areaGrave" required>
                            <option value="">בחר קודם שורה</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">שם הקבר *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">מספר קבר</label>
                        <input type="text" class="form-control" name="grave_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">קוד</label>
                        <input type="text" class="form-control" name="code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">סטטוס</label>
                        <select class="form-select" name="is_available">
                            <option value="1">פנוי</option>
                            <option value="0">תפוס</option>
                        </select>
                    </div>`;
                break;
        }
        
        $('#formFields').html(fields);
        
        // Setup dynamic selects
        setupDynamicSelects();
        
        // If editing, load existing data
        if (id) {
            $.ajax({
                url: 'api/cemetery-api.php',
                method: 'GET',
                data: {
                    action: 'getItem',
                    type: type,
                    id: id
                },
                success: function(data) {
                    for (let key in data) {
                        $(`[name="${key}"]`).val(data[key]);
                    }
                }
            });
        }
    }
    
    // Setup dynamic select handlers
    function setupDynamicSelects() {
        $('#select_cemetery').on('change', function() {
            const cemeteryId = $(this).val();
            $('#select_block').html('<option value="">טוען...</option>');
            
            if (cemeteryId) {
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { 
                        action: 'getBlocksByCemetery',
                        cemetery_id: cemeteryId
                    },
                    success: function(blocks) {
                        let options = '<option value="">בחר גוש</option>';
                        blocks.forEach(block => {
                            options += `<option value="${block.id}">${block.name}</option>`;
                        });
                        $('#select_block').html(options);
                    }
                });
            } else {
                $('#select_block').html('<option value="">בחר קודם בית עלמין</option>');
            }
        });
        
        $('#select_block').on('change', function() {
            const blockId = $(this).val();
            $('#select_plot').html('<option value="">טוען...</option>');
            
            if (blockId) {
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { 
                        action: 'getPlotsByBlock',
                        block_id: blockId
                    },
                    success: function(plots) {
                        let options = '<option value="">בחר חלקה</option>';
                        plots.forEach(plot => {
                            options += `<option value="${plot.id}">${plot.name}</option>`;
                        });
                        $('#select_plot').html(options);
                    }
                });
            } else {
                $('#select_plot').html('<option value="">בחר קודם גוש</option>');
            }
        });
        
        $('#select_plot').on('change', function() {
            const plotId = $(this).val();
            $('#select_row').html('<option value="">טוען...</option>');
            
            if (plotId) {
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { 
                        action: 'getRowsByPlot',
                        plot_id: plotId
                    },
                    success: function(rows) {
                        let options = '<option value="">בחר שורה</option>';
                        rows.forEach(row => {
                            options += `<option value="${row.id}">${row.name}</option>`;
                        });
                        $('#select_row').html(options);
                    }
                });
            } else {
                $('#select_row').html('<option value="">בחר קודם חלקה</option>');
            }
        });
        
        $('#select_row').on('change', function() {
            const rowId = $(this).val();
            $('#select_areaGrave').html('<option value="">טוען...</option>');
            
            if (rowId) {
                $.ajax({
                    url: 'api/cemetery-api.php',
                    method: 'GET',
                    data: { 
                        action: 'getAreaGravesByRow',
                        row_id: rowId
                    },
                    success: function(areaGraves) {
                        let options = '<option value="">בחר אחוזת קבר</option>';
                        areaGraves.forEach(areaGrave => {
                            options += `<option value="${areaGrave.id}">${areaGrave.name}</option>`;
                        });
                        $('#select_areaGrave').html(options);
                    }
                });
            } else {
                $('#select_areaGrave').html('<option value="">בחר קודם שורה</option>');
            }
        });
    }
    
    // Save button handler
    $('#saveBtn').on('click', function() {
        const formData = $('#editForm').serialize();
        const action = $('#itemId').val() ? 'update' : 'create';
        
        $.ajax({
            url: 'api/cemetery-api.php',
            method: 'POST',
            data: formData + '&action=' + action,
            success: function(response) {
                if (response.success) {
                    $('#editModal').modal('hide');
                    Swal.fire('הצלחה', response.message || 'הפעולה בוצעה בהצלחה', 'success');
                    loadPage(currentPage);
                } else {
                    showError(response.message || 'שגיאה בשמירה');
                }
            }
        });
    });
});