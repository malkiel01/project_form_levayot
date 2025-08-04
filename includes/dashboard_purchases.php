<?php
// dashboard_purchases.php - דשבורד רכישות בעיצוב קליל
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* כותרת הדשבורד */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .dashboard-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* כפתורי פעולה עליונים */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            font-size: 0.95rem;
        }

        .btn-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-search {
            background: white;
            color: #667eea;
            border: 2px solid #e2e8f0;
        }

        .btn-search:hover {
            background: #f8f9ff;
            border-color: #667eea;
            color: #667eea;
        }

        .btn-export {
            background: white;
            color: #48bb78;
            border: 2px solid #e2e8f0;
        }

        .btn-export:hover {
            background: #f0fff4;
            border-color: #48bb78;
            color: #48bb78;
        }

        /* כרטיסי סטטיסטיקה */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-left: 1rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        /* צבעי כרטיסים */
        .stat-card.purple .stat-icon {
            background: #f3f4ff;
            color: #667eea;
        }

        .stat-card.green .stat-icon {
            background: #f0fff4;
            color: #48bb78;
        }

        .stat-card.blue .stat-icon {
            background: #ebf8ff;
            color: #4299e1;
        }

        .stat-card.orange .stat-icon {
            background: #fffaf0;
            color: #ed8936;
        }

        .stat-card.red .stat-icon {
            background: #fff5f5;
            color: #f56565;
        }

        .stat-card.yellow .stat-icon {
            background: #fffff0;
            color: #ecc94b;
        }

        .stat-card.teal .stat-icon {
            background: #e6fffa;
            color: #38b2ac;
        }

        .stat-card.pink .stat-icon {
            background: #fff5f7;
            color: #ed64a6;
        }

        /* גרפים */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .chart-options {
            display: flex;
            gap: 0.5rem;
        }

        .chart-option {
            padding: 0.4rem 0.8rem;
            border: 1px solid #e2e8f0;
            background: white;
            color: #718096;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chart-option:hover {
            background: #f7fafc;
        }

        .chart-option.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* טבלה */
        .recent-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .recent-table .table-header {
            background: #f8f9fa;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .recent-table h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #e2e8f0;
            color: #718096;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem;
        }

        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
        }

        /* תגיות סטטוס */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-draft {
            background: #fff5f5;
            color: #e53e3e;
        }

        .status-in_progress {
            background: #fffaf0;
            color: #dd6b20;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
        }

        .status-archived {
            background: #f7fafc;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .action-buttons {
                justify-content: stretch;
            }

            .action-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'nav.php'; ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-shopping-cart"></i> דשבורד רכישות</h1>
            <p>ניהול וסטטיסטיקות רכישות חלקות ושירותים</p>
        </div>

        <!-- כפתורי פעולה -->
        <div class="action-buttons">
            <a href="form/purchase_form.php" class="action-btn btn-primary-gradient">
                <i class="fas fa-plus"></i> רכישה חדשה
            </a>
            <button class="action-btn btn-search" onclick="showSearchModal()">
                <i class="fas fa-search"></i> חיפוש רכישות
            </button>
            <button class="action-btn btn-export" onclick="exportData()">
                <i class="fas fa-file-excel"></i> ייצוא לאקסל
            </button>
        </div>

        <!-- סטטיסטיקות כספיות -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="stat-card green">
                    <div class="stat-content">
                        <div class="stat-value">₪<?= number_format($stats['total_amount']) ?></div>
                        <p class="stat-label">סך כל העסקאות</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-shekel-sign"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-3">
                <div class="stat-card blue">
                    <div class="stat-content">
                        <div class="stat-value">₪<?= number_format($stats['paid_amount']) ?></div>
                        <p class="stat-label">סה"כ שולם</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-3">
                <div class="stat-card orange">
                    <div class="stat-content">
                        <div class="stat-value">₪<?= number_format($stats['pending_amount']) ?></div>
                        <p class="stat-label">יתרה לתשלום</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- סטטיסטיקות כמותיות -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card purple">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['total']) ?></div>
                        <p class="stat-label">סה"כ רכישות</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card green">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['completed']) ?></div>
                        <p class="stat-label">עסקאות הושלמו</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card yellow">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['today']) ?></div>
                        <p class="stat-label">רכישות היום</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card red">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['draft']) ?></div>
                        <p class="stat-label">טיוטות</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- שורה נוספת של סטטיסטיקות -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="stat-card teal">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['this_week']) ?></div>
                        <p class="stat-label">רכישות השבוע</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-3">
                <div class="stat-card pink">
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['this_month']) ?></div>
                        <p class="stat-label">רכישות החודש</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
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
                <div class="chart-container mb-3">
                    <div class="chart-header">
                        <h3 class="chart-title">התפלגות לפי סוג</h3>
                    </div>
                    <canvas id="typeChart" height="200"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">אמצעי תשלום</h3>
                    </div>
                    <canvas id="paymentChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- רכישות אחרונות -->
        <div class="recent-table">
            <div class="table-header">
                <h3>רכישות אחרונות</h3>
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
    </div>

    <!-- מודל חיפוש -->
    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">חיפוש רכישות</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="searchForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">שם הרוכש</label>
                                <input type="text" class="form-control" name="buyer_name" placeholder="הכנס שם...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">מספר טופס</label>
                                <input type="text" class="form-control" name="form_id" placeholder="הכנס מספר...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">סוג רכישה</label>
                                <select class="form-select" name="purchase_type">
                                    <option value="">כל הסוגים</option>
                                    <option value="grave">קבר</option>
                                    <option value="plot">חלקה</option>
                                    <option value="structure">מבנה</option>
                                    <option value="service">שירות</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">סטטוס</label>
                                <select class="form-select" name="status">
                                    <option value="">כל הסטטוסים</option>
                                    <option value="draft">טיוטה</option>
                                    <option value="in_progress">בתהליך</option>
                                    <option value="completed">הושלם</option>
                                    <option value="archived">בארכיון</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">תאריך מ-</label>
                                <input type="date" class="form-control" name="date_from">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">תאריך עד</label>
                                <input type="date" class="form-control" name="date_to">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                    <button type="button" class="btn btn-primary" onclick="performSearch()">חיפוש</button>
                </div>
            </div>
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
                borderColor: '#48bb78',
                backgroundColor: 'rgba(72, 187, 120, 0.1)',
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
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
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
                    },
                    grid: {
                        borderDash: [5, 5]
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
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($purchaseTypeStats, 'type')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($purchaseTypeStats, 'count')) ?>,
                backgroundColor: [
                    '#667eea',
                    '#48bb78',
                    '#ed8936',
                    '#38b2ac'
                ],
                borderWidth: 0
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
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($paymentMethodStats, 'method')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($paymentMethodStats, 'count')) ?>,
                backgroundColor: [
                    '#f56565',
                    '#ecc94b',
                    '#4299e1',
                    '#48bb78',
                    '#ed64a6'
                ],
                borderWidth: 0
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
                            borderRadius: 8
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
                                },
                                grid: {
                                    borderDash: [5, 5]
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
                            backgroundColor: '#48bb78',
                            borderRadius: 8
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
                                    callback: function(value) {
                                        return '₪' + value.toLocaleString();
                                    }
                                },
                                grid: {
                                    borderDash: [5, 5]
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

    // פונקציות
    function showSearchModal() {
        const modal = new bootstrap.Modal(document.getElementById('searchModal'));
        modal.show();
    }

    function performSearch() {
        const form = document.getElementById('searchForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        window.location.href = 'purchases_list.php?' + params.toString();
    }

    function exportData() {
        if (confirm('האם לייצא את כל הרכישות לקובץ אקסל?')) {
            window.location.href = 'export_purchases.php';
        }
    }

    // פונקציות תרגום
    <?php
    function translateStatus($status) {
        $translations = [
            'draft' => 'טיוטה',
            'in_progress' => 'בתהליך',
            'completed' => 'הושלם',
            'archived' => 'בארכיון'
        ];
        return $translations[$status] ?? $status;
    }

    function translatePurchaseType($type) {
        $translations = [
            'grave' => 'קבר',
            'plot' => 'חלקה',
            'structure' => 'מבנה',
            'service' => 'שירות'
        ];
        return $translations[$type] ?? $type;
    }
    ?>
    </script>
</body>
</html>