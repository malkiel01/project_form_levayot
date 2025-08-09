// js/utils.js
const Utils = {
    showLoader() {
        $('#content-area').html(`
            <div class="loader">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">טוען...</span>
                </div>
            </div>
        `);
    },
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'שגיאה',
            text: message
        });
    },
    
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'הצלחה',
            text: message,
            timer: 2000
        });
    },
    
    getTypeName(type) {
        const types = {
            cemetery: 'בית עלמין',
            block: 'גוש',
            plot: 'חלקה',
            row: 'שורה',
            areaGrave: 'אחוזת קבר',
            grave: 'קבר'
        };
        return types[type] || type;
    },
    
    formatStatus(isActive) {
        return `<span class="status-badge ${isActive == 1 ? 'status-active' : 'status-inactive'}">
            ${isActive == 1 ? 'פעיל' : 'לא פעיל'}
        </span>`;
    },
    
    formatGraveStatus(isAvailable) {
        return `<span class="status-badge ${isAvailable == 1 ? 'status-active' : 'status-inactive'}">
            ${isAvailable == 1 ? 'פנוי' : 'תפוס'}
        </span>`;
    },
    
    createActionButtons(type, id) {
        return `
            <button class="btn btn-sm btn-primary" onclick="App.editItem('${type}', ${id})">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-danger" onclick="App.deleteItem('${type}', ${id})">
                <i class="fas fa-trash"></i>
            </button>
        `;
    }
};