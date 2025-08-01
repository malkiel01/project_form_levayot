<?php
// // login.php - דף התחברות למערכת

// require_once 'config.php';

// // אם המשתמש כבר מחובר
// if (isset($_SESSION['user_id'])) {
//     header('Location: dashboard.php');
//     exit;
// }

// $error = '';

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // בדיקת CSRF token
//     if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//         die('Invalid CSRF token');
//     }
    
//     $username = sanitizeInput($_POST['username'] ?? '');
//     $password = $_POST['password'] ?? '';
    
//     // כאן תוסיף את הלוגיקה לבדיקת משתמש מול הדטהבייס
//     // לצורך הדגמה:
//     if ($username === 'admin' && $password === 'admin123') {
//         $_SESSION['user_id'] = 1;
//         $_SESSION['username'] = $username;
//         $_SESSION['permission_level'] = 4; // מנהל
        
//         header('Location: dashboard.php');
//         exit;
//     } elseif ($username === 'editor' && $password === 'editor123') {
//         $_SESSION['user_id'] = 2;
//         $_SESSION['username'] = $username;
//         $_SESSION['permission_level'] = 2; // עורך
        
//         header('Location: ' . FORM_URL );
//         exit;
//     } else {
//         $error = 'שם משתמש או סיסמה שגויים';
//     }
// }
?>
<!-- <!DOCTYPE html> -->
<!-- <html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות למערכת</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 48px;
            color: #007bff;
            margin-bottom: 10px;
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
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">שם משתמש</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">סיסמה</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">
                        זכור אותי
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt"></i> התחבר
                </button>
            </form>
            
            <hr class="my-4">
            
            <div class="text-center text-muted">
                <small>
                    לצורך הדגמה:<br>
                    מנהל: admin / admin123<br>
                    עורך: editor / editor123
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->