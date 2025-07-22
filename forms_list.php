<?php
// forms_list.php - רשימת טפסים
require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

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
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$forms = $stmt->fetchAll();

// קבלת רשימת בתי עלמין לסינון
$cemeteries = $db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>רשימת טפסים - מערכת ניהול נפטרים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .filter-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .status-badge {
            font-size: 0.875rem;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                        <a class="nav-link" href="form.php">
                            <i class="fas fa-plus"></i> טופס חדש
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
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">הפרופיל שלי</a></li>
                            <li><a class="dropdown-item" href="settings.php">הגדרות</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">יציאה</a></li>
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

        <!-- פילטרים -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">חיפוש חופשי</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="שם, ת.ז., מספר טופס...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">סטטוס</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">הכל</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>טיוטה</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>בתהליך</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>הושלם</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>ארכיון</option>
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label for="date_from" class="form-label">מתאריך</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">עד תאריך</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> חפש
                    </button>
                </div>
            </form>
            <div class="row mt-3">
                <div class="col-12">
                    <a href="forms_list.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-redo"></i> נקה פילטרים
                    </a>
                    <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-file-excel"></i> ייצוא לאקסל
                    </a>
                    <button type="button" class="btn btn-info btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> הדפסה
                    </button>
                </div>
            </div>
        </div>

        <!-- טבלת טפסים -->
        <div class="table-container">
            <div class="row mb-3">
                <div class="col-6">
                    <h5>נמצאו <?= number_format($totalRecords) ?> טפסים</h5>
                </div>
                <div class="col-6 text-end">
                    <a href="form.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> טופס חדש
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>מס' טופס</th>
                            <th>שם הנפטר</th>
                            <th>ת.ז./דרכון</th>
                            <th>תאריך פטירה</th>
                            <th>תאריך קבורה</th>
                            <th>בית עלמין</th>
                            <th>סטטוס</th>
                            <th>התקדמות</th>
                            <th>נוצר ע"י</th>
                            <th>נוצר ב</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($forms)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">
                                לא נמצאו טפסים
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($forms as $form): ?>
                        <tr>
                            <td>
                                <small><?= substr($form['form_uuid'], 0, 8) ?>...</small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($form['deceased_name']) ?></strong>
                            </td>
                            <td>
                                <?php if ($form['identification_number']): ?>
                                <small><?= htmlspecialchars($form['identification_number']) ?></small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($form['death_date'])) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($form['burial_date'])) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($form['cemetery_name'] ?? '-') ?>
                            </td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'draft' => '<span class="badge bg-secondary status-badge">טיוטה</span>',
                                    'in_progress' => '<span class="badge bg-warning status-badge">בתהליך</span>',
                                    'completed' => '<span class="badge bg-success status-badge">הושלם</span>',
                                    'archived' => '<span class="badge bg-dark status-badge">ארכיון</span>'
                                ];
                                echo $statusLabels[$form['status']] ?? $form['status'];
                                ?>
                            </td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $form['progress_percentage'] ?>%">
                                        <?= $form['progress_percentage'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($form['created_by_name'] ?? '-') ?></small>
                            </td>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($form['created_at'])) ?></small>
                            </td>
                            <td class="action-buttons">
                                <a href="form.php?id=<?= $form['form_uuid'] ?>" 
                                   class="btn btn-primary btn-sm" title="עריכה">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="view_form.php?id=<?= $form['form_uuid'] ?>" 
                                   class="btn btn-info btn-sm" title="צפייה">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="export_pdf.php?id=<?= $form['form_uuid'] ?>" 
                                   class="btn btn-secondary btn-sm" title="PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <?php if ($userPermissionLevel >= 3): ?>
                                <button type="button" class="btn btn-danger btn-sm" 
                                        onclick="deleteForm('<?= $form['form_uuid'] ?>')" title="מחיקה">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="דפדוף">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            הקודם
                        </a>
                    </li>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function deleteForm(formUuid) {
            Swal.fire({
                title: 'האם אתה בטוח?',
                text: "פעולה זו לא ניתנת לביטול!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'כן, מחק!',
                cancelButtonText: 'ביטול'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'admin/delete_form.php',
                        method: 'POST',
                        data: {
                            form_id: formUuid,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    'נמחק!',
                                    'הטופס נמחק בהצלחה.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'שגיאה!',
                                    response.message || 'אירעה שגיאה במחיקת הטופס.',
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                'שגיאה!',
                                'אירעה שגיאה במחיקת הטופס.',
                                'error'
                            );
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>