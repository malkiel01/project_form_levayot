<?php
require_once '../config.php'; // עדכן נתיב בהתאם למבנה

// הגדרת CSRF TOKEN אם לא קיים
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? DASHBOARD_URL;
    if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
        $redirect = basename($redirect);
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
            $redirect = DASHBOARD_URL;
        }
    }
    header('Location: ' . ltrim($redirect, '/'));
    exit;
}

$error = '';
// $redirect = $_GET['redirect'] ?? DASHBOARD_URL;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = sanitizeInput($_POST['redirect'] ?? DASHBOARD_URL);

    if (empty($username) || empty($password)) {
        $error = 'יש להזין שם משתמש וסיסמה';
    } else {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id, username, password, full_name, permission_level, is_active, failed_login_attempts, locked_until FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user) {
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = 'החשבון נעול זמנית. נסה שוב מאוחר יותר.';
                } elseif (!$user['is_active']) {
                    $error = 'החשבון לא פעיל. פנה למנהל המערכת.';
                } elseif (password_verify($password, $user['password'])) {
                    // אפס ניסיונות כושלים
                    $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['permission_level'] = $user['permission_level'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // רישום בלוג
                    $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, 'login_success', ?, ?, ?)")
                       ->execute([$user['id'], json_encode(['redirect' => $redirect]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    // הפניה
                    if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
                        $redirect = basename($redirect);
                        if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
                            $redirect = DASHBOARD_URL;
                        }
                    }
                    header('Location: ' . ltrim($redirect, '/'));
                    exit;
                } else {
                    // סיסמה שגויה
                    $attempts = $user['failed_login_attempts'] + 1;
                    $lockUntil = null;
                    if ($attempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                        $error = 'יותר מדי ניסיונות התחברות כושלים. החשבון נעול ל-30 דקות.';
                    } else {
                        $error = 'שם משתמש או סיסמה שגויים. נותרו ' . (5 - $attempts) . ' ניסיונות.';
                    }
                    $db->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")->execute([$attempts, $lockUntil, $user['id']]);
                    $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, 'login_failed', ?, ?, ?)")
                       ->execute([$user['id'], json_encode(['reason' => 'wrong_password', 'attempts' => $attempts]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                }
            } else {
                $error = 'שם משתמש או סיסמה שגויים';
                $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (NULL, 'login_failed', ?, ?, ?)")
                   ->execute([json_encode(['reason' => 'user_not_found', 'username' => $username]), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'שגיאה במערכת. נסה שוב מאוחר יותר.';
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
    <link href="auth.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <i class="fas fa-user-circle"></i>
                <h3>התחברות למערכת</h3>
                <p class="text-muted">מערכת ניהול טפסי נפטרים</p>
            </div>
            <?php if ($redirect !== DASHBOARD_URL): ?>
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
                <button type="button" class="btn btn-google w-100 mb-2">
                    <i class="fab fa-google"></i> התחבר עם Google
                </button>
                <div class="mb-3">
                    <label for="username" class="form-label">שם משתמש</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
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
                    <label class="form-check-label" for="remember">זכור אותי</label>
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
                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="fillLogin('admin', 'admin123')"><i class="fas fa-user-tie"></i> מנהל מערכת</button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="fillLogin('editor', 'editor123')"><i class="fas fa-user-edit"></i> עורך</button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" onclick="fillLogin('viewer', 'viewer123')"><i class="fas fa-user"></i> צופה</button>
                    </div>
                </div>
                <small class="text-muted d-block text-center mt-2"><i class="fas fa-warning"></i> לצורך הדגמה בלבד</small>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('username').focus();
        }
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
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מתחבר...';
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 5000);
        });
    </script>
</body>
</html>
