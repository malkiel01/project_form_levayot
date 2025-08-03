<?php
// dashboard.php - דשבורד ראשי
require_once 'config.php';

// בדיקת התחברות - חייב להיות לפני כל פלט!
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// אם רוצים לבדוק את ה-session, אפשר להשתמש בזה רק בדף נפרד או אחרי ה-HTML
// או להסיר את הקוד הזה לגמרי אחרי שסיימת את הדיבוג
$debug_mode = false; // שנה ל-true רק כשצריך דיבוג

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// קבלת סטטיסטיקות כלליות
$stats = [];

// סטטיסטיקות בסיסיות לכולם
$stats['total_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms")->fetchColumn();
$stats['completed_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'completed'")->fetchColumn();
$stats['in_progress_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'in_progress'")->fetchColumn();
$stats['draft_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE status = 'draft'")->fetchColumn();

// סטטיסטיקות של היום
$stats['today_forms'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['today_burials'] = $db->query("SELECT COUNT(*) FROM deceased_forms WHERE DATE(burial_date) = CURDATE()")->fetchColumn();

// טפסים אחרונים
$recentFormsQuery = "SELECT * FROM deceased_forms ";
if ($userPermissionLevel < 4) {
    $recentFormsQuery .= "WHERE created_by = " . $_SESSION['user_id'] . " ";
}
$recentFormsQuery .= "ORDER BY created_at DESC LIMIT 10";
$recentForms = $db->query($recentFormsQuery)->fetchAll();

// התראות למשתמש
$notificationsQuery = "
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
";
$notificationsStmt = $db->prepare($notificationsQuery);
$notificationsStmt->execute([$_SESSION['user_id']]);
$userNotifications = $notificationsStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת ניהול נפטרים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Debug Info - יוצג רק אם debug_mode = true -->
    <?php if ($debug_mode): ?>
    <div class="alert alert-info m-3">
        <h5>מידע דיבוג:</h5>
        <pre style='background:#f8f9fa;padding:10px;'>
=== נתוני Session ===
מחובר? <?= isset($_SESSION['user_id']) ? 'כן' : 'לא' ?>

User ID: <?= $_SESSION['user_id'] ?? 'לא קיים' ?>

Username: <?= $_SESSION['username'] ?? 'לא קיים' ?>

Full Name: <?= $_SESSION['full_name'] ?? 'לא קיים' ?>

Permission Level: <?= $_SESSION['permission_level'] ?? 'לא קיים' ?>


כל נתוני Session:
<?php print_r($_SESSION); ?>
        </pre>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home"></i> מערכת ניהול נפטרים
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= FORM_URL ?>">
                            <i class="fas fa-plus-circle"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search"></i> חיפוש
                        </a>
                    </li>
                    <?php if ($userPermissionLevel >= 3): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> ניהול
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="admin/users.php">משתמשים</a></li>
                            <li><a class="dropdown-item" href="admin/permissions.php">הרשאות</a></li>
                            <li><a class="dropdown-item" href="admin/reports.php">דוחות</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                            <i class="fas fa-bell"></i>
                            <?php if (count($userNotifications) > 0): ?>
                            <span class="notification-badge"><?= count($userNotifications) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'משתמש') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">הפרופיל שלי</a></li>
                            <li><a class="dropdown-item" href="settings.php">הגדרות</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= LOGOUT_URL ?>">יציאה</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">דשבורד</h2>
        
        <?php if (!isset($_SESSION['username']) || empty($_SESSION['username'])): ?>
        <div class="alert alert-warning">
            <strong>שים לב:</strong> נראה שחסרים נתונים בסשן שלך. 
            <a href="<?= LOGOUT_URL ?>" class="alert-link">נסה להתחבר מחדש</a>
        </div>
        <?php endif; ?>

        <!-- כרטיסי סטטיסטיקה -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">סה"כ טפסים</h6>
                                <h3 class="mb-0"><?= number_format($stats['total_forms']) ?></h3>
                            </div>
                            <i class="fas fa-file-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">הושלמו</h6>
                                <h3 class="mb-0"><?= number_format($stats['completed_forms']) ?></h3>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">בתהליך</h6>
                                <h3 class="mb-0"><?= number_format($stats['in_progress_forms']) ?></h3>
                            </div>
                            <i class="fas fa-spinner fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">לוויות היום</h6>
                                <h3 class="mb-0"><?= number_format($stats['today_burials']) ?></h3>
                            </div>
                            <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- טבלת טפסים אחרונים -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">טפסים אחרונים</h5>
            </div>
            <div class="card-body">
                <?php if (count($recentForms) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>מס' טופס</th>
                                <th>שם הנפטר/ת</th>
                                <th>תאריך פטירה</th>
                                <th>תאריך קבורה</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentForms as $form): ?>
                            <tr>
                                <td><?= htmlspecialchars($form['form_number'] ?? $form['id']) ?></td>
                                <td><?= htmlspecialchars($form['first_name'] . ' ' . $form['last_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($form['death_date'])) ?></td>
                                <td><?= $form['burial_date'] ? date('d/m/Y', strtotime($form['burial_date'])) : '-' ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'draft' => 'bg-secondary',
                                        'in_progress' => 'bg-warning',
                                        'completed' => 'bg-success'
                                    ][$form['status']] ?? 'bg-secondary';
                                    
                                    $statusText = [
                                        'draft' => 'טיוטה',
                                        'in_progress' => 'בתהליך',
                                        'completed' => 'הושלם'
                                    ][$form['status']] ?? $form['status'];
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <a href="view_form.php?id=<?= $form['uuid'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_form.php?id=<?= $form['uuid'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-muted">אין טפסים להצגה</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>