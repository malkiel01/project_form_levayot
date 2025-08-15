<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Structure Analyzer - מנתח מבנה מסד נתונים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
        }

        /* Modal Styles */
        #connectionModal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .modal-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-connect {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-connect:hover {
            transform: translateY(-2px);
        }

        .btn-connect:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Main Content */
        #mainContent {
            display: none;
            padding: 20px;
        }

        .container-main {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header-section h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .db-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-right: 4px solid #667eea;
        }

        .info-card h6 {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-card p {
            color: #333;
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-copy {
            background: #28a745;
            color: white;
        }

        .btn-json {
            background: #17a2b8;
            color: white;
        }

        .btn-print {
            background: #ffc107;
            color: #333;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .table-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .table-item:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateX(-5px);
        }

        .table-item h5 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 16px;
        }

        .table-item small {
            color: #666;
        }

        .table-detail {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            display: none;
        }

        .table-detail.active {
            display: block;
        }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            position: relative;
            margin-top: 15px;
        }

        .copy-code-btn {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .alert-box {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-right: 4px solid;
        }

        .alert-danger {
            background: #fee;
            border-color: #dc3545;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }

        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }

        .trigger-box {
            background: #e8f4f8;
            border-right: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .view-box {
            background: #e8f5e9;
            border-right: 4px solid #4caf50;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .relation-diagram {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 32px;
            margin: 0;
        }

        .stat-card p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        @media print {
            body {
                background: white;
            }
            .action-buttons {
                display: none;
            }
            .section-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <!-- Connection Modal -->
    <div id="connectionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🔐 התחברות למסד נתונים</h2>
                <p>הזן את פרטי ההתחברות למסד הנתונים MySQL</p>
            </div>
            <form id="connectionForm">
                <div class="form-group">
                    <label for="host">
                        <i class="fas fa-server"></i> כתובת שרת (Host)
                    </label>
                    <input type="text" id="host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="database">
                        <i class="fas fa-database"></i> שם מסד נתונים
                    </label>
                    <input type="text" id="database" class="form-control" required placeholder="database_name">
                </div>
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> שם משתמש
                    </label>
                    <input type="text" id="username" class="form-control" required placeholder="root">
                </div>
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> סיסמה
                    </label>
                    <input type="password" id="password" class="form-control" placeholder="הכנס סיסמה">
                </div>
                <button type="submit" class="btn-connect" id="connectBtn">
                    <i class="fas fa-plug"></i> התחבר למסד הנתונים
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent">
        <div class="container-main">
            <!-- Header Section -->
            <div class="header-section">
                <h1>
                    <i class="fas fa-database"></i>
                    מנתח מבנה מסד נתונים
                </h1>
                <div class="db-info">
                    <div class="info-card">
                        <h6>מסד נתונים</h6>
                        <p id="dbName">-</p>
                    </div>
                    <div class="info-card">
                        <h6>שרת</h6>
                        <p id="serverName">-</p>
                    </div>
                    <div class="info-card">
                        <h6>סה"כ טבלאות</h6>
                        <p id="totalTables">0</p>
                    </div>
                    <div class="info-card">
                        <h6>סה"כ טריגרים</h6>
                        <p id="totalTriggers">0</p>
                    </div>
                    <div class="info-card">
                        <h6>סה"כ Views</h6>
                        <p id="totalViews">0</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="action-btn btn-copy" onclick="copyAllData()">
                    <i class="fas fa-copy"></i> העתק את כל הניתוח
                </button>
                <button class="action-btn btn-json" onclick="downloadJSON()">
                    <i class="fas fa-download"></i> הורד כ-JSON
                </button>
                <button class="action-btn btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> הדפס דוח
                </button>
            </div>

            <!-- Search Box -->
            <div class="section-card">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="חפש טבלה, עמודה או טריגר..." onkeyup="searchDatabase()">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <!-- Statistics Overview -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-chart-pie"></i> סטטיסטיקות כלליות
                </h3>
                <div class="stats-grid" id="statsGrid">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Tables List -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-table"></i> רשימת טבלאות
                </h3>
                <div class="table-list" id="tablesList">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Table Details -->
            <div class="section-card" id="tableDetailsSection" style="display: none;">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i> פרטי טבלה
                </h3>
                <div id="tableDetails">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Triggers -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-bolt"></i> טריגרים (Triggers)
                </h3>
                <div id="triggersList">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Views -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-eye"></i> תצוגות (Views)
                </h3>
                <div id="viewsList">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Foreign Keys -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-link"></i> קשרים בין טבלאות (Foreign Keys)
                </h3>
                <div id="foreignKeysList">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Procedures & Functions -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-code"></i> פרוצדורות ופונקציות
                </h3>
                <div id="proceduresList">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Issues Summary -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-exclamation-triangle"></i> סיכום בעיות וממצאים
                </h3>
                <div id="issuesSummary">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let databaseData = {
            host: '',
            database: '',
            tables: [],
            triggers: [],
            views: [],
            foreignKeys: [],
            procedures: [],
            functions: [],
            issues: []
        };

        // Connection form handler
        document.getElementById('connectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const connectBtn = document.getElementById('connectBtn');
            connectBtn.innerHTML = '<span class="loading"></span> מתחבר...';
            connectBtn.disabled = true;
            
            // Simulate connection and data loading
            setTimeout(() => {
                loadDatabaseStructure();
            }, 1500);
        });

        // Load database structure (simulation)
        function loadDatabaseStructure() {
            // Get form values
            databaseData.host = document.getElementById('host').value;
            databaseData.database = document.getElementById('database').value;
            
            // Update UI
            document.getElementById('dbName').textContent = databaseData.database;
            document.getElementById('serverName').textContent = databaseData.host;
            
            // Generate sample data for demonstration
            generateSampleData();
            
            // Hide modal and show main content
            document.getElementById('connectionModal').style.display = 'none';
            document.getElementById('mainContent').style.display = 'block';
            
            // Populate all sections
            populateStatistics();
            populateTables();
            populateTriggers();
            populateViews();
            populateForeignKeys();
            populateProcedures();
            populateIssues();
        }

        // Generate sample data
        function generateSampleData() {
            // Sample tables
            databaseData.tables = [
                {
                    name: 'users',
                    columns: 15,
                    rows: 1250,
                    engine: 'InnoDB',
                    collation: 'utf8mb4_general_ci',
                    size: '2.5 MB'
                },
                {
                    name: 'orders',
                    columns: 22,
                    rows: 5430,
                    engine: 'InnoDB',
                    collation: 'utf8mb4_general_ci',
                    size: '8.7 MB'
                },
                {
                    name: 'products',
                    columns: 18,
                    rows: 892,
                    engine: 'InnoDB',
                    collation: 'utf8mb4_general_ci',
                    size: '3.2 MB'
                },
                {
                    name: 'categories',
                    columns: 8,
                    rows: 45,
                    engine: 'InnoDB',
                    collation: 'utf8mb4_general_ci',
                    size: '0.1 MB'
                },
                {
                    name: 'activity_log',
                    columns: 12,
                    rows: 15420,
                    engine: 'InnoDB',
                    collation: 'utf8mb4_general_ci',
                    size: '12.3 MB'
                }
            ];
            
            // Sample triggers
            databaseData.triggers = [
                {
                    name: 'before_user_update',
                    table: 'users',
                    event: 'UPDATE',
                    timing: 'BEFORE',
                    statement: 'BEGIN\n  SET NEW.updated_at = NOW();\nEND'
                },
                {
                    name: 'after_order_insert',
                    table: 'orders',
                    event: 'INSERT',
                    timing: 'AFTER',
                    statement: 'BEGIN\n  INSERT INTO activity_log(action, table_name, record_id)\n  VALUES("INSERT", "orders", NEW.id);\nEND'
                }
            ];
            
            // Sample views
            databaseData.views = [
                {
                    name: 'active_users_view',
                    definition: 'SELECT * FROM users WHERE status = "active"'
                },
                {
                    name: 'order_summary_view',
                    definition: 'SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id = u.id'
                }
            ];
            
            // Sample foreign keys
            databaseData.foreignKeys = [
                {
                    table: 'orders',
                    column: 'user_id',
                    referencedTable: 'users',
                    referencedColumn: 'id',
                    constraintName: 'fk_orders_users'
                },
                {
                    table: 'products',
                    column: 'category_id',
                    referencedTable: 'categories',
                    referencedColumn: 'id',
                    constraintName: 'fk_products_categories'
                }
            ];
            
            // Sample procedures
            databaseData.procedures = [
                {
                    name: 'GetUserOrders',
                    type: 'PROCEDURE',
                    parameters: 'IN user_id INT'
                },
                {
                    name: 'CalculateTotalPrice',
                    type: 'FUNCTION',
                    parameters: 'order_id INT',
                    returns: 'DECIMAL(10,2)'
                }
            ];
            
            // Sample issues
            databaseData.issues = [
                {
                    type: 'warning',
                    message: 'טבלת activity_log מכילה יותר מ-10,000 רשומות - מומלץ לבצע ארכיון',
                    table: 'activity_log'
                },
                {
                    type: 'info',
                    message: 'נמצאו 2 טבלאות ללא Primary Key',
                    tables: ['temp_data', 'logs']
                }
            ];
        }

        // Populate statistics
        function populateStatistics() {
            document.getElementById('totalTables').textContent = databaseData.tables.length;
            document.getElementById('totalTriggers').textContent = databaseData.triggers.length;
            document.getElementById('totalViews').textContent = databaseData.views.length;
            
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <h3>${databaseData.tables.reduce((sum, t) => sum + t.rows, 0).toLocaleString()}</h3>
                    <p>סה"כ רשומות</p>
                </div>
                <div class="stat-card">
                    <h3>${databaseData.foreignKeys.length}</h3>
                    <p>קשרי Foreign Key</p>
                </div>
                <div class="stat-card">
                    <h3>${databaseData.procedures.length}</h3>
                    <p>פרוצדורות</p>
                </div>
                <div class="stat-card">
                    <h3>${calculateTotalSize()}</h3>
                    <p>גודל כולל</p>
                </div>
            `;
        }

        // Calculate total database size
        function calculateTotalSize() {
            let totalMB = 0;
            databaseData.tables.forEach(t => {
                const size = parseFloat(t.size);
                totalMB += size;
            });
            return totalMB.toFixed(1) + ' MB';
        }

        // Populate tables list
        function populateTables() {
            const tablesList = document.getElementById('tablesList');
            tablesList.innerHTML = '';
            
            databaseData.tables.forEach(table => {
                const tableItem = document.createElement('div');
                tableItem.className = 'table-item';
                tableItem.innerHTML = `
                    <h5><i class="fas fa-table"></i> ${table.name}</h5>
                    <small>${table.columns} עמודות | ${table.rows.toLocaleString()} רשומות | ${table.size}</small>
                `;
                tableItem.onclick = () => showTableDetails(table);
                tablesList.appendChild(tableItem);
            });
        }

        // Show table details
        function showTableDetails(table) {
            const detailsSection = document.getElementById('tableDetailsSection');
            const details = document.getElementById('tableDetails');
            
            detailsSection.style.display = 'block';
            details.innerHTML = `
                <h4>${table.name}</h4>
                <div class="info-card">
                    <p>מנוע: ${table.engine}</p>
                    <p>קידוד: ${table.collation}</p>
                    <p>גודל: ${table.size}</p>
                </div>
                <div class="code-block">
                    <button class="copy-code-btn" onclick="copyCode(this)">העתק</button>
                    <pre>CREATE TABLE ${table.name} (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=${table.engine} DEFAULT CHARSET=utf8mb4;</pre>
                </div>
            `;
            
            // Scroll to details
            detailsSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Populate triggers
        function populateTriggers() {
            const triggersList = document.getElementById('triggersList');
            
            if (databaseData.triggers.length === 0) {
                triggersList.innerHTML = '<p>לא נמצאו טריגרים במסד הנתונים</p>';
                return;
            }
            
            triggersList.innerHTML = '';
            databaseData.triggers.forEach(trigger => {
                const triggerBox = document.createElement('div');
                triggerBox.className = 'trigger-box';
                triggerBox.innerHTML = `
                    <h5><strong>${trigger.name}</strong></h5>
                    <p>טבלה: <strong>${trigger.table}</strong> | 
                       אירוע: <strong>${trigger.event}</strong> | 
                       תזמון: <strong>${trigger.timing}</strong></p>
                    <div class="code-block">
                        <pre>${trigger.statement}</pre>
                    </div>
                `;
                triggersList.appendChild(triggerBox);
            });
        }

        // Populate views
        function populateViews() {
            const viewsList = document.getElementById('viewsList');
            
            if (databaseData.views.length === 0) {
                viewsList.innerHTML = '<p>לא נמצאו Views במסד הנתונים</p>';
                return;
            }
            
            viewsList.innerHTML = '';
            databaseData.views.forEach(view => {
                const viewBox = document.createElement('div');
                viewBox.className = 'view-box';
                viewBox.innerHTML = `
                    <h5><strong>${view.name}</strong></h5>
                    <div class="code-block">
                        <pre>${view.definition}</pre>
                    </div>
                `;
                viewsList.appendChild(viewBox);
            });
        }

        // Populate foreign keys
        function populateForeignKeys() {
            const fkList = document.getElementById('foreignKeysList');
            
            if (databaseData.foreignKeys.length === 0) {
                fkList.innerHTML = '<p>לא נמצאו קשרי Foreign Key</p>';
                return;
            }
            
            let html = '<table class="table table-bordered"><thead><tr>';
            html += '<th>טבלה</th><th>עמודה</th><th>מפנה לטבלה</th><th>עמודה</th><th>שם Constraint</th>';
            html += '</tr></thead><tbody>';
            
            databaseData.foreignKeys.forEach(fk => {
                html += `<tr>
                    <td>${fk.table}</td>
                    <td>${fk.column}</td>
                    <td>${fk.referencedTable}</td>
                    <td>${fk.referencedColumn}</td>
                    <td>${fk.constraintName}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            fkList.innerHTML = html;
        }

        // Populate procedures
        function populateProcedures() {
            const procList = document.getElementById('proceduresList');
            
            if (databaseData.procedures.length === 0) {
                procList.innerHTML = '<p>לא נמצאו פרוצדורות או פונקציות</p>';
                return;
            }
            
            let html = '';
            databaseData.procedures.forEach(proc => {
                html += `<div class="info-card">
                    <h5>${proc.type}: ${proc.name}</h5>
                    <p>פרמטרים: ${proc.parameters}</p>
                    ${proc.returns ? `<p>מחזיר: ${proc.returns}</p>` : ''}
                </div>`;
            });
            
            procList.innerHTML = html;
        }

        // Populate issues
        function populateIssues() {
            const issuesList = document.getElementById('issuesSummary');
            
            if (databaseData.issues.length === 0) {
                issuesList.innerHTML = '<div class="alert-success alert-box">✅ לא נמצאו בעיות או אזהרות</div>';
                return;
            }
            
            let html = '';
            databaseData.issues.forEach(issue => {
                const alertClass = issue.type === 'warning' ? 'alert-warning' : 
                                  issue.type === 'danger' ? 'alert-danger' : 'alert-info';
                html += `<div class="${alertClass} alert-box">${issue.message}</div>`;
            });
            
            issuesList.innerHTML = html;
        }

        // Search functionality
        function searchDatabase() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            // Search in tables
            const tableItems = document.querySelectorAll('.table-item');
            tableItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
            
            // Search in other sections
            const allSections = document.querySelectorAll('.trigger-box, .view-box');
            allSections.forEach(section => {
                const text = section.textContent.toLowerCase();
                section.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        }

        // Copy all data to clipboard
        function copyAllData() {
            let content = `=== ניתוח מסד נתונים ===\n`;
            content += `תאריך: ${new Date().toLocaleString('he-IL')}\n`;
            content += `מסד נתונים: ${databaseData.database}\n`;
            content += `שרת: ${databaseData.host}\n\n`;
            
            content += `=== סטטיסטיקות ===\n`;
            content += `טבלאות: ${databaseData.tables.length}\n`;
            content += `טריגרים: ${databaseData.triggers.length}\n`;
            content += `Views: ${databaseData.views.length}\n`;
            content += `Foreign Keys: ${databaseData.foreignKeys.length}\n\n`;
            
            content += `=== טבלאות ===\n`;
            databaseData.tables.forEach(table => {
                content += `${table.name}: ${table.rows} רשומות, ${table.columns} עמודות, ${table.size}\n`;
            });
            
            content += `\n=== טריגרים ===\n`;
            databaseData.triggers.forEach(trigger => {
                content += `${trigger.name} (${trigger.table}): ${trigger.timing} ${trigger.event}\n`;
            });
            
            content += `\n=== Views ===\n`;
            databaseData.views.forEach(view => {
                content += `${view.name}: ${view.definition}\n`;
            });
            
            navigator.clipboard.writeText(content).then(() => {
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> הועתק בהצלחה!';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                }, 2000);
            });
        }

        // Download as JSON
        function downloadJSON() {
            const dataStr = JSON.stringify(databaseData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `database_analysis_${databaseData.database}_${Date.now()}.json`;
            link.click();
        }

        // Copy code snippet
        function copyCode(btn) {
            const code = btn.parentElement.querySelector('pre').textContent;
            navigator.clipboard.writeText(code).then(() => {
                btn.textContent = 'הועתק!';
                setTimeout(() => {
                    btn.textContent = 'העתק';
                }, 2000);
            });
        }
    </script>
</body>
</html>