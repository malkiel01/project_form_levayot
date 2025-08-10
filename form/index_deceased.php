<?php
// form/index_deceased.php - הקובץ הראשי של הטופס

require_once '../config.php';
require_once '../DeceasedForm.php';
require_once 'includes/form_auth.php';
require_once 'includes/form_header.php';
require_once 'includes/form_sections.php';
require_once 'includes/form_scripts.php';

// טיפול באימות ובהרשאות
$authResult = handleFormAuth();
extract($authResult); // מחלץ: $isLinkAccess, $linkPermissions, $viewOnly, $formUuid, $userPermissionLevel

// טיפול בנתוני הטופס
$formHandler = handleFormData($formUuid, $userPermissionLevel);
extract($formHandler); // מחלץ: $isNewForm, $formData, $form, $successMessage, $errorMessage, $errors

// קבלת נתוני עזר
$formHelpers = getFormHelpers($form, $formData, $userPermissionLevel);
extract($formHelpers); // מחלץ: $requiredFields, $cemeteries, $blocks, $rows, $graves, $plots

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$viewOnly) {
    $postResult = handleFormSubmit($form, $formUuid, $isNewForm, $userPermissionLevel);
    if ($postResult['success']) {
        if ($postResult['redirect']) {
            header("Location: " . $postResult['redirect']);
            exit;
        }
        $successMessage = $postResult['message'];
        if (isset($postResult['formData'])) {
            $formData = $postResult['formData'];
        }
    } else {
        $errorMessage = $postResult['message'];
        if (isset($postResult['errors'])) {
            $errors = $postResult['errors'];
        }
    }
}

// בדוק הודעות מה-session
if (isset($_SESSION['form_saved_message'])) {
    $successMessage = $_SESSION['form_saved_message'];
    unset($_SESSION['form_saved_message']);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <?php renderFormHead($isNewForm); ?>
    <style>
        /* התאמות עיצוב כשיש תפריט */
        <?php if (!$isLinkAccess && $userPermissionLevel > 1): ?>
        body {
            padding-top: 0;
        }
        .container {
            margin-top: 20px;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <?php 
    // הצג תפריט רק למשתמשים מחוברים עם הרשאה גבוהה מצופה (רמה 2 ומעלה)
    if (!$isLinkAccess && isset($_SESSION['user_id']) && $userPermissionLevel > 1) {
        $navBasePath = '../';
        include '../includes/nav.php';
    }
    ?>
    
    <?php renderLoadingOverlay(); ?>
    <?php renderUserStatusIndicator($isLinkAccess, $viewOnly); ?>
    
    <div class="container">
        <div class="form-container">
            <?php renderFormHeader($isNewForm, $formUuid, $isLinkAccess, $viewOnly); ?>
            
            <?php if (!$isNewForm): ?>
                <?php renderFormStatus($formData); ?>
            <?php endif; ?>
            
            <?php renderMessages($successMessage ?? null, $errorMessage ?? null); ?>
            <?php renderProgressBar($formData['progress_percentage'] ?? 0); ?>
            <?php renderMissingFieldsAlert(); ?>
            
            <form method="POST" id="deceasedForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="form_uuid" value="<?= $formUuid ?>">
                
                <?php renderDeceasedSection($formData, $form, $requiredFields, $errors ?? [], $viewOnly); ?>
                <?php renderDeathSection($formData, $form, $requiredFields, $errors ?? [], $viewOnly); ?>
                <?php renderCemeterySection($formData, $form, $cemeteries, $blocks, $plots, $rows, $areaGraves, $graves, $viewOnly); ?>
                <?php renderInformantSection($formData, $form, $requiredFields, $errors ?? [], $viewOnly); ?>
                <!-- הוסף את מנהל הקבצים רק אם לא טופס חדש -->
                <?php 
                if (!$isNewForm): 
                    include 'includes/file_manager.php';
                endif; 
                ?>
                <?php renderSignatureSection($formData, $viewOnly); ?>
                <?php renderActionButtons($isNewForm, $formUuid, $viewOnly, $isLinkAccess); ?>
            </form>
        </div>
    </div>

    <?php renderShareModals(); ?>
    
    <script>
        const formConfig = {
            isNewForm: <?= $isNewForm ? 'true' : 'false' ?>,
            isViewOnly: <?= $viewOnly ? 'true' : 'false' ?>,
            isUserLoggedIn: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>,
            isLinkAccess: <?= $isLinkAccess ? 'true' : 'false' ?>,
            formUuid: '<?= $formUuid ?>',
            requiredFields: <?= json_encode($requiredFields) ?>,
            csrfToken: '<?= $_SESSION['csrf_token'] ?>',
            // הוספת נתוני הרשאות
            userPermissionLevel: <?= isset($_SESSION['user_id']) ? $_SESSION['permission_level'] : 0 ?>,
            canShare: <?= (isset($_SESSION['user_id']) && isset($_SESSION['permission_level']) && $_SESSION['permission_level'] >= 3) ? 'true' : 'false' ?>
        };
        
        const formData = <?= json_encode($formData) ?>;
   
        // וודא שהתפריט נמצא ב-body ולא בתוך קונטיינר
        document.addEventListener('DOMContentLoaded', function() {
            const contextMenu = document.getElementById('contextMenu');
            if (contextMenu && contextMenu.parentElement !== document.body) {
                document.body.appendChild(contextMenu);
            }
        });
    </script>

    <?php renderFormScripts(); ?>

</body>
</html>