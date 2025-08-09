// js/views/overview.js
if (!window.Views) window.Views = {};

Views.Overview = {
    async load() {
        try {
            const stats = await API.getStats();
            this.render(stats);
        } catch (error) {
            console.error('Error loading overview:', error);
        }
    },
    
    render(stats) {
        const html = `
            <h2>סקירה כללית</h2>
            <div class="row mt-4">
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.cemeteries || 0}</h3>
                        <p>בתי עלמין</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.blocks || 0}</h3>
                        <p>גושים</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.plots || 0}</h3>
                        <p>חלקות</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.rows || 0}</h3>
                        <p>שורות</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.areaGraves || 0}</h3>
                        <p>אחוזות קבר</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <h3>${stats.graves || 0}</h3>
                        <p>קברים</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="hierarchy-card">
                        <h4>קברים פנויים</h4>
                        <h2 class="text-success">${stats.available_graves || 0}</h2>
                    </div>
                </div>
            </div>
        `;
        
        $('#content-area').html(html);
    }
};