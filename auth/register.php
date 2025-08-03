<?php
require_once '../config.php';

// הגדרת CSRF TOKEN אם לא קיים
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    header('Location: ' . DASHBOARD_URL);
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // קבלת נתונים
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    // ולידציות
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'שם משתמש חייב להכיל לפחות 3 תווים';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'כתובת אימייל לא תקינה';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'סיסמה חייבת להכיל לפחות 6 תווים';
    }
    
    if ($password !== $password_confirm) {
        $errors[] = 'הסיסמאות אינן תואמות';
    }
    
    if (empty($full_name)) {
        $errors[] = 'יש להזין שם מלא';
    }
    
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            // בדיקה אם המשתמש כבר קיים
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->fetch()) {
                $error = 'שם המשתמש או האימייל כבר קיימים במערכת';
            } else {
                // יצירת משתמש חדש
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertStmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, full_name, phone, 
                        permission_level, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, 1, 0, NOW())
                ");
                
                $insertStmt->execute([
                    $username, $email, $hashedPassword, $full_name, $phone
                ]);
                
                $userId = $db->lastInsertId();
                
                // רישום בלוג
                $logStmt = $db->prepare("
                    INSERT INTO activity_log 
                    (user_id, action, details, ip_address, user_agent) 
                    VALUES (?, 'user_registered', ?, ?, ?)
                ");
                
                $logStmt->execute([
                    $userId,
                    json_encode([
                        'username' => $username,
                        'email' => $email,
                        'registration_type' => 'manual'
                    ]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                // שליחת הודעה למנהלים
                notifyAdminsAboutNewUser($userId, $username, $email, $full_name);
                
                // הצגת הודעת הצלחה והפניה
                $_SESSION['registration_success'] = 'הרישום הושלם בהצלחה! החשבון שלך ממתין לאישור מנהל.';
                header('Location: login.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'שגיאה ביצירת החשבון. נסה שוב מאוחר יותר.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// פונקציה לשליחת הודעה למנהלים
function notifyAdminsAboutNewUser($userId, $username, $email, $fullName) {
    try {
        $db = getDbConnection();
        
        // קבלת כל המנהלים
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE permission_level = 4 AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        // כאן תוכל להוסיף שליחת מייל למנהלים
        // לדוגמה: mail($admin['email'], 'משתמש חדש ממתין לאישור', $message);
        
    } catch (Exception $e) {
        error_log("Error notifying admins: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>רישום למערכת</title>
    <!-- הוסף את השורות האלה לPWA -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
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
            max-width: 500px;
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
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <i class="fas fa-user-plus"></i>
                <h3>רישום למערכת</h3>
                <p class="text-muted">צור חשבון חדש</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Google Sign-In Button -->
            <div class="google-signin text-center">
                <div id="g_id_onload"
                     data-client_id="453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com"
                     data-callback="handleGoogleSignUp"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     data-theme="outline"
                     data-text="signup_with"
                     data-shape="rectangular"
                     data-logo_alignment="left"
                     data-width="100%">
                </div>
            </div>

            <div class="divider">
                <span>או</span>
            </div>

            <form method="POST" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">שם משתמש <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                   required minlength="3">
                        </div>
                        <small class="form-text text-muted">לפחות 3 תווים</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">אימייל <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="full_name" class="form-label">שם מלא <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                               required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">טלפון</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">סיסמה <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="form-text text-muted">לפחות 6 תווים</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="password_confirm" class="form-label">אימות סיסמה <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                   required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            אני מסכים/ה ל<a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">תנאי השימוש</a> 
                            ול<a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">מדיניות הפרטיות</a>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="registerBtn">
                    <i class="fas fa-user-plus"></i> הרשם
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">כבר יש לך חשבון? 
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-sign-in-alt"></i> התחבר
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">תנאי שימוש</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. כללי</h6>
                    <p>השימוש במערכת ניהול טפסי נפטרים כפוף לתנאים המפורטים להלן...</p>
                    
                    <h6>2. פרטיות</h6>
                    <p>אנו מתחייבים לשמור על פרטיות המשתמשים ולא לחשוף מידע אישי...</p>
                    
                    <h6>3. אחריות</h6>
                    <p>המשתמש אחראי לשמירת סודיות שם המשתמש והסיסמה שלו...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">מדיניות פרטיות</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>איסוף מידע</h6>
                    <p>אנו אוספים מידע שאתה מספק באופן ישיר, כגון שם, אימייל וטלפון...</p>
                    
                    <h6>שימוש במידע</h6>
                    <p>המידע משמש אותנו לצורך ניהול החשבון שלך ומתן שירות...</p>
                    
                    <h6>אבטחת מידע</h6>
                    <p>אנו נוקטים באמצעי אבטחה מתקדמים להגנה על המידע שלך...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            togglePasswordVisibility('password', this);
        });
        
        document.getElementById('togglePasswordConfirm').addEventListener('click', function() {
            togglePasswordVisibility('password_confirm', this);
        });
        
        function togglePasswordVisibility(fieldId, button) {
            const passwordField = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
            } else if (password.length < 6) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (password.length < 10 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('הסיסמאות אינן תואמות');
                return false;
            }
            
            const btn = document.getElementById('registerBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> נרשם...';
            
            // Safety timeout
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 5000);
        });
        
        // Google Sign-Up callback
        function handleGoogleSignUp(response) {
            // שלח את הטוקן לשרת
            fetch('google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    credential: response.credential,
                    action: 'register'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.message) {
                        alert(data.message);
                    }
                    window.location.href = 'login.php';
                } else {
                    alert(data.message || 'שגיאה ברישום עם Google');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה ברישום עם Google');
            });
        }
    </script>
</body>
</html>