<?php
// dashboard.php - דשבורד משולב משופר
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

// קבלת סוגי הטפסים הפעילים
$formTypes = $db->query("
    SELECT * FROM form_types WHERE is_active = 1 ORDER BY id
")->fetchAll();

// בניית תנאי WHERE לפי הרשאות
$whereClause = "1=1";
$params = [];
if ($userPermissionLevel < 4) {
    $whereClause .= " AND created_by = ?";
    $params[] = $_SESSION['user_id'];
}

// סטטיסטיקות משולבות
$stats = [
    'deceased' => [],
    'purchase' => [],
    'combined' => []
];

// סטטיסטיקות נפטרים
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause");
$stmt->execute($params);
$stats['deceased']['total'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND status = 'completed'");
$stmt->execute($params);
$stats['deceased']['completed'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND DATE(created_at) = CURDATE()");
$stmt->execute($params);
$stats['deceased']['today'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE $whereClause AND DATE(burial_date) = CURDATE()");
$stmt->execute($params);
$stats['deceased']['today_burials'] = $stmt->fetchColumn();

// סטטיסטיקות רכישות
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['purchase']['total'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND status = 'completed'");
$stmt->execute($params);
$stats['purchase']['completed'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND DATE(created_at) = CURDATE()");
$stmt->execute($params);
$stats['purchase']['today'] = $stmt->fetchColumn();

// סכומים כספיים
$stmt = $db->prepare("SELECT SUM(total_amount) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['purchase']['total_amount'] = $stmt->fetchColumn() ?? 0;

$stmt = $db->prepare("SELECT SUM(paid_amount) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['purchase']['paid_amount'] = $stmt->fetchColumn() ?? 0;

// סטטיסטיקות משולבות
$stats['combined']['total_forms'] = $stats['deceased']['total'] + $stats['purchase']['total'];
$stats['combined']['total_completed'] = $stats['deceased']['completed'] + $stats['purchase']['completed'];
$stats['combined']['total_today'] = $stats['deceased']['today'] + $stats['purchase']['today'];

// נתונים לגרפים - 12 חודשים אחרונים
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    
    // נפטרים
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM deceased_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $params_with_date = array_merge($params, [$date]);
    $stmt->execute($params_with_date);
    $deceased_count = $stmt->fetchColumn();
    
    // רכישות
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM purchase_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute($params_with_date);
    $purchase_count = $stmt->fetchColumn();
    
    // הכנסות
    $stmt = $db->prepare("
        SELECT SUM(paid_amount) FROM purchase_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(purchase_date, '%Y-%m') = ?
    ");
    $stmt->execute($params_with_date);
    $revenue = $stmt->fetchColumn() ?? 0;
    
    $monthlyData[] = [
        'month' => date('m/Y', strtotime("-$i months")),
        'deceased' => $deceased_count,
        'purchases' => $purchase_count,
        'revenue' => $revenue
    ];
}

// פעילות אחרונה - משולב
$recentActivity = [];

// נפטרים אחרונים
$stmt = $db->prepare("
    SELECT 'deceased' as type, form_uuid, deceased_name as name, 
           created_at, status, death_date as event_date
    FROM deceased_forms
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute($params);
$recentDeceased = $stmt->fetchAll();

// רכישות אחרונות
$stmt = $db->prepare("
    SELECT 'purchase' as type, form_uuid, buyer_name as name, 
           created_at, status, purchase_date as event_date
    FROM purchase_forms
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute($params);
$recentPurchases = $stmt->fetchAll();

// איחוד ומיון לפי תאריך
$recentActivity = array_merge($recentDeceased, $recentPurchases);
usort($recentActivity, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recentActivity = array_slice($recentActivity, 0, 10);

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד משולב - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-styles-optimized.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-line"></i> דשבורד ראשי</h1>
            <p>מבט כולל על המערכת - נפטרים ורכישות</p>
        </div>

        <!-- בחירת תצוגה -->
        <div class="text-center mb-4">
            <div class="btn-group btn-group-sm" role="group">
                <a href="dashboard.php" class="btn btn-primary active">
                    <i class="fas fa-th"></i> <span class="d-none d-sm-inline">תצוגה</span> משולבת
                </a>
                <a href="dashboard_deceased.php" class="btn btn-outline-primary">
                    <i class="fas fa-cross"></i> נפטרים<span class="d-none d-sm-inline"> בלבד</span>
                </a>
                <a href="dashboard_purchases.php" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-cart"></i> רכישות<span class="d-none d-sm-inline"> בלבד</span>
                </a>
            </div>
        </div>

        <!-- כרטיסי סוגי טפסים -->
        <div class="row mb-4">
            <!-- כרטיס נפטרים -->
            <div class="col-12 col-lg-6 mb-3">
                <div class="form-type-card" onclick="location.href='../forms_list.php?type=deceased'">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-center text-md-start mb-3 mb-md-0">
                            <div class="form-type-icon mx-auto mx-md-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-cross text-white"></i>
                            </div>
                            <h3 class="mt-3">טפסי נפטרים</h3>
                            <p class="text-muted mb-0 d-none d-md-block">ניהול רישום נפטרים וקבורה</p>
                        </div>
                        <div class="text-center text-md-end w-100 w-md-auto">
                            <div class="counter-section">
                                <div class="counter-item">
                                    <span class="counter-value text-primary"><?= number_format($stats['deceased']['total']) ?></span>
                                    <span class="counter-label">סה"כ</span>
                                </div>
                                <div class="counter-item">
                                    <span class="counter-value text-success"><?= number_format($stats['deceased']['completed']) ?></span>
                                    <span class="counter-label">הושלמו</span>
                                </div>
                                <div class="counter-item">
                                    <span class="counter-value text-info"><?= number_format($stats['deceased']['today']) ?></span>
                                    <span class="counter-label">היום</span>
                                </div>
                            </div>
                            <a href="../form/form.php" class="btn btn-primary action-btn mt-3 w-100" onclick="event.stopPropagation();">
                                <i class="fas fa-plus"></i> טופס חדש
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- כרטיס רכישות -->
            <div class="col-12 col-lg-6 mb-3">
                <div class="form-type-card" onclick="location.href='../forms_list.php?type=purchase'">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-center text-md-start mb-3 mb-md-0">
                            <div class="form-type-icon mx-auto mx-md-0" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="fas fa-shopping-cart text-white"></i>
                            </div>
                            <h3 class="mt-3">טפסי רכישות</h3>
                            <p class="text-muted mb-0 d-none d-md-block">רכישת חלקות ושירותים</p>
                        </div>
                        <div class="text-center text-md-end w-100 w-md-auto">
                            <div class="counter-section">
                                <div class="counter-item">
                                    <span class="counter-value text-primary"><?= number_format($stats['purchase']['total']) ?></span>
                                    <span class="counter-label">סה"כ</span>
                                </div>
                                <div class="counter-item">
                                    <span class="counter-value text-success"><?= number_format($stats['purchase']['completed']) ?></span>
                                    <span class="counter-label">הושלמו</span>
                                </div>
                                <div class="counter-item">
                                    <span class="counter-value text-info"><?= number_format($stats['purchase']['today']) ?></span>
                                    <span class="counter-label">היום</span>
                                </div>
                            </div>
                            <a href="../form/purchase_form.php" class="btn btn-success action-btn mt-3 w-100" onclick="event.stopPropagation();">
                                <i class="fas fa-plus"></i> רכישה חדשה
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות ראשיות -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['combined']['total_forms']) ?></h2>
                        <p class="stat-label">סה"כ טפסים</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-shekel-sign"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['purchase']['total_amount']) ?></h2>
                        <p class="stat-label">סך העסקאות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card warning-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-praying-hands"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['deceased']['today_burials']) ?></h2>
                        <p class="stat-label">קבורות היום</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['combined']['total_today']) ?></h2>
                        <p class="stat-label">פעילות היום</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- גרפים -->
        <div class="row mb-4">
            <!-- גרף פעילות שנתית -->
            <div class="col-lg-8 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">פעילות שנתית - 12 חודשים אחרונים</h3>
                        <div class="chart-options">
                            <button class="chart-option active" data-view="all">הכל</button>
                            <button class="chart-option d-none d-sm-inline-block" data-view="deceased">נפטרים</button>
                            <button class="chart-option d-none d-sm-inline-block" data-view="purchases">רכישות</button>
                            <button class="chart-option d-none d-sm-inline-block" data-view="revenue">הכנסות</button>
                        </div>
                    </div>
                    <div class="chart-placeholder" id="yearlyChartPlaceholder" style="height: 250px;">
                        <p class="text-muted text-center py-5">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="yearlyChart" style="display: none; max-height: 250px;"></canvas>
                </div>
            </div>
            
            <!-- סיכום סטטיסטי -->
            <div class="col-lg-4 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">סיכום פעילות</h3>
                    </div>
                    <div class="chart-placeholder" id="summaryChartPlaceholder" style="height: 180px;">
                        <p class="text-muted text-center py-3">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="summaryChart" style="display: none; max-height: 180px;"></canvas>
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>אחוז השלמה כולל</span>
                            <strong><?= round(($stats['combined']['total_completed'] / max($stats['combined']['total_forms'], 1)) * 100) ?>%</strong>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= round(($stats['combined']['total_completed'] / max($stats['combined']['total_forms'], 1)) * 100) ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- פעילות אחרונה -->
        <div class="recent-table">
            <div class="p-3">
                <h3 class="mb-0">פעילות אחרונה במערכת</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell">סוג</th>
                            <th>מספר טופס</th>
                            <th>שם</th>
                            <th class="d-none d-sm-table-cell">תאריך</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <?php if ($activity['type'] === 'deceased'): ?>
                                    <i class="fas fa-cross text-primary"></i> נפטר
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart text-success"></i> רכישה
                                <?php endif; ?>
                            </td>
                            <td class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($activity['form_uuid']) ?></td>
                            <td class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($activity['name']) ?></td>
                            <td class="d-none d-sm-table-cell"><?= date('d/m/Y', strtotime($activity['event_date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $activity['status'] ?>">
                                    <?= translateStatus($activity['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($activity['type'] === 'deceased'): ?>
                                    <a href="../form/form.php?id=<?= $activity['form_uuid'] ?>" 
                                       class="btn btn-sm action-btn btn-primary-gradient">
                                        <i class="fas fa-eye"></i> <span class="d-none d-sm-inline">צפייה</span>
                                    </a>
                                <?php else: ?>
                                    <a href="../form/purchase_form.php?id=<?= $activity['form_uuid'] ?>" 
                                       class="btn btn-sm action-btn btn-success-gradient">
                                        <i class="fas fa-eye"></i> <span class="d-none d-sm-inline">צפייה</span>
                                    </a>
                                <?php endif; ?>
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
    const deceasedData = <?= json_encode(array_column($monthlyData, 'deceased')) ?>;
    const purchasesData = <?= json_encode(array_column($monthlyData, 'purchases')) ?>;
    const revenueData = <?= json_encode(array_column($monthlyData, 'revenue')) ?>;

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
        document.getElementById('yearlyChartPlaceholder').style.display = 'none';
        document.getElementById('summaryChartPlaceholder').style.display = 'none';
        document.getElementById('yearlyChart').style.display = 'block';
        document.getElementById('summaryChart').style.display = 'block';

        // גרף פעילות שנתית
        const yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
        window.yearlyChart = new Chart(yearlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'נפטרים',
                    data: deceasedData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    tension: 0.3
                }, {
                    label: 'רכישות',
                    data: purchasesData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 3,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: window.innerWidth > 768,
                        position: 'top'
                    }
                }
            }
        });

        // גרף סיכום
        const summaryCtx = document.getElementById('summaryChart').getContext('2d');
        new Chart(summaryCtx, {
            type: 'doughnut',
            data: {
                labels: ['נפטרים', 'רכישות'],
                datasets: [{
                    data: [
                        <?= $stats['deceased']['total'] ?>,
                        <?= $stats['purchase']['total'] ?>
                    ],
                    backgroundColor: [
                        '#667eea',
                        '#28a745'
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
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });

        // החלפת תצוגות גרף
        document.querySelectorAll('.chart-option').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-option').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.dataset.view;
                window.yearlyChart.destroy();
                
                let config = {
                    type: 'line',
                    data: {
                        labels: monthlyLabels
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 500
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                };

                switch(view) {
                    case 'deceased':
                        config.type = 'bar';
                        config.data.datasets = [{
                            label: 'נפטרים',
                            data: deceasedData,
                            backgroundColor: '#667eea'
                        }];
                        break;
                    case 'purchases':
                        config.type = 'bar';
                        config.data.datasets = [{
                            label: 'רכישות',
                            data: purchasesData,
                            backgroundColor: '#28a745'
                        }];
                        break;
                    case 'revenue':
                        config.type = 'area';
                        config.data.datasets = [{
                            label: 'הכנסות (₪)',
                            data: revenueData,
                            backgroundColor: 'rgba(255, 193, 7, 0.2)',
                            borderColor: '#ffc107',
                            borderWidth: 3,
                            fill: true
                        }];
                        config.options.scales.y.ticks = {
                            callback: function(value) {
                                return '₪' + value.toLocaleString();
                            }
                        };
                        break;
                    default:
                        location.reload();
                }
                
                window.yearlyChart = new Chart(yearlyCtx, config);
            });
        });
    }
    </script>
</body>
</html>