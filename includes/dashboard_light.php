<?php
// includes/dashboard_light.php - דשבורד קל וממוטב
require_once dirname(__DIR__) . '/config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// סטטיסטיקות בסיסיות בלבד - שאילתה אחת משולבת
$whereClause = $userPermissionLevel < 4 ? "WHERE created_by = " . $_SESSION['user_id'] : "";

// שאילתה אחת לכל הסטטיסטיקות
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM deceased_forms 
    $whereClause
";
$stats = $db->query($statsQuery)->fetch();

// 5 רשומות אחרונות בלבד
$recentQuery = "
    SELECT form_uuid, deceased_name, death_date, status 
    FROM deceased_forms 
    $whereClause
    ORDER BY created_at DESC 
    LIMIT 5
";
$recentForms = $db->query($recentQuery)->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת קדישא</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS מינימלי ללא אנימציות */
        body {
            background-color: #f8f9fa;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        .stat-label {
            color: #6c757d;
            margin: 0;
        }
        .quick-actions {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- תפריט פשוט -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-home"></i> מערכת קדישא
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_light.php">
                            <i class="fas fa-chart-line"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../form/form.php">
                            <i class="fas fa-plus"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                    <?php if ($userPermissionLevel >= 4): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/users.php">
                            <i class="fas fa-users"></i> ניהול
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="navbar-nav">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </span>
                    <a class="btn btn-sm btn-outline-light" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> יציאה
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <h1 class="mb-4">
            <i class="fas fa-chart-line"></i> דשבורד ראשי
        </h1>

        <!-- סטטיסטיקות -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="text-center">
                        <p class="stat-value"><?= number_format($stats['total']) ?></p>
                        <p class="stat-label">סה"כ טפסים</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="text-center">
                        <p class="stat-value"><?= number_format($stats['completed']) ?></p>
                        <p class="stat-label">הושלמו</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-warning text-white">
                    <div class="text-center">
                        <p class="stat-value"><?= number_format($stats['in_progress']) ?></p>
                        <p class="stat-label">בתהליך</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-info text-white">
                    <div class="text-center">
                        <p class="stat-value"><?= number_format($stats['today']) ?></p>
                        <p class="stat-label">היום</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- פעולות מהירות -->
            <div class="col-md-4 mb-4">
                <div class="quick-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt"></i> פעולות מהירות
                    </h5>
                    <div class="d-grid gap-2">
                        <a href="../form/form.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> הוספת נפטר חדש
                        </a>
                        <a href="../forms_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> רשימת כל הטפסים
                        </a>
                        <a href="../form/search.php" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> חיפוש טופס
                        </a>
                        <?php if ($userPermissionLevel >= 3): ?>
                        <a href="../reports/daily.php" class="btn btn-outline-secondary">
                            <i class="fas fa-file-pdf"></i> דוח יומי
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- טפסים אחרונים -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> טפסים אחרונים
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>מספר טופס</th>
                                        <th>שם הנפטר</th>
                                        <th>תאריך פטירה</th>
                                        <th>סטטוס</th>
                                        <th>פעולות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentForms)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            אין טפסים להצגה
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentForms as $form): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($form['form_uuid']) ?></td>
                                            <td><?= htmlspecialchars($form['deceased_name']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($form['death_date'])) ?></td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'draft' => 'secondary',
                                                    'in_progress' => 'warning',
                                                    'completed' => 'success'
                                                ];
                                                $statusText = [
                                                    'draft' => 'טיוטה',
                                                    'in_progress' => 'בתהליך',
                                                    'completed' => 'הושלם'
                                                ];
                                                $color = $statusColors[$form['status']] ?? 'secondary';
                                                $text = $statusText[$form['status']] ?? $form['status'];
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= $text ?></span>
                                            </td>
                                            <td>
                                                <a href="../form/form.php?id=<?= $form['form_uuid'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (count($recentForms) >= 5): ?>
                    <div class="card-footer text-center">
                        <a href="../forms_list.php" class="btn btn-sm btn-outline-primary">
                            צפייה בכל הטפסים <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>