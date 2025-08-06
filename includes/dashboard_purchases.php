<?php
// dashboard_purchases.php - דשבורד רכישות בלבד
require_once '../config.php';
require_once 'auth_check.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// בדיקת הרשאה לדשבורד רכישות
checkPageAccess('dashboard_purchases', 1, true);

// כלול פונקציות עזר
require_once 'dashboard_functions.php';

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// בדוק אם המשתמש יכול למחוק טפסים
$canDelete = canDeleteForms($_SESSION['user_id'], 'delete_forms');

// כולם רואים את כל הרשומות
$whereClause = "1=1";
$params = [];

// סטטיסטיקות רכישות
$stats = [
    'purchase' => []
];

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

// נתונים לגרפים - 12 חודשים אחרונים
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    
    // רכישות
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM purchase_forms 
        WHERE $whereClause 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $params_with_date = array_merge($params, [$date]);
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
        'purchases' => $purchase_count,
        'revenue' => $revenue
    ];
}

// פעילות אחרונה - רכישות בלבד
$stmt = $db->prepare("
    SELECT 
        'purchase' as type, 
        pf.form_uuid, 
        pf.buyer_name as name, 
        pf.created_at, 
        pf.status, 
        pf.purchase_date as event_date,
        pf.total_amount,
        pf.created_by,
        u.full_name as creator_name,
        u.username as creator_username
    FROM purchase_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    WHERE $whereClause
    ORDER BY pf.created_at DESC
    LIMIT 10
");
$stmt->execute($params);
$recentActivity = $stmt->fetchAll();

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <h1><i class="fas fa-shopping-cart"></i> דשבורד רכישות</h1>
            <p>מבט כולל על רכישות חלקות ושירותים</p>
        </div>

        <!-- בחירת תצוגה -->
        <div class="view-selector-container">
            <div class="btn-group" role="group">
                <a href="<?= DASHBOARD_FULL_URL ?>" class="btn btn-outline-primary">
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

        <!-- כרטיס רכישות -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="form-type-card" onclick="location.href='../<?= PURCHASE_LIST_URL ?>'">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-center text-md-start mb-3 mb-md-0">
                            <div class="form-type-icon mx-auto mx-md-0" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="fas fa-shopping-cart text-white"></i>
                            </div>
                            <h3 class="mt-3">טפסי רכישות</h3>
                            <p class="text-muted mb-0 d-none d-md-block">רכישת חלקות ושירותים</p>
                        </div>
                        <div class="text-center text-md-start w-100 w-md-auto">
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
                            <a href="../form/index_purchase.php" class="btn btn-success action-btn mt-3 w-100" onclick="event.stopPropagation();">
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
                        <h2 class="stat-value"><?= number_format($stats['purchase']['total']) ?></h2>
                        <p class="stat-label">סה"כ רכישות</p>
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
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h2 class="stat-value">₪<?= number_format($stats['purchase']['paid_amount']) ?></h2>
                        <p class="stat-label">סה"כ שולם</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card info-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['purchase']['today']) ?></h2>
                        <p class="stat-label">רכישות היום</p>
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
                    <div class="mt-3 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>אחוז השלמה</span>
                            <strong><?= round(($stats['purchase']['completed'] / max($stats['purchase']['total'], 1)) * 100) ?>%</strong>
                        </div>
                        <div class="progress mb-4" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= round(($stats['purchase']['completed'] / max($stats['purchase']['total'], 1)) * 100) ?>%">
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <h4>₪<?= number_format($stats['purchase']['total_amount']) ?></h4>
                            <p class="text-muted">סך כל העסקאות</p>
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
                            <th>סוג</th>
                            <th>שם</th>
                            <th class="d-none d-md-table-cell">סכום</th>
                            <th>תאריך</th>
                            <th class="d-none d-sm-table-cell">נוצר ע"י</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td>
                                <i class="fas fa-shopping-cart text-success" title="רכישה"></i>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 150px;">
                                    <strong><?= htmlspecialchars($activity['name'] ?? 'לא ידוע') ?></strong>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <small>₪<?= number_format($activity['total_amount'] ?? 0) ?></small>
                            </td>
                            <td>
                                <small><?= date('d/m/y', strtotime($activity['event_date'])) ?></small>
                            </td>
                            <td class="d-none d-sm-table-cell">
                                <small class="text-muted">
                                    <?= htmlspecialchars($activity['creator_name'] ?? $activity['creator_username'] ?? 'לא ידוע') ?>
                                    <?php if ($activity['created_by'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info ms-1">שלי</span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $activity['status'] ?>">
                                    <?= translateStatus($activity['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="../form/purchase_form.php?uuid=<?= $activity['form_uuid'] ?>&view=1" 
                                       class="btn btn-sm btn-view-gradient" 
                                       title="צפייה">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../form/purchase_form.php?uuid=<?= $activity['form_uuid'] ?>" 
                                       class="btn btn-sm btn-edit-gradient" 
                                       title="עריכה">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($canDelete): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-delete-gradient delete-form-btn" 
                                                data-form-uuid="<?= $activity['form_uuid'] ?>"
                                                data-form-type="purchase"
                                                data-form-name="<?= htmlspecialchars($activity['name']) ?>"
                                                title="מחיקה">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
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
        const purchasesData = <?= json_encode(array_column($monthlyData, 'purchases')) ?>;
        const revenueData = <?= json_encode(array_column($monthlyData, 'revenue')) ?>;

        // טעינת Chart.js אחרי שהדף מוכן
        window.addEventListener('load', function() {
            document.body.classList.add('animations-ready');
            
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = initCharts;
            document.head.appendChild(script);
        });

        function initCharts() {
            document.getElementById('yearlyChartPlaceholder').style.display = 'none';
            document.getElementById('yearlyChart').style.display = 'block';

            // גרף פעילות שנתית
            const yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
            window.yearlyChart = new Chart(yearlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
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
                            display: false
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

        // סקריפט למחיקת טפסים
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-form-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const formUuid = this.dataset.formUuid;
                    const formType = this.dataset.formType;
                    const formName = this.dataset.formName;
                    
                    Swal.fire({
                        title: 'האם אתה בטוח?',
                        html: `האם למחוק את הטופס של <strong>${formName}</strong>?<br>פעולה זו אינה ניתנת לביטול!`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'כן, מחק',
                        cancelButtonText: 'ביטול'
                    }).then((result) => { 
                        if (result.isConfirmed) {
                            fetch('../form/ajax/delete_form.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    form_uuid: formUuid,
                                    form_type: formType,
                                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                                })
                            })
                            .then(response => response.text())
                            .then(text => {
                                try {
                                    const data = JSON.parse(text);
                                    if (data.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'נמחק!',
                                            text: 'הטופס נמחק בהצלחה',
                                            timer: 1500,
                                            showConfirmButton: false
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'שגיאה',
                                            text: data.message || 'אירעה שגיאה במחיקת הטופס'
                                        });
                                    }
                                } catch (e) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'שגיאה',
                                        text: 'תגובה לא תקינה מהשרת'
                                    });
                                }
                            })
                            .catch(error => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'שגיאה',
                                    text: 'אירעה שגיאה בתקשורת עם השרת'
                                });
                            });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>