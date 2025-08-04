<?php
// dashboard_purchases.php - דשבורד רכישות בלבד
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

// סטטיסטיקות רכישות
$stats = [];

// סה"כ רכישות
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['total'] = $stmt->fetchColumn();

// רכישות שהושלמו
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND status = 'completed'");
$stmt->execute($params);
$stats['completed'] = $stmt->fetchColumn();

// בתהליך
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND status = 'in_progress'");
$stmt->execute($params);
$stats['in_progress'] = $stmt->fetchColumn();

// טיוטות
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND status = 'draft'");
$stmt->execute($params);
$stats['draft'] = $stmt->fetchColumn();

// רכישות היום
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND DATE(created_at) = CURDATE()");
$stmt->execute($params);
$stats['today'] = $stmt->fetchColumn();

// השבוע
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND YEARWEEK(purchase_date) = YEARWEEK(NOW())");
$stmt->execute($params);
$stats['this_week'] = $stmt->fetchColumn();

// החודש
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND MONTH(purchase_date) = MONTH(NOW()) AND YEAR(purchase_date) = YEAR(NOW())");
$stmt->execute($params);
$stats['this_month'] = $stmt->fetchColumn();

// סכומים כספיים
$stmt = $db->prepare("SELECT SUM(total_amount) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['total_amount'] = $stmt->fetchColumn() ?? 0;

$stmt = $db->prepare("SELECT SUM(paid_amount) FROM purchase_forms WHERE $whereClause");
$stmt->execute($params);
$stats['paid_amount'] = $stmt->fetchColumn() ?? 0;

$stats['pending_amount'] = $stats['total_amount'] - $stats['paid_amount'];

// סטטיסטיקות לפי סוג רכישה
$purchaseTypeStats = [];
$types = ['grave' => 'קבר', 'plot' => 'חלקה', 'structure' => 'מבנה', 'service' => 'שירות'];
foreach ($types as $key => $label) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND purchase_type = ?");
    $params_with_type = array_merge($params, [$key]);
    $stmt->execute($params_with_type);
    $purchaseTypeStats[] = [
        'type' => $label,
        'count' => $stmt->fetchColumn()
    ];
}

// סטטיסטיקות לפי אמצעי תשלום
$paymentMethodStats = [];
$methods = [
    'cash' => 'מזומן',
    'check' => 'צ\'ק',
    'credit' => 'אשראי',
    'transfer' => 'העברה',
    'installments' => 'תשלומים'
];
foreach ($methods as $key => $label) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE $whereClause AND payment_method = ?");
    $params_with_method = array_merge($params, [$key]);
    $stmt->execute($params_with_method);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $paymentMethodStats[] = [
            'method' => $label,
            'count' => $count
        ];
    }
}

// נתוני גרף חודשי - 6 חודשים אחרונים
$monthlyData = [];
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    
    // מספר רכישות
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM purchase_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(purchase_date, '%Y-%m') = ?
    ");
    $params_with_date = array_merge($params, [$date]);
    $stmt->execute($params_with_date);
    $count = $stmt->fetchColumn();
    
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
        'count' => $count,
        'revenue' => $revenue
    ];
}

// 10 רכישות אחרונות
$recentQuery = "
    SELECT pf.*, c.name as cemetery_name 
    FROM purchase_forms pf
    LEFT JOIN cemeteries c ON pf.cemetery_id = c.id
    WHERE $whereClause
    ORDER BY pf.created_at DESC
    LIMIT 10
";
$stmt = $db->prepare($recentQuery);
$stmt->execute($params);
$recentPurchases = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד רכישות - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-styles-optimized.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <h1><i class="fas fa-shopping-cart"></i> דשבורד רכישות</h1>
            <p>ניהול וסטטיסטיקות רכישות חלקות ושירותים</p>
        </div>

        <!-- בחירת תצוגה -->
        <div class="text-center mb-4">
            <div class="btn-group btn-group-sm" role="group">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-th"></i> <span class="d-none d-sm-inline">תצוגה</span> משולבת
                </a>
                <a href="dashboard_deceased.php" class="btn btn-outline-primary">
                    <i class="fas fa-cross"></i> נפטרים<span class="d-none d-sm-inline"> בלבד</span>
                </a>
                <a href="dashboard_purchases.php" class="btn btn-primary active">
                    <i class="fas fa-shopping-cart"></i> רכישות<span class="d-none d-sm-inline"> בלבד</span>
                </a>
            </div>
        </div>

        <!-- כפתורי פעולה מהירה -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="../form/purchase_form.php" class="btn btn-success action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-plus-circle"></i> רכישה חדשה
                    </a>
                    <a href="../search.php?type=purchase" class="btn btn-outline-success action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-search"></i> חיפוש רכישה
                    </a>
                    <a href="../reports/purchase_report.php" class="btn btn-outline-secondary action-btn flex-fill flex-sm-grow-0">
                        <i class="fas fa-file-excel"></i> ייצוא לאקסל
                    </a>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות כספיות -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-shekel-sign"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['total_amount']) ?></h2>
                        <p class="stat-label">סך כל העסקאות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['paid_amount']) ?></h2>
                        <p class="stat-label">סה"כ שולם</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card warning-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['pending_amount']) ?></h2>
                        <p class="stat-label">יתרה לתשלום</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['total']) ?></h2>
                        <p class="stat-label">סה"כ רכישות</p>
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
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['today']) ?></h2>
                        <p class="stat-label">רכישות היום</p>
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
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['completed']) ?></h2>
                        <p class="stat-label">הושלמו</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- גרפים -->
        <div class="row mb-4">
            <!-- גרף רכישות והכנסות -->
            <div class="col-lg-8 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">מגמת רכישות והכנסות - 6 חודשים אחרונים</h3>
                        <div class="chart-options">
                            <button class="chart-option active" data-chart="combined">משולב</button>
                            <button class="chart-option" data-chart="count">רכישות</button>
                            <button class="chart-option" data-chart="revenue">הכנסות</button>
                        </div>
                    </div>
                    <div class="chart-placeholder" id="monthlyChartPlaceholder" style="height: 250px;">
                        <p class="text-muted text-center py-5">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="monthlyChart" style="display: none; max-height: 250px;"></canvas>
                </div>
            </div>
            
            <!-- התפלגות לפי סוג רכישה -->
            <div class="col-lg-4 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">התפלגות לפי סוג רכישה</h3>
                    </div>
                    <div class="chart-placeholder" id="typeChartPlaceholder" style="height: 250px;">
                        <p class="text-muted text-center py-5">הגרף יטען בעוד רגע...</p>
                    </div>
                    <canvas id="typeChart" style="display: none; max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- רכישות אחרונות -->
        <div class="recent-table">
            <div class="p-3">
                <h3 class="mb-0">רכישות אחרונות</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell">מספר טופס</th>
                            <th>שם הרוכש</th>
                            <th class="d-none d-sm-table-cell">סוג רכישה</th>
                            <th>סכום</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPurchases as $purchase): ?>
                        <tr>
                            <td class="d-none d-md-table-cell text-truncate" style="max-width: 100px;">
                                <?= htmlspecialchars($purchase['form_uuid']) ?>
                            </td>
                            <td class="text-truncate" style="max-width: 150px;">
                                <?= htmlspecialchars($purchase['buyer_name']) ?>
                            </td>
                            <td class="d-none d-sm-table-cell">
                                <?= translatePurchaseType($purchase['purchase_type']) ?>
                            </td>
                            <td>₪<?= number_format($purchase['total_amount']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $purchase['status'] ?>">
                                    <?= translateStatus($purchase['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="../form/purchase_form.php?id=<?= $purchase['form_uuid'] ?>" 
                                   class="btn btn-sm action-btn btn-success-gradient">
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
    const monthlyCount = <?= json_encode(array_column($monthlyData, 'count')) ?>;
    const monthlyRevenue = <?= json_encode(array_column($monthlyData, 'revenue')) ?>;
    const purchaseTypeLabels = <?= json_encode(array_column($purchaseTypeStats, 'type')) ?>;
    const purchaseTypeData = <?= json_encode(array_column($purchaseTypeStats, 'count')) ?>;

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
        document.getElementById('typeChartPlaceholder').style.display = 'none';
        document.getElementById('monthlyChart').style.display = 'block';
        document.getElementById('typeChart').style.display = 'block';

        // גרף משולב - ברירת מחדל
        let monthlyChart = createCombinedChart();

        // גרף סוגי רכישה
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: purchaseTypeLabels,
                datasets: [{
                    data: purchaseTypeData,
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

        // החלפת תצוגת גרף
        document.querySelectorAll('.chart-option').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-option').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const chartType = this.dataset.chart;
                monthlyChart.destroy();
                
                if (chartType === 'count') {
                    monthlyChart = createCountChart();
                } else if (chartType === 'revenue') {
                    monthlyChart = createRevenueChart();
                } else {
                    monthlyChart = createCombinedChart();
                }
            });
        });

        function createCombinedChart() {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            return new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'מספר רכישות',
                        data: monthlyCount,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        yAxisID: 'y-count'
                    }, {
                        label: 'הכנסות (₪)',
                        data: monthlyRevenue,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        yAxisID: 'y-revenue'
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
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    scales: {
                        'y-count': {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        'y-revenue': {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₪' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        function createCountChart() {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            return new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'מספר רכישות',
                        data: monthlyCount,
                        backgroundColor: '#667eea',
                        borderRadius: 5
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
        }

        function createRevenueChart() {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            return new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'הכנסות (₪)',
                        data: monthlyRevenue,
                        backgroundColor: '#28a745',
                        borderRadius: 5
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
                                callback: function(value) {
                                    return '₪' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // פונקציות תרגום
    function translateStatus(status) {
        const translations = {
            'draft': 'טיוטה',
            'in_progress': 'בתהליך',
            'completed': 'הושלם',
            'archived': 'בארכיון'
        };
        return translations[status] || status;
    }

    function translatePurchaseType(type) {
        const translations = {
            'grave': 'קבר',
            'plot': 'חלקה',
            'structure': 'מבנה',
            'service': 'שירות'
        };
        return translations[type] || type;
    }
    </script>
</body>
</html>