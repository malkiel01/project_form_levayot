<?php
// manage_links.php - ניהול קישורי שיתוף

require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// טיפול במחיקת קישור
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $linkUuid = $_POST['link_uuid'];
    
    // בדוק הרשאות - רק יוצר הקישור או מנהל יכולים למחוק
    $checkStmt = $db->prepare("
        SELECT created_by FROM form_links WHERE link_uuid = ?
    ");
    $checkStmt->execute([$linkUuid]);
    $link = $checkStmt->fetch();
    
    if ($link && ($link['created_by'] == $_SESSION['user_id'] || $userPermissionLevel >= 4)) {
        $deleteStmt = $db->prepare("DELETE FROM form_links WHERE link_uuid = ?");
        $deleteStmt->execute([$linkUuid]);
        $successMessage = "הקישור נמחק בהצלחה";
    } else {
        $errorMessage = "אין לך הרשאה למחוק קישור זה";
    }
}

// קבלת רשימת קישורים
$where = "1=1";
$params = [];

// הגבל למשתמש הנוכחי אם לא מנהל
if ($userPermissionLevel < 4) {
    $where .= " AND fl.created_by = ?";
    $params[] = $_SESSION['user_id'];
}

// סינון לפי טופס ספציפי אם נשלח
$filterFormUuid = $_GET['form_uuid'] ?? null;
if ($filterFormUuid) {
    $where .= " AND fl.form_uuid = ?";
    $params[] = $filterFormUuid;
}

$stmt = $db->prepare("
    SELECT 
        fl.*,
        df.deceased_name,
        u.full_name as created_by_name,
        (SELECT COUNT(*) FROM form_link_access_log WHERE link_uuid = fl.link_uuid) as total_views
    FROM form_links fl
    LEFT JOIN deceased_forms df ON fl.form_uuid = df.form_uuid
    LEFT JOIN users u ON fl.created_by = u.id
    WHERE $where
    ORDER BY fl.created_at DESC
");
$stmt->execute($params);
$links = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול קישורי שיתוף</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .content-wrapper {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .link-url {
            font-family: monospace;
            font-size: 0.85rem;
            word-break: break-all;
        }
        .expired {
            opacity: 0.6;
        }
        .badge-active {
            background-color: #28a745;
        }
        .badge-expired {
            background-color: #dc3545;
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
                        <a class="nav-link" href="dashboard.php">דשבורד</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="forms_list.php">רשימת טפסים</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_links.php">קישורי שיתוף</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="content-wrapper">
            <div class="row mb-4">
                <div class="col">
                    <h2><i class="fas fa-link"></i> ניהול קישורי שיתוף</h2>
                </div>
            </div>

            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $successMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $errorMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>טופס</th>
                            <th>סוג גישה</th>
                            <th>הרשאות</th>
                            <th>סטטוס</th>
                            <th>תוקף</th>
                            <th>שימוש</th>
                            <th>נוצר על ידי</th>
                            <th>נוצר ב</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                        <?php 
                        $isExpired = $link['expires_at'] && strtotime($link['expires_at']) < time();
                        $allowedUsers = $link['allowed_user_ids'] ? json_decode($link['allowed_user_ids'], true) : null;
                        ?>
                        <tr class="<?= $isExpired ? 'expired' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($link['deceased_name'] ?? 'ללא שם') ?></strong><br>
                                <small class="text-muted"><?= substr($link['form_uuid'], 0, 8) ?>...</small>
                            </td>
                            <td>
                                <?php if ($allowedUsers): ?>
                                    <i class="fas fa-users"></i> <?= count($allowedUsers) ?> משתמשים
                                <?php else: ?>
                                    <i class="fas fa-globe"></i> פתוח לכולם
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($link['can_edit']): ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-edit"></i> עריכה
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-eye"></i> צפייה
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="badge badge-expired">פג תוקף</span>
                                <?php else: ?>
                                    <span class="badge badge-active">פעיל</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($link['expires_at']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($link['expires_at'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">ללא הגבלה</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?= $link['use_count'] ?> פעמים<br>
                                    <?= $link['total_views'] ?> צפיות
                                </small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($link['created_by_name'] ?? '-') ?></small>
                            </td>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($link['created_at'])) ?></small>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" 
                                        onclick="showLink('<?= $link['link_uuid'] ?>')">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="viewStats('<?= $link['link_uuid'] ?>')">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <?php if ($link['created_by'] == $_SESSION['user_id'] || $userPermissionLevel >= 4): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="link_uuid" value="<?= $link['link_uuid'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('האם למחוק את הקישור?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($links)): ?>
            <div class="text-center text-muted py-4">
                <p>אין קישורי שיתוף</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal הצגת קישור -->
    <div class="modal fade" id="showLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">קישור שיתוף</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group">
                        <input type="text" class="form-control" id="linkUrl" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">
                            <i class="fas fa-copy"></i> העתק
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        function showLink(linkUuid) {
            const url = '<?= SITE_URL ?>/form.php?link=' + linkUuid;
            document.getElementById('linkUrl').value = url;
            $('#showLinkModal').modal('show');
        }
        
        function copyLink() {
            document.getElementById('linkUrl').select();
            document.execCommand('copy');
            alert('הקישור הועתק ללוח');
        }
        
        function viewStats(linkUuid) {
            window.location.href = 'link_stats.php?uuid=' + linkUuid;
        }
    </script>
</body>
</html>