<?php
// admin/export_form.php - ייצוא טופס ל-PDF או Excel

require_once '../config.php';
require_once '../DeceasedForm.php';

// בדיקת הרשאות
if (($_SESSION['permission_level'] ?? 0) < 2) {
    header('Location: ../login.php');
    exit;
}

$formId = $_GET['id'] ?? null;
$format = $_GET['format'] ?? 'pdf';

if (!$formId) {
    die('Form ID missing');
}

$form = new DeceasedForm($formId, $_SESSION['permission_level']);
$formData = $form->getFormData();

if (!$formData) {
    die('Form not found');
}

if ($format === 'excel') {
    // ייצוא ל-Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="form_' . $formId . '.xls"');
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; }
            th, td { border: 1px solid black; padding: 8px; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <th colspan="2">טופס נפטר - <?= htmlspecialchars($formData['deceased_name']) ?></th>
            </tr>
            <tr>
                <td><strong>סוג זיהוי</strong></td>
                <td><?= $formData['identification_type'] ?></td>
            </tr>
            <tr>
                <td><strong>מספר זיהוי</strong></td>
                <td><?= $formData['identification_number'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>שם הנפטר</strong></td>
                <td><?= $formData['deceased_name'] ?></td>
            </tr>
            <tr>
                <td><strong>שם האב</strong></td>
                <td><?= $formData['father_name'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>שם האם</strong></td>
                <td><?= $formData['mother_name'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>תאריך לידה</strong></td>
                <td><?= $formData['birth_date'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>תאריך פטירה</strong></td>
                <td><?= $formData['death_date'] ?></td>
            </tr>
            <tr>
                <td><strong>שעת פטירה</strong></td>
                <td><?= $formData['death_time'] ?></td>
            </tr>
            <tr>
                <td><strong>תאריך קבורה</strong></td>
                <td><?= $formData['burial_date'] ?></td>
            </tr>
            <tr>
                <td><strong>שעת קבורה</strong></td>
                <td><?= $formData['burial_time'] ?></td>
            </tr>
            <tr>
                <td><strong>רשיון קבורה</strong></td>
                <td><?= $formData['burial_license'] ?></td>
            </tr>
            <tr>
                <td><strong>מקום הפטירה</strong></td>
                <td><?= $formData['death_location'] ?? '-' ?></td>
            </tr>
        </table>
    </body>
    </html>
    <?php
} else {
    // ייצוא ל-PDF (דוגמה בסיסית - בפועל כדאי להשתמש בספריה כמו TCPDF או mPDF)
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <title>טופס נפטר - <?= htmlspecialchars($formData['deceased_name']) ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                direction: rtl;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .section {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 5px;
            }
            .section-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
                border-bottom: 2px solid #007bff;
                padding-bottom: 5px;
            }
            .field {
                margin-bottom: 8px;
                display: flex;
                justify-content: space-between;
            }
            .field-label {
                font-weight: bold;
                width: 40%;
            }
            .field-value {
                width: 60%;
            }
            @media print {
                body { padding: 0; }
                .section { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>טופס נפטר</h1>
            <p>תאריך הדפסה: <?= date('d/m/Y H:i') ?></p>
        </div>
        
        <div class="section">
            <div class="section-title">פרטי הנפטר</div>
            <div class="field">
                <span class="field-label">סוג זיהוי:</span>
                <span class="field-value"><?= $formData['identification_type'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">מספר זיהוי:</span>
                <span class="field-value"><?= $formData['identification_number'] ?? '-' ?></span>
            </div>
            <div class="field">
                <span class="field-label">שם הנפטר:</span>
                <span class="field-value"><?= $formData['deceased_name'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">שם האב:</span>
                <span class="field-value"><?= $formData['father_name'] ?? '-' ?></span>
            </div>
            <div class="field">
                <span class="field-label">שם האם:</span>
                <span class="field-value"><?= $formData['mother_name'] ?? '-' ?></span>
            </div>
            <div class="field">
                <span class="field-label">תאריך לידה:</span>
                <span class="field-value"><?= $formData['birth_date'] ?? '-' ?></span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">פרטי הפטירה</div>
            <div class="field">
                <span class="field-label">תאריך פטירה:</span>
                <span class="field-value"><?= $formData['death_date'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">שעת פטירה:</span>
                <span class="field-value"><?= $formData['death_time'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">תאריך קבורה:</span>
                <span class="field-value"><?= $formData['burial_date'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">שעת קבורה:</span>
                <span class="field-value"><?= $formData['burial_time'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">רשיון קבורה:</span>
                <span class="field-value"><?= $formData['burial_license'] ?></span>
            </div>
            <div class="field">
                <span class="field-label">מקום הפטירה:</span>