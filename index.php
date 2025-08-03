<?php
// index.php - דף הבית הראשי
session_start();

// אם המשתמש מחובר, העבר לדשבורד
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת ניהול טפסי נפטרים</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .welcome-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .logo-section {
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 100px;
            height: 100px;
            background: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 50px;
            color: white;
        }
        
        .app-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .app-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn-main {
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-login {
            background: #0d6efd;
            color: white;
        }
        
        .btn-login:hover {
            background: #0b5ed7;
            color: white;
        }
        
        .btn-register {
            background: white;
            color: #0d6efd;
            border: 2px solid #0d6efd;
        }
        
        .btn-register:hover {
            background: #0d6efd;
            color: white;
        }
        
        .btn-guest {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #dee2e6;
        }
        
        .btn-guest:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .features {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            text-align: right;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: #e7f1ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            flex-shrink: 0;
        }
        
        /* PWA Install Button */
        #installButton {
            display: none;
            margin-top: 20px;
            background: #28a745;
            color: white;
        }
        
        #installButton:hover {
            background: #218838;
            color: white;
        }
        
        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .welcome-container {
            animation: slideIn 0.6s ease-out;
        }
        
        @media (max-width: 576px) {
            .welcome-container {
                padding: 30px 20px;
            }
            
            .app-title {
                font-size: 24px;
            }
            
            .btn-main {
                font-size: 16px;
                padding: 12px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h1 class="app-title">מערכת ניהול טפסי נפטרים</h1>
            <p class="app-subtitle">ניהול טפסים דיגיטלי, מאובטח ונוח</p>
        </div>
        
        <div class="action-buttons">
            <a href="auth/login.php" class="btn btn-main btn-login">
                <i class="fas fa-sign-in-alt"></i>
                כניסה למערכת
            </a>
            
            <a href="auth/register.php" class="btn btn-main btn-register">
                <i class="fas fa-user-plus"></i>
                הרשמה חדשה
            </a>
            
            <a href="form/" class="btn btn-main btn-guest">
                <i class="fas fa-eye"></i>
                צפייה בטופס לדוגמה
            </a>
            
            <!-- PWA Install Button -->
            <button id="installButton" class="btn btn-main" style="display: none;">
                <i class="fas fa-download"></i>
                התקן כאפליקציה
            </button>
        </div>
        
        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div>
                    <strong>אפליקציה מובנית</strong>
                    <br>
                    <small>התקן על המכשיר לגישה מהירה</small>
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <div>
                    <strong>שיתוף קבצים חכם</strong>
                    <br>
                    <small>שתף ישירות מכל אפליקציה</small>
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <strong>אבטחה מתקדמת</strong>
                    <br>
                    <small>הצפנה והגנה על המידע</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PWA Script -->
    <script>
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        
        // PWA Install Prompt
        let deferredPrompt;
        const installButton = document.getElementById('installButton');
        
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt = e;
            // Update UI to notify the user they can add to home screen
            installButton.style.display = 'flex';
            
            installButton.addEventListener('click', (e) => {
                // Hide our user interface that shows our A2HS button
                installButton.style.display = 'none';
                // Show the prompt
                deferredPrompt.prompt();
                // Wait for the user to respond to the prompt
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                    }
                    deferredPrompt = null;
                });
            });
        });
        
        // Detect if app is installed
        window.addEventListener('appinstalled', (evt) => {
            console.log('App was installed');
            installButton.style.display = 'none';
        });
        
        // Check if running as PWA
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('Running as PWA');
            // Hide install button if already installed
            installButton.style.display = 'none';
        }
    </script>
</body>
</html>