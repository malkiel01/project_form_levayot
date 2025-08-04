<?php
// dashboard.php - דשבורד ראשי משופר
require_once 'config.php';
session_start();

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];
$userPermission = $_SESSION['permission_level'] ?? 'viewer';

// פונקציה לבדיקת הרשאה
function hasPermission($permissionName) {
    global $db, $userId, $userPermission;
    
    // מנהלים תמיד מקבלים הרשאה
    if ($userPermission === 'admin') {
        return true;
    }
    
    // בדיקה בטבלת ההרשאות
    $stmt = $db->prepare("
        SELECT has_permission 
        FROM user_permissions 
        WHERE user_id = ? AND permission_name = ?
    ");
    $stmt->execute([$userId, $permissionName]);
    return (bool)$stmt->fetchColumn();
}

// קבלת סטטיסטיקות נפטרים
$stats = [
    'total_forms' => 0,
    'completed_forms' => 0,
    'in_progress_forms' => 0,
    'today_forms' => 0
];

if (hasPermission('view_deceased')) {
    $statsQuery = $db->prepare("
        SELECT 
            COUNT(*) as total_forms,
            SUM(CASE WHEN form_status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
            SUM(CASE WHEN form_status = 'draft' THEN 1 ELSE 0 END) as in_progress_forms,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms
        FROM deceased_forms
        WHERE deleted_at IS NULL
    ");
    $statsQuery->execute();
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
}

// קבלת סטטיסטיקות רכישות
$purchaseStats = [
    'total_purchases' => 0,
    'completed_purchases' => 0,
    'pending_purchases' => 0,
    'today_purchases' => 0
];

if (hasPermission('view_purchase')) {
    $purchaseQuery = $db->prepare("
        SELECT 
            COUNT(*) as total_purchases,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_purchases,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_purchases,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_purchases
        FROM purchase_forms
        WHERE deleted_at IS NULL
    ");
    $purchaseQuery->execute();
    $purchaseStats = $purchaseQuery->fetch(PDO::FETCH_ASSOC);
}

// קבלת רשימת טפסים אחרונים
$recentForms = [];
if (hasPermission('view_deceased')) {
    $recentQuery = $db->prepare("
        SELECT 
            form_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            death_date,
            form_status,
            created_at
        FROM deceased_forms
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentQuery->execute();
    $recentForms = $recentQuery->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת ניהול בתי עלמין</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: white !important;
        }
        .dashboard-container {
            padding: 20px;
        }
        
        /* עיצוב צבעוני לכרטיסי סטטיסטיקה */
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        .stat-card .card-body {
            position: relative;
            z-index: 1;
        }
        .stat-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            opacity: 0.3;
        }
        .bg-primary { background-color: #3498db !important; }
        .bg-success { background-color: #2ecc71 !important; }
        .bg-warning { background-color: #f39c12 !important; }
        .bg-info { background-color: #00cec9 !important; }
        .bg-danger { background-color: #e74c3c !important; }
        .bg-purple { background-color: #9b59b6 !important; }
        
        .card-title {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        .card h2 {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .action-cards .card {
            transition: transform 0.2s;
            cursor: pointer;
            min-height: 150px;
        }
        .action-cards .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .section-header {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .recent-forms-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-draft {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- ניווט עליון -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= SITE_URL ?>">
                <i class="fas fa-memorial me-2"></i>מערכת ניהול בתי עלמין
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= DASHBOARD_URL ?>">
                            <i class="fas fa-home me-1"></i>דשבורד
                        </a>
                    </li>
                    <?php if (hasPermission('view_deceased')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/forms_list.php?type=deceased">
                            <i class="fas fa-list me-1"></i>טפסי נפטרים
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('view_purchase')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/forms_list.php?type=purchase">
                            <i class="fas fa-shopping-cart me-1"></i>רכישות
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('manage_users')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/admin/users.php">
                            <i class="fas fa-users me-1"></i>משתמשים
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>יציאה
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid dashboard-container">
        <h1 class="mb-4">דשבורד ראשי</h1>

        <?php if (hasPermission('view_deceased')): ?>
            <!-- כרטיסי סטטיסטיקה צבעוניים - טפסי נפטרים -->
            <h3 class="section-header">סטטיסטיקות טפסי נפטרים</h3>
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
        <?php endif; ?>

        <?php if (hasPermission('view_purchase')): ?>
            <!-- כרטיסי סטטיסטיקה צבעוניים - רכישות -->
            <h3 class="section-header">סטטיסטיקות רכישות</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-purple">
                        <div class="card-body position-relative">
                            <i class="fas fa-shopping-cart stat-icon"></i>
                            <h5 class="card-title">סה"כ רכישות</h5>
                            <h2 class="mb-0"><?= number_format($purchaseStats['total_purchases']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-success">
                        <div class="card-body position-relative">
                            <i class="fas fa-check stat-icon"></i>
                            <h5 class="card-title">רכישות שהושלמו</h5>
                            <h2 class="mb-0"><?= number_format($purchaseStats['completed_purchases']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-danger">
                        <div class="card-body position-relative">
                            <i class="fas fa-clock stat-icon"></i>
                            <h5 class="card-title">ממתינות לאישור</h5>
                            <h2 class="mb-0"><?= number_format($purchaseStats['pending_purchases']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-info">
                        <div class="card-body position-relative">
                            <i class="fas fa-calendar-check stat-icon"></i>
                            <h5 class="card-title">רכישות היום</h5>
                            <h2 class="mb-0"><?= number_format($purchaseStats['today_purchases']) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- כרטיסי פעולה -->
        <h3 class="section-header">פעולות מהירות</h3>
        <div class="row action-cards g-3 mb-4">
            <?php if (hasPermission('edit_deceased')): ?>
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='<?= FORM_URL ?>?type=deceased'">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                        <h5>טופס נפטר חדש</h5>
                        <p class="text-muted mb-0">יצירת טופס נפטר חדש</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasPermission('edit_purchase')): ?>
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='<?= FORM_URL ?>?type=purchase'">
                    <div class="card-body">
                        <i class="fas fa-cart-plus fa-3x text-purple mb-3"></i>
                        <h5>רכישה חדשה</h5>
                        <p class="text-muted mb-0">יצירת טופס רכישה חדש</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasPermission('view_reports')): ?>
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='<?= SITE_URL ?>/reports/'">
                    <div class="card-body">
                        <i class="fas fa-chart-bar fa-3x text-success mb-3"></i>
                        <h5>דוחות</h5>
                        <p class="text-muted mb-0">צפייה בדוחות וסטטיסטיקות</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasPermission('manage_cemeteries')): ?>
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='<?= SITE_URL ?>/admin/cemeteries.php'">
                    <div class="card-body">
                        <i class="fas fa-map-marked-alt fa-3x text-warning mb-3"></i>
                        <h5>ניהול בתי עלמין</h5>
                        <p class="text-muted mb-0">ניהול בתי עלמין וחלקות</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (hasPermission('view_deceased') && !empty($recentForms)): ?>
            <!-- טפסים אחרונים -->
            <h3 class="section-header">טפסים אחרונים</h3>
            <div class="recent-forms-table">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>מס' טופס</th>
                            <th>שם הנפטר</th>
                            <th>תאריך פטירה</th>
                            <th>סטטוס</th>
                            <th>תאריך יצירה</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentForms as $form): ?>
                        <tr>
                            <td><?= htmlspecialchars($form['form_id']) ?></td>
                            <td><?= htmlspecialchars($form['full_name']) ?></td>
                            <td><?= $form['death_date'] ? date('d/m/Y', strtotime($form['death_date'])) : '-' ?></td>
                            <td>
                                <span class="status-badge status-<?= $form['form_status'] ?>">
                                    <?= $form['form_status'] == 'completed' ? 'הושלם' : 'טיוטה' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($form['created_at'])) ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/view_form.php?id=<?= $form['form_id'] ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('edit_deceased')): ?>
                                <a href="<?= FORM_URL ?>?type=deceased&id=<?= $form['form_id'] ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .text-purple { color: #9b59b6 !important; }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>