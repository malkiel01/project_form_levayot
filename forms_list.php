<?php
// forms_list.php - רשימת טפסים - גרסה מתוקנת למובייל

// תיקון ל-Session במובייל
if (session_status() === PHP_SESSION_NONE) {
    // הגדרות Session ידידותיות למובייל
    ini_set('session.cookie_secure', '1'); // רק ב-HTTPS
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax'); // חשוב למובייל!
    ini_set('session.gc_maxlifetime', '7200');
    
    session_start();
}

require_once 'config.php';

// בדיקת התחברות - עם טיפול מיוחד למובייל
if (!isset($_SESSION['user_id'])) {
    // אם זו גישה מ-PWA, נסה לטעון מחדש את ה-session
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Session expired', 'redirect' => LOGIN_URL]);
        exit;
    }
    
    header('Location: ' . LOGIN_URL);
    exit;
}

// שמירת פעילות אחרונה
$_SESSION['last_activity'] = time();

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 4;

// פרמטרים לחיפוש וסינון
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$cemetery = $_GET['cemetery'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// בניית שאילתת החיפוש
$where = ["1=1"];
$params = [];

// הגבלה למשתמשים שאינם מנהלים
if ($userPermissionLevel < 4) {
    $where[] = "df.created_by = ?";
    $params[] = $_SESSION['user_id'];
}

// חיפוש טקסט חופשי
if ($search) {
    $where[] = "(df.deceased_name LIKE ? OR df.identification_number LIKE ? OR df.form_uuid LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// סינון לפי סטטוס
if ($status) {
    $where[] = "df.status = ?";
    $params[] = $status;
}

// סינון לפי בית עלמין
if ($cemetery) {
    $where[] = "df.cemetery_id = ?";
    $params[] = $cemetery;
}

// סינון לפי תאריך
if ($dateFrom) {
    $where[] = "df.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "df.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(" AND ", $where);

// ספירת סך הרשומות
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM deceased_forms df 
    WHERE $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// שליפת הרשומות
$stmt = $db->prepare("
    SELECT 
        df.*,
        c.name as cemetery_name,
        u.full_name as created_by_name
    FROM deceased_forms df
    LEFT JOIN cemeteries c ON df.cemetery_id = c.id
    LEFT JOIN users u ON df.created_by = u.id
    WHERE $whereClause
    ORDER BY df.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$forms = $stmt->fetchAll();

// שליפת רשימת בתי עלמין לפילטר
$cemeteries = $db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <title>רשימת טפסים - מערכת ניהול נפטרים</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS מותאם למובייל -->
    <style>
        /* תיקונים למובייל */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .filter-card {
                margin-bottom: 1rem;
            }
            
            /* הסתרת עמודות לא חיוניות במובייל */
            .hide-mobile {
                display: none !important;
            }
            
            /* שיפור תצוגת הטבלה במובייל */
            .table td {
                padding: 0.5rem;
                vertical-align: middle;
            }
            
            /* כפתורי פעולה קטנים יותר */
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                margin: 0.1rem;
            }
        }
        
        /* מניעת זום לא רצוי במובייל */
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="password"],
        input[type="date"],
        input[type="datetime-local"],
        select,
        textarea {
            font-size: 16px !important;
        }
    </style>
    
    <!-- PWA Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">
</head>
<body>
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= FORM_URL ?>">
                            <i class="fas fa-plus-circle"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                    <?php if ($userPermissionLevel >= 4): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> ניהול
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="admin/users.php">משתמשים</a></li>
                            <li><a class="dropdown-item" href="admin/cemeteries.php">בתי עלמין</a></li>
                            <li><a class="dropdown-item" href="admin/permissions.php">הרשאות</a></li>
                            <li><a class="dropdown-item" href="admin/reports.php">דוחות</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
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
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">רשימת טפסים</h2>
            </div>
        </div>

        <!-- פילטרים - מכווץ במובייל -->
        <div class="card filter-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center" 
                 data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <h5 class="mb-0">סינון וחיפוש</h5>
                <i class="fas fa-chevron-down d-md-none"></i>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3 col-12">
                            <label for="search" class="form-label">חיפוש חופשי</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="שם, ת.ז., מספר טופס...">
                        </div>
                        <div class="col-md-2 col-6">
                            <label for="status" class="form-label">סטטוס</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">הכל</option>
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>טיוטה</option>
                                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>בתהליך</option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>הושלם</option>
                                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>ארכיון</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label for="cemetery" class="form-label">בית עלמין</label>
                            <select class="form-select" id="cemetery" name="cemetery">
                                <option value="">הכל</option>
                                <?php foreach ($cemeteries as $cem): ?>
                                <option value="<?= $cem['id'] ?>" <?= $cemetery == $cem['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cem['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-auto">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> חפש
                                </button>
                                <a href="forms_list.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> נקה
                                </a>
                                <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-success">
                                    <i class="fas fa-file-excel"></i> ייצוא
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- טבלת טפסים -->
        <div class="card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <h5>נמצאו <?= number_format($totalRecords) ?> טפסים</h5>
                    </div>
                    <div class="col-6 text-end">
                        <a href="<?= FORM_URL ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> <span class="d-none d-md-inline">טופס חדש</span>
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>שם הנפטר</th>
                                <th class="hide-mobile">ת.ז./דרכון</th>
                                <th>תאריך פטירה</th>
                                <th class="hide-mobile">בית עלמין</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($forms)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">לא נמצאו טפסים</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($forms as $form): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($form['deceased_name'] ?: 'ללא שם') ?></strong>
                                        <br>
                                        <small class="text-muted d-md-none">
                                            <?= htmlspecialchars($form['identification_number'] ?: 'ללא ת.ז.') ?>
                                        </small>
                                    </td>
                                    <td class="hide-mobile"><?= htmlspecialchars($form['identification_number'] ?: '-') ?></td>
                                    <td><?= $form['death_date'] ? date('d/m/Y', strtotime($form['death_date'])) : '-' ?></td>
                                    <td class="hide-mobile"><?= htmlspecialchars($form['cemetery_name'] ?: '-') ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'draft' => 'bg-secondary',
                                            'in_progress' => 'bg-warning',
                                            'completed' => 'bg-success',
                                            'archived' => 'bg-info'
                                        ][$form['status']] ?? 'bg-secondary';
                                        
                                        $statusText = [
                                            'draft' => 'טיוטה',
                                            'in_progress' => 'בתהליך',
                                            'completed' => 'הושלם',
                                            'archived' => 'ארכיון'
                                        ][$form['status']] ?? $form['status'];
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="view_form.php?id=<?= $form['form_uuid'] ?>" 
                                           class="btn btn-sm btn-info" title="צפייה">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= FORM_URL ?>?id=<?= $form['form_uuid'] ?>" 
                                           class="btn btn-sm btn-warning" title="עריכה">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="print_form.php?id=<?= $form['form_uuid'] ?>" 
                                           class="btn btn-sm btn-secondary" title="הדפסה" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="ניווט בין דפים" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                הקודם
                            </a>
                        </li>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                    <?= $totalPages ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                הבא
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- בדיקת Session למובייל -->
    <script>
    // בדיקה תקופתית של ה-Session במובייל
    setInterval(function() {
        fetch('ajax/check_login_status.php')
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    window.location.href = '<?= LOGIN_URL ?>';
                }
            })
            .catch(error => {
                console.error('Session check failed:', error);
            });
    }, 60000); // כל דקה
    
    // מניעת זום לא רצוי במובייל
    document.addEventListener('gesturestart', function(e) {
        e.preventDefault();
    });
    </script>
</body>
</html>