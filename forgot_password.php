<?php
// forgot_password.php - שחזור סיסמה

require_once 'config.php';

// אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // בדיקת CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'יש להזין כתובת אימייל';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'כתובת אימייל לא תקינה';
    } else {
        $db = getDbConnection();
        
        // בדיקה אם המשתמש קיים
        $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // יצירת טוקן לאיפוס סיסמה
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // שמירת הטוקן בדטהבייס
            $tokenStmt = $db->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $tokenStmt->execute([$user['id'], $token, $expiresAt]);
            
            // שליחת אימייל
            $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
            $subject = 'איפוס סיסמה - מערכת ניהול נפטרים';
            $body = "
                <html dir='rtl'>
                <body>
                    <h2>שלום {$user['full_name']},</h2>
                    <p>קיבלנו בקשה לאיפוס הסיסמה שלך.</p>
                    <p>לחץ על הקישור הבא לאיפוס הסיסמה:</p>
                    <p><a href='{$resetLink}'>איפוס סיסמה</a></p>
                    <p>הקישור תקף לשעה אחת בלבד.</p>
                    <p>אם לא ביקשת איפוס סיסמה, אנא התעלם מהודעה זו.</p>
                    <br>
                    <p>בברכה,<br>צוות מערכת ניהול נפטרים</p>
                </body>
                </html>
            ";
            
            // כאן תוסיף את הקוד לשליחת אימייל
            // mail($email, $subject, $body, $headers);
            
            $message = 'נשלח אימייל עם הוראות לאיפוס הסיסמה';
            
            // רישום בלוג
            $logStmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'password_reset_request', ?, ?, ?)
            ");
            $logStmt->execute([
                $user['id'],
                json_encode(['email' => $email]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } else {
            // למניעת חשיפת מידע, נציג את אותה הודעה
            $message = 'נשלח אימייל עם הוראות לאיפוס הסיסמה';
        }
    }
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>שחזור סיסמה</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .forgot-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header i {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-container">
            <div class="forgot-header">
                <i class="fas fa-key"></i>
                <h3>שחזור סיסמה</h3>
                <p class="text-muted">הזן את כתובת האימייל שלך</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label for="email" class="form-label">כתובת אימייל</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autofocus>
                    </div>
                    <div class="form-text">נשלח קישור לאיפוס סיסמה לכתובת זו</div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-paper-plane"></i> שלח קישור לאיפוס
                </button>
                
                <div class="text-center mt-3">
                    <a href="<?= LOGIN_URL ?>" class="text-decoration-none">
                        <i class="fas fa-arrow-right"></i> חזרה להתחברות
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>