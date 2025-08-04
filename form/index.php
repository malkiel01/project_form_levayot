<?php
// form/index.php - תיקון לתמיכה בסוגי טפסים
require_once '../config.php';
session_start();

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

// קבלת חיבור לדטאבייס
$db = getDbConnection();

// קבלת סוג הטופס
$formType = $_GET['type'] ?? 'deceased';
$formId = $_GET['id'] ?? null;

// טעינת המחלקה המתאימה
if ($formType === 'purchase') {
    require_once '../classes/PurchaseForm.php';
    $form = new PurchaseForm($db);
    $formTitle = $formId ? 'עריכת טופס רכישה' : 'טופס רכישה חדש';
} else {
    require_once '../DeceasedForm.php';
    $form = new DeceasedForm($db);
    $formTitle = $formId ? 'עריכת טופס נפטר' : 'טופס נפטר חדש';
}

// אם יש ID, טען את הנתונים
$formData = null;
if ($formId) {
    $formData = $form->getFormById($formId);
    if (!$formData) {
        header("Location: " . DASHBOARD_URL . "?error=form_not_found");
        exit;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $formTitle ?> - מערכת ניהול נפטרים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .section-card {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .section-header {
            background: #e9ecef;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
        }
        .section-body {
            padding: 20px;
        }
        .field-group {
            margin-bottom: 15px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="form-container">
            <div class="form-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><?= $formTitle ?></h1>
                        <p class="text-muted mb-0">
                            <?= $formType === 'purchase' ? 'מלא את פרטי הרכישה' : 'מלא את פרטי הנפטר' ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="<?= DASHBOARD_URL ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-right me-2"></i>חזרה לדשבורד
                        </a>
                    </div>
                </div>
            </div>

            <form id="mainForm" method="POST" action="<?= SITE_URL ?>/ajax/save_form.php" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="<?= $formType ?>">
                <?php if ($formId): ?>
                    <input type="hidden" name="form_id" value="<?= $formId ?>">
                <?php endif; ?>

                <?php if ($formType === 'purchase'): ?>
                    <!-- טופס רכישה -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="mb-0"><i class="fas fa-user me-2"></i>פרטי הרוכש</h3>
                        </div>
                        <div class="section-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label required">שם פרטי</label>
                                        <input type="text" class="form-control" name="buyer_first_name" 
                                               value="<?= $formData['buyer_first_name'] ?? '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label required">שם משפחה</label>
                                        <input type="text" class="form-control" name="buyer_last_name" 
                                               value="<?= $formData['buyer_last_name'] ?? '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label required">תעודת זהות</label>
                                        <input type="text" class="form-control" name="buyer_id" 
                                               value="<?= $formData['buyer_id'] ?? '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label">טלפון</label>
                                        <input type="tel" class="form-control" name="buyer_phone" 
                                               value="<?= $formData['buyer_phone'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>פרטי החלקה</h3>
                        </div>
                        <div class="section-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label required">בית עלמין</label>
                                        <select class="form-select" name="cemetery_id" id="cemetery_id" required>
                                            <option value="">בחר בית עלמין</option>
                                            <?php
                                            $cemeteries = $db->query("SELECT * FROM cemeteries ORDER BY name")->fetchAll();
                                            foreach ($cemeteries as $cemetery):
                                            ?>
                                                <option value="<?= $cemetery['id'] ?>" 
                                                        <?= ($formData['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cemetery['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label">בלוק</label>
                                        <select class="form-select" name="block_id" id="block_id">
                                            <option value="">בחר בלוק</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label">שורה</label>
                                        <input type="text" class="form-control" name="plot_row" 
                                               value="<?= $formData['plot_row'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="field-group">
                                        <label class="form-label">מספר</label>
                                        <input type="text" class="form-control" name="plot_number" 
                                               value="<?= $formData['plot_number'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- טופס נפטר - השתמש במבנה הקיים -->
                    <?php include '../includes/deceased_form_sections.php'; ?>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>שמור טופס
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg ms-2" onclick="saveDraft()">
                        <i class="fas fa-file-alt me-2"></i>שמור כטיוטה
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // טעינת נתונים דינמיים
        $('#cemetery_id').change(function() {
            const cemeteryId = $(this).val();
            if (cemeteryId) {
                $.get('<?= SITE_URL ?>/ajax/get_blocks.php', {cemetery_id: cemeteryId}, function(data) {
                    $('#block_id').html(data);
                });
            }
        });

        // שמירת טיוטה
        function saveDraft() {
            const formData = $('#mainForm').serialize() + '&is_draft=1';
            $.post('<?= SITE_URL ?>/ajax/save_form.php', formData, function(response) {
                if (response.success) {
                    alert('הטופס נשמר כטיוטה');
                } else {
                    alert('שגיאה בשמירת הטיוטה');
                }
            }, 'json');
        }

        // אתחול הטופס אם יש נתונים
        <?php if ($formId && $formType === 'purchase' && isset($formData['cemetery_id'])): ?>
        $(document).ready(function() {
            $('#cemetery_id').trigger('change');
            setTimeout(function() {
                $('#block_id').val('<?= $formData['block_id'] ?? '' ?>');
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>