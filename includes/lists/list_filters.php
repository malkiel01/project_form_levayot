<?php
// includes/lists/list_filters.php - רכיב פילטרים לרשימות

function renderListFilters($listType, $currentFilters = [], $savedPreferences = []) {
    $db = getDbConnection();
    
    // טען נתוני עזר
    $cemeteries = $db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name")->fetchAll();
    ?>
    
    <div class="filters-container card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-filter"></i> סינון תוצאות
            </h5>
            <div class="filter-actions">
                <?php if (!empty($savedPreferences)): ?>
                    <div class="dropdown d-inline-block me-2">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bookmark"></i> העדפות שמורות
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach ($savedPreferences as $pref): ?>
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center" 
                                       href="#" onclick="loadPreference(<?= $pref['id'] ?>)">
                                        <span><?= htmlspecialchars($pref['preference_name']) ?></span>
                                        <?php if ($pref['is_default']): ?>
                                            <span class="badge bg-primary ms-2">ברירת מחדל</span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <button type="button" class="btn btn-sm btn-success" onclick="saveCurrentFilters()">
                    <i class="fas fa-save"></i> שמור העדפה
                </button>
                
                <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo"></i> איפוס
                </button>
            </div>
        </div>
        
        <div class="card-body">
            <form id="filterForm" method="GET" class="row g-3">
                <!-- טווח תאריכים -->
                <div class="col-md-3">
                    <label class="form-label">טווח תאריכים</label>
                    <select class="form-select" name="date_range" id="dateRangeSelect" onchange="toggleCustomDates()">
                        <option value="">כל התאריכים</option>
                        <option value="last_month" <?= ($currentFilters['date_range'] ?? '') == 'last_month' ? 'selected' : '' ?>>
                            חודש אחרון
                        </option>
                        <option value="last_year" <?= ($currentFilters['date_range'] ?? '') == 'last_year' ? 'selected' : '' ?>>
                            שנה אחרונה
                        </option>
                        <option value="custom" <?= ($currentFilters['date_range'] ?? '') == 'custom' ? 'selected' : '' ?>>
                            טווח מותאם
                        </option>
                    </select>
                </div>
                
                <!-- תאריכים מותאמים -->
                <div class="col-md-2" id="customDateFrom" style="display: <?= ($currentFilters['date_range'] ?? '') == 'custom' ? 'block' : 'none' ?>">
                    <label class="form-label">מתאריך</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?= $currentFilters['date_from'] ?? '' ?>">
                </div>
                
                <div class="col-md-2" id="customDateTo" style="display: <?= ($currentFilters['date_range'] ?? '') == 'custom' ? 'block' : 'none' ?>">
                    <label class="form-label">עד תאריך</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?= $currentFilters['date_to'] ?? '' ?>">
                </div>
                
                <!-- חיפוש טקסט -->
                <div class="col-md-3">
                    <label class="form-label">חיפוש חופשי</label>
                    <input type="text" class="form-control" name="search_text" 
                           placeholder="שם, ת.ז., טלפון..." 
                           value="<?= htmlspecialchars($currentFilters['search_text'] ?? '') ?>">
                </div>
                
                <!-- בית עלמין -->
                <div class="col-md-3">
                    <label class="form-label">בית עלמין</label>
                    <select class="form-select" name="cemetery_id" id="cemeterySelect" onchange="loadBlocks()">
                        <option value="">כל בתי העלמין</option>
                        <?php foreach ($cemeteries as $cemetery): ?>
                            <option value="<?= $cemetery['id'] ?>" 
                                    <?= ($currentFilters['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cemetery['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- גוש -->
                <div class="col-md-2">
                    <label class="form-label">גוש</label>
                    <select class="form-select" name="block_id" id="blockSelect" onchange="loadSections()">
                        <option value="">כל הגושים</option>
                    </select>
                </div>
                
                <!-- חלקה -->
                <div class="col-md-2">
                    <label class="form-label">חלקה</label>
                    <select class="form-select" name="section_id" id="sectionSelect">
                        <option value="">כל החלקות</option>
                    </select>
                </div>
                
                <!-- סטטוס -->
                <div class="col-md-2">
                    <label class="form-label">סטטוס</label>
                    <select class="form-select" name="status">
                        <option value="">כל הסטטוסים</option>
                        <option value="draft" <?= ($currentFilters['status'] ?? '') == 'draft' ? 'selected' : '' ?>>טיוטה</option>
                        <option value="completed" <?= ($currentFilters['status'] ?? '') == 'completed' ? 'selected' : '' ?>>הושלם</option>
                        <option value="archived" <?= ($currentFilters['status'] ?? '') == 'archived' ? 'selected' : '' ?>>ארכיון</option>
                    </select>
                </div>
                
                <!-- כפתור חיפוש -->
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> חפש
                    </button>
                    
                    <button type="button" class="btn btn-success ms-2" onclick="exportResults()">
                        <i class="fas fa-file-excel"></i> ייצוא לאקסל
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal לשמירת העדפה -->
    <div class="modal fade" id="savePreferenceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">שמירת העדפת חיפוש</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">שם ההעדפה</label>
                        <input type="text" class="form-control" id="preferenceName" 
                               placeholder="לדוגמה: טפסים מהחודש האחרון">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="setAsDefault">
                        <label class="form-check-label" for="setAsDefault">
                            הגדר כברירת מחדל
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                    <button type="button" class="btn btn-primary" onclick="doSavePreference()">שמור</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // פונקציות JavaScript לניהול הפילטרים
    function toggleCustomDates() {
        const dateRange = document.getElementById('dateRangeSelect').value;
        document.getElementById('customDateFrom').style.display = dateRange === 'custom' ? 'block' : 'none';
        document.getElementById('customDateTo').style.display = dateRange === 'custom' ? 'block' : 'none';
    }
    
    function loadBlocks() {
        const cemeteryId = document.getElementById('cemeterySelect').value;
        if (!cemeteryId) {
            document.getElementById('blockSelect').innerHTML = '<option value="">כל הגושים</option>';
            return;
        }
        
        fetch(`../../ajax/get_blocks.php?cemetery_id=${cemeteryId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">כל הגושים</option>';
                data.forEach(block => {
                    options += `<option value="${block.id}">${block.name}</option>`;
                });
                document.getElementById('blockSelect').innerHTML = options;
            });
    }
    
    function loadSections() {
        const blockId = document.getElementById('blockSelect').value;
        if (!blockId) {
            document.getElementById('sectionSelect').innerHTML = '<option value="">כל החלקות</option>';
            return;
        }
        
        fetch(`../ajax/get_sections.php?block_id=${blockId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">כל החלקות</option>';
                data.forEach(section => {
                    options += `<option value="${section.id}">${section.name}</option>`;
                });
                document.getElementById('sectionSelect').innerHTML = options;
            });
    }
    
    function saveCurrentFilters() {
        const modal = new bootstrap.Modal(document.getElementById('savePreferenceModal'));
        modal.show();
    }
    
    function doSavePreference() {
        const name = document.getElementById('preferenceName').value;
        if (!name) {
            alert('אנא הזן שם להעדפה');
            return;
        }
        
        const formData = new FormData(document.getElementById('filterForm'));
        const filters = Object.fromEntries(formData);
        
        const data = {
            action: 'save_preference',
            preference_key: '<?= $listType ?>_filters',
            preference_name: name,
            preference_data: filters,
            is_default: document.getElementById('setAsDefault').checked
        };
        
        fetch('../ajax/user_preferences.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('ההעדפה נשמרה בהצלחה');
                location.reload();
            } else {
                alert('שגיאה בשמירת ההעדפה');
            }
        });
    }
    
    function loadPreference(preferenceId) {
        fetch(`../ajax/user_preferences.php?action=get&id=${preferenceId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.preference) {
                    const filters = data.preference.preference_data;
                    Object.keys(filters).forEach(key => {
                        const element = document.querySelector(`[name="${key}"]`);
                        if (element) {
                            element.value = filters[key];
                        }
                    });
                    document.getElementById('filterForm').submit();
                }
            });
    }
    
    function resetFilters() {
        document.getElementById('filterForm').reset();
        document.getElementById('filterForm').submit();
    }
    
    function exportResults() {
        const params = new URLSearchParams(window.location.search);
        params.append('export', 'excel');
        window.location.href = window.location.pathname + '?' + params.toString();
    }
    
    // טען ערכים בטעינת הדף
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($currentFilters['cemetery_id'])): ?>
            loadBlocks();
            <?php if (!empty($currentFilters['block_id'])): ?>
                setTimeout(() => {
                    document.getElementById('blockSelect').value = '<?= $currentFilters['block_id'] ?>';
                    loadSections();
                    <?php if (!empty($currentFilters['section_id'])): ?>
                        setTimeout(() => {
                            document.getElementById('sectionSelect').value = '<?= $currentFilters['section_id'] ?>';
                        }, 300);
                    <?php endif; ?>
                }, 300);
            <?php endif; ?>
        <?php endif; ?>
    });
    </script>
    <?php
}
?>