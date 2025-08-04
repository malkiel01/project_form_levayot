<?php
// includes/nav.php - תפריט ניווט משותף

// בדיקת הרשאות
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;
$username = $_SESSION['username'] ?? 'משתמש';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= SITE_URL ?>">
            <i class="fas fa-home"></i> מערכת קדישא
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- דשבורד -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-line"></i> דשבורדים
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">
                            <i class="fas fa-th"></i> דשבורד משולב
                        </a></li>
                        <li><a class="dropdown-item" href="dashboard_deceased.php">
                            <i class="fas fa-cross"></i> דשבורד נפטרים
                        </a></li>
                        <li><a class="dropdown-item" href="dashboard_purchases.php">
                            <i class="fas fa-shopping-cart"></i> דשבורד רכישות
                        </a></li>
                    </ul>
                </li>
                
                <!-- טפסים -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-file-alt"></i> טפסים
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../form/form.php">
                            <i class="fas fa-plus"></i> טופס נפטר חדש
                        </a></li>
                        <li><a class="dropdown-item" href="../form/purchase_form.php">
                            <i class="fas fa-plus"></i> טופס רכישה חדש
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../forms_list.php?type=deceased">
                            <i class="fas fa-list"></i> רשימת נפטרים
                        </a></li>
                        <li><a class="dropdown-item" href="../forms_list.php?type=purchase">
                            <i class="fas fa-list"></i> רשימת רכישות
                        </a></li>
                    </ul>
                </li>
                
                <!-- ניהול (למנהלים בלבד) -->
                <?php if ($userPermissionLevel >= 4): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs"></i> ניהול
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../admin/users.php">
                            <i class="fas fa-users"></i> ניהול משתמשים
                        </a></li>
                        <li><a class="dropdown-item" href="../admin/permissions.php">
                            <i class="fas fa-shield-alt"></i> ניהול הרשאות
                        </a></li>
                        <li><a class="dropdown-item" href="../admin/cemeteries.php">
                            <i class="fas fa-map-marked-alt"></i> ניהול בתי עלמין
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../admin/logs.php">
                            <i class="fas fa-history"></i> יומן פעילות
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- פרופיל משתמש -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($username) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php">
                            <i class="fas fa-user"></i> הפרופיל שלי
                        </a></li>
                        <li><a class="dropdown-item" href="../settings.php">
                            <i class="fas fa-cog"></i> הגדרות
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> יציאה
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>