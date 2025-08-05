<?php
// form/purchase_form_pdf.php - יצירת PDF של טופס רכישות

require_once '../config.php';
require_once '../PurchaseForm.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// קבלת UUID של הטופס
$formUuid = $_GET['uuid'] ?? null;
if (!$formUuid) {
    die('טופס לא נמצא');
}

// טעינת נתוני הטופס
$form = new PurchaseForm($pdo);
$result = $form->loadForm($formUuid);

if (!$result['success']) {
    die('שגיאה בטעינת הטופס');
}

$formData = $result['formData'];

// יצירת HTML לטופס
ob_start();
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>טופס רכישה - <?= htmlspecialchars($formData['purchaser_first_name'] . ' ' . $formData['purchaser_last_name']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            direction: rtl;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .form-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .form-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            padding-right: 10px;
            vertical-align: top;
        }
        
        .form-value {
            display: table-cell;
            width: 70%;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .signature-section {
            margin-top: 50px;
            border: 1px solid #ddd;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .signature-image {
            max-width: 300px;
            max-height: 100px;
            margin-top: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .status-draft { background-color: #6c757d; }
        .status-submitted { background-color: #ffc107; color: #333; }
        .status-approved { background-color: #28a745; }
        .status-completed { background-color: #17a2b8; }
        .status-rejected { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>טופס רכישת חלקת קבר</h1>
        <p>מספר טופס: <?= substr($formData['form_uuid'], 0, 8) ?></p>
        <p>תאריך יצירה: <?= date('d/m/Y', strtotime($formData['created_at'])) ?></p>
        <p>סטטוס: <span class="status-badge status-<?= $formData['status'] ?>">
            <?php
            $statusTexts = [
                'draft' => 'טיוטה',
                'submitted' => 'ממתין לאישור',
                'approved' => 'מאושר',
                'completed' => 'הושלם',
                'rejected' => 'נדחה'
            ];
            echo $statusTexts[$formData['status']] ?? 'לא ידוע';
            ?>
        </span></p>
    </div>
    
    <!-- פרטי הרוכש -->
    <div class="section">
        <h2 class="section-title">פרטי הרוכש</h2>
        <div class="form-row">
            <div class="form-label">שם מלא:</div>
            <div class="form-value"><?= htmlspecialchars($formData['purchaser_first_name'] . ' ' . $formData['purchaser_last_name']) ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">תעודת זהות:</div>
            <div class="form-value"><?= htmlspecialchars($formData['purchaser_id'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">טלפון:</div>
            <div class="form-value"><?= htmlspecialchars($formData['purchaser_phone'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">אימייל:</div>
            <div class="form-value"><?= htmlspecialchars($formData['purchaser_email'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">כתובת:</div>
            <div class="form-value"><?= htmlspecialchars($formData['purchaser_address'] ?? '-') ?></div>
        </div>
    </div>
    
    <!-- פרטי הרכישה -->
    <div class="section">
        <h2 class="section-title">פרטי הרכישה</h2>
        <div class="form-row">
            <div class="form-label">תאריך רכישה:</div>
            <div class="form-value"><?= $formData['purchase_date'] ? date('d/m/Y', strtotime($formData['purchase_date'])) : '-' ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">סוג רכישה:</div>
            <div class="form-value">
                <?php
                $purchaseTypes = PurchaseForm::getPurchaseTypes();
                echo htmlspecialchars($purchaseTypes[$formData['purchase_type']] ?? '-');
                ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label">מספר חוזה:</div>
            <div class="form-value"><?= htmlspecialchars($formData['contract_number'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">מחיר רכישה:</div>
            <div class="form-value">₪<?= number_format($formData['purchase_price'] ?? 0, 2) ?></div>
        </div>
    </div>
    
    <!-- פרטי החלקה -->
    <div class="section">
        <h2 class="section-title">פרטי החלקה</h2>
        <div class="form-row">
            <div class="form-label">בית עלמין:</div>
            <div class="form-value"><?= htmlspecialchars($formData['cemetery_name'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">גוש:</div>
            <div class="form-value"><?= htmlspecialchars($formData['block_name'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">חלקה:</div>
            <div class="form-value"><?= htmlspecialchars($formData['section_name'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">שורה:</div>
            <div class="form-value"><?= htmlspecialchars($formData['row_number'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">קבר:</div>
            <div class="form-value"><?= htmlspecialchars($formData['grave_number'] ?? '-') ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">חלקת קבר:</div>
            <div class="form-value"><?= htmlspecialchars($formData['plot_number'] ?? '-') ?></div>
        </div>
    </div>
    
    <!-- פרטי תשלום -->
    <div class="section">
        <h2 class="section-title">פרטי תשלום</h2>
        <div class="form-row">
            <div class="form-label">אמצעי תשלום:</div>
            <div class="form-value">
                <?php
                $paymentMethods = PurchaseForm::getPaymentMethods();
                echo htmlspecialchars($paymentMethods[$formData['payment_method']] ?? '-');
                ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label">סכום ששולם:</div>
            <div class="form-value">₪<?= number_format($formData['payment_amount'] ?? 0, 2) ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">תאריך תשלום:</div>
            <div class="form-value"><?= $formData['payment_date'] ? date('d/m/Y', strtotime($formData['payment_date'])) : '-' ?></div>
        </div>
        <div class="form-row">
            <div class="form-label">יתרה לתשלום:</div>
            <div class="form-value">₪<?= number_format($formData['remaining_balance'] ?? 0, 2) ?></div>
        </div>
        <?php if ($formData['installments']): ?>
        <div class="form-row">
            <div class="form-label">מספר תשלומים:</div>
            <div class="form-value"><?= htmlspecialchars($formData['installments']) ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- הנהנים -->
    <?php if (!empty($formData['beneficiaries'])): ?>
    <div class="section">
        <h2 class="section-title">הנהנים/זכאים לשימוש בחלקה</h2>
        <?php foreach ($formData['beneficiaries'] as $index => $beneficiary): ?>
            <?php if (!empty($beneficiary['name'])): ?>
            <div style="margin-bottom: 15px; padding: 10px; background-color: #f5f5f5;">
                <div class="form-row">
                    <div class="form-label">שם מלא:</div>
                    <div class="form-value"><?= htmlspecialchars($beneficiary['name']) ?></div>
                </div>
                <div class="form-row">
                    <div class="form-label">תעודת זהות:</div>
                    <div class="form-value"><?= htmlspecialchars($beneficiary['id_number'] ?? '-') ?></div>
                </div>
                <div class="form-row">
                    <div class="form-label">קרבה לרוכש:</div>
                    <div class="form-value"><?= htmlspecialchars($beneficiary['relation'] ?? '-') ?></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- הערות -->
    <?php if ($formData['notes'] || $formData['special_conditions']): ?>
    <div class="section">
        <h2 class="section-title">הערות והתניות</h2>
        <?php if ($formData['notes']): ?>
        <div class="form-row">
            <div class="form-label">הערות כלליות:</div>
            <div class="form-value"><?= nl2br(htmlspecialchars($formData['notes'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($formData['special_conditions']): ?>
        <div class="form-row">
            <div class="form-label">תנאים מיוחדים:</div>
            <div class="form-value"><?= nl2br(htmlspecialchars($formData['special_conditions'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- חתימה -->
    <?php if ($formData['signature_data'] || $formData['agreement_check']): ?>
    <div class="signature-section">
        <h2 class="section-title">אישור וחתימה</h2>
        <?php if ($formData['agreement_check']): ?>
        <p>✓ אני מאשר/ת שכל הפרטים שמילאתי נכונים ומדויקים, ואני מסכים/ה לתנאי הרכישה</p>
        <?php endif; ?>
        <?php if ($formData['signature_data']): ?>
        <div class="form-row">
            <div class="form-label">חתימה:</div>
            <div class="form-value">
                <img src="<?= $formData['signature_data'] ?>" alt="חתימה" class="signature-image">
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>הופק על ידי מערכת ניהול בתי עלמין - <?= date('d/m/Y H:i') ?></p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// במקום להשתמש בספריית PDF, נציג את ה-HTML עם הוראה להדפסה
header('Content-Type: text/html; charset=utf-8');
echo $html;
?>
<script>
// הדפסה אוטומטית והמרה ל-PDF דרך הדפדפן
window.onload = function() {
    window.print();
}
</script>