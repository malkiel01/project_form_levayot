<?php
// admin/users.php - ניהול משתמשים
require_once '../config.php';

// בדיקת הרשאות מנהל
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] < 4) {
    header('Location: ../' . LOGIN_URL);
    exit;
}

$db = getDbConnection();
$message = '';
$error = '';

// טיפול בפעולות
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // הוספת משתמש חדש
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            $email = sanitizeInput($_POST['email']);
            $fullName = sanitizeInput($_POST['full_name']);
            $phone = sanitizeInput($_POST['phone']);
            $permissionLevel = intval($_POST['permission_level']);
            
            // ולידציה
            if (strlen($username) < 3) {
                $error = 'שם המשתמש חייב להכיל לפחות 3 תווים';
            } elseif (strlen($password) < 6) {
                $error = 'הסיסמה חייבת להכיל לפחות 6 תווים';
            } else {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, password, email, full_name, phone, permission_level) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $hashedPassword, $email, $fullName, $phone, $permissionLevel]);
                    
                    // יצירת הגדרות ברירת מחדל
                    $userId = $db->lastInsertId();
                    $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$userId]);
                    
                    // רישום בלוג
                    $logStmt = $db->prepare("
                        INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                        VALUES (?, 'create_user', ?, ?, ?)
                    ");
                    $logStmt->execute([
                        $_SESSION['user_id'],
                        json_encode(['new_user_id' => $userId, 'username' => $username]),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $message = 'המשתמש נוסף בהצלחה';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = 'שם המשתמש כבר קיים במערכת';
                    } else {
                        $error = 'שגיאה בהוספת המשתמש';
                    }
                }
            }
            break;
            
        case 'edit':
            // עריכת משתמש
            $userId = intval($_POST['user_id']);
            $email = sanitizeInput($_POST['email']);
            $fullName = sanitizeInput($_POST['full_name']);
            $phone = sanitizeInput($_POST['phone']);
            $permissionLevel = intval($_POST['permission_level']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $updates = [
                'email = ?',
                'full_name = ?',
                'phone = ?',
                'permission_level = ?',
                'is_active = ?'
            ];
            $params = [$email, $fullName, $phone, $permissionLevel, $isActive];
            
            // אם הוזנה סיסמה חדשה
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) {
                    $error = 'הסיסמה חייבת להכיל לפחות 6 תווים';
                } else {
                    $updates[] = 'password = ?';
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
            }
            
            if (!$error) {
                $params[] = $userId;
                $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
                $stmt->execute($params);
                $message = 'המשתמש עודכן בהצלחה';
            }
            break;
            
        case 'delete':
            // מחיקת משתמש
            $userId = intval($_POST['user_id']);
            
            // אי אפשר למחוק את עצמך
            if ($userId == $_SESSION['user_id']) {
                $error = 'לא ניתן למחוק את המשתמש הנוכחי';
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'המשתמש נמחק בהצלחה';
            }
            break;
    }
}

// קבלת רשימת משתמשים
$stmt = $db->query("
    SELECT u.*, p.name as permission_name,
           (SELECT COUNT(*) FROM deceased_forms WHERE created_by = u.id) as forms_count
    FROM users u 
    JOIN permissions p ON u.permission_level = p.permission_level 
    ORDER BY u.id
");
$users = $stmt->fetchAll();

// קבלת רשימת הרשאות
$permissions = $db->query("SELECT * FROM permissions ORDER BY permission_level")->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים - מערכת ניהול נפטרים</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .content-wrapper {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-home"></i> מערכת ניהול נפטרים
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> דשבורד
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../<?= FORM_URL ?>">
                            <i class="fas fa-plus"></i> טופס חדש
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../forms_list.php">
                            <i class="fas fa-list"></i> רשימת טפסים
                        </a>
                    </li>
                    <li class="nav-item dropdown active">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> ניהול
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item active" href="users.php">משתמשים</a></li>
                            <li><a class="dropdown-item" href="cemeteries.php">בתי עלמין</a></li>
                            <li><a class="dropdown-item" href="permissions.php">הרשאות</a></li>
                            <li><a class="dropdown-item" href="reports.php">דוחות</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../<?= LOGOUT_URL ?>">יציאה</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="content-wrapper">
            <div class="row mb-4">
                <div class="col">
                    <h2><i class="fas fa-users"></i> ניהול משתמשים</h2>
                </div>
                <div class="col text-end">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus"></i> הוסף משתמש
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>שם משתמש</th>
                            <th>שם מלא</th>
                            <th>אימייל</th>
                            <th>טלפון</th>
                            <th>הרשאה</th>
                            <th>טפסים</th>
                            <th>סטטוס</th>
                            <th>כניסה אחרונה</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-info">אתה</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['full_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($user['permission_name']) ?></span>
                            </td>
                            <td><?= number_format($user['forms_count']) ?></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">פעיל</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">לא פעיל</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">טרם התחבר</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="editUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                <a href="user_activity.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info" title="היסטוריית פעילות">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal הוספת משתמש -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">הוסף משתמש חדש</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="add_username" class="form-label required">שם משתמש</label>
                            <input type="text" class="form-control" id="add_username" name="username" required minlength="3">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_password" class="form-label required">סיסמה</label>
                            <input type="password" class="form-control" id="add_password" name="password" required minlength="6">
                            <div class="form-text">לפחות 6 תווים</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_full_name" class="form-label">שם מלא</label>
                            <input type="text" class="form-control" id="add_full_name" name="full_name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">אימייל</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_phone" class="form-label">טלפון</label>
                            <input type="tel" class="form-control" id="add_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_permission_level" class="form-label required">רמת הרשאה</label>
                            <select class="form-select" id="add_permission_level" name="permission_level" required>
                                <?php foreach ($permissions as $perm): ?>
                                <option value="<?= $perm['permission_level'] ?>">
                                    <?= htmlspecialchars($perm['name']) ?> - <?= htmlspecialchars($perm['description']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                        <button type="submit" class="btn btn-primary">הוסף משתמש</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal עריכת משתמש -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ערוך משתמש</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">שם משתמש</label>
                            <input type="text" class="form-control" id="edit_username" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">סיסמה חדשה</label>
                            <input type="password" class="form-control" id="edit_password" name="password" minlength="6">
                            <div class="form-text">השאר ריק אם לא רוצה לשנות</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">שם מלא</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">אימייל</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">טלפון</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_permission_level" class="form-label">רמת הרשאה</label>
                            <select class="form-select" id="edit_permission_level" name="permission_level">
                                <?php foreach ($permissions as $perm): ?>
                                <option value="<?= $perm['permission_level'] ?>">
                                    <?= htmlspecialchars($perm['name']) ?> - <?= htmlspecialchars($perm['description']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                                <label class="form-check-label" for="edit_is_active">
                                    משתמש פעיל
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                        <button type="submit" class="btn btn-primary">שמור שינויים</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function editUser(user) {
            $('#edit_user_id').val(user.id);
            $('#edit_username').val(user.username);
            $('#edit_full_name').val(user.full_name || '');
            $('#edit_email').val(user.email || '');
            $('#edit_phone').val(user.phone || '');
            $('#edit_permission_level').val(user.permission_level);
            $('#edit_is_active').prop('checked', user.is_active == 1);
            
            $('#editUserModal').modal('show');
        }
        
        function deleteUser(userId, username) {
            Swal.fire({
                title: 'האם אתה בטוח?',
                text: `האם למחוק את המשתמש "${username}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'כן, מחק!',
                cancelButtonText: 'ביטול'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // הוספת שדה חובה
        $('.required').append(' <span class="text-danger">*</span>');
    </script>
</body>
</html>