<?php
// dashboard_deceased.php - דשבורד נפטרים בלבד
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
    <link href="../css/dashboard-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require_once 'includes/nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-cross"></i> דשבורד נפטרים</h1>
            <p>ניהול וסטטיסטיקות טפסי נפטרים</p>
        </div>

        <!-- סטטיסטיקות ראשיות -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['total']) ?></h2>
                        <p class="stat-label">סה"כ נפטרים</p>
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
                        <p class="stat-label">טפסים שהושלמו</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card warning-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['in_progress']) ?></h2>
                        <p class="stat-label">בתהליך טיפול</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon">
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
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card purple-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-praying-hands"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['today_burials']) ?></h2>
                        <p class="stat-label">קבורות היום</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card danger-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['draft']) ?></h2>
                        <p class="stat-label">טיוטות</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card primary-card">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['this_week']) ?></h2>
                        <p class="stat-label">השבוע</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon">
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
            <div class="col-lg-8 mb-3">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">מגמת רישום נפטרים - 6 חודשים אחרונים</h3>
                    </div>
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
            
            <!-- התפלגות בתי עלמין -->
            <div class="col-lg-4 mb-3">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">התפלגות לפי בתי עלמין</h3>
                    </div>
                    <canvas id="cemeteryChart" height="200"></canvas>
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
                            <th>מספר טופס</th>
                            <th>שם הנפטר</th>
                            <th>בית עלמין</th>
                            <th>תאריך פטירה</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDeceased as $deceased): ?>
                        <tr>
                            <td><?= htmlspecialchars($deceased['form_uuid']) ?></td>
                            <td><?= htmlspecialchars($deceased['deceased_name']) ?></td>
                            <td><?= htmlspecialchars($deceased['cemetery_name'] ?? 'לא צוין') ?></td>
                            <td><?= date('d/m/Y', strtotime($deceased['death_date'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $deceased['status'] ?>">
                                    <?= translateStatus($deceased['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="form/form.php?id=<?= $deceased['form_uuid'] ?>" 
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

        <!-- כפתור הוספת נפטר -->
        <div class="text-center mt-4">
            <a href="form/form.php" class="btn btn-lg action-btn btn-success-gradient">
                <i class="fas fa-plus-circle"></i> הוספת נפטר חדש
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // גרף מגמה חודשית
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthlyData, 'month')) ?>,
            datasets: [{
                label: 'נפטרים',
                data: <?= json_encode(array_column($monthlyData, 'count')) ?>,
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
            labels: <?= json_encode(array_column($cemeteryStats, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($cemeteryStats, 'count')) ?>,
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
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });

    // פונקציית תרגום סטטוס
    function translateStatus(status) {
        const translations = {
            'draft': 'טיוטה',
            'in_progress': 'בתהליך',
            'completed': 'הושלם',
            'archived': 'בארכיון'
        };
        return translations[status] || status;
    }
    </script>
</body>
</html>