<?php
// login.php - דף התחברות מתוקן עם redirect

require_once 'config.php';

// אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    // בדוק אם יש redirect
    $redirect = $_GET['redirect'] ?? 'dashboard.php';
    // ודא שה-redirect בטוח (למניעת open redirect)
    if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
        $redirect = basename($redirect); // קח רק את שם הקובץ
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
            $redirect = 'dashboard.php';
        }
    }
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = sanitizeInput($_POST['redirect'] ?? 'dashboard.php');
    
    if (empty($username) || empty($password)) {
        $error = 'יש להזין שם משתמש וסיסמה';
    } else {
        try {
            $db = getDbConnection();
            
            // חיפוש המשתמש
            $stmt = $db->prepare("
                SELECT id, username, password, full_name, permission_level, is_active, 
                       failed_login_attempts, locked_until
                FROM users 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // בדוק אם החשבון נעול
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = 'החשבון נעול זמנית. נסה שוב מאוחר יותר.';
                } elseif (!$user['is_active']) {
                    $error = 'החשבון לא פעיל. פנה למנהל המערכת.';
                } elseif (password_verify($password, $user['password'])) {
                    // התחברות מוצלחת
                    
                    // אפס ניסיונות כושלים
                    $resetStmt = $db->prepare("
                        UPDATE users 
                        SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() 
                        WHERE id = ?
                    ");
                    $resetStmt->execute([$user['id']]);
                    
                    // הגדר session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['permission_level'] = $user['permission_level'];
                    $_SESSION['login_time'] = time();
                    
                    // רענן CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // רישום בלוג
                    $logStmt = $db->prepare("
                        INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                        VALUES (?, 'login_success', ?, ?, ?)
                    ");
                    $logStmt->execute([
                        $user['id'],
                        json_encode(['redirect' => $redirect]),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // הפניה
                    $redirectUrl = $redirect;
                    // ודא שה-redirect בטוח
                    if (filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
                        $redirectUrl = basename($redirectUrl);
                        if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirectUrl)) {
                            $redirectUrl = 'dashboard.php';
                        }
                    }
                    
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    // סיסמה שגויה - הוסף ניסיון כושל
                    $attempts = $user['failed_login_attempts'] + 1;
                    $lockUntil = null;
                    
                    // נעל את החשבון אחרי 5 ניסיונות כושלים
                    if ($attempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                        $error = 'יותר מדי ניסיונות התחברות כושלים. החשבון נעול ל-30 דקות.';
                    } else {
                        $error = 'שם משתמש או סיסמה שגויים. נותרו ' . (5 - $attempts) . ' ניסיונות.';
                    }
                    
                    $updateStmt = $db->prepare("
                        UPDATE users 
                        SET failed_login_attempts = ?, locked_until = ? 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$attempts, $lockUntil, $user['id']]);
                    
                    // רישום ניסיון כושל
                    $logStmt = $db->prepare("
                        INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                        VALUES (?, 'login_failed', ?, ?, ?)
                    ");
                    $logStmt->execute([
                        $user['id'],
                        json_encode(['reason' => 'wrong_password', 'attempts' => $attempts]),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
            } else {
                $error = 'שם משתמש או סיסמה שגויים';
                
                // רישום ניסיון כושל עם משתמש לא קיים
                $logStmt = $db->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                    VALUES (NULL, 'login_failed', ?, ?, ?)
                ");
                $logStmt->execute([
                    json_encode(['reason' => 'user_not_found', 'username' => $username]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'שגיאה במערכת. אנא נסה שוב מאוחר יותר.';
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות למערכת</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            padding: 10px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #5a67d8;
            border-color: #5a67d8;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .demo-users {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .redirect-info {
            background-color: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-user-circle"></i>
                <h3>התחברות למערכת</h3>
                <p class="text-muted">מערכת ניהול טפסי נפטרים</p>
            </div>
            
            <?php if ($redirect !== 'dashboard.php'): ?>
            <div class="redirect-info">
                <i class="fas fa-info-circle"></i>
                <strong>הודעה:</strong> תועבר לטופס לאחר ההתחברות
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">שם משתמש</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">סיסמה</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">
                        זכור אותי
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> התחבר
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none text-muted">
                    <i class="fas fa-key"></i> שכחת סיסמה?
                </a>
            </div>
            
            <hr class="my-4">
            
            <div class="demo-users">
                <h6 class="text-center mb-3">
                    <i class="fas fa-users"></i> משתמשי דוגמה
                </h6>
                <div class="row g-2">
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-success btn-sm w-100" 
                                onclick="fillLogin('admin', 'admin123')">
                            <i class="fas fa-user-tie"></i> מנהל מערכת
                        </button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" 
                                onclick="fillLogin('editor', 'editor123')">
                            <i class="fas fa-user-edit"></i> עורך
                        </button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" 
                                onclick="fillLogin('viewer', 'viewer123')">
                            <i class="fas fa-user"></i> צופה
                        </button>
                    </div>
                </div>
                <small class="text-muted d-block text-center mt-2">
                    <i class="fas fa-warning"></i> לצורך הדגמה בלבד
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // מילוי פרטי התחברות דוגמה
        function fillLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('username').focus();
        }
        
        // הצגה/הסתרה של סיסמה
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // אנימציה בעת שליחה
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const originalHTML = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מתחבר...';
            
            // החזר את הכפתור למצב נורמלי אחרי 5 שניות (למקרה של שגיאה)
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 5000);
        });
        
        // אוטו-פוקוס על שדה הסיסמה אם שם המשתמש מלא
        document.getElementById('username').addEventListener('input', function() {
            if (this.value.length > 0) {
                document.getElementById('password').focus();
            }
        });
        
        // Enter על שדה שם המשתמש עובר לסיסמה
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>