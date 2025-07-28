<?php
// dashboard.php - דשבורד ראשי
require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// קבלת סטטיסטיקות כלליות
$stats = [];

// סטטיסטיקות בסיסיות לכולם
$stats['total_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms")->fetchColumn();
$stats['completed_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'completed'")->fetchColumn();
$stats['in_progress_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'in_progress'")->fetchColumn();
$stats['draft_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'draft'")->fetchColumn();

// סטטיסטיקות של היום
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
    // כמות משתמשים פעילים
    $stats['active_users'] = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    
    // התפלגות לפי בתי עלמין
    $cemeteryStats = $db->query("
        SELECT c.name, COUNT(df.id) as count 
        FROM cemeteries c
        LEFT JOIN deceased_forms df ON c.id = df.cemetery_id
        GROUP BY c.id
        ORDER BY count DESC
        LIMIT 5
    ")->fetchAll();
    
    // פעילות לפי חודש
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
    <title>דשבורד - מערכת ניהול נפטרים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home"></i> מערכת ניהול נפטרים
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= FORM_URL ?>">
                            <i class="fas fa-plus"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                    <?php if ($userPermissionLevel >= 4): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> ניהול
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="admin/users.php">משתמשים</a></li>
                            <li><a class="dropdown-item" href="admin/cemeteries.php">בתי עלמין</a></li>
                            <li><a class="dropdown-item" href="admin/permissions.php">הרשאות</a></li>
                            <li><a class="dropdown-item" href="admin/reports.php">דוחות</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                            <i class="fas fa-bell"></i>
                            <?php if (count($userNotifications) > 0): ?>
                            <span class="notification-badge"><?= count($userNotifications) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
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

    <div class="container-fluid mt-4">
        <h2 class="mb-4">דשבורד</h2>

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

        <div class="row">
            <!-- טפסים אחרונים -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> טפסים אחרונים</h5>
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
                                            <a href="<?= FORM_URL ?>?id=<?= $form['form_uuid'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view_form.php?id=<?= $form['form_uuid'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="forms_list.php" class="btn btn-primary">
                                ראה את כל הטפסים <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- פאנל צדדי -->
            <div class="col-lg-4">
                <!-- קיצורי דרך -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-rocket"></i> קיצורי דרך</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?= FORM_URL ?>" class="btn btn-success">
                                <i class="fas fa-plus"></i> יצירת טופס חדש
                            </a>
                            <a href="search.php" class="btn btn-primary">
                                <i class="fas fa-search"></i> חיפוש מתקדם
                            </a>
                            <?php if ($userPermissionLevel >= 4): ?>
                            <a href="export.php" class="btn btn-info">
                                <i class="fas fa-download"></i> ייצוא נתונים
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($userPermissionLevel >= 4): ?>
                <!-- גרף עוגה - התפלגות לפי בתי עלמין -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> התפלגות לפי בתי עלמין</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="cemeteryChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($userPermissionLevel >= 4): ?>
        <!-- גרף קווים - פעילות חודשית -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> פעילות חודשית</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- מודל התראות -->
    <div class="modal fade" id="notificationsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">התראות</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (count($userNotifications) > 0): ?>
                        <?php foreach ($userNotifications as $notification): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($notification['message']) ?>
                            <small class="d-block text-muted mt-1">
                                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                            </small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="markAsRead(<?= $notification['id'] ?>)"></button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">אין התראות חדשות</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <?php if ($userPermissionLevel >= 4 && isset($cemeteryStats)): ?>
    <script>
        // גרף עוגה - בתי עלמין
        const cemeteryCtx = document.getElementById('cemeteryChart').getContext('2d');
        const cemeteryChart = new Chart(cemeteryCtx, {
            type: 'pie',
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
                        position: 'bottom',
                    }
                }
            }
        });

        // גרף קווים - פעילות חודשית
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyLabels = <?= json_encode(array_map(function($stat) {
            $months = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
            return $months[$stat['month'] - 1] . ' ' . $stat['year'];
        }, array_reverse($monthlyStats))) ?>;
        
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'מספר טפסים',
                    data: <?= json_encode(array_column(array_reverse($monthlyStats), 'count')) ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

    <script>
        // סימון התראה כנקראה
        function markAsRead(notificationId) {
            $.post('ajax/mark_notification_read.php', {
                notification_id: notificationId,
                csrf_token: '<?= $_SESSION['csrf_token'] ?>'
            });
        }

        // רענון אוטומטי של הדשבורד כל 60 שניות
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>