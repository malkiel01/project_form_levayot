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

    // אתחול
    init(formUuid) {
        this.config.formUuid = formUuid;
        this.loadFiles();
        this.bindEvents();
        this.setView(localStorage.getItem('fileManagerView') || 'medium');
    },

    // קישור אירועים
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

        // תפריט קליק ימני
        document.addEventListener('contextmenu', this.handleContextMenu.bind(this));
        document.addEventListener('click', this.hideContextMenu);

        // פעולות תפריט
        document.querySelectorAll('.context-menu-item').forEach(item => {
            item.addEventListener('click', this.handleMenuAction.bind(this));
        });
    },

    // טעינת קבצים
    async loadFiles(path = '/') {
        try {
            const response = await fetch('../ajax/get_form_files.php', {
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
            iconDiv.innerHTML = '<i class="fas fa-folder icon-folder"></i>';
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
            this.navigateTo(file.path);
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
            const response = await fetch('../ajax/create_folder.php', {
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

            const response = await fetch('../ajax/upload_file.php', {
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

    // תפריט קליק ימני
    handleContextMenu(e) {
        const fileItem = e.target.closest('.file-item');
        if (!fileItem) return;

        e.preventDefault();

        const fileId = fileItem.dataset.fileId;
        if (fileId && !this.config.selectedFiles.has(parseInt(fileId))) {
            this.clearSelection();
            this.toggleSelection(parseInt(fileId));
        }

        this.showContextMenu(e.pageX, e.pageY);
    },

    // הצגת תפריט
    showContextMenu(x, y) {
        const menu = document.getElementById('contextMenu');
        if (!menu) return;

        // התאמת פריטי תפריט
        const selectedCount = this.config.selectedFiles.size;
        const singleFile = selectedCount === 1;

        // הצג/הסתר פריטים לפי הקשר
        document.querySelector('[data-action="rename"]').style.display = singleFile ? '' : 'none';
        document.querySelector('[data-action="paste"]').style.display = this.config.clipboard.files.length ? '' : 'none';

        // מיקום
        menu.style.display = 'block';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';

        // התאמה אם חורג מהמסך
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            menu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            menu.style.top = (y - rect.height) + 'px';
        }
    },

    // הסתרת תפריט
    hideContextMenu() {
        const menu = document.getElementById('contextMenu');
        if (menu) menu.style.display = 'none';
    },

    // טיפול בפעולת תפריט
    handleMenuAction(e) {
        const action = e.currentTarget.dataset.action;
        this.hideContextMenu();

        switch (action) {
            case 'rename':
                this.renameFile();
                break;
            case 'properties':
                this.showProperties();
                break;
            case 'copy':
                this.copyFiles();
                break;
            case 'cut':
                this.cutFiles();
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
                this.deleteFiles();
                break;
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
            const response = await fetch('../ajax/rename_file.php', {
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
                a.href = `../ajax/download_file.php?id=${fileId}`;
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
            const response = await fetch('../ajax/delete_files.php', {
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

    // תצוגה מקדימה
    previewFile(file) {
        // פתיחה בחלון חדש או הצגה במודל
        window.open(`../ajax/preview_file.php?id=${file.id}`, '_blank');
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

// אישור שהאובייקט נטען
console.log('FileManager loaded successfully:', typeof window.FileManager);