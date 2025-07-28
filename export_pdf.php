<?php
// export_pdf.php - ייצוא טופס ל-PDF

require_once 'config.php';
require_once 'DeceasedForm.php';
require_once 'vendor/autoload.php'; // עבור TCPDF

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$formUuid = $_GET['id'] ?? null;
if (!$formUuid) {
    header('Location: forms_list.php');
    exit;
}

$userPermissionLevel = $_SESSION['permission_level'] ?? 1;
$form = new DeceasedForm($formUuid, $userPermissionLevel);

// בדיקה אם הטופס נמצא
if (!$form->getFormData()) {
    header('Location: forms_list.php');
    exit;
}

$formData = $form->getFormData();

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
        FROM deceased_forms df
        LEFT JOIN cemeteries c ON df.cemetery_id = c.id
        LEFT JOIN blocks b ON df.block_id = b.id
        LEFT JOIN sections s ON df.section_id = s.id
        LEFT JOIN rows r ON df.row_id = r.id
        LEFT JOIN graves g ON df.grave_id = g.id
        LEFT JOIN plots p ON df.plot_id = p.id
        WHERE df.form_uuid = ?
    ");
    $stmt->execute([$formUuid]);
    $locationDetails = $stmt->fetch();
}

// יצירת PDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 16);
        $this->Cell(0, 10, 'טופס הזנת נפטר', 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 10, 'עמוד ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// יצירת אובייקט PDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Cemetery Management System');
$pdf->SetAuthor($_SESSION['full_name'] ?? $_SESSION['username']);
$pdf->SetTitle('טופס נפטר - ' . $formData['deceased_name']);
$pdf->SetSubject('טופס הזנת נפטר');

// הגדרות
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('dejavusans', '', 11);

// הוספת דף
$pdf->AddPage();

// כותרת וסטטוס
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 5, 'מספר טופס: ' . $formData['form_uuid'], 0, 1, 'C');

$statusText = [
    'draft' => 'טיוטה',
    'in_progress' => 'בתהליך',
    'completed' => 'הושלם',
    'archived' => 'ארכיון'
];
$pdf->Cell(0, 5, 'סטטוס: ' . ($statusText[$formData['status']] ?? $formData['status']), 0, 1, 'C');
$pdf->Ln(10);

// פונקציה להוספת שורת מידע
function addInfoRow($pdf, $label, $value, $newLine = true) {
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(40, 6, $label . ':', 0, 0, 'R');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 6, $value, 0, $newLine ? 1 : 0, 'R');
}

// פונקציה להוספת כותרת סעיף
function addSectionTitle($pdf, $title) {
    $pdf->Ln(5);
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, $title, 0, 1, 'R', true);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Ln(2);
}

// פרטי הנפטר
addSectionTitle($pdf, 'פרטי הנפטר');
$idTypes = [
    'tz' => 'תעודת זהות',
    'passport' => 'דרכון',
    'anonymous' => 'אלמוני',
    'baby' => 'תינוק'
];
addInfoRow($pdf, 'סוג זיהוי', $idTypes[$formData['identification_type']] ?? $formData['identification_type']);
if ($formData['identification_number']) {
    addInfoRow($pdf, 'מספר זיהוי', $formData['identification_number']);
}
addInfoRow($pdf, 'שם הנפטר', $formData['deceased_name']);
if ($formData['father_name']) {
    addInfoRow($pdf, 'שם האב', $formData['father_name']);
}
if ($formData['mother_name']) {
    addInfoRow($pdf, 'שם האם', $formData['mother_name']);
}
if ($formData['birth_date']) {
    addInfoRow($pdf, 'תאריך לידה', date('d/m/Y', strtotime($formData['birth_date'])));
}

// פרטי הפטירה
addSectionTitle($pdf, 'פרטי הפטירה');
addInfoRow($pdf, 'תאריך פטירה', date('d/m/Y', strtotime($formData['death_date'])));
addInfoRow($pdf, 'שעת פטירה', date('H:i', strtotime($formData['death_time'])));
if ($formData['death_location']) {
    addInfoRow($pdf, 'מקום הפטירה', $formData['death_location']);
}
addInfoRow($pdf, 'תאריך קבורה', date('d/m/Y', strtotime($formData['burial_date'])));
addInfoRow($pdf, 'שעת קבורה', date('H:i', strtotime($formData['burial_time'])));
addInfoRow($pdf, 'רשיון קבורה', $formData['burial_license']);

// מקום הקבורה
if ($locationDetails && ($locationDetails['cemetery_name'] || $locationDetails['plot_name'])) {
    addSectionTitle($pdf, 'מקום הקבורה');
    if ($locationDetails['cemetery_name']) {
        addInfoRow($pdf, 'בית עלמין', $locationDetails['cemetery_name']);
    }
    
    $location = [];
    if ($locationDetails['block_name']) $location[] = "גוש: " . $locationDetails['block_name'];
    if ($locationDetails['section_name']) $location[] = "חלקה: " . $locationDetails['section_name'];
    if ($locationDetails['row_name']) $location[] = "שורה: " . $locationDetails['row_name'];
    if ($locationDetails['grave_name']) $location[] = "קבר: " . $locationDetails['grave_name'];
    
    if (!empty($location)) {
        addInfoRow($pdf, 'מיקום', implode(', ', $location));
    }
    
    if ($locationDetails['plot_name']) {
        addInfoRow($pdf, 'אחוזת קבר', $locationDetails['plot_name']);
    }
}

// פרטי המודיע
if ($formData['informant_name'] || $formData['informant_phone'] || $formData['informant_relationship']) {
    addSectionTitle($pdf, 'פרטי המודיע');
    if ($formData['informant_name']) {
        addInfoRow($pdf, 'שם', $formData['informant_name']);
    }
    if ($formData['informant_phone']) {
        addInfoRow($pdf, 'טלפון', $formData['informant_phone']);
    }
    if ($formData['informant_relationship']) {
        addInfoRow($pdf, 'קרבה משפחתית', $formData['informant_relationship']);
    }
}

// הערות
if ($formData['notes']) {
    addSectionTitle($pdf, 'הערות');
    $pdf->MultiCell(0, 6, $formData['notes'], 0, 'R');
}

// חתימת לקוח
if ($formData['client_signature']) {
    addSectionTitle($pdf, 'חתימת לקוח');
    $pdf->Ln(5);
    
    // המרת base64 לתמונה
    $signatureData = $formData['client_signature'];
    if (strpos($signatureData, 'data:image') === 0) {
        $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
    }
    $signatureImage = base64_decode($signatureData);
    
    // שמירת התמונה זמנית
    $tempFile = tempnam(sys_get_temp_dir(), 'signature_');
    file_put_contents($tempFile, $signatureImage);
    
    // הוספת התמונה ל-PDF
    $pdf->Image($tempFile, '', '', 60, 30, '', '', 'C');
    
    // מחיקת הקובץ הזמני
    unlink($tempFile);
}

// פרטי מטה-דטה
$pdf->Ln(10);
$pdf->SetFont('dejavusans', 'I', 8);
$pdf->Cell(0, 5, 'נוצר ב: ' . date('d/m/Y H:i', strtotime($formData['created_at'])), 0, 1, 'R');
$pdf->Cell(0, 5, 'עודכן לאחרונה: ' . date('d/m/Y H:i', strtotime($formData['updated_at'])), 0, 1, 'R');

// רישום בלוג
$logStmt = $db->prepare("
    INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
    VALUES (?, (SELECT id FROM deceased_forms WHERE form_uuid = ?), 'export_pdf', ?, ?, ?)
");
$logStmt->execute([
    $_SESSION['user_id'],
    $formUuid,
    json_encode(['form_uuid' => $formUuid]),
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

// פלט PDF
$filename = 'deceased_form_' . $formData['deceased_name'] . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'D');