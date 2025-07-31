<?php
// form/includes/file_manager.php
?>
<div class="section-title">מסמכים וקבצים</div>
<div class="file-manager-container" id="fileManagerContainer">
    <!-- כלי עבודה עליונים -->
    <div class="file-manager-toolbar">
        <div class="toolbar-left">
            <button type="button" class="btn btn-sm btn-primary" onclick="FileManager.uploadFiles()">
                <i class="fas fa-upload"></i> העלה קבצים
            </button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="FileManager.createFolder()">
                <i class="fas fa-folder-plus"></i> תיקייה חדשה
            </button>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.selectAll()">
                    <i class="fas fa-check-square"></i> בחר הכל
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.clearSelection()">
                    <i class="fas fa-square"></i> נקה בחירה
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.setView('list')" title="תצוגת רשימה">
                    <i class="fas fa-list"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.setView('small')" title="אייקונים קטנים">
                    <i class="fas fa-th"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.setView('medium')" title="אייקונים בינוניים">
                    <i class="fas fa-th-large"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.setView('large')" title="אייקונים גדולים">
                    <i class="fas fa-square"></i>
                </button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="FileManager.refresh()">
                <i class="fas fa-sync"></i>
            </button>
        </div>
    </div>

    <!-- נתיב נוכחי -->
    <div class="file-manager-breadcrumb">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" id="breadcrumb">
                <li class="breadcrumb-item"><a href="#" onclick="FileManager.navigateTo('/')"><i class="fas fa-home"></i></a></li>
            </ol>
        </nav>
    </div>

    <!-- אזור הקבצים -->
    <div class="file-manager-content" id="fileContent">
        <div class="files-grid view-medium" id="filesGrid">
            <!-- הקבצים יוצגו כאן באמצעות JavaScript -->
        </div>
    </div>

    <!-- שורת מצב -->
    <div class="file-manager-status">
        <span id="fileCount">0 קבצים</span>
        <span class="separator">|</span>
        <span id="selectedCount">0 נבחרו</span>
        <span class="separator">|</span>
        <span id="totalSize">0 MB</span>
    </div>
</div>

<!-- תפריט קליק ימני -->
 <!-- new -->
<div class="context-menu" id="contextMenu">
    <ul class="context-menu-list">
        <li class="context-menu-item" data-action="select">
            <i class="fas fa-check-square"></i> בחר
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="copy">
            <i class="fas fa-copy"></i> העתק
        </li>
        <li class="context-menu-item" data-action="cut">
            <i class="fas fa-cut"></i> גזור
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="delete">
            <i class="fas fa-trash"></i> מחק
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="properties">
            <i class="fas fa-info-circle"></i> מאפיינים
        </li>
        <li class="context-menu-item" data-action="associate">
            <i class="fas fa-link"></i> שיוך לטופס אחר
        </li>
    </ul>
</div>

<!-- new -->
<!-- מודל בחירת תיקיית יעד -->
<div class="modal fade" id="folderSelectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="folderSelectTitle">בחר תיקיית יעד</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="folder-tree" id="folderTree">
                    <!-- עץ תיקיות יוצג כאן -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                <button type="button" class="btn btn-primary" id="confirmFolderSelect">אישור</button>
            </div>
        </div>
    </div>
</div>


<!-- מודל מאפייני קובץ -->
<div class="modal fade" id="propertiesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">מאפייני קובץ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="propertiesContent">
                <!-- תוכן המאפיינים יוצג כאן -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
            </div>
        </div>
    </div>
</div>

<!-- קלט מוסתר לשמירת רשימת הקבצים -->
<input type="hidden" id="form_files" name="form_files" value="">

<script>
// אתחול מנהל הקבצים אחרי שהדף נטען
document.addEventListener('DOMContentLoaded', function() {
    // וידוא שהאובייקט FileManager קיים
    if (typeof FileManager !== 'undefined' && typeof formConfig !== 'undefined' && formConfig.formUuid) {
        // אתחול מנהל הקבצים
        FileManager.init(formConfig.formUuid);
    } else {
        console.error('FileManager not loaded or formConfig.formUuid missing');
    }
});
</script>

<!-- הוספה לפיצול -->

<!-- הוסף את זה ישירות לקובץ file_manager.php לפני תג הסגירה של body -->

<style>
/* תיקון דחוף לנראות התפריט */
#contextMenu {
    position: fixed !important;
    background: white !important;
    border: 2px solid #333 !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3) !important;
    z-index: 999999 !important; /* ערך גבוה מאוד */
    min-width: 220px !important;
    padding: 0 !important;
    margin: 0 !important;
}

#contextMenu .context-menu-list {
    list-style: none !important;
    margin: 0 !important;
    padding: 8px 0 !important;
    background: white !important;
}

#contextMenu .context-menu-item {
    padding: 12px 20px !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    font-size: 14px !important;
    color: #333 !important;
    background: white !important;
    transition: all 0.2s !important;
}

#contextMenu .context-menu-item:hover {
    background-color: #e7f3ff !important;
    color: #0078d4 !important;
}

#contextMenu .context-menu-item i {
    width: 20px !important;
    text-align: center !important;
    color: #6c757d !important;
    font-size: 16px !important;
}

#contextMenu .context-menu-divider {
    height: 1px !important;
    background-color: #e9ecef !important;
    margin: 8px 0 !important;
    padding: 0 !important;
}

/* וודא שהתפריט מעל כל דבר אחר */
.modal {
    z-index: 1050 !important;
}

.modal-backdrop {
    z-index: 1040 !important;
}
</style>

<script>
// בדיקה מיידית
document.addEventListener('DOMContentLoaded', function() {
    // בדיקת z-index
    setTimeout(() => {
        const menu = document.getElementById('contextMenu');
        if (menu) {
            const computedStyle = window.getComputedStyle(menu);
            console.log('Context menu z-index:', computedStyle.zIndex);
            console.log('Context menu position:', computedStyle.position);
            console.log('Context menu display:', computedStyle.display);
            
            // בדיקה אם יש אלמנטים שמסתירים
            const allElements = document.getElementsByTagName('*');
            let highestZIndex = 0;
            for (let el of allElements) {
                const zIndex = parseInt(window.getComputedStyle(el).zIndex);
                if (!isNaN(zIndex) && zIndex > highestZIndex) {
                    highestZIndex = zIndex;
                    console.log('Element with high z-index:', el, zIndex);
                }
            }
        }
    }, 2000);
});

// תיקון זמני - הוסף listener נוסף
document.addEventListener('contextmenu', function(e) {
    const fileItem = e.target.closest('.file-item');
    if (fileItem) {
        e.preventDefault();
        e.stopPropagation();
        
        // הצג תפריט באופן ידני
        const menu = document.getElementById('contextMenu');
        if (menu) {
            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
            menu.style.zIndex = '999999';
            
            console.log('Manual menu show at:', e.pageX, e.pageY);
            console.log('Menu visible:', menu.style.display);
            console.log('Menu position:', menu.style.left, menu.style.top);
        }
    }
});

// הסתר תפריט בלחיצה
document.addEventListener('click', function() {
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.style.display = 'none';
    }
});
</script>