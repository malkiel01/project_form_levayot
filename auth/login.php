<?php
    require_once '../config.php';

    // הגדרת CSRF TOKEN אם לא קיים
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // אם המשתמש כבר מחובר
    if (isset($_SESSION['user_id'])) {
        // קבל את הדשבורד המתאים למשתמש הנוכחי
        $userDashboard = getUserDashboardUrl($_SESSION['user_id'], $_SESSION['permission_level']);
        $redirect = $_GET['redirect'] ?? $userDashboard;
        
        // בדיקת תקינות ה-redirect
        if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
            // אם זה לא URL מלא, תקן אותו
            $redirect = SITE_URL . '/' . ltrim($redirect, '/');
        }
        
        // בדוק אם למשתמש יש הרשאה ל-redirect
        $allowedDashboards = getUserAllowedDashboards($_SESSION['user_id']);
        $canAccessRedirect = false;
        
        foreach ($allowedDashboards as $dashboard) {
            if (strpos($redirect, basename($dashboard['url'])) !== false) {
                $canAccessRedirect = true;
                break;
            }
        }
        
        // אם אין הרשאה, הפנה לדשבורד המתאים
        if (!$canAccessRedirect) {
            $redirect = $userDashboard;
        }
        
        // תיקון קריטי - השתמש ב-URL מלא!
        header('Location: ' . $redirect);
        exit;
    }

    $error = '';
    $success = '';
    $redirect = $_GET['redirect'] ?? DASHBOARD_FULL_URL;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        error_log("===== LOGIN ATTEMPT =====");
        // בדיקת CSRF token
        error_log("POST CSRF: " . ($_POST['csrf_token'] ?? 'NULL'));
        error_log("SESSION CSRF: " . ($_SESSION['csrf_token'] ?? 'NULL'));
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("ERROR: CSRF mismatch!");
            die('Invalid CSRF token');
        }
        
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = sanitizeInput($_POST['redirect'] ?? DASHBOARD_FULL_URL);

        error_log("Username entered: $username");
        error_log("Password entered: [$password] (len=" . strlen($password) . ")");

        if (empty($username) || empty($password)) {
            $error = 'יש להזין שם משתמש וסיסמה';
            error_log("ERROR: Username or password empty");
        } else {
            try {
                $db = getDbConnection();
                $stmt = $db->prepare("
                    SELECT id, username, password, full_name, permission_level, 
                        is_active, failed_login_attempts, locked_until 
                    FROM users 
                    WHERE username = ? OR email = ?
                ");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    error_log("User found: id=" . $user['id'] . ", username=" . $user['username']);
                    error_log("DB password hash: " . $user['password'] . " (len=" . strlen($user['password']) . ")");
                    error_log("is_active: " . $user['is_active'] . ", locked_until: " . $user['locked_until']);
                    
                    // בדיקה אם החשבון נעול
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $remainingTime = ceil((strtotime($user['locked_until']) - time()) / 60);
                        $error = "החשבון נעול. נסה שוב בעוד $remainingTime דקות";
                        error_log("ERROR: Account locked until " . $user['locked_until']);
                    } elseif (!$user['is_active']) {
                        $error = 'החשבון אינו פעיל. פנה למנהל המערכת';
                        error_log("ERROR: Account not active");
                    } else {
                        // בדיקת סיסמה
                        error_log("Verifying password...");
                        $pwResult = password_verify($password, $user['password']);
                        error_log("Password verification result: " . ($pwResult ? "TRUE" : "FALSE"));

                        if ($pwResult) {
                            // התחברות מוצלחת
                            error_log("LOGIN SUCCESS for user " . $user['username']);
                            $db->prepare("
                                UPDATE users 
                                SET failed_login_attempts = 0, 
                                    locked_until = NULL, 
                                    last_login = NOW() 
                                WHERE id = ?
                            ")->execute([$user['id']]);
                            
                            // הגדרת סשן
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['permission_level'] = $user['permission_level'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            // רישום בלוג
                            $db->prepare("
                                INSERT INTO activity_log 
                                (user_id, action, details, ip_address, user_agent) 
                                VALUES (?, 'login_success', ?, ?, ?)
                            ")->execute([
                                $user['id'], 
                                json_encode(['redirect' => $redirect]), 
                                $_SERVER['REMOTE_ADDR'] ?? '', 
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                            
                            // קבלת הדשבורד המתאים למשתמש
                            $userDashboard = getUserDashboardUrl($user['id'], $user['permission_level']);
                            
                            // אם יש redirect ספציפי ולמשתמש יש הרשאה אליו, השתמש בו
                            if ($redirect && $redirect !== DASHBOARD_FULL_URL) {
                                // בדוק אם המשתמש יכול לגשת ל-redirect המבוקש
                                $allowedDashboards = getUserAllowedDashboards($user['id']);
                                $canAccessRedirect = false;
                                
                                foreach ($allowedDashboards as $dashboard) {
                                    if (strpos($redirect, basename($dashboard['url'])) !== false) {
                                        $canAccessRedirect = true;
                                        break;
                                    }
                                }
                                
                                if ($canAccessRedirect) {
                                    // תיקון קריטי - השתמש ב-URL מלא!
                                    header('Location: ' . $redirect);
                                } else {
                                    // אם אין הרשאה, הפנה לדשבורד המתאים
                                    header('Location: ' . $userDashboard);
                                }
                            } else {
                                // הפנה לדשבורד המתאים
                                header('Location: ' . $userDashboard);
                            }
                            exit;
                        } else {
                            // סיסמה שגויה
                            error_log("ERROR: Invalid password");
                            $attempts = $user['failed_login_attempts'] + 1;
                            
                            if ($attempts >= 5) {
                                $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                                $db->prepare("
                                    UPDATE users 
                                    SET failed_login_attempts = ?, locked_until = ? 
                                    WHERE id = ?
                                ")->execute([$attempts, $lockUntil, $user['id']]);
                                $error = 'יותר מדי ניסיונות כושלים. החשבון ננעל ל-30 דקות';
                                error_log("ERROR: Account locked due to too many failed attempts");
                            } else {
                                $db->prepare("
                                    UPDATE users 
                                    SET failed_login_attempts = ? 
                                    WHERE id = ?
                                ")->execute([$attempts, $user['id']]);
                                $remaining = 5 - $attempts;
                                $error = "שם משתמש או סיסמה שגויים. נותרו $remaining ניסיונות";
                                error_log("ERROR: Failed attempts: $attempts");
                            }
                        }
                    }
                } else {
                    $error = 'שם משתמש או סיסמה שגויים';
                    error_log("ERROR: User not found in database");
                }
                
            } catch (PDOException $e) {
                $error = 'שגיאת מערכת. נסה שוב מאוחר יותר';
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות - מערכת ניהול בית עלמין</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .auth-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header i {
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
        
        .demo-users, .redirect-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .redirect-info {
            background-color: #e3f2fd;
            font-size: 0.9rem;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #666;
        }
        
        .google-signin {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
    
        .g_id_signin {
            width: 100%;
            max-width: 400px;
            min-height: 44px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <i class="fas fa-user-circle"></i>
                <h3>התחברות למערכת</h3>
                <p class="text-muted">מערכת ניהול בית עלמין</p>
            </div>
            
            <?php if ($redirect !== DASHBOARD_FULL_URL): ?>
                <div class="redirect-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>הודעה:</strong> תועבר לטופס לאחר ההתחברות
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <input type="hidden" name="action" value="login">
                
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> שם משתמש או מייל
                    </label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> סיסמה
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" id="loginBtn" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt"></i> התחבר
                </button>
            </form>
            
            <div class="divider">
                <span>או</span>
            </div>
            
            <!-- Google Sign-In Button -->
            <div class="google-signin">
                <div id="g_id_onload"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-callback="handleCredentialResponse"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     data-theme="outline"
                     data-text="sign_in_with"
                     data-shape="rectangular"
                     data-logo_alignment="left"
                     data-width="350">
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="register.php" class="text-decoration-none">
                    <i class="fas fa-user-plus"></i> משתמש חדש? הרשם כאן
                </a>
                <br>
                <a href="forgot_password.php" class="text-decoration-none">
                    <i class="fas fa-key"></i> שכחת סיסמה?
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginBtn').addEventListener('click', function(e) {
            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מתחבר...';
            
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 5000);
        });

        // Google Sign-In callback - גרסה מתוקנת
        async function handleCredentialResponse(response) {
            console.log('Google Sign-In response received');
            
            // הצג אינדיקטור טעינה
            const loadingDiv = document.createElement('div');
            loadingDiv.innerHTML = '<div class="text-center my-3"><i class="fas fa-spinner fa-spin"></i> מתחבר עם Google...</div>';
            document.querySelector('.google-signin').appendChild(loadingDiv);
            
            try {
                const result = await fetch('google_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        credential: response.credential,
                        redirect: '<?= htmlspecialchars($redirect) ?>',
                        action: 'login'
                    })
                });
                
                console.log('Response status:', result.status);
                
                // בדוק אם התגובה היא JSON
                const contentType = result.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    const data = await result.json();
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        // הצלחה - הפנה למיקום המבוקש
                        window.location.href = data.redirect;
                    } else {
                        // הצג הודעת שגיאה
                        showGoogleError(data.message || 'שגיאה בהתחברות עם Google');
                        loadingDiv.remove();
                    }
                } else {
                    // אם התגובה אינה JSON, נסה לקרוא כטקסט
                    const text = await result.text();
                    console.error('Non-JSON response:', text);
                    showGoogleError('שגיאה בתקשורת עם השרת');
                    loadingDiv.remove();
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showGoogleError('שגיאה בהתחברות. אנא נסה שוב.');
                loadingDiv.remove();
            }
        }
        
        // פונקציה להצגת שגיאות Google
        function showGoogleError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.auth-header').after(alertDiv);
            
            // הסר אחרי 5 שניות
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>