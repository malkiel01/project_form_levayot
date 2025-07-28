<?php
// view_form.php - צפייה בטופס נפטר - גרסה מתוקנת

require_once 'config.php';
require_once 'DeceasedForm.php';

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
$formData = $form->getFormData();
if (!$formData) {
    header('Location: forms_list.php');
    exit;
}

$documents = $form->getDocuments();

// קבלת פרטי בית העלמין אם קיים
$db = getDbConnection();
$locationDetails = null;
if (!empty($formData['cemetery_id'])) {
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

// פונקציות עזר למניעת שגיאות
function safeGet($array, $key, $default = '') {
    return isset($array[$key]) && $array[$key] !== null ? $array[$key] : $default;
}

function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?: $default);
}

function safeDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

function safeTime($time, $format = 'H:i') {
    if (empty($time)) return '-';
    try {
        return date($format, strtotime($time));
    } catch (Exception $e) {
        return '-';
    }
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>צפייה בטופס - <?= safeHtml(safeGet($formData, 'deceased_name', 'טופס נפטר')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
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
            border-right: 4px solid #007bff;
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
        .form-uuid-display {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home"></i> מערכת ניהול נפטרים
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="forms_list.php">
                        <i class="fas fa-arrow-right"></i> חזרה לרשימה
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="view-container">
            <!-- כותרת -->
            <div class="text-center mb-4">
                <h2>טופס הזנת נפטר</h2>
                <div class="form-uuid-display">
                    <small>מספר טופס: <strong><?= safeHtml(safeGet($formData, 'form_uuid', 'לא זמין')) ?></strong></small>
                </div>
                <?php
                $status = safeGet($formData, 'status', 'draft');
                $statusLabels = [
                    'draft' => '<span class="badge bg-secondary status-badge">טיוטה</span>',
                    'in_progress' => '<span class="badge bg-warning status-badge">בתהליך</span>',
                    'completed' => '<span class="badge bg-success status-badge">הושלם</span>',
                    'archived' => '<span class="badge bg-dark status-badge">ארכיון</span>'
                ];
                echo $statusLabels[$status] ?? '<span class="badge bg-secondary status-badge">' . safeHtml($status) . '</span>';
                ?>
            </div>

            <!-- Progress Bar -->
            <div class="progress mb-4" style="height: 25px;">
                <div class="progress-bar" role="progressbar" 
                     style="width: <?= intval(safeGet($formData, 'progress_percentage', 0)) ?>%">
                    <?= intval(safeGet($formData, 'progress_percentage', 0)) ?>% הושלם
                </div>
            </div>

            <!-- פרטי הנפטר -->
            <div class="section-title">פרטי הנפטר</div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">סוג זיהוי:</span>
                        <?php
                        $idType = safeGet($formData, 'identification_type');
                        $idTypes = [
                            'tz' => 'תעודת זהות',
                            'passport' => 'דרכון',
                            'anonymous' => 'אלמוני',
                            'baby' => 'תינוק'
                        ];
                        echo $idTypes[$idType] ?? safeHtml($idType);
                        ?>
                    </div>
                    <?php if (safeGet($formData, 'identification_number')): ?>
                    <div class="info-row">
                        <span class="info-label">מספר זיהוי:</span>
                        <?= safeHtml(safeGet($formData, 'identification_number')) ?>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">שם הנפטר:</span>
                        <strong><?= safeHtml(safeGet($formData, 'deceased_name', 'לא צוין')) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if (safeGet($formData, 'father_name')): ?>
                    <div class="info-row">
                        <span class="info-label">שם האב:</span>
                        <?= safeHtml(safeGet($formData, 'father_name')) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (safeGet($formData, 'mother_name')): ?>
                    <div class="info-row">
                        <span class="info-label">שם האם:</span>
                        <?= safeHtml(safeGet($formData, 'mother_name')) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (safeGet($formData, 'birth_date')): ?>
                    <div class="info-row">
                        <span class="info-label">תאריך לידה:</span>
                        <?= safeDate(safeGet($formData, 'birth_date')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- פרטי הפטירה -->
            <div class="section-title">פרטי הפטירה</div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">תאריך פטירה:</span>
                        <?= safeDate(safeGet($formData, 'death_date')) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">שעת פטירה:</span>
                        <?= safeTime(safeGet($formData, 'death_time')) ?>
                    </div>
                    <?php if (safeGet($formData, 'death_location')): ?>
                    <div class="info-row">
                        <span class="info-label">מקום הפטירה:</span>
                        <?= safeHtml(safeGet($formData, 'death_location')) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">תאריך קבורה:</span>
                        <?= safeDate(safeGet($formData, 'burial_date')) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">שעת קבורה:</span>
                        <?= safeTime(safeGet($formData, 'burial_time')) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">רשיון קבורה:</span>
                        <?= safeHtml(safeGet($formData, 'burial_license', 'לא צוין')) ?>
                    </div>
                </div>
            </div>

            <!-- מקום הקבורה -->
            <?php if ($locationDetails && (safeGet($locationDetails, 'cemetery_name') || safeGet($locationDetails, 'plot_name'))): ?>
            <div class="section-title">מקום הקבורה</div>
            <div class="row">
                <div class="col-12">
                    <?php if (safeGet($locationDetails, 'cemetery_name')): ?>
                    <div class="info-row">
                        <span class="info-label">בית עלמין:</span>
                        <?= safeHtml(safeGet($locationDetails, 'cemetery_name')) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    $locationParts = [];
                    if (safeGet($locationDetails, 'block_name')) $locationParts[] = "גוש: " . safeGet($locationDetails, 'block_name');
                    if (safeGet($locationDetails, 'section_name')) $locationParts[] = "חלקה: " . safeGet($locationDetails, 'section_name');
                    if (safeGet($locationDetails, 'row_name')) $locationParts[] = "שורה: " . safeGet($locationDetails, 'row_name');
                    if (safeGet($locationDetails, 'grave_name')) $locationParts[] = "קבר: " . safeGet($locationDetails, 'grave_name');
                    
                    if (!empty($locationParts)): ?>
                    <div class="info-row">
                        <span class="info-label">מיקום:</span>
                        <?= safeHtml(implode(', ', $locationParts)) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (safeGet($locationDetails, 'plot_name')): ?>
                    <div class="info-row">
                        <span class="info-label">אחוזת קבר:</span>
                        <?= safeHtml(safeGet($locationDetails, 'plot_name')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- פרטי המודיע -->
            <?php if (safeGet($formData, 'informant_name') || safeGet($formData, 'informant_phone') || safeGet($formData, 'informant_relationship')): ?>
            <div class="section-title">פרטי המודיע</div>
            <div class="row">
                <div class="col-md-4">
                    <?php if (safeGet($formData, 'informant_name')): ?>
                    <div class="info-row">
                        <span class="info-label">שם:</span>
                        <?= safeHtml(safeGet($formData, 'informant_name')) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if (safeGet($formData, 'informant_phone')): ?>
                    <div class="info-row">
                        <span class="info-label">טלפון:</span>
                        <?= safeHtml(safeGet($formData, 'informant_phone')) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if (safeGet($formData, 'informant_relationship')): ?>
                    <div class="info-row">
                        <span class="info-label">קרבה:</span>
                        <?= safeHtml(safeGet($formData, 'informant_relationship')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- הערות -->
            <?php if (safeGet($formData, 'notes')): ?>
            <div class="section-title">הערות</div>
            <div class="row">
                <div class="col-12">
                    <p><?= nl2br(safeHtml(safeGet($formData, 'notes'))) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- חתימת לקוח -->
            <?php if (safeGet($formData, 'client_signature')): ?>
            <div class="section-title">חתימת לקוח</div>
            <div class="row">
                <div class="col-12">
                    <div class="signature-container">
                        <img src="<?= safeHtml(safeGet($formData, 'client_signature')) ?>" alt="חתימת לקוח" style="max-width: 100%; max-height: 180px;">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- מסמכים -->
            <?php if (!empty($documents)): ?>
            <div class="section-title no-print">מסמכים מצורפים</div>
            <div class="row no-print">
                <div class="col-12">
                    <div class="list-group">
                        <?php foreach ($documents as $doc): ?>
                        <a href="download.php?id=<?= intval($doc['id']) ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-file"></i> <?= safeHtml(safeGet($doc, 'file_name', 'קובץ')) ?>
                            <small class="text-muted">(<?= number_format(intval(safeGet($doc, 'file_size', 0)) / 1024, 2) ?> KB)</small>
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
                        נוצר ב: <?= safeDate(safeGet($formData, 'created_at'), 'd/m/Y H:i') ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        עודכן לאחרונה: <?= safeDate(safeGet($formData, 'updated_at'), 'd/m/Y H:i') ?>
                    </small>
                </div>
            </div>

            <!-- כפתורי פעולה -->
            <div class="row mt-4 no-print">
                <div class="col-12 text-center">
                    <a href="<?= FORM_URL ?>?id=<?= safeHtml(safeGet($formData, 'form_uuid')) ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> ערוך טופס
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> הדפס
                    </button>
                    <a href="export_pdf.php?id=<?= safeHtml(safeGet($formData, 'form_uuid')) ?>" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> ייצוא ל-PDF
                    </a>
                    <button type="button" class="btn btn-info" onclick="shareForm()">
                        <i class="fas fa-share"></i> שתף
                    </button>
                    <a href="forms_list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right"></i> חזרה לרשימה
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareForm() {
            const formUrl = window.location.href;
            const formName = '<?= safeHtml(safeGet($formData, 'deceased_name', 'טופס נפטר'), ENT_QUOTES) ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: 'טופס נפטר - ' + formName,
                    url: formUrl
                }).catch(() => {
                    copyToClipboard(formUrl);
                });
            } else {
                copyToClipboard(formUrl);
            }
        }
        
        function copyToClipboard(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('הקישור הועתק ללוח', 'success');
                }).catch(() => {
                    fallbackCopyToClipboard(text);
                });
            } else {
                fallbackCopyToClipboard(text);
            }
        }
        
        function fallbackCopyToClipboard(text) {
            const input = document.createElement('input');
            input.value = text;
            input.style.position = 'fixed';
            input.style.left = '-999999px';
            document.body.appendChild(input);
            input.focus();
            input.select();
            
            try {
                document.execCommand('copy');
                showNotification('הקישור הועתק ללוח', 'success');
            } catch (err) {
                showNotification('לא ניתן להעתיק את הקישור: ' + text, 'error');
            }
            
            document.body.removeChild(input);
        }
        
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible position-fixed`;
            notification.style.cssText = 'top: 70px; right: 20px; z-index: 9999; max-width: 400px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(notification);
            
            // הסר אוטומטית אחרי 5 שניות
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // בדיקת טעינת התמונות
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const container = this.closest('.signature-container');
                    if (container) {
                        container.innerHTML = '<div class="text-muted">לא ניתן להציג חתימה</div>';
                    }
                });
            });
        });
    </script>
</body>
</html>