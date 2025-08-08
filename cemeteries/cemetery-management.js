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
    
    // Show/hide loader
    function showLoader() {
        $('.loader').show();
    }
    
    function hideLoader() {
        $('.loader').hide();
    }
    
    // Show error message
    function showError(message) {
        hideLoader();
        Swal.fire({
            icon: 'error',
            title: 'שגיאה',
            text: message
        });
    }
    
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
                
            // Add more cases for other types
        }
        
        $('#formFields').html(fields);
        
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