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
                                <option value="1">פנויים בלבד</option>
                                <option value="0">תפוסים בלבד</option>
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
        return `
            <tr data-status="${grave.is_available}" 
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
                <td>${Utils.formatGraveStatus(grave.is_available)}</td>
                <td class="action-buttons">
                    ${Utils.createActionButtons('grave', grave.id)}
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
        if (visibleRows === 0) {
            $('#gravesTable').html(`
                <tr>
                    <td colspan="11" class="text-center">לא נמצאו תוצאות מתאימות</td>
                </tr>
            `);
        }
    }
};