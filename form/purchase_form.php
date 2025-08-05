<?php
// form/purchase_form.php - הקובץ הראשי של טופס רכישות

require_once '../config.php';
require_once '../PurchaseForm.php';
require_once 'includes/form_auth.php';
require_once 'form/includes/purchase_form_header.php';
require_once 'includes/purchase_form_sections.php';
require_once 'includes/purchase_form_scripts.php';

// טיפול באימות ובהרשאות
$authResult = handlePurchaseFormAuth();
extract($authResult); // מחלץ: $isLinkAccess, $linkPermissions, $viewOnly, $formUuid, $userPermissionLevel

// טיפול בנתוני הטופס
$formHandler = handlePurchaseFormData($formUuid, $userPermissionLevel);
extract($formHandler); // מחלץ: $isNewForm, $formData, $form, $successMessage, $errorMessage, $errors

// קבלת נתוני עזר
$formHelpers = getPurchaseFormHelpers($form, $formData, $userPermissionLevel);
extract($formHelpers); // מחלץ: $requiredFields, $cemeteries, $blocks, $sections, $rows, $graves, $plots, $paymentMethods, $purchaseTypes

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$viewOnly) {
    $postResult = handlePurchaseFormSubmit($form, $formUuid, $isNewForm, $userPermissionLevel);
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
if (isset($_SESSION['purchase_form_saved_message'])) {
    $successMessage = $_SESSION['purchase_form_saved_message'];
    unset($_SESSION['purchase_form_saved_message']);
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <?php renderPurchaseFormHead($isNewForm); ?>
</head>
<body>
    <?php renderLoadingOverlay(); ?>
    <?php renderUserStatusIndicator($isLinkAccess, $viewOnly); ?>
    
    <div class="container">
        <div class="form-container">
            <?php renderPurchaseFormHeader($isNewForm, $formUuid, $isLinkAccess, $viewOnly); ?>
            
            <?php if (!$isNewForm): ?>
                <?php renderPurchaseFormStatus($formData); ?>
            <?php endif; ?>
            
            <?php renderMessages($successMessage ?? null, $errorMessage ?? null); ?>
            <?php renderProgressBar($formData['progress_percentage'] ?? 0); ?>
            <?php renderMissingFieldsAlert(); ?>
            
            <form method="POST" id="purchaseForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- סעיף פרטי הרוכש -->
                <?php renderPurchaserSection($formData, $form, $requiredFields, $errors ?? [], $viewOnly); ?>
                
                <!-- סעיף פרטי הרכישה -->
                <?php renderPurchaseDetailsSection($formData, $form, $purchaseTypes, $requiredFields, $errors ?? [], $viewOnly); ?>
                
                <!-- סעיף פרטי החלקה -->
                <?php renderPlotSection($formData, $form, $cemeteries, $blocks, $sections, $rows, $graves, $plots, $viewOnly); ?>
                
                <!-- סעיף פרטי תשלום -->
                <?php renderPaymentSection($formData, $form, $paymentMethods, $requiredFields, $errors ?? [], $viewOnly); ?>
                
                <!-- סעיף הנהנים/זכאים -->
                <?php renderBeneficiariesSection($formData, $form, $requiredFields, $errors ?? [], $viewOnly); ?>
                
                <!-- סעיף הערות והתניות -->
                <?php renderNotesSection($formData, $form, $viewOnly); ?>
                
                <!-- מנהל קבצים - רק אם לא טופס חדש -->
                <?php 
                if (!$isNewForm): 
                    include 'includes/purchase_file_manager.php';
                endif; 
                ?>
                
                <!-- סעיף חתימה -->
                <?php renderPurchaseSignatureSection($formData, $viewOnly); ?>
                
                <!-- כפתורי פעולה -->
                <?php renderPurchaseActionButtons($isNewForm, $formUuid, $viewOnly, $isLinkAccess); ?>
            </form>
        </div>
    </div>

    <?php renderPurchaseShareModals(); ?>
    
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
            userPermissionLevel: <?= isset($_SESSION['permission_level']) ? $_SESSION['permission_level'] : 0 ?>,
            canShare: <?= (isset($_SESSION['user_id']) && isset($_SESSION['permission_level']) && $_SESSION['permission_level'] >= 3) ? 'true' : 'false' ?>
        };
        
        const formData = <?= json_encode($formData) ?>;
        
        // אובייקט תרגומים לשדות הטופס
        const fieldTranslations = {
            // פרטי הרוכש
            'purchaser_first_name': 'שם פרטי של הרוכש',
            'purchaser_last_name': 'שם משפחה של הרוכש',
            'purchaser_id': 'ת.ז. של הרוכש',
            'purchaser_phone': 'טלפון של הרוכש',
            'purchaser_email': 'אימייל של הרוכש',
            'purchaser_address': 'כתובת של הרוכש',
            
            // פרטי הרכישה
            'purchase_date': 'תאריך רכישה',
            'purchase_type': 'סוג רכישה',
            'contract_number': 'מספר חוזה',
            'purchase_price': 'מחיר רכישה',
            
            // פרטי החלקה
            'cemetery_id': 'בית עלמין',
            'block_id': 'גוש',
            'section_id': 'חלקה',
            'row_id': 'שורה',
            'grave_id': 'קבר',
            'plot_id': 'חלקת קבר',
            
            // פרטי תשלום
            'payment_method': 'אמצעי תשלום',
            'payment_amount': 'סכום ששולם',
            'payment_date': 'תאריך תשלום',
            'remaining_balance': 'יתרה לתשלום',
            'installments': 'מספר תשלומים',
            
            // הנהנים
            'beneficiary_name': 'שם הנהנה',
            'beneficiary_id': 'ת.ז. הנהנה',
            'beneficiary_relation': 'קרבה לרוכש',
            
            // כללי
            'notes': 'הערות',
            'special_conditions': 'תנאים מיוחדים'
        };
   
        // וודא שהתפריט נמצא ב-body ולא בתוך קונטיינר
        document.addEventListener('DOMContentLoaded', function() {
            const contextMenu = document.getElementById('contextMenu');
            if (contextMenu && contextMenu.parentElement !== document.body) {
                document.body.appendChild(contextMenu);
            }
            
            // אתחול תאריכים
            initializeDatePickers();
            
            // אתחול חישובי תשלומים
            initializePaymentCalculations();
            
            // אתחול בחירת חלקות
            initializePlotSelection();
        });
    </script>

    <?php renderPurchaseFormScripts(); ?>

</body>
</html>