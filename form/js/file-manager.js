// form/js/file-manager.js

// הודעת אישור שהקובץ נטען
console.log('Loading file-manager.js...');

// הגדרת האובייקט FileManager בטווח גלובלי
window.FileManager = {
    // הגדרות
    config: {
        formUuid: null,
        currentPath: '/',
        currentView: 'medium',
        selectedFiles: new Set(),
        clipboard: { action: null, files: [] },
        files: [],
        allowedTypes: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'],
        maxFileSize: 10 * 1024 * 1024 // 10MB
    },

    // משתנים נוספים לתפריט
    contextMenuTarget: null,
    touchTimer: null,
    selectedFolder: null,

    // אתחול
    init(formUuid) {
        this.config.formUuid = formUuid;
        this.loadFiles();
        this.bindEvents();
        this.setView(localStorage.getItem('fileManagerView') || 'medium');
        this.initExtended();
    },

    // אתחול מורחב
    initExtended() {
        // הוסף אירועי מגע למכשירים ניידים
        this.bindTouchEvents();
        
        // הוסף אירועי תפריט
        this.bindContextMenuEvents();
    },

    // אירועי תפריט נוספים
    bindContextMenuEvents() {
        // כבר מטופל ב-bindEvents
    },

    // אירועי מגע
    bindTouchEvents() {
        document.addEventListener('touchstart', (e) => {
            const fileItem = e.target.closest('.file-item');
            if (!fileItem) return;
            
            this.touchTimer = setTimeout(() => {
                fileItem.classList.add('touch-hold');
                this.showContextMenuForItem(fileItem, e.touches[0].clientX, e.touches[0].clientY);
            }, 500);
        });
        
        document.addEventListener('touchend', () => {
            if (this.touchTimer) {
                clearTimeout(this.touchTimer);
                document.querySelectorAll('.file-item.touch-hold').forEach(item => {
                    item.classList.remove('touch-hold');
                });
            }
        });
        
        document.addEventListener('touchmove', () => {
            if (this.touchTimer) {
                clearTimeout(this.touchTimer);
            }
        });
    },

    // טעינת קבצים
    async loadFiles(path = '/') {
        try {
            const response = await fetch('ajax/get_form_files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `form_uuid=${this.config.formUuid}&path=${encodeURIComponent(path)}`
            });

            if (!response.ok) throw new Error('Failed to load files');

            const data = await response.json();
            this.config.files = data.files || [];
            this.config.currentPath = path;
            this.renderFiles();
            this.updateBreadcrumb();
            this.updateStatus();
        } catch (error) {
            console.error('Error loading files:', error);
            this.showNotification('שגיאה בטעינת קבצים', 'error');
        }
    },

    // הצגת קבצים
    renderFiles() {
        const grid = document.getElementById('filesGrid');
        if (!grid) return;

        grid.innerHTML = '';
        grid.className = `files-grid view-${this.config.currentView}`;

        // הוסף תיקיית חזרה אם לא בשורש
        if (this.config.currentPath !== '/') {
            grid.appendChild(this.createBackItem());
        }

        // הצג תיקיות קודם, אחר כך קבצים
        const folders = this.config.files.filter(f => f.is_folder);
        const files = this.config.files.filter(f => !f.is_folder);

        [...folders, ...files].forEach(file => {
            grid.appendChild(this.createFileItem(file));
        });
    },

    // יצירת פריט קובץ
    createFileItem(file) {
        const item = document.createElement('div');
        item.className = 'file-item';
        item.dataset.fileId = file.id;
        item.dataset.fileName = file.name;

        // צ'קבוקס
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input file-checkbox';
        checkbox.onclick = (e) => {
            e.stopPropagation();
            this.toggleSelection(file.id);
        };

        // אייקון או תצוגה מקדימה
        const iconDiv = document.createElement('div');
        iconDiv.className = 'file-icon';

        if (file.is_folder) {
            // הוסף אינדיקטור אם יש תוכן בתיקייה
            if (file.item_count && file.item_count > 0) {
                iconDiv.innerHTML = '<i class="fas fa-folder icon-folder"></i><small class="text-muted"> (' + file.item_count + ')</small>';
            } else {
                iconDiv.innerHTML = '<i class="fas fa-folder-open icon-folder"></i>';
            }
        } else if (file.thumbnail) {
            const img = document.createElement('img');
            img.src = file.thumbnail;
            img.className = 'file-preview';
            img.alt = file.name;
            iconDiv.appendChild(img);
        } else {
            iconDiv.innerHTML = this.getFileIcon(file.extension);
        }

        // שם קובץ
        const nameDiv = document.createElement('div');
        nameDiv.className = 'file-name';
        nameDiv.textContent = file.name;
        nameDiv.title = file.name;

        // מידע נוסף לתצוגת רשימה
        if (this.config.currentView === 'list') {
            const infoDiv = document.createElement('div');
            infoDiv.className = 'file-info';
            infoDiv.innerHTML = `
                <span>${this.formatFileSize(file.size)}</span>
                <span>${this.formatDate(file.upload_date)}</span>
            `;
            item.appendChild(infoDiv);
        }

        // הרכבת הפריט
        item.appendChild(checkbox);
        item.appendChild(iconDiv);
        item.appendChild(nameDiv);

        // אירועים
        item.onclick = () => this.handleFileClick(file);
        item.ondblclick = () => this.handleFileDoubleClick(file);

        return item;
    },

    // יצירת פריט חזרה
    createBackItem() {
        const item = document.createElement('div');
        item.className = 'file-item';
        
        const iconDiv = document.createElement('div');
        iconDiv.className = 'file-icon';
        iconDiv.innerHTML = '<i class="fas fa-level-up-alt"></i>';

        const nameDiv = document.createElement('div');
        nameDiv.className = 'file-name';
        nameDiv.textContent = '..';

        item.appendChild(iconDiv);
        item.appendChild(nameDiv);

        item.onclick = () => this.navigateUp();

        return item;
    },

    // קבלת אייקון לפי סוג קובץ
    getFileIcon(extension) {
        const icons = {
            // מסמכים
            pdf: '<i class="fas fa-file-pdf icon-pdf"></i>',
            doc: '<i class="fas fa-file-word icon-word"></i>',
            docx: '<i class="fas fa-file-word icon-word"></i>',
            xls: '<i class="fas fa-file-excel icon-excel"></i>',
            xlsx: '<i class="fas fa-file-excel icon-excel"></i>',
            ppt: '<i class="fas fa-file-powerpoint icon-powerpoint"></i>',
            pptx: '<i class="fas fa-file-powerpoint icon-powerpoint"></i>',
            txt: '<i class="fas fa-file-alt icon-text"></i>',
            
            // תמונות
            jpg: '<i class="fas fa-file-image icon-image"></i>',
            jpeg: '<i class="fas fa-file-image icon-image"></i>',
            png: '<i class="fas fa-file-image icon-image"></i>',
            gif: '<i class="fas fa-file-image icon-image"></i>',
            bmp: '<i class="fas fa-file-image icon-image"></i>',
            
            // וידאו
            mp4: '<i class="fas fa-file-video icon-video"></i>',
            avi: '<i class="fas fa-file-video icon-video"></i>',
            mov: '<i class="fas fa-file-video icon-video"></i>',
            
            // אודיו
            mp3: '<i class="fas fa-file-audio icon-audio"></i>',
            wav: '<i class="fas fa-file-audio icon-audio"></i>',
            
            // ארכיונים
            zip: '<i class="fas fa-file-archive icon-archive"></i>',
            rar: '<i class="fas fa-file-archive icon-archive"></i>',
            '7z': '<i class="fas fa-file-archive icon-archive"></i>',
            
            // קוד
            js: '<i class="fas fa-file-code icon-code"></i>',
            css: '<i class="fas fa-file-code icon-code"></i>',
            html: '<i class="fas fa-file-code icon-code"></i>',
            php: '<i class="fas fa-file-code icon-code"></i>'
        };

        return icons[extension?.toLowerCase()] || '<i class="fas fa-file"></i>';
    },

    // טיפול בלחיצה על קובץ
    handleFileClick(file) {
        if (!event.ctrlKey && !event.shiftKey) {
            this.clearSelection();
        }
        this.toggleSelection(file.id);
    },

    // טיפול בלחיצה כפולה
    handleFileDoubleClick(file) {
        if (file.is_folder) {
            // ניווט לתוך התיקייה
            const newPath = this.config.currentPath === '/' 
                ? '/' + file.name 
                : this.config.currentPath + '/' + file.name;
            this.navigateTo(newPath);
        } else {
            this.previewFile(file);
        }
    },

    // החלפת בחירה
    toggleSelection(fileId) {
        const item = document.querySelector(`[data-file-id="${fileId}"]`);
        const checkbox = item?.querySelector('.file-checkbox');
        
        if (this.config.selectedFiles.has(fileId)) {
            this.config.selectedFiles.delete(fileId);
            item?.classList.remove('selected');
            if (checkbox) checkbox.checked = false;
        } else {
            this.config.selectedFiles.add(fileId);
            item?.classList.add('selected');
            if (checkbox) checkbox.checked = true;
        }
        
        this.updateStatus();
    },

    // ניקוי בחירה
    clearSelection() {
        this.config.selectedFiles.clear();
        document.querySelectorAll('.file-item.selected').forEach(item => {
            item.classList.remove('selected');
            const checkbox = item.querySelector('.file-checkbox');
            if (checkbox) checkbox.checked = false;
        });
        this.updateStatus();
    },

    // בחירת הכל
    selectAll() {
        this.config.files.forEach(file => {
            this.config.selectedFiles.add(file.id);
            const item = document.querySelector(`[data-file-id="${file.id}"]`);
            item?.classList.add('selected');
            const checkbox = item?.querySelector('.file-checkbox');
            if (checkbox) checkbox.checked = true;
        });
        this.updateStatus();
    },

    // עדכון שורת מצב
    updateStatus() {
        const fileCount = document.getElementById('fileCount');
        const selectedCount = document.getElementById('selectedCount');
        const totalSize = document.getElementById('totalSize');

        if (fileCount) {
            const folderCount = this.config.files.filter(f => f.is_folder).length;
            const filesCount = this.config.files.filter(f => !f.is_folder).length;
            fileCount.textContent = `${folderCount} תיקיות, ${filesCount} קבצים`;
        }

        if (selectedCount) {
            selectedCount.textContent = `${this.config.selectedFiles.size} נבחרו`;
        }

        if (totalSize) {
            const size = this.config.files
                .filter(f => !f.is_folder)
                .reduce((sum, file) => sum + (file.size || 0), 0);
            totalSize.textContent = this.formatFileSize(size);
        }
    },

    // עדכון נתיב
    updateBreadcrumb() {
        const breadcrumb = document.getElementById('breadcrumb');
        if (!breadcrumb) return;

        breadcrumb.innerHTML = '<li class="breadcrumb-item"><a href="#" onclick="FileManager.navigateTo(\'/\')"><i class="fas fa-home"></i></a></li>';

        if (this.config.currentPath !== '/') {
            const parts = this.config.currentPath.split('/').filter(p => p);
            let path = '';
            
            parts.forEach((part, index) => {
                path += '/' + part;
                const isLast = index === parts.length - 1;
                
                if (isLast) {
                    breadcrumb.innerHTML += `<li class="breadcrumb-item active">${part}</li>`;
                } else {
                    breadcrumb.innerHTML += `<li class="breadcrumb-item"><a href="#" onclick="FileManager.navigateTo('${path}')">${part}</a></li>`;
                }
            });
        }
    },

    // ניווט לתיקייה
    navigateTo(path) {
        this.loadFiles(path);
    },

    // ניווט למעלה
    navigateUp() {
        const parts = this.config.currentPath.split('/').filter(p => p);
        parts.pop();
        const newPath = parts.length ? '/' + parts.join('/') : '/';
        this.navigateTo(newPath);
    },

    // החלפת תצוגה
    setView(view) {
        this.config.currentView = view;
        localStorage.setItem('fileManagerView', view);
        
        // עדכון כפתורים
        document.querySelectorAll('.toolbar-right .btn-group button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`.toolbar-right button[onclick*="setView('${view}')"]`);
        activeBtn?.classList.add('active');
        
        this.renderFiles();
    },

    // רענון
    refresh() {
        this.loadFiles(this.config.currentPath);
    },

    // העלאת קבצים
    uploadFiles() {
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    },

    // יצירת תיקייה
    async createFolder() {
        const name = prompt('שם התיקייה:');
        if (!name) return;

        try {
            const response = await fetch('ajax/create_folder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `form_uuid=${this.config.formUuid}&path=${this.config.currentPath}&name=${encodeURIComponent(name)}`
            });

            const data = await response.json();
            if (data.success) {
                this.refresh();
                this.showNotification('התיקייה נוצרה בהצלחה', 'success');
            } else {
                this.showNotification(data.message || 'שגיאה ביצירת תיקייה', 'error');
            }
        } catch (error) {
            console.error('Error creating folder:', error);
            this.showNotification('שגיאה ביצירת תיקייה', 'error');
        }
    },

    // טיפול בגרירה
    handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.add('drag-over');
    },

    handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-over');
    },

    handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-over');

        const files = Array.from(e.dataTransfer.files);
        this.addFilesToQueue(files);
    },

    // בחירת קבצים
    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.addFilesToQueue(files);
    },

    // הוספת קבצים לתור
    addFilesToQueue(files) {
        const queue = document.getElementById('uploadQueue');
        if (!queue) return;

        files.forEach(file => {
            // בדיקת סוג קובץ
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.config.allowedTypes.includes(extension)) {
                this.showNotification(`סוג קובץ לא מורשה: ${extension}`, 'error');
                return;
            }

            // בדיקת גודל
            if (file.size > this.config.maxFileSize) {
                this.showNotification(`הקובץ ${file.name} גדול מדי (מקסימום 10MB)`, 'error');
                return;
            }

            // הוספה לתור
            const item = this.createUploadItem(file);
            queue.appendChild(item);
        });
    },

    // יצירת פריט העלאה
    createUploadItem(file) {
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.dataset.fileName = file.name;

        const extension = file.name.split('.').pop().toLowerCase();
        
        item.innerHTML = `
            <div class="upload-item-icon">${this.getFileIcon(extension)}</div>
            <div class="upload-item-info">
                <div class="upload-item-name">${file.name}</div>
                <div class="upload-item-size">${this.formatFileSize(file.size)}</div>
            </div>
            <div class="progress upload-item-progress">
                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="upload-item-status text-muted">ממתין</div>
        `;

        item.file = file;
        return item;
    },

    // התחלת העלאה
    async startUpload() {
        const items = document.querySelectorAll('.upload-item');
        
        for (const item of items) {
            await this.uploadFile(item);
        }

        // סגירת המודל ורענון
        bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
        document.getElementById('uploadQueue').innerHTML = '';
        this.refresh();
    },

    // העלאת קובץ בודד
    async uploadFile(item) {
        const formData = new FormData();
        formData.append('file', item.file);
        formData.append('form_uuid', this.config.formUuid);
        formData.append('path', this.config.currentPath);

        const progressBar = item.querySelector('.progress-bar');
        const status = item.querySelector('.upload-item-status');

        try {
            status.textContent = 'מעלה...';
            status.className = 'upload-item-status text-primary';

            const response = await fetch('ajax/upload_file.php', {
                method: 'POST',
                body: formData,
                onUploadProgress: (e) => {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        progressBar.style.width = percent + '%';
                    }
                }
            });

            const data = await response.json();
            
            if (data.success) {
                progressBar.style.width = '100%';
                progressBar.classList.add('bg-success');
                status.textContent = 'הושלם';
                status.className = 'upload-item-status text-success';
            } else {
                throw new Error(data.message || 'שגיאה בהעלאה');
            }
        } catch (error) {
            progressBar.classList.add('bg-danger');
            status.textContent = 'שגיאה';
            status.className = 'upload-item-status text-danger';
            console.error('Upload error:', error);
        }
    },

    // ------------
    // תיקון ל-file-manager.js - החלף את הפונקציות הבאות:

// תפריט קליק ימני
// פונקציה לטיפול בקליק ימני
handleContextMenu(e) {
    const fileItem = e.target.closest('.file-item');
    if (!fileItem) return;

    e.preventDefault();
    e.stopPropagation(); // חשוב למנוע התפשטות

    const fileId = parseInt(fileItem.dataset.fileId);
    if (!fileId) return;

    // אם הקובץ לא נבחר, בחר רק אותו
    if (!this.config.selectedFiles.has(fileId)) {
        this.clearSelection();
        this.toggleSelection(fileId);
    }

    // שמור את היעד
    this.contextMenuTarget = this.config.files.find(f => f.id === fileId);

    // הצג את התפריט עם המיקום הנכון
    this.showContextMenu(e.clientX, e.clientY);
},

// הצגת תפריט
// פונקציה להצגת התפריט
showContextMenu(x, y) {
    const menu = document.getElementById('contextMenu');
    if (!menu) {
        console.error('Context menu element not found!');
        return;
    }

    // הצג/הסתר פריטים לפי הקשר
    const selectedCount = this.config.selectedFiles.size;
    const hasSelection = selectedCount > 0;
    
    // התאם את הטקסט של "בחר"
    const selectItem = menu.querySelector('[data-action="select"]');
    if (selectItem) {
        selectItem.innerHTML = hasSelection ? 
            '<i class="fas fa-times-circle"></i> בטל בחירה' : 
            '<i class="fas fa-check-square"></i> בחר';
    }

    // הצג את התפריט
    menu.style.display = 'block';
    menu.style.visibility = 'visible';
    menu.style.opacity = '1';
    
    // חשב מיקום ראשוני
    let finalX = x;
    let finalY = y;

    // מקם את התפריט
    menu.style.left = finalX + 'px';
    menu.style.top = finalY + 'px';

    // המתן frame אחד ואז בדוק אם חורג מהמסך
    requestAnimationFrame(() => {
        const rect = menu.getBoundingClientRect();
        
        // התאם אם חורג מצד ימין
        if (rect.right > window.innerWidth) {
            finalX = window.innerWidth - rect.width - 10;
        }
        
        // התאם אם חורג מלמטה
        if (rect.bottom > window.innerHeight) {
            finalY = window.innerHeight - rect.height - 10;
        }
        
        // וודא שלא חורג משמאל או מלמעלה
        if (finalX < 10) finalX = 10;
        if (finalY < 10) finalY = 10;
        
        // עדכן מיקום סופי
        menu.style.left = finalX + 'px';
        menu.style.top = finalY + 'px';
    });

    // הוסף מאזין לסגירה
    setTimeout(() => {
        document.addEventListener('click', this.handleClickOutside);
        document.addEventListener('contextmenu', this.handleClickOutside);
    }, 100);
},

// הסתרת תפריט
// פונקציה להסתרת התפריט
hideContextMenu() {
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.style.display = 'none';
        menu.style.visibility = 'hidden';
        menu.style.opacity = '0';
    }
    
    // הסר מאזינים
    document.removeEventListener('click', this.handleClickOutside);
    document.removeEventListener('contextmenu', this.handleClickOutside);
},

// פונקציה לטיפול בקליק מחוץ לתפריט
handleClickOutside(e) {
    const menu = document.getElementById('contextMenu');
    if (!menu || !menu.contains(e.target)) {
        this.hideContextMenu();
    }
},

// קישור אירועים
// עדכון ל-bindEvents - וודא שהקישורים נכונים
bindEvents() {
    // אזור גרירה
    const dropzone = document.getElementById('uploadDropzone');
    if (dropzone) {
        dropzone.addEventListener('click', () => document.getElementById('fileInput').click());
        dropzone.addEventListener('dragover', this.handleDragOver.bind(this));
        dropzone.addEventListener('dragleave', this.handleDragLeave.bind(this));
        dropzone.addEventListener('drop', this.handleDrop.bind(this));
    }

    // בחירת קבצים
    document.getElementById('fileInput')?.addEventListener('change', this.handleFileSelect.bind(this));

    // תפריט קליק ימני - חשוב לקשר לאזור הנכון
    const fileContent = document.getElementById('fileContent');
    if (fileContent) {
        fileContent.addEventListener('contextmenu', this.handleContextMenu.bind(this));
    }

    // קשר את handleClickOutside עם bind
    this.handleClickOutside = this.handleClickOutside.bind(this);

    // פעולות תפריט
    document.querySelectorAll('.context-menu-item').forEach(item => {
        item.addEventListener('click', this.handleMenuAction.bind(this));
    });
},


    // -----------

    // הצגת תפריט לפריט ספציפי
    showContextMenuForItem(fileItem, x, y) {
        const fileId = parseInt(fileItem.dataset.fileId);
        const file = this.config.files.find(f => f.id === fileId);
        
        if (!file) return;
        
        this.contextMenuTarget = file;
        
        // אם הקובץ לא נבחר, בחר רק אותו
        if (!this.config.selectedFiles.has(fileId)) {
            this.clearSelection();
            this.toggleSelection(fileId);
        }
        
        this.showContextMenu(x, y);
    },

    // טיפול בפעולת תפריט
    handleMenuAction(e) {
        const action = e.currentTarget.dataset.action;
        this.hideContextMenu();

        switch (action) {
            case 'select':
                this.toggleSelectionFromMenu();
                break;
            case 'rename':
                this.renameFile();
                break;
            case 'properties':
                this.showPropertiesEnhanced();
                break;
            case 'copy':
                this.showFolderSelector('copy');
                break;
            case 'cut':
                this.showFolderSelector('cut');
                break;
            case 'paste':
                this.pasteFiles();
                break;
            case 'share':
                this.shareFiles();
                break;
            case 'download':
                this.downloadFiles();
                break;
            case 'delete':
                this.deleteFilesWithConfirm();
                break;
            case 'associate':
                this.associateToAnotherForm();
                break;
        }
    },

    // החלפת בחירה מהתפריט
    toggleSelectionFromMenu() {
        if (this.contextMenuTarget) {
            this.toggleSelection(this.contextMenuTarget.id);
        }
    },

    // הצגת בורר תיקיות
    async showFolderSelector(action) {
        const modal = new bootstrap.Modal(document.getElementById('folderSelectModal'));
        const title = document.getElementById('folderSelectTitle');
        
        title.textContent = action === 'copy' ? 'בחר תיקיה להעתקה' : 'בחר תיקיה להעברה';
        
        // בנה עץ תיקיות
        await this.buildFolderTree();
        
        // הגדר פעולת אישור
        document.getElementById('confirmFolderSelect').onclick = () => {
            this.executeFolderAction(action);
            modal.hide();
        };
        
        modal.show();
    },

    // בניית עץ תיקיות
    async buildFolderTree() {
        const treeContainer = document.getElementById('folderTree');
        treeContainer.innerHTML = '';
        
        // תיקיית שורש
        const rootItem = this.createFolderTreeItem({
            name: 'תיקיית ראשית',
            path: '/',
            isCurrent: this.config.currentPath === '/'
        });
        treeContainer.appendChild(rootItem);
        
        // טען את כל התיקיות
        await this.loadFolderStructure('/', rootItem);
    },

    // טעינת מבנה תיקיות
    async loadFolderStructure(path, parentElement) {
        try {
            const response = await fetch('ajax/get_form_files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `form_uuid=${this.config.formUuid}&path=${encodeURIComponent(path)}`
            });
            
            const data = await response.json();
            const folders = data.files.filter(f => f.is_folder);
            
            if (folders.length > 0) {
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'folder-tree-children';
                
                for (const folder of folders) {
                    const folderPath = path === '/' ? '/' + folder.name : path + '/' + folder.name;
                    const folderItem = this.createFolderTreeItem({
                        name: folder.name,
                        path: folderPath,
                        isCurrent: this.config.currentPath === folderPath
                    });
                    
                    childrenContainer.appendChild(folderItem);
                    
                    // טען תת-תיקיות
                    await this.loadFolderStructure(folderPath, folderItem);
                }
                
                parentElement.appendChild(childrenContainer);
            }
        } catch (error) {
            console.error('Error loading folder structure:', error);
        }
    },

    // יצירת פריט עץ תיקיות
    createFolderTreeItem(folder) {
        const item = document.createElement('div');
        item.className = 'folder-tree-item';
        if (folder.isCurrent) item.classList.add('current');
        
        item.innerHTML = `
            <i class="fas fa-folder${folder.isCurrent ? '-open' : ''}"></i>
            <span>${folder.name}</span>
        `;
        
        item.onclick = (e) => {
            e.stopPropagation();
            document.querySelectorAll('.folder-tree-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            this.selectedFolder = folder.path;
        };
        
        return item;
    },

    // ביצוע פעולת תיקייה
    async executeFolderAction(action) {
        if (!this.selectedFolder) {
            this.showNotification('לא נבחרה תיקיית יעד', 'warning');
            return;
        }
        
        const selectedFiles = Array.from(this.config.selectedFiles);
        
        try {
            const response = await fetch('ajax/move_copy_files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    files: selectedFiles,
                    source: this.config.currentPath,
                    destination: this.selectedFolder,
                    form_uuid: this.config.formUuid
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.clearSelection();
                this.refresh();
                this.showNotification(
                    action === 'copy' ? 'הקבצים הועתקו בהצלחה' : 'הקבצים הועברו בהצלחה',
                    'success'
                );
            } else {
                this.showNotification(data.message || 'שגיאה בביצוע הפעולה', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('שגיאה בביצוע הפעולה', 'error');
        }
    },

    // פעולות קבצים
    async renameFile() {
        const fileId = Array.from(this.config.selectedFiles)[0];
        const file = this.config.files.find(f => f.id === fileId);
        if (!file) return;

        const newName = prompt('שם חדש:', file.name);
        if (!newName || newName === file.name) return;

        try {
            const response = await fetch('ajax/rename_file.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `file_id=${fileId}&new_name=${encodeURIComponent(newName)}`
            });

            const data = await response.json();
            if (data.success) {
                this.refresh();
                this.showNotification('השם שונה בהצלחה', 'success');
            } else {
                this.showNotification(data.message || 'שגיאה בשינוי השם', 'error');
            }
        } catch (error) {
            console.error('Error renaming file:', error);
            this.showNotification('שגיאה בשינוי השם', 'error');
        }
    },

    // הצגת מאפיינים
    showProperties() {
        const fileIds = Array.from(this.config.selectedFiles);
        const files = this.config.files.filter(f => fileIds.includes(f.id));

        let content = '';

        if (files.length === 1) {
            const file = files[0];
            content = `
                <div class="properties-content">
                    <div class="text-center mb-3">
                        <div style="font-size: 48px;">${this.getFileIcon(file.extension)}</div>
                        <h5>${file.name}</h5>
                    </div>
                    <table class="table table-sm">
                        <tr><td>סוג:</td><td>${file.is_folder ? 'תיקייה' : file.extension}</td></tr>
                        <tr><td>גודל:</td><td>${this.formatFileSize(file.size)}</td></tr>
                        <tr><td>תאריך העלאה:</td><td>${this.formatDate(file.upload_date)}</td></tr>
                        <tr><td>הועלה על ידי:</td><td>${file.uploaded_by || 'לא ידוע'}</td></tr>
                    </table>
                </div>
            `;
        } else {
            const totalSize = files.reduce((sum, f) => sum + (f.size || 0), 0);
            content = `
                <div class="properties-content">
                    <p>נבחרו ${files.length} פריטים</p>
                    <p>גודל כולל: ${this.formatFileSize(totalSize)}</p>
                </div>
            `;
        }

        document.getElementById('propertiesContent').innerHTML = content;
        const modal = new bootstrap.Modal(document.getElementById('propertiesModal'));
        modal.show();
    },

    // הצגת מאפיינים משופרת
    showPropertiesEnhanced() {
        const fileIds = Array.from(this.config.selectedFiles);
        const files = this.config.files.filter(f => fileIds.includes(f.id));
        
        let content = '';
        
        if (files.length === 1) {
            const file = files[0];
            content = `
                <div class="properties-content">
                    <div class="text-center mb-3">
                        <div style="font-size: 64px;">${this.getFileIcon(file.extension)}</div>
                        <h5 class="mt-2">${file.name}</h5>
                    </div>
                    <table class="table table-sm table-striped">
                        <tr>
                            <td width="40%"><strong>סוג:</strong></td>
                            <td>${file.is_folder ? 'תיקייה' : (file.extension ? file.extension.toUpperCase() : 'קובץ')}</td>
                        </tr>
                        ${!file.is_folder ? `
                        <tr>
                            <td><strong>גודל:</strong></td>
                            <td>${this.formatFileSize(file.size)}</td>
                        </tr>
                        ` : ''}
                        <tr>
                            <td><strong>מיקום:</strong></td>
                            <td>${file.path || this.config.currentPath}</td>
                        </tr>
                        <tr>
                            <td><strong>תאריך העלאה:</strong></td>
                            <td>${this.formatDate(file.upload_date)}</td>
                        </tr>
                        ${file.uploaded_by ? `
                        <tr>
                            <td><strong>הועלה על ידי:</strong></td>
                            <td>${file.uploaded_by}</td>
                        </tr>
                        ` : ''}
                    </table>
                </div>
            `;
        } else {
            const totalSize = files.filter(f => !f.is_folder).reduce((sum, f) => sum + (f.size || 0), 0);
            const folderCount = files.filter(f => f.is_folder).length;
            const fileCount = files.filter(f => !f.is_folder).length;
            
            content = `
                <div class="properties-content">
                    <h5 class="text-center mb-3">מאפיינים מרובים</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>סה"כ פריטים:</strong></td>
                            <td>${files.length}</td>
                        </tr>
                        ${folderCount > 0 ? `
                        <tr>
                            <td><strong>תיקיות:</strong></td>
                            <td>${folderCount}</td>
                        </tr>
                        ` : ''}
                        ${fileCount > 0 ? `
                        <tr>
                            <td><strong>קבצים:</strong></td>
                            <td>${fileCount}</td>
                        </tr>
                        <tr>
                            <td><strong>גודל כולל:</strong></td>
                            <td>${this.formatFileSize(totalSize)}</td>
                        </tr>
                        ` : ''}
                    </table>
                </div>
            `;
        }
        
        document.getElementById('propertiesContent').innerHTML = content;
        const modal = new bootstrap.Modal(document.getElementById('propertiesModal'));
        modal.show();
    },

    // העתקה
    copyFiles() {
        this.config.clipboard = {
            action: 'copy',
            files: Array.from(this.config.selectedFiles)
        };
        this.showNotification(`${this.config.selectedFiles.size} פריטים הועתקו`, 'info');
    },

    // גזירה
    cutFiles() {
        this.config.clipboard = {
            action: 'cut',
            files: Array.from(this.config.selectedFiles)
        };
        this.showNotification(`${this.config.selectedFiles.size} פריטים נגזרו`, 'info');
    },

    // הדבקה
    async pasteFiles() {
        if (!this.config.clipboard.files.length) return;

        try {
            const response = await fetch('../ajax/paste_files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: this.config.clipboard.action,
                    files: this.config.clipboard.files,
                    destination: this.config.currentPath,
                    form_uuid: this.config.formUuid
                })
            });

            const data = await response.json();
            if (data.success) {
                if (this.config.clipboard.action === 'cut') {
                    this.config.clipboard = { action: null, files: [] };
                }
                this.refresh();
                this.showNotification('הפעולה בוצעה בהצלחה', 'success');
            } else {
                this.showNotification(data.message || 'שגיאה בהדבקה', 'error');
            }
        } catch (error) {
            console.error('Error pasting files:', error);
            this.showNotification('שגיאה בהדבקה', 'error');
        }
    },

    // שיתוף
    shareFiles() {
        const fileIds = Array.from(this.config.selectedFiles);
        const shareUrl = window.location.origin + '/shared/' + this.config.formUuid;
        
        if (navigator.share) {
            navigator.share({
                title: 'קבצים משותפים',
                url: shareUrl
            });
        } else {
            navigator.clipboard.writeText(shareUrl);
            this.showNotification('הקישור הועתק ללוח', 'success');
        }
    },

    // הורדה
    downloadFiles() {
        const fileIds = Array.from(this.config.selectedFiles);
        
        fileIds.forEach(fileId => {
            const file = this.config.files.find(f => f.id === fileId);
            if (file && !file.is_folder) {
                const a = document.createElement('a');
                a.href = `ajax/download_file.php?id=${fileId}`;
                a.download = file.name;
                a.click();
            }
        });
    },

    // מחיקה
    async deleteFiles() {
        const fileIds = Array.from(this.config.selectedFiles);
        const count = fileIds.length;
        
        if (!confirm(`האם למחוק ${count} פריטים?`)) return;

        try {
            const response = await fetch('ajax/delete_files.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ files: fileIds })
            });

            const data = await response.json();
            if (data.success) {
                this.clearSelection();
                this.refresh();
                this.showNotification(`${count} פריטים נמחקו`, 'success');
            } else {
                this.showNotification(data.message || 'שגיאה במחיקה', 'error');
            }
        } catch (error) {
            console.error('Error deleting files:', error);
            this.showNotification('שגיאה במחיקה', 'error');
        }
    },

    // מחיקה עם אישור משופר
    async deleteFilesWithConfirm() {
        const selectedFiles = Array.from(this.config.selectedFiles);
        const count = selectedFiles.length;
        
        // הצג הודעה מפורטת יותר
        const files = this.config.files.filter(f => selectedFiles.includes(f.id));
        const folders = files.filter(f => f.is_folder);
        const regularFiles = files.filter(f => !f.is_folder);
        
        let message = `האם אתה בטוח שברצונך למחוק `;
        if (folders.length && regularFiles.length) {
            message += `${folders.length} תיקיות ו-${regularFiles.length} קבצים?`;
        } else if (folders.length) {
            message += `${folders.length} תיקיות?`;
        } else {
            message += `${regularFiles.length} קבצים?`;
        }
        
        if (folders.length) {
            message += '\n\nאזהרה: מחיקת תיקייה תמחק את כל תוכנה!';
        }
        
        if (!confirm(message)) return;
        
        await this.deleteFiles();
    },

    // שיוך לטופס אחר - לטיפול בהמשך
    associateToAnotherForm() {
        this.showNotification('פונקציה זו תהיה זמינה בקרוב', 'info');
    },

    // תצוגה מקדימה
    previewFile(file) {
        // פתיחה בחלון חדש או הצגה במודל
        window.open(`ajax/preview_file.php?id=${file.id}`, '_blank');
    },

    // פונקציות עזר
    formatFileSize(bytes) {
        if (!bytes) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('he-IL') + ' ' + date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
    },

    showNotification(message, type = 'info') {
        // יצירת הודעה
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed bottom-0 end-0 m-3`;
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // הסרה אוטומטית
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 150);
        }, 3000);
    }
};

// אישור שהאובייקט נטען
console.log('FileManager loaded successfully:', typeof window.FileManager);