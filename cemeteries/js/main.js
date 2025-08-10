// js/main.js
const App = {
    currentPage: 'overview',
    
    init() {
        // Check authentication first
        Auth.checkSession();
        
        this.bindEvents();
        this.loadPage('overview');
    },
    
    bindEvents() {
        // Navigation
        $('.nav-link').on('click', (e) => {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const page = $link.data('page');
            
            $('.nav-link').removeClass('active');
            $link.addClass('active');
            
            $('#current-page').text($link.text().trim());
            this.loadPage(page);
        });
        
        // Save button
        $('#saveBtn').on('click', () => {
            Forms.save();
        });
    },
    
    async loadPage(page) {
        this.currentPage = page;
        Utils.showLoader();
        
        try {
            switch(page) {
                case 'overview':
                    await Views.Overview.load();
                    break;
                case 'cemeteries':
                    await Views.Cemeteries.load();
                    break;
                case 'blocks':
                    await Views.Blocks.load();
                    break;
                case 'plots':
                    await Views.Plots.load();
                    break;
                case 'rows':
                    await Views.Rows.load();
                    break;
                case 'areaGraves':
                    await Views.AreaGraves.load();
                    break;
                case 'graves':
                    await Views.Graves.load();
                    break;
            }
        } catch (error) {
            console.error('Error loading page:', error);
        }
    },
    
    async addItem(type) {
        $('#itemId').val('');
        $('#itemType').val(type);
        $('#modalTitle').text(`הוספת ${Utils.getTypeName(type)}`);
        await Forms.loadFields(type);
        $('#editModal').modal('show');
    },
    
    async editItem(type, id) {
        $('#itemId').val(id);
        $('#itemType').val(type);
        $('#modalTitle').text(`עריכת ${Utils.getTypeName(type)}`);
        await Forms.loadFields(type, id);
        $('#editModal').modal('show');
    },
    
    async deleteItem(type, id) {
        const result = await Swal.fire({
            title: 'האם אתה בטוח?',
            text: 'לא ניתן לשחזר פעולה זו!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'כן, מחק',
            cancelButtonText: 'ביטול'
        });
        
        if (result.isConfirmed) {
            try {
                const response = await API.deleteItem(type, id);
                if (response.success) {
                    Utils.showSuccess('הרשומה נמחקה בהצלחה');
                    this.loadPage(this.currentPage);
                } else {
                    Utils.showError(response.message || 'שגיאה במחיקה');
                }
            } catch (error) {
                console.error('Delete error:', error);
            }
        }
    }
};

// Initialize on document ready
$(document).ready(() => {
    window.App = App;
    App.init();
});