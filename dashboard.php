<?php
// dashboard.php - דשבורד ראשי עם תיקון חיבור למסד נתונים

require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// תיקון - הוספת חיבור למסד נתונים
$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// קבלת סוגי הטפסים הפעילים
$formTypes = $db->query("
    SELECT * FROM form_types WHERE is_active = 1 ORDER BY id
")->fetchAll();

// סטטיסטיקות לפי סוג טופס
$statsByType = [];
foreach ($formTypes as $type) {
    $stats = [];
    $tableName = $type['table_name'];
    
    // בניית תנאי WHERE לפי הרשאות
    $whereClause = "1=1";
    $params = [];
    if ($userPermissionLevel < 4) {
        $whereClause .= " AND created_by = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    // סטטיסטיקות בסיסיות
    $stmt = $db->prepare("SELECT COUNT(*) FROM $tableName WHERE $whereClause");
    $stmt->execute($params);
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM $tableName WHERE $whereClause AND status = 'completed'");
    $stmt->execute($params);
    $stats['completed'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM $tableName WHERE $whereClause AND status = 'in_progress'");
    $stmt->execute($params);
    $stats['in_progress'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM $tableName WHERE $whereClause AND status = 'draft'");
    $stmt->execute($params);
    $stats['draft'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM $tableName WHERE $whereClause AND DATE(created_at) = CURDATE()");
    $stmt->execute($params);
    $stats['today'] = $stmt->fetchColumn();
    
    $statsByType[$type['type_key']] = $stats;
}

// סטטיסטיקות כלליות - תואם לקובץ המקורי
$stats = [];
$stats['total_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms")->fetchColumn();
$stats['completed_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'completed'")->fetchColumn();
$stats['in_progress_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'in_progress'")->fetchColumn();
$stats['draft_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'draft'")->fetchColumn();
$stats['today_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['today_burials'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE DATE(burial_date) = CURDATE()")->fetchColumn();

// טפסים אחרונים
$recentFormsQuery = "SELECT * FROM deceased_forms ";
if ($userPermissionLevel < 4) {
    $recentFormsQuery .= "WHERE created_by = " . $_SESSION['user_id'] . " ";
}
$recentFormsQuery .= "ORDER BY created_at DESC LIMIT 10";
$recentForms = $db->query($recentFormsQuery)->fetchAll();

// סטטיסטיקות למנהלים בלבד
if ($userPermissionLevel >= 4) {
    $stats['active_users'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    
    $cemeteryStats = $db->query("
        SELECT c.name, COUNT(df.id) as count 
        FROM cemeteries c
        LEFT JOIN deceased_forms df ON c.id = df.cemetery_id
        GROUP BY c.id
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll();
    
    $monthlyStats = $db->query("
        SELECT 
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            COUNT(*) as count
        FROM deceased_forms
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year DESC, month DESC
    ")->fetchAll();
}

// קבלת התראות למשתמש
$notifications = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications->execute([$_SESSION['user_id']]);
$userNotifications = $notifications->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת ניהול טפסים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .content-wrapper {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .form-type-card {
            cursor: pointer;
            border: 2px solid transparent;
        }
        .form-type-card:hover {
            border-color: #007bff;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-home"></i> מערכת ניהול טפסים
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-chart-line"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="formsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-folder"></i> טפסים
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($formTypes as $type): ?>
                            <li>
                                <a class="dropdown-item" href="forms_list.php?type=<?= $type['type_key'] ?>">
                                    <i class="fas <?= $type['icon'] ?>" style="color: <?= $type['color'] ?>"></i>
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="forms_list.php">
                                    <i class="fas fa-list"></i> כל הטפסים
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php if ($userPermissionLevel >= 4): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/users.php">
                            <i class="fas fa-users"></i> ניהול משתמשים
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? 'משתמש') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">הפרופיל שלי</a></li>
                            <li><a class="dropdown-item" href="settings.php">הגדרות</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= LOGOUT_URL ?>">יציאה</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="content-wrapper">
            <h1 class="mb-4">
                <i class="fas fa-chart-line"></i> דשבורד
            </h1>
            
            <!-- כרטיסי סוגי טפסים -->
             <!-- מסכם ראשי -->
            <div class="row mb-4">
                <?php foreach ($formTypes as $type): ?>
                <div class="col-md-6 mb-3">
                    <div class="stat-card form-type-card" onclick="location.href='forms_list.php?type=<?= $type['type_key'] ?>'">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas <?= $type['icon'] ?> fa-3x" style="color: <?= $type['color'] ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h4><?= htmlspecialchars($type['type_name']) ?></h4>
                                <div class="row mt-2">
                                    <div class="col-4 text-center">
                                        <h5><?= number_format($statsByType[$type['type_key']]['total']) ?></h5>
                                        <small class="text-muted">סה"כ</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <h5><?= number_format($statsByType[$type['type_key']]['completed']) ?></h5>
                                        <small class="text-muted">הושלמו</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <h5><?= number_format($statsByType[$type['type_key']]['today']) ?></h5>
                                        <small class="text-muted">היום</small>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="form.php?type=<?= $type['type_key'] ?>" class="btn btn-sm btn-success" onclick="event.stopPropagation()">
                                    <i class="fas fa-plus"></i> חדש
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

                    <!-- כרטיסי סטטיסטיקה -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-primary">
                        <div class="card-body position-relative">
                            <i class="fas fa-file-alt stat-icon"></i>
                            <h5 class="card-title">סה"כ טפסים</h5>
                            <h2 class="mb-0"><?= number_format($stats['total_forms']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-success">
                        <div class="card-body position-relative">
                            <i class="fas fa-check-circle stat-icon"></i>
                            <h5 class="card-title">טפסים שהושלמו</h5>
                            <h2 class="mb-0"><?= number_format($stats['completed_forms']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-warning">
                        <div class="card-body position-relative">
                            <i class="fas fa-hourglass-half stat-icon"></i>
                            <h5 class="card-title">בתהליך</h5>
                            <h2 class="mb-0"><?= number_format($stats['in_progress_forms']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-info">
                        <div class="card-body position-relative">
                            <i class="fas fa-calendar-day stat-icon"></i>
                            <h5 class="card-title">טפסים היום</h5>
                            <h2 class="mb-0"><?= number_format($stats['today_forms']) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- סטטיסטיקות כלליות - טפסי נפטרים -->
            <h3 class="mb-3">סטטיסטיקות טפסי נפטרים</h3>
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                        <h3><?= number_format($stats['total_forms']) ?></h3>
                        <p class="text-muted mb-0">סה"כ טפסים</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h3><?= number_format($stats['completed_forms']) ?></h3>
                        <p class="text-muted mb-0">טפסים שהושלמו</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h3><?= number_format($stats['in_progress_forms']) ?></h3>
                        <p class="text-muted mb-0">טפסים בתהליך</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card text-center">
                        <i class="fas fa-calendar-day fa-2x text-info mb-2"></i>
                        <h3><?= number_format($stats['today_forms']) ?></h3>
                        <p class="text-muted mb-0">טפסים היום</p>
                    </div>
                </div>
            </div>
            
            <!-- טפסים אחרונים -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> טפסי נפטרים אחרונים</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>מס' טופס</th>
                                            <th>שם הנפטר</th>
                                            <th>תאריך פטירה</th>
                                            <th>סטטוס</th>
                                            <th>נוצר ב</th>
                                            <th>פעולות</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentForms)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">אין טפסים להצגה</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentForms as $form): ?>
                                            <tr>
                                                <td><?= substr($form['form_uuid'], 0, 8) ?>...</td>
                                                <td><?= htmlspecialchars($form['deceased_name']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($form['death_date'])) ?></td>
                                                <td>
                                                    <?php
                                                    $statusLabels = [
                                                        'draft' => '<span class="badge bg-secondary">טיוטה</span>',
                                                        'in_progress' => '<span class="badge bg-warning">בתהליך</span>',
                                                        'completed' => '<span class="badge bg-success">הושלם</span>',
                                                        'archived' => '<span class="badge bg-dark">ארכיון</span>'
                                                    ];
                                                    echo $statusLabels[$form['status']] ?? $form['status'];
                                                    ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($form['created_at'])) ?></td>
                                                <td>
                                                    <a href="view_form.php?id=<?= $form['form_uuid'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="form.php?id=<?= $form['form_uuid'] ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!empty($recentForms)): ?>
                            <div class="text-center mt-3">
                                <a href="forms_list.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i> צפה בכל הטפסים
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($userPermissionLevel >= 4 && isset($cemeteryStats)): ?>
            <!-- סטטיסטיקות למנהלים -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> התפלגות לפי בתי עלמין</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="cemeteryChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> מגמה חודשית</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    
    <?php if ($userPermissionLevel >= 4 && isset($cemeteryStats)): ?>
    <script>
    // גרף בתי עלמין
    const cemeteryCtx = document.getElementById('cemeteryChart').getContext('2d');
    new Chart(cemeteryCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($cemeteryStats, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($cemeteryStats, 'count')) ?>,
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            family: 'Arial'
                        }
                    }
                }
            }
        }
    });
    
    // גרף מגמה חודשית
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyData = <?= json_encode($monthlyStats) ?>;
    const monthNames = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
    
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => monthNames[item.month - 1] + ' ' + item.year),
            datasets: [{
                label: 'טפסים',
                data: monthlyData.map(item => item.count),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>