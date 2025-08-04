<?php
// includes/dashboard_ajax.php - דשבורד עם טעינה מדורגת
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// אם זו בקשת AJAX, תחזיר רק את הנתונים
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    
    $db = getDbConnection();
    $userPermissionLevel = $_SESSION['permission_level'] ?? 1;
    $whereClause = $userPermissionLevel < 4 ? "WHERE created_by = " . $_SESSION['user_id'] : "";
    
    // סטטיסטיקות
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM deceased_forms 
        $whereClause
    ";
    $stats = $db->query($statsQuery)->fetch();
    
    // רשומות אחרונות
    $recentQuery = "
        SELECT form_uuid, deceased_name, death_date, status 
        FROM deceased_forms 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT 5
    ";
    $recentForms = $db->query($recentQuery)->fetchAll();
    
    echo json_encode([
        'stats' => $stats,
        'recent' => $recentForms
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-light.css" rel="stylesheet">
</head>
<body>
    <!-- תפריט -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-home"></i> מערכת קדישא
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../form/form.php">
                            <i class="fas fa-plus"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </span>
                    <a class="btn btn-sm btn-outline-light" href="../auth/logout.php">יציאה</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <h1 class="mb-4">דשבורד ראשי</h1>

        <!-- סטטיסטיקות - יטענו עם AJAX -->
        <div class="row mb-4" id="statsContainer">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">טוען...</span>
                        </div>
                        <p class="stat-label">סה"כ טפסים</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">טוען...</span>
                        </div>
                        <p class="stat-label">הושלמו</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-warning text-white">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">טוען...</span>
                        </div>
                        <p class="stat-label">בתהליך</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-info text-white">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">טוען...</span>
                        </div>
                        <p class="stat-label">היום</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- פעולות מהירות -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">פעולות מהירות</h5>
                        <div class="d-grid gap-2">
                            <a href="../form/form.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> הוספת נפטר
                            </a>
                            <a href="../forms_list.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> כל הטפסים
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- טפסים אחרונים -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">טפסים אחרונים</h5>
                    </div>
                    <div class="card-body">
                        <div id="recentFormsContainer" class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">טוען...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // טעינת נתונים לאחר שהדף נטען
    document.addEventListener('DOMContentLoaded', function() {
        fetch('dashboard_ajax.php?action=get_stats')
            .then(response => response.json())
            .then(data => {
                // עדכון סטטיסטיקות
                updateStatCard(0, data.stats.total || 0);
                updateStatCard(1, data.stats.completed || 0);
                updateStatCard(2, data.stats.in_progress || 0);
                updateStatCard(3, data.stats.today || 0);
                
                // עדכון טבלת טפסים אחרונים
                updateRecentForms(data.recent || []);
            })
            .catch(error => {
                console.error('Error loading data:', error);
            });
    });
    
    function updateStatCard(index, value) {
        const cards = document.querySelectorAll('.stat-card');
        if (cards[index]) {
            const cardContent = cards[index].querySelector('.text-center');
            const label = cardContent.querySelector('.stat-label').textContent;
            cardContent.innerHTML = `
                <p class="stat-value">${value.toLocaleString()}</p>
                <p class="stat-label">${label}</p>
            `;
        }
    }
    
    function updateRecentForms(forms) {
        const container = document.getElementById('recentFormsContainer');
        
        if (forms.length === 0) {
            container.innerHTML = '<p class="text-muted">אין טפסים להצגה</p>';
            return;
        }
        
        let html = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>שם הנפטר</th>
                            <th>תאריך</th>
                            <th>סטטוס</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        forms.forEach(form => {
            const statusBadge = getStatusBadge(form.status);
            const date = new Date(form.death_date).toLocaleDateString('he-IL');
            html += `
                <tr>
                    <td>${form.deceased_name}</td>
                    <td>${date}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <a href="../form/form.php?id=${form.form_uuid}" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }
    
    function getStatusBadge(status) {
        const statusMap = {
            'draft': '<span class="badge bg-secondary">טיוטה</span>',
            'in_progress': '<span class="badge bg-warning">בתהליך</span>',
            'completed': '<span class="badge bg-success">הושלם</span>'
        };
        return statusMap[status] || status;
    }
    </script>
</body>
</html>