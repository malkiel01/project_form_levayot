<?php
// select-form-for-share.php - בחירת טופס לשיתוף קבצים
require_once 'config.php';

session_start();

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// בדיקה שיש קבצים לשיתוף
if (!isset($_SESSION['shared_files']) || empty($_SESSION['shared_files'])) {
    header('Location: /forms_list.php');
    exit;
}

$db = getDbConnection();

// שליפת טפסים אחרונים של המשתמש
$stmt = $db->prepare("
    SELECT form_uuid, deceased_name, identification_number, created_at, status
    FROM deceased_forms
    WHERE created_by = ? OR permission_level >= ?
    ORDER BY updated_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['permission_level']]);
$recentForms = $stmt->fetchAll();

// טיפול בבחירת טופס
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_uuid'])) {
    $formUuid = $_POST['form_uuid'];
    $sharedFiles = $_SESSION['shared_files'];
    
    // העברת הקבצים לטופס שנבחר
    foreach ($sharedFiles as $file) {
        // העברת הקובץ מתיקיית temp לתיקיית הטופס
        $formUploadPath = UPLOAD_PATH . '/' . $formUuid . '/';
        if (!is_dir($formUploadPath)) {
            mkdir($formUploadPath, 0777, true);
        }
        
        $newPath = $formUploadPath . $file['stored_name'];
        rename($file['upload_path'], $newPath);
        
        // רישום בDB
        $stmt = $db->prepare("
            INSERT INTO form_files (
                form_uuid, file_uuid, original_name, stored_name,
                file_type, file_size, folder_path, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, '/', ?)
        ");
        
        $stmt->execute([
            $formUuid,
            generateUUID(),
            $file['original_name'],
            $file['stored_name'],
            $file['file_type'],
            $file['file_size'],
            $_SESSION['user_id']
        ]);
    }
    
    // ניקוי session
    unset($_SESSION['shared_files']);
    
    // הפניה לטופס
    header('Location: /form/?uuid=' . $formUuid . '&tab=files&shared=success');
    exit;
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בחר טופס לשיתוף קבצים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/rtl.css">
    <style>
        .shared-files-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-icon {
            font-size: 24px;
            margin-left: 10px;
            color: #6c757d;
        }
        .form-card {
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }
        .form-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .form-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .status-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= DASHBOARD_FULL_URL ?>">
                <i class="fas fa-home"></i> מערכת ניהול נפטרים
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4">
                    <i class="fas fa-share-alt"></i> שיתוף קבצים לטופס
                </h2>

                <!-- תצוגה מקדימה של הקבצים -->
                <div class="shared-files-preview">
                    <h5 class="mb-3">קבצים לשיתוף:</h5>
                    <?php foreach ($_SESSION['shared_files'] as $file): ?>
                        <div class="file-item">
                            <i class="file-icon <?php
                                if (strpos($file['file_type'], 'image') !== false) echo 'fas fa-image text-primary';
                                elseif ($file['file_type'] === 'application/pdf') echo 'fas fa-file-pdf text-danger';
                                else echo 'fas fa-file-word text-info';
                            ?>"></i>
                            <div class="flex-grow-1">
                                <div><?= htmlspecialchars($file['original_name']) ?></div>
                                <small class="text-muted">
                                    <?= number_format($file['file_size'] / 1024, 1) ?> KB
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- בחירת טופס -->
                <form method="POST" id="selectFormForm">
                    <h5 class="mb-3">בחר טופס:</h5>
                    
                    <?php if (empty($recentForms)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            אין טפסים זמינים. 
                            <a href="/form/" class="alert-link">צור טופס חדש</a>
                        </div>
                    <?php else: ?>
                        <div class="forms-list mb-4">
                            <?php foreach ($recentForms as $form): ?>
                                <div class="card form-card" data-form-uuid="<?= $form['form_uuid'] ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <input type="radio" name="form_uuid" 
                                                       value="<?= $form['form_uuid'] ?>" 
                                                       class="form-check-input">
                                            </div>
                                            <div class="col">
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($form['deceased_name'] ?: 'ללא שם') ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($form['identification_number'] ?: 'ללא ת.ז.') ?>
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <?php
                                                $statusLabels = [
                                                    'draft' => '<span class="badge bg-secondary status-badge">טיוטה</span>',
                                                    'in_progress' => '<span class="badge bg-warning status-badge">בתהליך</span>',
                                                    'completed' => '<span class="badge bg-success status-badge">הושלם</span>'
                                                ];
                                                echo $statusLabels[$form['status']] ?? '';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary" disabled id="shareButton">
                                <i class="fas fa-share"></i> שתף לטופס שנבחר
                            </button>
                            <a href="/form/" class="btn btn-success">
                                <i class="fas fa-plus"></i> צור טופס חדש
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // טיפול בבחירת טופס
        document.querySelectorAll('.form-card').forEach(card => {
            card.addEventListener('click', function() {
                // הסרת בחירה קודמת
                document.querySelectorAll('.form-card').forEach(c => c.classList.remove('selected'));
                
                // סימון הכרטיס הנבחר
                this.classList.add('selected');
                
                // בחירת הרדיו
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // הפעלת כפתור שיתוף
                document.getElementById('shareButton').disabled = false;
            });
        });

        // טיפול בלחיצה על רדיו ישירות
        document.querySelectorAll('input[name="form_uuid"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('shareButton').disabled = false;
                
                // סימון הכרטיס המתאים
                document.querySelectorAll('.form-card').forEach(card => {
                    card.classList.remove('selected');
                    if (card.dataset.formUuid === this.value) {
                        card.classList.add('selected');
                    }
                });
            });
        });
    </script>
</body>
</html>