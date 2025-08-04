<?php
// dashboard.php - דשבורד מתוקן
require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];
$userPermission = $_SESSION['permission_level'] ?? 'viewer';

// קבלת סטטיסטיקות בסיסיות
$stats = [
    'total_forms' => 0,
    'completed_forms' => 0,
    'in_progress_forms' => 0,
    'today_forms' => 0
];

try {
    $statsQuery = $db->query("
        SELECT 
            COUNT(*) as total_forms,
            SUM(CASE WHEN form_status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
            SUM(CASE WHEN form_status = 'draft' THEN 1 ELSE 0 END) as in_progress_forms,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms
        FROM deceased_forms
        WHERE deleted_at IS NULL
    ");
    $result = $statsQuery->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // אם יש בעיה, נשאיר ב-0
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
    </style>
</head>
<body>
    <!-- ניווט עליון -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-memorial me-2"></i>מערכת ניהול בתי עלמין
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>דשבורד
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['username'] ?? 'משתמש') ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>יציאה
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid dashboard-container">
        <h1 class="mb-4">דשבורד ראשי</h1>

        <!-- כרטיסי סטטיסטיקה צבעוניים -->
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

        <!-- כרטיסי פעולה -->
        <h3 class="mb-3">פעולות מהירות</h3>
        <div class="row action-cards g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='form/index.php?type=deceased'">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                        <h5>טופס נפטר חדש</h5>
                        <p class="text-muted mb-0">יצירת טופס נפטר חדש</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='form/index.php?type=purchase'">
                    <div class="card-body">
                        <i class="fas fa-cart-plus fa-3x text-success mb-3"></i>
                        <h5>רכישה חדשה</h5>
                        <p class="text-muted mb-0">יצירת טופס רכישה חדש</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='reports/'">
                    <div class="card-body">
                        <i class="fas fa-chart-bar fa-3x text-warning mb-3"></i>
                        <h5>דוחות</h5>
                        <p class="text-muted mb-0">צפייה בדוחות וסטטיסטיקות</p>
                    </div>
                </div>
            </div>
            
            <?php if ($userPermission === 'admin'): ?>
            <div class="col-md-3">
                <div class="card text-center" onclick="location.href='admin/users.php'">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-info mb-3"></i>
                        <h5>ניהול משתמשים</h5>
                        <p class="text-muted mb-0">ניהול משתמשים והרשאות</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>