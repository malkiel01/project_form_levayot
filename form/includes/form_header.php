<?php
// form/includes/form_header.php - רכיבי header וכותרות

function renderFormHead($isNewForm) {
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>טופס הזנת נפטר <?= $isNewForm ? '- חדש' : '- עריכה' ?></title>
    <!-- הוסף את השורות האלה לPWA -->
    <link rel="manifest" href="/project_form_levayot/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="/project_form_levayot/icons/icon-192x192.png">
    <!-- סוף הוספות PWA -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="css/form-styles.css" rel="stylesheet">
    <link href="css/file-manager.css" rel="stylesheet">
    <?php
}

function renderLoadingOverlay() {
    ?>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-container">
                <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                    <span class="visually-hidden">טוען...</span>
                </div>
            </div>
            <h3 class="text-white mt-3">שומר נתונים...</h3>
            <div class="progress mt-3" style="width: 300px; margin: 0 auto;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                    role="progressbar" style="width: 100%"></div>
            </div>
            <p class="text-white mt-2">אנא המתן, מעדכן את הטופס במערכת...</p>
        </div>
    </div>
    <?php
}

function renderUserStatusIndicator($isLinkAccess, $viewOnly) {
    ?>
    <div class="user-status">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="badge bg-success">
                <i class="fas fa-user"></i> מחובר: <?= htmlspecialchars($_SESSION['username'] ?? 'משתמש') ?>
            </span>
        <?php else: ?>
            <span class="badge bg-warning">
                <i class="fas fa-user-slash"></i> אורח
            </span>
        <?php endif; ?>
    </div>
    
    <?php if ($isLinkAccess): ?>
    <div class="link-access-notice">
        <i class="fas fa-link"></i>
        <strong>גישה בקישור שיתוף</strong> - 
        <?php if (isset($_SESSION['user_id'])): ?>
            אתה נכנס דרך קישור שיתוף כמשתמש מחובר
        <?php else: ?>
            אתה נכנס דרך קישור שיתוף כאורח
        <?php endif; ?>
        <?php if ($viewOnly): ?>
            <br><small><i class="fas fa-eye"></i> צפייה בלבד - לא ניתן לערוך</small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
}

function renderFormHeader($isNewForm, $formUuid, $isLinkAccess, $viewOnly) {
    ?>
    <h2 class="text-center mb-4">
        <i class="fas fa-file-alt"></i> טופס הזנת נפטר
        <?php if ($isNewForm): ?>
            <span class="badge bg-success">חדש</span>
        <?php else: ?>
            <span class="badge bg-info">עריכה</span>
        <?php endif; ?>
    </h2>
    
    <div class="form-uuid-display text-center">
        <small>גירסה: <strong>7.2</strong></small>
    </div>
    <?php
}

function renderFormStatus($formData) {
    ?>
    <div class="text-center mb-3">
        <span class="status-indicator <?= ($formData['status'] ?? 'draft') === 'completed' ? 'status-completed' : 'status-draft' ?>">
            <?= ($formData['status'] ?? 'draft') === 'completed' ? 'הושלם' : 'טיוטה' ?>
        </span>
    </div>
    <?php
}

function renderMessages($successMessage, $errorMessage) {
    if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif;
    
    if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif;
}

function renderProgressBar($percentage) {
    ?>
    <div class="progress">
        <div class="progress-bar" role="progressbar" 
             style="width: <?= $percentage ?>%">
            <?= $percentage ?>% הושלם
        </div>
    </div>
    <?php
}

function renderMissingFieldsAlert() {
    ?>
    <div id="missingFieldsAlert" class="missing-fields-alert" style="display: none;">
        <i class="fas fa-info-circle"></i>
        <span id="missingFieldsText"></span>
    </div>
    <?php
}