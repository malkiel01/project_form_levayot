<?php
    require_once '../config.php';

    // הגדרת CSRF TOKEN אם לא קיים
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // אם המשתמש כבר מחובר
    if (isset($_SESSION['user_id'])) {
        $redirect = $_GET['redirect'] ?? DASHBOARD_FULL_URL;
        if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
            $redirect = basename($redirect);
            if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
                $redirect = DASHBOARD_FULL_URL;
            }
        }
        header('Location: ' . ltrim($redirect, '/'));
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
                    
                    // השוואה תו-לתו של הסיסמה עם מה שנשלח (לראות אם יש רווחים/תוים מוזרים)
                    $inputPassHex = bin2hex($password);
                    error_log("Password entered (hex): $inputPassHex");
                    
                    // בדוק נעילה/לא פעיל
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = 'החשבון נעול זמנית. נסה שוב מאוחר יותר.';
                        error_log("ERROR: Account locked (locked_until=" . $user['locked_until'] . ")");
                    } elseif (!$user['is_active']) {
                        $error = 'החשבון לא פעיל. פנה למנהל המערכת.';
                        error_log("ERROR: Account not active");
                    } else {
                        // בדיקת התאמה
                        $pwResult = password_verify($password, $user['password']);
                        error_log("password_verify result: " . ($pwResult ? "TRUE" : "FALSE"));

                        // הדפס פערים תו-לתו בין סיסמה לסיסמה ב־DB (במקרה של סיסמאות פשוטות לטסט בלבד)
                        // (שים לב - לא מומלץ לייצור, רק לדיבוג)
                        $diff = [];
                        for ($i = 0; $i < max(strlen($password), strlen($user['password'])); $i++) {
                            $a = $password[$i] ?? '';
                            $b = $user['password'][$i] ?? '';
                            if ($a !== $b) $diff[] = "pos $i: entered='" . addslashes($a) . "', db='" . addslashes($b) . "'";
                        }
                        if ($diff) {
                            error_log("DIFF password vs db: " . implode(' | ', $diff));
                        }

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
                            
                            // הפניה
                            if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
                                $redirect = basename($redirect);
                                if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
                                    $redirect = DASHBOARD_FULL_URL;
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
                                error_log("ERROR: too many login attempts, locking account!");
                            } else {
                                $error = 'שם משתמש או סיסמה שגויים. נותרו ' . (5 - $attempts) . ' ניסיונות.';
                                error_log("ERROR: wrong password, $attempts attempts");
                            }
                            
                            $db->prepare("
                                UPDATE users 
                                SET failed_login_attempts = ?, locked_until = ? 
                                WHERE id = ?
                            ")->execute([$attempts, $lockUntil, $user['id']]);
                            
                            // רישום בלוג
                            $db->prepare("
                                INSERT INTO activity_log 
                                (user_id, action, details, ip_address, user_agent) 
                                VALUES (?, 'login_failed', ?, ?, ?)
                            ")->execute([
                                $user['id'], 
                                json_encode(['reason' => 'wrong_password', 'attempts' => $attempts]), 
                                $_SERVER['REMOTE_ADDR'] ?? '', 
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                        }
                    }
                } else {
                    $error = 'שם משתמש או סיסמה שגויים';
                    error_log("ERROR: User not found for username: $username");
                    // רישום בלוג
                    $db->prepare("
                        INSERT INTO activity_log 
                        (user_id, action, details, ip_address, user_agent) 
                        VALUES (NULL, 'login_failed', ?, ?, ?)
                    ")->execute([
                        json_encode(['reason' => 'user_not_found', 'username' => $username]), 
                        $_SERVER['REMOTE_ADDR'] ?? '', 
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'שגיאה במערכת. נסה שוב מאוחר יותר.';
            }
        }
    }

    // בדיקה אם יש הודעה מהרישום
    if (isset($_SESSION['registration_success'])) {
        $success = $_SESSION['registration_success'];
        unset($_SESSION['registration_success']);
    }
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות למערכת</title>
    <!-- הוסף את השורות האלה לPWA -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="/project_form_levayot/icons/icon-192x192.png">
    <!-- סוף הוספות PWA -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .auth-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
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
        
        .auth-header h3 {
            color: #333;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }
        
        .divider::before {
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .redirect-info {
            background: #e3f2fd;
            border: 1px solid #64b5f6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #1976d2;
        }

        /* <!-- הוסף CSS לשיפור התצוגה --> */

        /* .google-signin {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            flex-direction: column;
            align-items: center;
        } */
        
        /* .g_id_signin {
            width: 100%;
            max-width: 400px;
            display: flex;
            justify-content: center;
        } */
        
        /* וודא שהכפתור ממורכז
        .g_id_signin iframe {
            margin: 0 auto;
            display: block;
        } */

            /* עיצוב מתוקן לכפתור Google */
        .google-signin {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
    
        /* הגדר גודל קבוע לקונטיינר כדי למנוע קפיצות */
        .g_id_signin {
            width: 100%;
            max-width: 400px;
            min-height: 44px; /* גובה מינימלי של כפתור Google */
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
                <p class="text-muted">מערכת ניהול טפסי נפטרים</p>
                <p class="text-muted">v2</p>
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

            <!-- Google Sign-In Button -->
            <div class="google-signin">
                <div id="g_id_onload"
                    data-client_id="453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com"
                    data-callback="handleGoogleSignIn"
                    data-auto_prompt="false"
                    data-cancel_on_tap_outside="false">
                </div>
                <div class="g_id_signin"
                    data-type="standard"
                    data-size="large"
                    data-theme="outline"
                    data-text="sign_in_with"
                    data-shape="rectangular"
                    data-logo_alignment="left">
                </div>
            </div>

            <div class="divider">
                <span>או</span>
            </div>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <input type="hidden" name="action" value="login">
                
                <div class="mb-3">
                    <label for="username" class="form-label">שם משתמש או אימייל</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
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
                
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">זכור אותי</label>
                    </div>
                    <a href="forgot_password.php" class="text-decoration-none">
                        <i class="fas fa-key"></i> שכחת סיסמה?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> התחבר
                </button>
            </form>
            
            <div class="register-link">
                <p class="mb-0">אין לך חשבון? 
                    <a href="register.php" class="text-decoration-none">
                        <i class="fas fa-user-plus"></i> הרשם עכשיו
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
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

        // Form submission loading
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מתחבר...';
            
            // Safety timeout
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 5000);
        });

        // Google Sign-In callback - גרסה מתוקנת
        async function handleGoogleSignIn(response) {
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
                        window.location.href = data.redirect || '<?= DASHBOARD_FULL_URL ?>';
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
        
        // הסר את הקוד שמשנה את גודל הכפתור - תן ל-Google לטפל בזה
        // window.addEventListener('load', function() { ... });
    </script>
</body>
</html>