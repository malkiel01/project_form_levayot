<?php
// dashboard_deceased.php - דשבורד נפטרים בלבד
require_once '../config.php';
require_once 'auth_check.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// בדיקת הרשאה לדשבורד נפטרים
checkPageAccess('dashboard_deceased', 1, true);

// כלול פונקציות עזר
require_once 'dashboard_functions.php';

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// בדוק אם המשתמש יכול למחוק טפסים
$canDelete = canDeleteForms($_SESSION['user_id'], 'delete_forms');

// כולם רואים את כל הרשומות
$whereClause = "1=1";
$params = [];

// סטטיסטיקות נפטרים
$stats = [
    'deceased' => []
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
    
    $monthlyData[] = [
        'month' => date('m/Y', strtotime("-$i months")),
        'deceased' => $deceased_count
    ];
}

// פעילות אחרונה - נפטרים בלבד
$stmt = $db->prepare("
    SELECT 
        'deceased' as type, 
        df.form_uuid, 
        df.deceased_name as name, 
        df.created_at, 
        df.status, 
        df.death_date as event_date,
        c.name as cemetery_name,
        b.name as block_name,
        df.created_by,
        u.full_name as creator_name,
        u.username as creator_username
    FROM deceased_forms df
    LEFT JOIN cemeteries c ON df.cemetery_id = c.id
    LEFT JOIN blocks b ON df.block_id = b.id
    LEFT JOIN users u ON df.created_by = u.id
    WHERE $whereClause
    ORDER BY df.created_at DESC
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
    <title>דשבורד נפטרים - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-styles-optimized.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-cross"></i> דשבורד נפטרים</h1>
            <p>מבט כולל על טפסי נפטרים</p>
        </div>

        <!-- בחירת תצוגה -->
        <div class="view-selector-container">
            <div class="btn-group" role="group">
                <a href="<?= DASHBOARD_FULL_URL ?>" class="btn btn-outline-primary">
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

        <!-- כרטיס נפטרים -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="form-type-card" onclick="location.href='../<?= DECEASED_LIST_URL ?>'">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-center text-md-start mb-3 mb-md-0">
                            <div class="form-type-icon mx-auto mx-md-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-cross text-white"></i>
                            </div>
                            <h3 class="mt-3">טפסי נפטרים</h3>
                            <p class="text-muted mb-0 d-none d-md-block">ניהול רישום נפטרים וקבורה</p>
                        </div>
                        <div class="text-center text-md-start w-100 w-md-auto">
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
                            <a href="../form/index_deceased.php" class="btn btn-primary action-btn mt-3 w-100" onclick="event.stopPropagation();">
                                <i class="fas fa-plus"></i> לוויה חדשה
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
                        <h2 class="stat-value"><?= number_format($stats['deceased']['total']) ?></h2>
                        <p class="stat-label">סה"כ טפסים</p>
                    </div>
                </div>
            </div>
            
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-card success-card">
                    <div class="card-body">
                        <div class="stat-icon d-none d-md-block">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="stat-value"><?= number_format($stats['deceased']['completed']) ?></h2>
                        <p class="stat-label">טפסים שהושלמו</p>
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
                        <h2 class="stat-value"><?= number_format($stats['deceased']['today']) ?></h2>
                        <p class="stat-label">נרשמו היום</p>
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
                        <?php if (!empty($recentActivity)): ?>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="no-results-container">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">אין פעילות אחרונה להצגה</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- גרפים -->
        <div class="row mb-4">
            <!-- גרף פעילות שנתית -->
            <div class="col-lg-8 col-12 mb-3">
                <div class="chart-container" style="max-height: 400px;">
                    <div class="chart-header">
                        <h3 class="chart-title">פעילות שנתית - 12 חודשים אחרונים</h3>
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
                            <strong><?= round(($stats['deceased']['completed'] / max($stats['deceased']['total'], 1)) * 100) ?>%</strong>
                        </div>
                        <div class="progress mb-4" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= round(($stats['deceased']['completed'] / max($stats['deceased']['total'], 1)) * 100) ?>%">
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <h4><?= number_format($stats['deceased']['total']) ?></h4>
                            <p class="text-muted">סך כל הטפסים</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- טעינת Chart.js אחרי שהדף נטען -->
    <script>
        // נתוני גרפים
        const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
        const deceasedData = <?= json_encode(array_column($monthlyData, 'deceased')) ?>;

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
            new Chart(yearlyCtx, {
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