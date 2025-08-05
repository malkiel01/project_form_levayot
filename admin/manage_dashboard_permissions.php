<?php
// admin/manage_dashboard_permissions.php - דף ניהול הרשאות דשבורדים
require_once '../config.php';

// רק מנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$db = getDbConnection();
$message = '';
$messageType = '';

// טיפול בהענקת/ביטול הרשאות
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    $dashboardType = $_POST['dashboard_type'] ?? '';
    
    if ($action === 'grant' && $userId && $dashboardType) {
        if (grantDashboardPermission($userId, $dashboardType, $_SESSION['user_id'])) {
            $message = 'ההרשאה ניתנה בהצלחה';
            $messageType = 'success';
        } else {
            $message = 'שגיאה בהענקת ההרשאה';
            $messageType = 'danger';
        }
    } elseif ($action === 'revoke' && $userId && $dashboardType) {
        if (revokeDashboardPermission($userId, $dashboardType)) {
            $message = 'ההרשאה בוטלה בהצלחה';
            $messageType = 'success';
        } else {
            $message = 'שגיאה בביטול ההרשאה';
            $messageType = 'danger';
        }
    }
}

// קבלת רשימת משתמשים
$users = $db->query("
    SELECT id, username, full_name, permission_level 
    FROM users 
    WHERE is_active = 1 AND permission_level <= 2
    ORDER BY full_name
")->fetchAll();

// קבלת הרשאות קיימות
$permissions = $db->query("
    SELECT 
        udp.*,
        u.username,
        u.full_name,
        u.permission_level,
        creator.full_name as created_by_name
    FROM user_dashboard_permissions udp
    JOIN users u ON udp.user_id = u.id
    LEFT JOIN users creator ON udp.created_by = creator.id
    WHERE udp.has_permission = 1
    ORDER BY u.full_name, udp.dashboard_type
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול הרשאות דשבורדים</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .dashboard-badge {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
        }
        
        .permission-level-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- ניווט עליון -->
    <nav class="navbar navbar-dark bg-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../<?php echo DASHBOARD_FULL_URL; ?>">
                <i class="fas fa-book-dead me-2"></i>
                מערכת ניהול לוויות
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../<?php echo DASHBOARD_FULL_URL; ?>">
                    <i class="fas fa-arrow-right me-1"></i>
                    חזרה לדשבורד
                </a>
            </div>
        </div>
    </nav>

    <!-- כותרת -->
    <div class="page-header">
        <div class="container">
            <h1 class="mb-0">
                <i class="fas fa-user-shield me-2"></i>
                ניהול הרשאות דשבורדים
            </h1>
            <p class="mb-0 mt-2">הענק או בטל הרשאות גישה לדשבורדים השונים</p>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- הענקת הרשאה חדשה -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    הענקת הרשאה חדשה
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="grant">
                    
                    <div class="col-md-4">
                        <label for="user_id" class="form-label">בחר משתמש</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">-- בחר משתמש --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                    (רמה <?php echo $user['permission_level']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="dashboard_type" class="form-label">סוג דשבורד</label>
                        <select name="dashboard_type" id="dashboard_type" class="form-select" required>
                            <option value="">-- בחר דשבורד --</option>
                            <option value="main">דשבורד ראשי</option>
                            <option value="deceased">דשבורד נפטרים</option>
                            <option value="purchases">דשבורד רכישות</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check me-2"></i>
                            הענק הרשאה
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- הרשאות קיימות -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    הרשאות קיימות
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($permissions)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        אין הרשאות מיוחדות. משתמשים ברמה 2 ומטה מקבלים גישה לדשבורד צפייה בלבד.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>משתמש</th>
                                    <th>רמת הרשאה</th>
                                    <th>דשבורד</th>
                                    <th>ניתנה על ידי</th>
                                    <th>תאריך</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissions as $perm): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-user me-2"></i>
                                            <?php echo htmlspecialchars($perm['full_name'] ?? $perm['username']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $levelClass = 'secondary';
                                            $levelText = 'רמה ' . $perm['permission_level'];
                                            
                                            if ($perm['permission_level'] == 1) {
                                                $levelClass = 'info';
                                                $levelText = 'צופה';
                                            } elseif ($perm['permission_level'] == 2) {
                                                $levelClass = 'warning';
                                                $levelText = 'עורך';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $levelClass; ?> permission-level-badge">
                                                <?php echo $levelText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $dashboardName = '';
                                            $dashboardClass = 'secondary';
                                            
                                            switch($perm['dashboard_type']) {
                                                case 'main':
                                                    $dashboardName = 'דשבורד ראשי';
                                                    $dashboardClass = 'primary';
                                                    break;
                                                case 'deceased':
                                                    $dashboardName = 'דשבורד נפטרים';
                                                    $dashboardClass = 'dark';
                                                    break;
                                                case 'purchases':
                                                    $dashboardName = 'דשבורד רכישות';
                                                    $dashboardClass = 'success';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $dashboardClass; ?> dashboard-badge">
                                                <?php echo $dashboardName; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($perm['created_by_name'] ?? 'מערכת'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($perm['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('האם אתה בטוח שברצונך לבטל הרשאה זו?');">
                                                <input type="hidden" name="action" value="revoke">
                                                <input type="hidden" name="user_id" value="<?php echo $perm['user_id']; ?>">
                                                <input type="hidden" name="dashboard_type" value="<?php echo $perm['dashboard_type']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times me-1"></i>
                                                    בטל
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- הסבר על רמות הרשאה -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    הסבר על מערכת ההרשאות
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>רמות הרשאה בסיסיות:</h6>
                        <ul>
                            <li><strong>מנהל (רמה 4):</strong> גישה מלאה לכל הדשבורדים</li>
                            <li><strong>עורך מתקדם (רמה 3):</strong> גישה מלאה לכל הדשבורדים</li>
                            <li><strong>עורך (רמה 2):</strong> דשבורד צפייה בלבד + הרשאות מיוחדות</li>
                            <li><strong>צופה (רמה 1):</strong> דשבורד צפייה בלבד + הרשאות מיוחדות</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>דשבורדים זמינים:</h6>
                        <ul>
                            <li><strong>דשבורד ראשי:</strong> תצוגה מלאה של כל הנתונים</li>
                            <li><strong>דשבורד נפטרים:</strong> מיקוד בטפסי נפטרים בלבד</li>
                            <li><strong>דשבורד רכישות:</strong> מיקוד בטפסי רכישות בלבד</li>
                            <li><strong>דשבורד צפייה בלבד:</strong> צפייה בטפסים שהמשתמש יצר</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>