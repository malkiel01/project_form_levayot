<?php
// includes/lists/deceased_list.php - רשימת טפסי נפטרים

require_once '../../config.php';
require_once 'list_functions.php';
require_once 'list_filters.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../' . LOGIN_URL);
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// טיפול בייצוא
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    exportDeceasedList();
    exit;
}

// קבלת פילטרים מה-GET או מהעדפת ברירת מחדל
$currentFilters = $_GET;
if (empty($currentFilters) || count($currentFilters) === 0) {
    $defaultPref = getDefaultPreference($userId, 'deceased_filters');
    if ($defaultPref) {
        $currentFilters = $defaultPref;
    }
}

// קבלת העדפות שמורות
$savedPreferences = getUserPreferences($userId, 'deceased_filters');

// בניית השאילתא
$params = [];
$whereClause = buildWhereClause($currentFilters, $params, 'df');

// הוסף הגבלת הרשאות
if ($userPermissionLevel < 4) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'df.created_by = ?';
    $params[] = $userId;
}

// מיון ועימוד
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// ספירת סך התוצאות - תיקון: הוספת df כ-alias
$countSql = "SELECT COUNT(*) FROM deceased_forms df $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// שליפת הנתונים
$sql = "
    SELECT 
        df.*,
        c.name as cemetery_name,
        b.name as block_name,
        s.name as section_name,
        u.full_name as created_by_name
    FROM deceased_forms df
    LEFT JOIN cemeteries c ON df.cemetery_id = c.id
    LEFT JOIN blocks b ON df.block_id = b.id
    LEFT JOIN sections s ON df.section_id = s.id
    LEFT JOIN users u ON df.created_by = u.id
    $whereClause
    ORDER BY df.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// פונקציה לייצוא לאקסל
function exportDeceasedList() {
    global $db, $currentFilters, $userId, $userPermissionLevel;
    
    // בניית השאילתא מחדש עבור הייצוא
    $params = [];
    $whereClause = buildWhereClause($currentFilters, $params, 'df');
    
    // הוסף הגבלת הרשאות
    if ($userPermissionLevel < 4) {
        $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'df.created_by = ?';
        $params[] = $userId;
    }
    
    $sql = "
        SELECT 
            df.form_uuid as 'מזהה טופס',
            df.deceased_name as 'שם הנפטר',
            df.father_name as 'שם האב',
            df.mother_name as 'שם האם',
            df.identification_number as 'מספר זיהוי',
            df.death_date as 'תאריך פטירה',
            df.burial_date as 'תאריך קבורה',
            c.name as 'בית עלמין',
            b.name as 'גוש',
            s.name as 'חלקה',
            df.status as 'סטטוס',
            df.created_at as 'תאריך יצירה'
        FROM deceased_forms df
        LEFT JOIN cemeteries c ON df.cemetery_id = c.id
        LEFT JOIN blocks b ON df.block_id = b.id
        LEFT JOIN sections s ON df.section_id = s.id
        $whereClause
        ORDER BY df.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    exportToExcel($data, 'deceased_list_' . date('Y-m-d'));
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>רשימת טפסי נפטרים - מערכת קדישא</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            padding-top: 0;
        }
        .filters-container {
            background-color: #f8f9fa;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
        .status-draft {
            background-color: #ffc107;
            color: #000;
        }
        .status-completed {
            background-color: #28a745;
            color: #fff;
        }
        .status-archived {
            background-color: #6c757d;
            color: #fff;
        }
        .main-content {
            padding: 20px 0;
        }
        .page-header {
            background-color: #f8f9fa;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- כלול את התפריט הראשי -->
    <?php 
    // עדכן את הנתיבים היחסיים בתפריט
    $navBasePath = '../../';
    include '../../includes/nav.php'; 
    ?>
    
    <!-- כותרת הדף -->
    <div class="page-header">
        <div class="container-fluid">
            <h1>
                <i class="fas fa-list"></i> רשימת טפסי נפטרים
            </h1>
        </div>
    </div>
    
    <div class="container-fluid main-content">
        <!-- פילטרים -->
        <?php renderListFilters('deceased', $currentFilters, $savedPreferences); ?>
        
        <!-- תוצאות -->
        <div class="card">
            <div class="card-header">
                <strong>נמצאו <?= $totalResults ?> תוצאות</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>מזהה</th>
                                <th>שם הנפטר</th>
                                <th>ת.ז.</th>
                                <th>תאריך פטירה</th>
                                <th>תאריך קבורה</th>
                                <th>בית עלמין</th>
                                <th>מיקום</th>
                                <th>סטטוס</th>
                                <th>נוצר ע"י</th>
                                <th>תאריך יצירה</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr onclick="window.location='../../view_decease44d_form.php?id=<?= $row['form_uuid'] ?>'">
                                    <td><?= substr($row['form_uuid'], 0, 8) ?>...</td>
                                    <td><strong><?= htmlspecialchars($row['deceased_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['identification_number'] ?? '-') ?></td>
                                    <td><?= $row['death_date'] ? date('d/m/Y', strtotime($row['death_date'])) : '-' ?></td>
                                    <td><?= $row['burial_date'] ? date('d/m/Y', strtotime($row['burial_date'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($row['cemetery_name'] ?? '-') ?></td>
                                    <td>
                                        <?php 
                                        $location = [];
                                        if ($row['block_name']) $location[] = $row['block_name'];
                                        if ($row['section_name']) $location[] = $row['section_name'];
                                        echo $location ? implode(' / ', $location) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge status-<?= $row['status'] ?>">
                                            <?= $row['status'] === 'draft' ? 'טיוטה' : ($row['status'] === 'completed' ? 'הושלם' : 'ארכיון') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['created_by_name'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td onclick="event.stopPropagation()">
                                        <div class="btn-group btn-group-sm">
                                            <a href="../../view_deceased_form.php?id=<?= $row['form_uuid'] ?>" 
                                               class="btn btn-info" title="צפייה">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../../form/?id=<?= $row['form_uuid'] ?>" 
                                               class="btn btn-warning" title="עריכה">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($userPermissionLevel >= 4 || $row['created_by'] == $userId): ?>
                                                <button class="btn btn-danger" 
                                                        onclick="deleteForm('<?= $row['form_uuid'] ?>')" 
                                                        title="מחיקה">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($results)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">לא נמצאו תוצאות</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                    הקודם
                                </a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                    הבא
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteForm(formUuid) {
        if (!confirm('האם אתה בטוח שברצונך למחוק טופס זה?')) {
            return;
        }
        
        fetch('../../ajax/delete_form.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
            },
            body: JSON.stringify({
                form_uuid: formUuid,
                csrf_token: '<?= $_SESSION['csrf_token'] ?>'
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert('שגיאה במחיקת הטופס: ' + (result.message || 'שגיאה לא ידועה'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('שגיאה במחיקת הטופס');
        });
    }
    </script>
</body>
</html>