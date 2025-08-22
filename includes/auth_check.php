<?php
// includes/auth_check.php - קובץ בדיקת הרשאות מרכזי

// טען את הקונפיג אם עוד לא נטען
if (!function_exists('getDbConnection')) {
    require_once __DIR__ . '/../config.php';
}

// טען את פונקציות הדשבורד
if (!function_exists('hasDashboardPermission')) {
    require_once __DIR__ . '/dashboard_functions.php';
}

// בדיקה בסיסית - האם המשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// קבלת פרטי המשתמש
$currentUserId = $_SESSION['user_id'];
$currentUserLevel = $_SESSION['permission_level'] ?? 1;

/**
 * פונקציה לבדיקת הרשאה לדף ספציפי
 * @param string $pageName - שם הדף/דשבורד
 * @param int $minLevel - רמה מינימלית נדרשת (ברירת מחדל 1)
 * @param bool $checkSpecific - האם לבדוק הרשאות ספציפיות
 */
function checkPageAccess($pageName, $minLevel = 1, $checkSpecific = true) {
    global $currentUserId, $currentUserLevel;
    
    // מנהלים ועורכים מתקדמים (רמה 3+) יכולים לגשת לכל דף
    if ($currentUserLevel >= 3) {
        return true;
    }
    
    // בדיקת רמה מינימלית
    if ($currentUserLevel < $minLevel) {
        accessDenied("אין לך הרשאה לגשת לדף זה. נדרשת רמת הרשאה $minLevel לפחות.");
    }
    
    // בדיקת הרשאות ספציפיות לדשבורדים
    if ($checkSpecific && in_array($pageName, ['dashboard', 'dashboard_deceased', 'dashboard_purchases'])) {
        $dashboardType = str_replace('dashboard_', '', $pageName);
        if ($dashboardType === 'dashboard') $dashboardType = 'main';
        
        if (!hasDashboardPermission($currentUserId, $dashboardType)) {
            // הפנה לדשבורד המתאים למשתמש
            $userDashboard = getUserDashboardUrl($currentUserId, $currentUserLevel);
            header('Location: ' . $userDashboard);
            exit;
        }
    }
    
    return true;
}

/**
 * פונקציה להצגת הודעת אין הרשאה
 */
function accessDenied($message = 'אין לך הרשאה לגשת לדף זה') {
    ?>
    <!DOCTYPE html>
    <html lang="he" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>אין הרשאה</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <i class="fas fa-lock fa-3x text-danger mb-3"></i>
                            <h3 class="text-danger">אין הרשאה</h3>
                            <p><?= htmlspecialchars($message) ?></p>
                            <a href="<?= getUserDashboardUrl($_SESSION['user_id'] ?? 0) ?>" class="btn btn-primary">
                                <i class="fas fa-home"></i> חזרה לדשבורד
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * בדיקה אם המשתמש הוא מנהל
 */
function isAdmin() {
    global $currentUserLevel;
    return $currentUserLevel >= 4;
}

/**
 * בדיקה אם המשתמש הוא עורך
 */
function isEditor() {
    global $currentUserLevel;
    return $currentUserLevel >= 2;
}

/**
 * בדיקה אם המשתמש יכול לראות את הטופס
 */
function canViewForm($formId, $formType = 'deceased') {
    global $currentUserId, $currentUserLevel;
    
    // מנהלים ועורכים יכולים לראות הכל
    if ($currentUserLevel >= 2) {
        return true;
    }
    
    try {
        $db = getDbConnection();
        $table = $formType === 'purchase' ? 'purchase_forms' : 'deceased_forms';
        
        // בדוק אם המשתמש יצר את הטופס
        $stmt = $db->prepare("SELECT created_by FROM $table WHERE form_uuid = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch();
        
        if ($form && $form['created_by'] == $currentUserId) {
            return true;
        }
        
    } catch (Exception $e) {
        error_log("Error checking form view permission: " . $e->getMessage());
    }
    
    return false;
}

/**
 * בדיקה אם המשתמש יכול למחוק
 */
function canDelete() {
    global $currentUserLevel;
    return $currentUserLevel >= 3;
}

/**
 * בדיקה אם המשתמש יכול לייצא נתונים
 */
function canExport() {
    global $currentUserLevel;
    return $currentUserLevel >= 2;
}

/**
 * בדיקה אם המשתמש יכול לנהל משתמשים
 */
function canManageUsers() {
    global $currentUserLevel;
    return $currentUserLevel >= 4;
}
?>