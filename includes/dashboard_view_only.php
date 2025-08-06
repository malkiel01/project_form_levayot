<?php
// dashboard_view_only.php - דשבורד צפייה בלבד
require_once '../config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;
$userId = $_SESSION['user_id'];

// בדיקה אם יש למשתמש הרשאה לדשבורדים אחרים
$stmt = $db->prepare("
    SELECT dashboard_type 
    FROM user_dashboard_permissions 
    WHERE user_id = ? AND has_permission = 1
");
$stmt->execute([$userId]);
$allowedDashboards = $stmt->fetchAll(PDO::FETCH_COLUMN);

// סטטיסטיקות בסיסיות - רק מה שנוצר על ידי המשתמש
$stats = [];

// טפסי נפטרים שנוצרו על ידי המשתמש
$stmt = $db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE created_by = ?");
$stmt->execute([$userId]);
$stats['my_deceased_forms'] = $stmt->fetchColumn();

// טפסי רכישות שנוצרו על ידי המשתמש
$stmt = $db->prepare("SELECT COUNT(*) FROM purchase_forms WHERE created_by = ?");
$stmt->execute([$userId]);
$stats['my_purchase_forms'] = $stmt->fetchColumn();

// גישה חלופית - בלי UNION
// הטפסים האחרונים שלי
$myRecentForms = [];

// טען טפסי נפטרים
$stmt = $db->prepare("
    SELECT 
        'deceased' as form_type,
        form_uuid,
        deceased_name as name,
        status,
        created_at
    FROM deceased_forms 
    WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$deceasedForms = $stmt->fetchAll();

// טען טפסי רכישות
$stmt = $db->prepare("
    SELECT 
        'purchase' as form_type,
        form_uuid,
        buyer_name as name,
        status,
        created_at
    FROM purchase_forms 
    WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$purchaseForms = $stmt->fetchAll();

// שלב את שתי הרשימות
$myRecentForms = array_merge($deceasedForms, $purchaseForms);

// מיין לפי תאריך יצירה
usort($myRecentForms, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// קח רק את ה-10 הראשונים
$myRecentForms = array_slice($myRecentForms, 0, 10);

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד צפייה - מערכת ניהול לוויות</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../css/dashboard-styles-optimized.css" rel="stylesheet">
    <style>
        /* body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        } */
        
        /* .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        } */
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .recent-forms-table {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .available-dashboards {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .navbar {
            background-color: #343a40 !important;
        }
        
        .status-badge {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <!-- ניווט עליון -->
    <?php 
    // בדיקת הרשאה וטעינת התפריט המתאים
    if ($userPermissionLevel > 1) {
        // משתמשים עם הרשאה גבוהה מצופה - תפריט ראשי
        require_once 'nav.php';
    } else {
        // משתמשים עם הרשאת צפייה בלבד - תפריט מצומצם
        require_once 'nav_view_only.php';
    }
    ?>

    <div class="container-fluid py-4">
        <!-- כותרת הדשבורד -->
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> דשבורד צפייה בלבד</h1>
            <p>ברוך הבא, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
        </div>


        <div class="container">
            <!-- סטטיסטיקות -->
            <div class="row">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="text-muted mb-1">טפסי נפטרים שיצרתי</h5>
                                <div class="stat-number"><?php echo number_format($stats['my_deceased_forms']); ?></div>
                            </div>
                            <div>
                                <i class="fas fa-user-alt-slash fa-3x text-muted opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="text-muted mb-1">טפסי רכישות שיצרתי</h5>
                                <div class="stat-number"><?php echo number_format($stats['my_purchase_forms']); ?></div>
                            </div>
                            <div>
                                <i class="fas fa-shopping-cart fa-3x text-muted opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הטפסים האחרונים שלי -->
            <div class="recent-forms-table">
                <h4 class="mb-4">
                    <i class="fas fa-clock me-2"></i>
                    הטפסים האחרונים שלי
                </h4>
                
                <?php if (empty($myRecentForms)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        עדיין לא יצרת טפסים במערכת
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>סוג</th>
                                    <th>שם</th>
                                    <th>סטטוס</th>
                                    <th>תאריך יצירה</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myRecentForms as $form): ?>
                                    <tr>
                                        <td>
                                            <?php if ($form['form_type'] == 'deceased'): ?>
                                                <i class="fas fa-user-alt-slash me-2"></i>נפטר
                                            <?php else: ?>
                                                <i class="fas fa-shopping-cart me-2"></i>רכישה
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($form['name']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = 'secondary';
                                            $statusText = $form['status'];
                                            
                                            switch($form['status']) {
                                                case 'completed':
                                                    $statusClass = 'success';
                                                    $statusText = 'הושלם';
                                                    break;
                                                case 'in_progress':
                                                    $statusClass = 'warning';
                                                    $statusText = 'בתהליך';
                                                    break;
                                                case 'draft':
                                                    $statusClass = 'secondary';
                                                    $statusText = 'טיוטה';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?> status-badge">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($form['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $formUrl = $form['form_type'] == 'deceased' ? FORM_URL : 'purchase_form.php';
                                            ?>
                                            <a href="../<?php echo $formUrl; ?>?id=<?php echo $form['form_uuid']; ?>" 
                                            class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>
                                                צפייה
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- הודעה על הגבלות -->
            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>שים לב:</strong> במצב צפייה בלבד, אתה יכול לצפות רק בטפסים שיצרת בעצמך. 
                לקבלת הרשאות נוספות, פנה למנהל המערכת.
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>