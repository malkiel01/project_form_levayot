// js/api.js
const API = {
    // GET requests
    async get(action, params = {}) {
        try {
            const response = await $.ajax({
                url: Config.API_URL,
                method: 'GET',
                data: { action, ...params },
                dataType: 'json'
            });
            return response;
        } catch (error) {
            console.error('API Error:', error);
            if (error.responseJSON && error.responseJSON.error) {
                Utils.showError(error.responseJSON.error);
            } else {
                Utils.showError('שגיאה בטעינת הנתונים');
            }
            throw error;
        }
    },
    
    // POST requests
    async post(data) {
        try {
            const response = await $.ajax({
                url: Config.API_URL,
                method: 'POST',
                data: data,
                dataType: 'json'
            });
            return response;
        } catch (error) {
            console.error('API Error:', error);
            if (error.responseJSON && error.responseJSON.error) {
                Utils.showError(error.responseJSON.error);
            } else {
                Utils.showError('שגיאה בשמירת הנתונים');
            }
            throw error;
        }
    },
    
    // Specific API calls
    async getStats() {
        return this.get('getStats');
    },
    
    async getCemeteries() {
        return this.get('getCemeteries');
    },
    
    async getBlocks() {
        return this.get('getBlocks');
    },
    
    async getPlots() {
        return this.get('getPlots');
    },
    
    async getRows() {
        return this.get('getRows');
    },
    
    async getAreaGraves() {
        return this.get('getAreaGraves');
    },
    
    async getGraves() {
        return this.get('getGraves');
    },
    
    async getItem(type, id) {
        return this.get('getItem', { type, id });
    },
    
    async getBlocksByCemetery(cemeteryId) {
        return this.get('getBlocksByCemetery', { cemetery_id: cemeteryId });
    },
    
    async getPlotsByBlock(blockId) {
        return this.get('getPlotsByBlock', { block_id: blockId });
    },
    
    async getRowsByPlot(plotId) {
        return this.get('getRowsByPlot', { plot_id: plotId });
    },
    
    async getAreaGravesByRow(rowId) {
        return this.get('getAreaGravesByRow', { row_id: rowId });
    },
    
    async saveItem(formData, isUpdate) {
        const action = isUpdate ? 'update' : 'create';
        return this.post(formData + '&action=' + action);
    },
    
    async deleteItem(type, id) {
        return this.post({ action: 'delete', type, id });
    }
};