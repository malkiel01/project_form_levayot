<?php
// includes/nav_view_only.php - תפריט ניווט לצפייה בלבד
?>
<nav class="navbar navbar-dark navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-book-dead me-2"></i>
            מערכת ניהול לוויות - צפייה בלבד
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../<?php echo LOGOUT_URL; ?>">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        יציאה
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>