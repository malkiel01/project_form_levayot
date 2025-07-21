<?php
// export.php - ייצוא נתונים לאקסל

require_once 'config.php';
require_once 'vendor/autoload.php'; // עבור PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDbConnection();
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

// פרמטרים מה-URL (אותם פרמטרים כמו ברשימת הטפסים)
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$cemetery = $_GET['cemetery'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// בניית שאילתה
$where = ["1=1"];
$params = [];

// הגבלה למשתמשים שאינם מנהלים
if ($userPermissionLevel < 4) {
    $where[] = "df.created_by = ?";
    $params[] = $_SESSION['user_id'];
}

// סינונים
if ($search) {
    $where[] = "(df.deceased_name LIKE ? OR df.identification_number LIKE ? OR df.form_uuid LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($status) {
    $where[] = "df.status = ?";
    $params[] = $status;
}

if ($cemetery) {
    $where[] = "df.cemetery_id = ?";
    $params[] = $cemetery;
}

if ($dateFrom) {
    $where[] = "df.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $where[] = "df.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(" AND ", $where);

// שליפת הנתונים
$stmt = $db->prepare("
    SELECT 
        df.*,
        c.name as cemetery_name,
        b.name as block_name,
        s.name as section_name,
        r.name as row_name,
        g.name as grave_name,
        p.name as plot_name,
        u.full_name as created_by_name
    FROM deceased_forms df
    LEFT JOIN cemeteries c ON df.cemetery_id = c.id
    LEFT JOIN blocks b ON df.block_id = b.id
    LEFT JOIN sections s ON df.section_id = s.id
    LEFT JOIN rows r ON df.row_id = r.id
    LEFT JOIN graves g ON df.grave_id = g.id
    LEFT JOIN plots p ON df.plot_id = p.id
    LEFT JOIN users u ON df.created_by = u.id
    WHERE $whereClause
    ORDER BY df.created_at DESC
");
$stmt->execute($params);
$data = $stmt->fetchAll();

// יצירת הספרדשיט
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('רשימת נפטרים');

// הגדרת כיוון RTL
$sheet->setRightToLeft(true);

// כותרות עמודות
$headers = [
    'מספר טופס',
    'סטטוס',
    'התקדמות %',
    'סוג זיהוי',
    'מספר זיהוי',
    'שם הנפטר',
    'שם האב',
    'שם האם',
    'תאריך לידה',
    'תאריך פטירה',
    'שעת פטירה',
    'מקום פטירה',
    'תאריך קבורה',
    'שעת קבורה',
    'רשיון קבורה',
    'בית עלמין',
    'גוש',
    'חלקה',
    'שורה',
    'קבר',
    'אחוזת קבר',
    'שם המודיע',
    'טלפון המודיע',
    'קרבה',
    'הערות',
    'נוצר על ידי',
    'תאריך יצירה',
    'תאריך עדכון אחרון'
];

// כתיבת הכותרות
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// עיצוב הכותרות
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '007BFF'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$sheet->getStyle('A1:AB1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// כתיבת הנתונים
$row = 2;
foreach ($data as $form) {
    // תרגום סטטוסים
    $statusText = [
        'draft' => 'טיוטה',
        'in_progress' => 'בתהליך',
        'completed' => 'הושלם',
        'archived' => 'ארכיון'
    ];
    
    // תרגום סוגי זיהוי
    $idTypes = [
        'tz' => 'תעודת זהות',
        'passport' => 'דרכון',
        'anonymous' => 'אלמוני',
        'baby' => 'תינוק'
    ];
    
    $sheet->setCellValue('A' . $row, $form['form_uuid']);
    $sheet->setCellValue('B' . $row, $statusText[$form['status']] ?? $form['status']);
    $sheet->setCellValue('C' . $row, $form['progress_percentage']);
    $sheet->setCellValue('D' . $row, $idTypes[$form['identification_type']] ?? $form['identification_type']);
    $sheet->setCellValue('E' . $row, $form['identification_number']);
    $sheet->setCellValue('F' . $row, $form['deceased_name']);
    $sheet->setCellValue('G' . $row, $form['father_name']);
    $sheet->setCellValue('H' . $row, $form['mother_name']);
    $sheet->setCellValue('I' . $row, $form['birth_date'] ? date('d/m/Y', strtotime($form['birth_date'])) : '');
    $sheet->setCellValue('J' . $row, date('d/m/Y', strtotime($form['death_date'])));
    $sheet->setCellValue('K' . $row, date('H:i', strtotime($form['death_time'])));
    $sheet->setCellValue('L' . $row, $form['death_location']);
    $sheet->setCellValue('M' . $row, date('d/m/Y', strtotime($form['burial_date'])));
    $sheet->setCellValue('N' . $row, date('H:i', strtotime($form['burial_time'])));
    $sheet->setCellValue('O' . $row, $form['burial_license']);
    $sheet->setCellValue('P' . $row, $form['cemetery_name']);
    $sheet->setCellValue('Q' . $row, $form['block_name']);
    $sheet->setCellValue('R' . $row, $form['section_name']);
    $sheet->setCellValue('S' . $row, $form['row_name']);
    $sheet->setCellValue('T' . $row, $form['grave_name']);
    $sheet->setCellValue('U' . $row, $form['plot_name']);
    $sheet->setCellValue('V' . $row, $form['informant_name']);
    $sheet->setCellValue('W' . $row, $form['informant_phone']);
    $sheet->setCellValue('X' . $row, $form['informant_relationship']);
    $sheet->setCellValue('Y' . $row, $form['notes']);
    $sheet->setCellValue('Z' . $row, $form['created_by_name']);
    $sheet->setCellValue('AA' . $row, date('d/m/Y H:i', strtotime($form['created_at'])));
    $sheet->setCellValue('AB' . $row, date('d/m/Y H:i', strtotime($form['updated_at'])));
    
    // עיצוב שורה לפי סטטוס
    if ($form['status'] === 'completed') {
        $sheet->getStyle('A' . $row . ':AB' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D4EDDA');
    } elseif ($form['status'] === 'draft') {
        $sheet->getStyle('A' . $row . ':AB' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F8F9FA');
    }
    
    $row++;
}

// התאמת רוחב עמודות
foreach (range('A', 'AB') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// הוספת מסגרת לכל הטבלה
$sheet->getStyle('A1:AB' . ($row - 1))->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
]);

// הוספת דף סיכום
$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle('סיכום');
$summarySheet->setRightToLeft(true);

// סטטיסטיקות
$summarySheet->setCellValue('A1', 'סיכום נתונים');
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

$summarySheet->setCellValue('A3', 'סה"כ טפסים:');
$summarySheet->setCellValue('B3', count($data));

$summarySheet->setCellValue('A4', 'תאריך הפקה:');
$summarySheet->setCellValue('B4', date('d/m/Y H:i'));

$summarySheet->setCellValue('A5', 'הופק על ידי:');
$summarySheet->setCellValue('B5', $_SESSION['full_name'] ?? $_SESSION['username']);

// התפלגות לפי סטטוס
$summarySheet->setCellValue('A7', 'התפלגות לפי סטטוס:');
$summarySheet->getStyle('A7')->getFont()->setBold(true);

$statusCount = array_count_values(array_column($data, 'status'));
$row = 8;
foreach ($statusCount as $status => $count) {
    $summarySheet->setCellValue('A' . $row, $statusText[$status] ?? $status);
    $summarySheet->setCellValue('B' . $row, $count);
    $row++;
}

// רישום בלוג
$logStmt = $db->prepare("
    INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
    VALUES (?, 'export_excel', ?, ?, ?)
");
$logStmt->execute([
    $_SESSION['user_id'],
    json_encode(['records_count' => count($data), 'filters' => $_GET]),
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

// יצירת הקובץ
$writer = new Xlsx($spreadsheet);
$filename = 'deceased_forms_' . date('Ymd_His') . '.xlsx';

// הגדרת headers להורדה
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;