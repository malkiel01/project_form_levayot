<?php
// dashboard_deceased.php - דשבורד נפטרים בלבד
require_once '../config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// כלול פונקציות עזר
require_once 'dashboard_functions.php';

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// בניית תנאי WHERE לפי הרשאות
$whereClause = "1=1";
$params = [];
if ($userPermissionLevel < 4) {
    $whereClause .= " AND created_by = ?";
    $params[] = $_SESSION['user_id'];
}

// סטטיסטיקות נפטרים
$stats = [];

// סה"כ נפטרים
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause");
$stmt->execute($params);
$stats['total'] = $stmt->fetchColumn();

// נפטרים שהושלמו
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND status = 'completed'");
$stmt->execute($params);
$stats['completed'] = $stmt->fetchColumn();

// בתהליך
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND status = 'in_progress'");
$stmt->execute($params);
$stats['in_progress'] = $stmt->fetchColumn();

// טיוטות
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND status = 'draft'");
$stmt->execute($params);
$stats['draft'] = $stmt->fetchColumn();

// נפטרים היום
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND DATE(created_at) = CURDATE()");
$stmt->execute($params);
$stats['today'] = $stmt->fetchColumn();

// קבורות היום
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND DATE(burial_date) = CURDATE()");
$stmt->execute($params);
$stats['today_burials'] = $stmt->fetchColumn();

// השבוע
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND YEARWEEK(created_at) = YEARWEEK(NOW())");
$stmt->execute($params);
$stats['this_week'] = $stmt->fetchColumn();

// החודש
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$stmt->execute($params);
$stats['this_month'] = $stmt->fetchColumn();

// סטטיסטיקות לפי בית עלמין
$cemeteryStatsQuery = "
    SELECT c.name, c.id, COUNT(df.id) as count 
    FROM cemeteries c
    LEFT JOIN deceased_forms df ON c.id = df.cemetery_id";
if ($userPermissionLevel < 4) {
    $cemeteryStatsQuery .= " AND df.created_by = ?";
}
$cemeteryStatsQuery .= " GROUP BY c.id ORDER BY count DESC";

$stmt = $db->prepare($cemeteryStatsQuery);
if ($userPermissionLevel < 4) {
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt->execute();
}
$cemeteryStats = $stmt->fetchAll();

// נתוני גרף חודשי - 6 חודשים אחרונים
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM deceased_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $params_with_date = array_merge($params, [$date]);
    $stmt->execute($params_with_date);
    $monthlyData[] = [
        'month' => date('m/Y', strtotime("-$i months")),
        'count' => $stmt->fetchColumn()
    ];
}

// 10 נפטרים אחרונים
$recentQuery = "
    SELECT df.*, c.name as cemetery_name 
    FROM deceased_forms df
    LEFT JOIN cemeteries c ON df.cemetery_id = c.id
    WHERE $whereClause
    ORDER BY df.created_at DESC
    LIMIT 10
";
$stmt = $db->prepare($recentQuery);
$stmt->execute($params);
$recentDeceased = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד נפטרים - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-styles-optimized.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-cross"></i> דשבורד נפטרים</h1>
            <p>ניהול וסטטיסטיקות טפסי נפטרים</p>
        </div>

        <!-- בחירת תצוגה -->
        <div class="text-center mb-4">
            <div class="btn-group btn-group-sm" role="group">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-th"></i> <span class="d-none d-sm-inline">תצוגה</span> משולבת
                </a>
                <a href="dashboard_deceased.php" class="btn btn-primary active">
                    <i class="fas fa-cross"></i> נפטרים<span class="d-none d-sm-inline"> בלבד</span>
                </a>
                <a href="dashboard_purchases.php" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-cart"></i> רכישות<span class="d-none d-sm-inline"> בלבד</span>
                </a>
            </div>
        </div>

        <!-- כפתורי פעולה מהירה -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="../form/form.php" class="btn btn-success action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-plus-circle"></i> הוספת נפטר חדש
                    </a>
                    <a href="../form/<?= FORM_DECEASED_URL ?>" class="btn btn-primary action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-calendar-plus"></i> הוספת לוויה
                    </a>
                    <a href="../search.php?type=deceased" class="btn btn-outline-primary action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-search"></i> חיפוש נפטר
                    </a>
                    <a href="../reports/daily_deceased.php" class="btn btn-outline-secondary action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-file-pdf"></i> דוח יומי
                    </a>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות ראשיות -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-users"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['total']) ?></h2>
                        <p class="stat-label">סה"כ נפטרים</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['completed']) ?></h2>
                        <p class="stat-label">טפסים שהושלמו</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card warning-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['in_progress']) ?></h2>
                        <p class="stat-label">בתהליך טיפול</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['today']) ?></h2>
                        <p class="stat-label">נרשמו היום</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות משניות -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card purple-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-praying-hands"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['today_burials']) ?></h2>
                        <p class="stat-label">קבורות היום</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card danger-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['draft']) ?></h2>
                        <p class="stat-label">טיוטות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['this_week']) ?></h2>
                        <p class="stat-label">השבוע</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['this_month']) ?></h2>
                        <p class="stat-label">החודש</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- גרפים -->
        <div class="row mb-4">
            <!-- גרף מגמה חודשית -->
            <div class="col-lg-8 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">מגמת רישום נפטרים - 6 חודשים אחרונים</h3>
                    </div>
                    <div class="chart-placeholder" id="monthlyChartPlaceholder" style="height: 250px;">
                        <p class="text-muted text-center py-5">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="monthlyChart" style="display: none; max-height: 250px;"></canvas>
                </div>
            </div>
            
            <!-- התפלגות בתי עלמין -->
            <div class="col-lg-4 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">התפלגות לפי בתי עלמין</h3>
                    </div>
                    <div class="chart-placeholder" id="cemeteryChartPlaceholder" style="height: 250px;">
                        <p class="text-muted text-center py-5">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="cemeteryChart" style="display: none; max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- נפטרים אחרונים -->
        <div class="recent-table">
            <div class="p-3">
                <h3 class="mb-0">נפטרים אחרונים</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell">מספר טופס</th>
                            <th>שם הנפטר</th>
                            <th class="d-none d-sm-table-cell">בית עלמין</th>
                            <th>תאריך פטירה</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDeceased as $deceased): ?>
                        <tr>
                            <td class="d-none d-md-table-cell text-truncate" style="max-width: 100px;">
                                <?= htmlspecialchars($deceased['form_uuid']) ?>
                            </td>
                            <td class="text-truncate" style="max-width: 150px;">
                                <?= htmlspecialchars($deceased['deceased_name']) ?>
                            </td>
                            <td class="d-none d-sm-table-cell">
                                <?= htmlspecialchars($deceased['cemetery_name'] ?? 'לא צוין') ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($deceased['death_date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $deceased['status'] ?>">
                                    <?= translateStatus($deceased['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="../form/form.php?id=<?= $deceased['form_uuid'] ?>" 
                                   class="btn btn-sm action-btn btn-primary-gradient">
                                    <i class="fas fa-eye"></i> <span class="d-none d-sm-inline">צפייה</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- טעינת Chart.js אחרי שהדף נטען -->
    <script>
    // נתוני גרפים
    const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
    const monthlyData = <?= json_encode(array_column($monthlyData, 'count')) ?>;
    const cemeteryLabels = <?= json_encode(array_column($cemeteryStats, 'name')) ?>;
    const cemeteryData = <?= json_encode(array_column($cemeteryStats, 'count')) ?>;

    // טעינת Chart.js אחרי שהדף מוכן
    window.addEventListener('load', function() {
        // הוסף את האנימציות רק אחרי טעינת הדף
        document.body.classList.add('animations-ready');
        
        // טען את Chart.js
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = initCharts;
        document.head.appendChild(script);
    });

    function initCharts() {
        // הסתר placeholders והצג canvas
        document.getElementById('monthlyChartPlaceholder').style.display = 'none';
        document.getElementById('cemeteryChartPlaceholder').style.display = 'none';
        document.getElementById('monthlyChart').style.display = 'block';
        document.getElementById('cemeteryChart').style.display = 'block';

        // גרף מגמה חודשית
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'נפטרים',
                    data: monthlyData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000
                },
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

        // גרף בתי עלמין
        const cemeteryCtx = document.getElementById('cemeteryChart').getContext('2d');
        new Chart(cemeteryCtx, {
            type: 'doughnut',
            data: {
                labels: cemeteryLabels,
                datasets: [{
                    data: cemeteryData,
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#84fab0',
                        '#8fd3f4',
                        '#fa709a',
                        '#fee140'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000
                },
                plugins: {
                    legend: {
                        display: window.innerWidth > 768,
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
    </script>
</body>
</html>