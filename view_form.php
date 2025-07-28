<?php
// view_form.php - צפייה בטופס נפטר

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
if (!$form->getFormData()) {
    header('Location: forms_list.php');
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

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>צפייה בטופס - <?= htmlspecialchars($formData['deceased_name']) ?></title>
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
                <p class="text-muted">מספר טופס: <?= htmlspecialchars($formData['form_uuid']) ?></p>
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
                <div class="progress-bar" role="progressbar" 
                     style="width: <?= $formData['progress_percentage'] ?? 0 ?>%">
                    <?= $formData['progress_percentage'] ?? 0 ?>% הושלם
                </div>
            </div>

            <!-- פרטי הנפטר -->
            <div class="section-title">פרטי הנפטר</div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">סוג זיהוי:</span>
                        <?php
                        $idTypes = [
                            'tz' => 'תעודת זהות',
                            'passport' => 'דרכון',
                            'anonymous' => 'אלמוני',
                            'baby' => 'תינוק'
                        ];
                        echo $idTypes[$formData['identification_type']] ?? $formData['identification_type'];
                        ?>
                    </div>
                    <?php if ($formData['identification_number']): ?>
                    <div class="info-row">
                        <span class="info-label">מספר זיהוי:</span>
                        <?= htmlspecialchars($formData['identification_number']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">שם הנפטר:</span>
                        <strong><?= htmlspecialchars($formData['deceased_name']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if ($formData['father_name']): ?>
                    <div class="info-row">
                        <span class="info-label">שם האב:</span>
                        <?= htmlspecialchars($formData['father_name']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['mother_name']): ?>
                    <div class="info-row">
                        <span class="info-label">שם האם:</span>
                        <?= htmlspecialchars($formData['mother_name']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($formData['birth_date']): ?>
                    <div class="info-row">
                        <span class="info-label">תאריך לידה:</span>
                        <?= date('d/m/Y', strtotime($formData['birth_date'])) ?>
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
                        <?= date('d/m/Y', strtotime($formData['death_date'])) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">שעת פטירה:</span>
                        <?= date('H:i', strtotime($formData['death_time'])) ?>
                    </div>
                    <?php if ($formData['death_location']): ?>
                    <div class="info-row">
                        <span class="info-label">מקום הפטירה:</span>
                        <?= htmlspecialchars($formData['death_location']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">תאריך קבורה:</span>
                        <?= date('d/m/Y', strtotime($formData['burial_date'])) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">שעת קבורה:</span>
                        <?= date('H:i', strtotime($formData['burial_time'])) ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">רשיון קבורה:</span>
                        <?= htmlspecialchars($formData['burial_license']) ?>
                    </div>
                </div>
            </div>

            <!-- מקום הקבורה -->
            <?php if ($locationDetails && ($locationDetails['cemetery_name'] || $locationDetails['plot_name'])): ?>
            <div class="section-title">מקום הקבורה</div>
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

            <!-- פרטי המודיע -->
            <?php if ($formData['informant_name'] || $formData['informant_phone'] || $formData['informant_relationship']): ?>
            <div class="section-title">פרטי המודיע</div>
            <div class="row">
                <div class="col-md-4">
                    <?php if ($formData['informant_name']): ?>
                    <div class="info-row">
                        <span class="info-label">שם:</span>
                        <?= htmlspecialchars($formData['informant_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if ($formData['informant_phone']): ?>
                    <div class="info-row">
                        <span class="info-label">טלפון:</span>
                        <?= htmlspecialchars($formData['informant_phone']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if ($formData['informant_relationship']): ?>
                    <div class="info-row">
                        <span class="info-label">קרבה:</span>
                        <?= htmlspecialchars($formData['informant_relationship']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- הערות -->
            <?php if ($formData['notes']): ?>
            <div class="section-title">הערות</div>
            <div class="row">
                <div class="col-12">
                    <p><?= nl2br(htmlspecialchars($formData['notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- חתימת לקוח -->
            <?php if ($formData['client_signature']): ?>
            <div class="section-title">חתימת לקוח</div>
            <div class="row">
                <div class="col-12">
                    <div class="signature-container">
                        <img src="<?= htmlspecialchars($formData['client_signature']) ?>" alt="חתימת לקוח" style="max-width: 100%; max-height: 180px;">
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
                        <a href="download.php?id=<?= $doc['id'] ?>" class="list-group-item list-group-item-action">
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
                    <a href="form.php?id=<?= $formData['form_uuid'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> ערוך טופס
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> הדפס
                    </button>
                    <a href="export_pdf.php?id=<?= $formData['form_uuid'] ?>" class="btn btn-danger">
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
            if (navigator.share) {
                navigator.share({
                    title: 'טופס נפטר - <?= htmlspecialchars($formData['deceased_name']) ?>',
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