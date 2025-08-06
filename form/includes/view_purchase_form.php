<?php
// view_purchase_form.php - צפייה בטופס רכישה

require_once '../../config.php';
require_once '../../PurchaseForm.php';

// בדיקת התחברות
$isLoggedIn = isset($_SESSION['user_id']);
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$formUuid = $_GET['id'] ?? null;
if (!$formUuid) {
    header('Location: forms_list.php?type=purchase');
    exit;
}

$form = new PurchaseForm($formUuid, $userPermissionLevel);

// בדיקה אם הטופס נמצא
if (!$form->getFormData()) {
    header('Location: forms_list.php?type=purchase');
    exit;
}

$formData = $form->getFormData();
$documents = $form->getDocuments();

// קבלת פרטי בית העלמין אם קיים
$db = getDbConnection();
$locationDetails = null;
if ($formData['cemetery_id']) {
    $stmt = $db->prepare("
        SELECT 
            c.name as cemetery_name,
            b.name as block_name,
            s.name as section_name,
            r.name as row_name,
            g.name as grave_name,
            p.name as plot_name
        FROM purchase_forms pf
        LEFT JOIN cemeteries c ON pf.cemetery_id = c.id
        LEFT JOIN blocks b ON pf.block_id = b.id
        LEFT JOIN sections s ON pf.section_id = s.id
        LEFT JOIN rows r ON pf.row_id = r.id
        LEFT JOIN graves g ON pf.grave_id = g.id
        LEFT JOIN plots p ON pf.plot_id = p.id
        WHERE pf.form_uuid = ?
    ");
    $stmt->execute([$formUuid]);
    $locationDetails = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>צפייה בטופס רכישה - <?= htmlspecialchars($formData['buyer_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../../css/dashboard-styles-optimized.css" rel="stylesheet">
    <style>
        .view-container {
            max-width: 900px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin: 20px -15px 15px -15px;
            border-right: 4px solid #28a745;
            font-weight: bold;
        }
        .info-row {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .status-badge {
            font-size: 1rem;
        }
        .signature-container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background-color: #f8f9fa;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .amount-highlight {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background-color: white;
            }
            .view-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <?php 
    // טעינת התפריט המתאים
    if (!$isLoggedIn || $userPermissionLevel == 1) {
        require_once '../../includes/nav_view_only.php';
    } else {
        require_once '../../includes/nav.php';
    }
    ?>

    <div class="container-fluid py-4">
        <div class="view-container">
            <!-- כותרת -->
            <div class="text-center mb-4">
                <h2>טופס רכישה</h2>
                <p class="text-muted">מספר טופס: <?= htmlspecialchars($formData['form_uuid']) ?></p>
                <?php if ($formData['contract_number']): ?>
                <p class="text-muted">מספר חוזה: <?= htmlspecialchars($formData['contract_number']) ?></p>
                <?php endif; ?>
                <?php
                $statusLabels = [
                    'draft' => '<span class="badge bg-secondary status-badge">טיוטה</span>',
                    'in_progress' => '<span class="badge bg-warning status-badge">בתהליך</span>',
                    'completed' => '<span class="badge bg-success status-badge">הושלם</span>',
                    'archived' => '<span class="badge bg-dark status-badge">ארכיון</span>'
                ];
                echo $statusLabels[$formData['status']] ?? $formData['status'];
                ?>
            </div>

            <!-- Progress Bar -->
            <div class="progress mb-4" style="height: 25px;">
                <div class="progress-bar bg-success" role="progressbar" 
                     style="width: <?= $formData['progress_percentage'] ?? 0 ?>%">
                    <?= $formData['progress_percentage'] ?? 0 ?>% הושלם
                </div>
            </div>

            <!-- פרטי הרוכש -->
            <div class="section-title">פרטי הרוכש</div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">סוג רוכש:</span>
                        <?php
                        $buyerTypes = [
                            'individual' => 'אדם פרטי',
                            'company' => 'חברה'
                        ];
                        echo $buyerTypes[$formData['buyer_type']] ?? $formData['buyer_type'];
                        ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">סוג זיהוי:</span>
                        <?php
                        $idTypes = [
                            'tz' => 'תעודת זהות',
                            'passport' => 'דרכון',
                            'company_id' => 'ח.פ.'
                        ];
                        echo $idTypes[$formData['buyer_id_type']] ?? $formData['buyer_id_type'];
                        ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">מספר זיהוי:</span>
                        <?= htmlspecialchars($formData['buyer_id_number']) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">שם הרוכש:</span>
                        <strong><?= htmlspecialchars($formData['buyer_name']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if ($formData['buyer_phone']): ?>
                    <div class="info-row">
                        <span class="info-label">טלפון:</span>
                        <?= htmlspecialchars($formData['buyer_phone']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['buyer_email']): ?>
                    <div class="info-row">
                        <span class="info-label">דוא"ל:</span>
                        <?= htmlspecialchars($formData['buyer_email']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['buyer_address']): ?>
                    <div class="info-row">
                        <span class="info-label">כתובת:</span>
                        <?= nl2br(htmlspecialchars($formData['buyer_address'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- פרטי הרכישה -->
            <div class="section-title">פרטי הרכישה</div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">סוג רכישה:</span>
                        <?php
                        $purchaseTypes = [
                            'grave' => 'קבר בודד',
                            'plot' => 'חלקת קבורה',
                            'structure' => 'מבנה',
                            'service' => 'שירות'
                        ];
                        echo $purchaseTypes[$formData['purchase_type']] ?? $formData['purchase_type'];
                        ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">תאריך רכישה:</span>
                        <?= date('d/m/Y', strtotime($formData['purchase_date'])) ?>
                    </div>
                    <?php if ($formData['payment_method']): ?>
                    <div class="info-row">
                        <span class="info-label">אופן תשלום:</span>
                        <?php
                        $paymentMethods = [
                            'cash' => 'מזומן',
                            'check' => 'צ\'ק',
                            'credit' => 'כרטיס אשראי',
                            'transfer' => 'העברה בנקאית',
                            'installments' => 'תשלומים'
                        ];
                        echo $paymentMethods[$formData['payment_method']] ?? $formData['payment_method'];
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">סכום כולל:</span>
                        <span class="amount-highlight">₪<?= number_format($formData['total_amount'], 2) ?></span>
                    </div>
                    <?php if ($formData['paid_amount']): ?>
                    <div class="info-row">
                        <span class="info-label">סכום ששולם:</span>
                        <span class="amount-highlight">₪<?= number_format($formData['paid_amount'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['installments_count'] && $formData['payment_method'] == 'installments'): ?>
                    <div class="info-row">
                        <span class="info-label">מספר תשלומים:</span>
                        <?= $formData['installments_count'] ?>
                    </div>
                    <?php endif; ?>
                    <?php 
                    $remaining = $formData['total_amount'] - $formData['paid_amount'];
                    if ($remaining > 0): 
                    ?>
                    <div class="info-row">
                        <span class="info-label">יתרה לתשלום:</span>
                        <span class="text-danger">₪<?= number_format($remaining, 2) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- מקום הקבר/חלקה -->
            <?php if ($locationDetails && ($locationDetails['cemetery_name'] || $locationDetails['plot_name'])): ?>
            <div class="section-title">מקום הקבר/חלקה</div>
            <div class="row">
                <div class="col-12">
                    <?php if ($locationDetails['cemetery_name']): ?>
                    <div class="info-row">
                        <span class="info-label">בית עלמין:</span>
                        <?= htmlspecialchars($locationDetails['cemetery_name']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($locationDetails['block_name'] || $locationDetails['section_name'] || $locationDetails['row_name'] || $locationDetails['grave_name']): ?>
                    <div class="info-row">
                        <span class="info-label">מיקום:</span>
                        <?php
                        $location = [];
                        if ($locationDetails['block_name']) $location[] = "גוש: " . $locationDetails['block_name'];
                        if ($locationDetails['section_name']) $location[] = "חלקה: " . $locationDetails['section_name'];
                        if ($locationDetails['row_name']) $location[] = "שורה: " . $locationDetails['row_name'];
                        if ($locationDetails['grave_name']) $location[] = "קבר: " . $locationDetails['grave_name'];
                        echo implode(', ', $location);
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($locationDetails['plot_name']): ?>
                    <div class="info-row">
                        <span class="info-label">אחוזת קבר:</span>
                        <?= htmlspecialchars($locationDetails['plot_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- הערות ותנאים מיוחדים -->
            <?php if ($formData['notes'] || $formData['special_conditions']): ?>
            <div class="section-title">הערות ותנאים</div>
            <div class="row">
                <div class="col-12">
                    <?php if ($formData['notes']): ?>
                    <div class="info-row">
                        <span class="info-label">הערות:</span><br>
                        <p><?= nl2br(htmlspecialchars($formData['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['special_conditions']): ?>
                    <div class="info-row">
                        <span class="info-label">תנאים מיוחדים:</span><br>
                        <p><?= nl2br(htmlspecialchars($formData['special_conditions'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- חתימות -->
            <?php if ($formData['buyer_signature'] || $formData['seller_signature']): ?>
            <div class="section-title">חתימות</div>
            <div class="row">
                <?php if ($formData['buyer_signature']): ?>
                <div class="col-md-6">
                    <h6>חתימת הרוכש</h6>
                    <div class="signature-container">
                        <img src="<?= htmlspecialchars($formData['buyer_signature']) ?>" alt="חתימת רוכש" style="max-width: 100%; max-height: 180px;">
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($formData['seller_signature']): ?>
                <div class="col-md-6">
                    <h6>חתימת המוכר</h6>
                    <div class="signature-container">
                        <img src="<?= htmlspecialchars($formData['seller_signature']) ?>" alt="חתימת מוכר" style="max-width: 100%; max-height: 180px;">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- מסמכים -->
            <?php if (!empty($documents)): ?>
            <div class="section-title no-print">מסמכים מצורפים</div>
            <div class="row no-print">
                <div class="col-12">
                    <div class="list-group">
                        <?php foreach ($documents as $doc): ?>
                        <a href="download.php?id=<?= $doc['id'] ?>&type=purchase" class="list-group-item list-group-item-action">
                            <i class="fas fa-file"></i> <?= htmlspecialchars($doc['file_name']) ?>
                            <small class="text-muted">(<?= number_format($doc['file_size'] / 1024, 2) ?> KB)</small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- פרטי מטה-דטה -->
            <div class="row mt-4 pt-3 border-top">
                <div class="col-md-6">
                    <small class="text-muted">
                        נוצר ב: <?= date('d/m/Y H:i', strtotime($formData['created_at'])) ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        עודכן לאחרונה: <?= date('d/m/Y H:i', strtotime($formData['updated_at'])) ?>
                    </small>
                </div>
            </div>

            <!-- כפתורי פעולה -->
            <div class="row mt-4 no-print">
                <div class="col-12 text-center">
                    <?php if ($isLoggedIn && $userPermissionLevel > 1): ?>
                    <a href="../purchase_form.php?uuid=<?= $formData['form_uuid'] ?>" class="btn btn-success">
                        <i class="fas fa-edit"></i> ערוך טופס
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> הדפס
                    </button>
                    <a href="export_pdf.php?id=<?= $formData['form_uuid'] ?>&type=purchase" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> ייצוא ל-PDF
                    </a>
                    <button type="button" class="btn btn-info" onclick="shareForm()">
                        <i class="fas fa-share"></i> שתף
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <a href="forms_list.php?type=purchase" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right"></i> חזרה לרשימה
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareForm() {
            const formUrl = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: 'טופס רכישה - <?= htmlspecialchars($formData['buyer_name']) ?>',
                    url: formUrl
                }).catch(() => {
                    copyToClipboard(formUrl);
                });
            } else {
                copyToClipboard(formUrl);
            }
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('הקישור הועתק ללוח');
            }).catch(() => {
                const input = document.createElement('input');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                alert('הקישור הועתק ללוח');
            });
        }
    </script>
</body>
</html>