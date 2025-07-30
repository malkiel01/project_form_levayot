<!-- form/includes/file_manager.php -->
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
<div class="context-menu" id="contextMenu">
    <ul class="context-menu-list">
        <li class="context-menu-item" data-action="rename">
            <i class="fas fa-edit"></i> שינוי שם
        </li>
        <li class="context-menu-item" data-action="properties">
            <i class="fas fa-info-circle"></i> מאפיינים
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="copy">
            <i class="fas fa-copy"></i> העתק
        </li>
        <li class="context-menu-item" data-action="cut">
            <i class="fas fa-cut"></i> גזור
        </li>
        <li class="context-menu-item" data-action="paste" id="pasteOption" style="display:none;">
            <i class="fas fa-paste"></i> הדבק
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="share">
            <i class="fas fa-share-alt"></i> שתף
        </li>
        <li class="context-menu-item" data-action="download">
            <i class="fas fa-download"></i> הורד
        </li>
        <li class="context-menu-divider"></li>
        <li class="context-menu-item" data-action="delete">
            <i class="fas fa-trash"></i> מחק
        </li>
    </ul>
</div>

<!-- מודל העלאת קבצים -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">העלאת קבצים</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="upload-dropzone" id="uploadDropzone">
                    <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                    <p>גרור קבצים לכאן או לחץ לבחירה</p>
                    <input type="file" id="fileInput" multiple style="display: none;">
                </div>
                <div class="upload-queue mt-3" id="uploadQueue">
                    <!-- רשימת קבצים להעלאה -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                <button type="button" class="btn btn-primary" onclick="FileManager.startUpload()">
                    <i class="fas fa-upload"></i> התחל העלאה
                </button>
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