<?php
// dashboard_purchases.php - דשבורד רכישות בלבד
require_once '../config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

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
    <link href="../css/dashboard-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require_once 'includes/nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <h1><i class="fas fa-shopping-cart"></i> דשבורד רכישות</h1>
            <p>ניהול וסטטיסטיקות רכישות חלקות ושירותים</p>
        </div>

        <!-- סטטיסטיקות כספיות -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-shekel-sign"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['total_amount']) ?></h2>
                        <p class="stat-label">סך כל העסקאות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['paid_amount']) ?></h2>
                        <p class="stat-label">סה"כ שולם</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-3">
                <div class="stat-card warning-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['pending_amount']) ?></h2>
                        <p class="stat-label">יתרה לתשלום</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות כמותיות -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['total']) ?></h2>
                        <p class="stat-label">סה"כ רכישות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['completed']) ?></h2>
                        <p class="stat-label">עסקאות הושלמו</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card purple-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['today']) ?></h2>
                        <p class="stat-label">רכישות היום</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card danger-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['this_week']) ?></h2>
                        <p class="stat-label">רכישות השבוע</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- גרפים -->
        <div class="row mb-4">
            <!-- גרף רכישות והכנסות -->
            <div class="col-lg-8 mb-3">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">רכישות והכנסות - 6 חודשים אחרונים</h3>
                        <div class="chart-options">
                            <button class="chart-option active" data-chart="combined">משולב</button>
                            <button class="chart-option" data-chart="count">רכישות</button>
                            <button class="chart-option" data-chart="revenue">הכנסות</button>
                        </div>
                    </div>
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
            
            <!-- התפלגות לפי סוג רכישה -->
            <div class="col-lg-4 mb-3">
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">התפלגות לפי סוג</h3>
                            </div>
                            <canvas id="typeChart" height="200"></canvas>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">אמצעי תשלום</h3>
                            </div>
                            <canvas id="paymentChart" height="200"></canvas>
                        </div>
                    </div>
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
                            <th>מספר טופס</th>
                            <th>שם הרוכש</th>
                            <th>סוג רכישה</th>
                            <th>סכום</th>
                            <th>שולם</th>
                            <th>תאריך</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPurchases as $purchase): ?>
                        <tr>
                            <td><?= htmlspecialchars($purchase['form_uuid']) ?></td>
                            <td><?= htmlspecialchars($purchase['buyer_name']) ?></td>
                            <td><?= translatePurchaseType($purchase['purchase_type']) ?></td>
                            <td>₪<?= number_format($purchase['total_amount']) ?></td>
                            <td>₪<?= number_format($purchase['paid_amount']) ?></td>
                            <td><?= date('d/m/Y', strtotime($purchase['purchase_date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $purchase['status'] ?>">
                                    <?= translateStatus($purchase['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="form/purchase_form.php?id=<?= $purchase['form_uuid'] ?>" 
                                   class="btn btn-sm action-btn btn-primary-gradient">
                                    <i class="fas fa-eye"></i> צפייה
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- כפתור הוספת רכישה -->
        <div class="text-center mt-4">
            <a href="form/purchase_form.php" class="btn btn-lg action-btn btn-success-gradient">
                <i class="fas fa-plus-circle"></i> רכישה חדשה
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // נתוני גרפים
    const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
    const monthlyCount = <?= json_encode(array_column($monthlyData, 'count')) ?>;
    const monthlyRevenue = <?= json_encode(array_column($monthlyData, 'revenue')) ?>;

    // גרף משולב
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    let monthlyChart = new Chart(monthlyCtx, {
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
            interaction: {
                mode: 'index',
                intersect: false,
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

    // גרף סוגי רכישה
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($purchaseTypeStats, 'type')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($purchaseTypeStats, 'count')) ?>,
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#84fab0',
                    '#8fd3f4'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
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

    // גרף אמצעי תשלום
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($paymentMethodStats, 'method')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($paymentMethodStats, 'count')) ?>,
                backgroundColor: [
                    '#fa709a',
                    '#fee140',
                    '#667eea',
                    '#84fab0',
                    '#8fd3f4'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
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
                monthlyChart = new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'מספר רכישות',
                            data: monthlyCount,
                            backgroundColor: '#667eea',
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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
            } else if (chartType === 'revenue') {
                monthlyChart = new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'הכנסות (₪)',
                            data: monthlyRevenue,
                            backgroundColor: '#28a745',
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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
            } else {
                // חזרה לגרף המשולב
                location.reload();
            }
        });
    });

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