<?php
// בדיקת הרשאות בצד השרת
// התחל את ה-session עם אותו שם לפני טעינת config
session_name('deceased_forms_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// כעת טען את הקונפיג
require_once '../config.php';

// בדיקה פשוטה - האם המשתמש מחובר
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// בדיקת רמת הרשאה - רק מנהלים (רמה 4) או הרשאה ספציפית
$hasAccess = false;
if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] >= 4) {
    $hasAccess = true;
} else {
    // בדוק הרשאה ספציפית למודול
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM user_permissions 
            WHERE user_id = ? 
            AND module_name = 'cemeteries' 
            AND can_access = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $hasAccess = $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        // אם אין טבלת הרשאות, תן גישה רק למנהלים
        $hasAccess = false;
    }
}

if (!$hasAccess) {
    // אין הרשאה - חזור לדשבורד הראשי
    header('Location: ../');
    exit;
}

// קבע את שם האתר אם לא מוגדר
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'מערכת ניהול');
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול בתי עלמין - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- הצג מידע על המשתמש המחובר -->
    <div class="position-fixed top-0 start-0 m-3 text-muted small">
        <i class="fas fa-user"></i> <?php echo $_SESSION['username'] ?? 'משתמש'; ?> 
        | רמה: <?php echo $_SESSION['permission_level'] ?? '?'; ?>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="text-center mb-4">
                    <i class="fas fa-landmark"></i> ניהול בתי עלמין
                </h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="#" data-page="overview">
                        <i class="fas fa-dashboard"></i> סקירה כללית
                    </a>
                    <a class="nav-link" href="#" data-page="cemeteries">
                        <i class="fas fa-building"></i> בתי עלמין
                    </a>
                    <a class="nav-link" href="#" data-page="blocks">
                        <i class="fas fa-th-large"></i> גושים
                    </a>
                    <a class="nav-link" href="#" data-page="plots">
                        <i class="fas fa-map"></i> חלקות
                    </a>
                    <a class="nav-link" href="#" data-page="rows">
                        <i class="fas fa-grip-lines"></i> שורות
                    </a>
                    <a class="nav-link" href="#" data-page="areaGraves">
                        <i class="fas fa-layer-group"></i> אחוזות קבר
                    </a>
                    <a class="nav-link" href="#" data-page="graves">
                        <i class="fas fa-cross"></i> קברים
                    </a>
                    <hr class="my-3">
                    <a class="nav-link" href="../">
                        <i class="fas fa-arrow-right"></i> חזרה לדשבורד
                    </a>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> יציאה
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Breadcrumb -->
                <nav class="breadcrumb-custom">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../">דף הבית</a></li>
                        <li class="breadcrumb-item">בתי עלמין</li>
                        <li class="breadcrumb-item active" id="current-page">סקירה כללית</li>
                    </ol>
                </nav>

                <!-- Dynamic Content Area -->
                <div id="content-area">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">טוען...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Template -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">טוען...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="itemId" name="id">
                        <input type="hidden" id="itemType" name="type">
                        <div id="formFields">
                            <!-- Dynamic fields will load here -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                    <button type="button" class="btn btn-primary" id="saveBtn">שמירה</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- App Scripts -->
    <script src="js/config.js"></script>
    <script src="js/utils.js"></script>
    <script src="js/validation.js"></script>
    <script src="js/api.js"></script>
    <script src="js/views/overview.js"></script>
    <script src="js/views/cemeteries.js"></script>
    <script src="js/views/blocks.js"></script>
    <script src="js/views/plots.js"></script>
    <script src="js/views/rows.js"></script>
    <script src="js/views/areaGraves.js"></script>
    <script src="js/views/graves.js"></script>
    <script src="js/forms.js"></script>
    <script src="js/main.js"></script>
</body>
</html>