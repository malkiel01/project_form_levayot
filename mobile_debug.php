<?php
// mobile_debug.php - בדיקת בעיות גישה מהמובייל

// התחל session בצורה בטוחה
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// פונקציה לבדיקת HTTPS
function isHTTPS() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
}

// פונקציה לבדיקת User Agent
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת תקלות מובייל</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-ok { color: green; }
        .status-warning { color: orange; }
        .status-error { color: red; }
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>בדיקת תקלות גישה מהמובייל</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>1. בדיקת סביבת הגלישה</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>סוג מכשיר:</td>
                        <td><?= isMobile() ? '<span class="status-ok">מובייל</span>' : '<span class="status-warning">דסקטופ</span>' ?></td>
                    </tr>
                    <tr>
                        <td>User Agent:</td>
                        <td class="debug-info"><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'לא זוהה') ?></td>
                    </tr>
                    <tr>
                        <td>כתובת IP:</td>
                        <td><?= $_SERVER['REMOTE_ADDR'] ?? 'לא זוהה' ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>2. בדיקת HTTPS/SSL</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>חיבור HTTPS:</td>
                        <td><?= isHTTPS() ? '<span class="status-ok">✓ מאובטח</span>' : '<span class="status-error">✗ לא מאובטח</span>' ?></td>
                    </tr>
                    <tr>
                        <td>פרוטוקול:</td>
                        <td><?= $_SERVER['SERVER_PROTOCOL'] ?? 'לא זוהה' ?></td>
                    </tr>
                    <tr>
                        <td>פורט:</td>
                        <td><?= $_SERVER['SERVER_PORT'] ?? 'לא זוהה' ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>3. בדיקת Session</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Session ID:</td>
                        <td class="debug-info"><?= session_id() ?: '<span class="status-error">אין Session פעיל</span>' ?></td>
                    </tr>
                    <tr>
                        <td>Session Status:</td>
                        <td>
                            <?php
                            $status = session_status();
                            if ($status == PHP_SESSION_ACTIVE) {
                                echo '<span class="status-ok">✓ פעיל</span>';
                            } elseif ($status == PHP_SESSION_NONE) {
                                echo '<span class="status-error">✗ לא פעיל</span>';
                            } else {
                                echo '<span class="status-warning">⚠ מושבת</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Session Cookie:</td>
                        <td>
                            <?php
                            $cookieName = session_name();
                            if (isset($_COOKIE[$cookieName])) {
                                echo '<span class="status-ok">✓ קיים</span>';
                            } else {
                                echo '<span class="status-error">✗ חסר</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>הגדרות Cookie:</td>
                        <td class="debug-info">
                            <?php
                            $params = session_get_cookie_params();
                            echo "Lifetime: {$params['lifetime']}<br>";
                            echo "Path: {$params['path']}<br>";
                            echo "Domain: {$params['domain']}<br>";
                            echo "Secure: " . ($params['secure'] ? 'Yes' : 'No') . "<br>";
                            echo "HttpOnly: " . ($params['httponly'] ? 'Yes' : 'No') . "<br>";
                            echo "SameSite: " . ($params['samesite'] ?? 'Not set');
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>4. בדיקת Headers</h3>
            </div>
            <div class="card-body">
                <div class="debug-info">
                    <?php
                    $headers = getallheaders();
                    foreach ($headers as $name => $value) {
                        echo htmlspecialchars("$name: $value") . "<br>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>5. בדיקת גישה לקבצים</h3>
            </div>
            <div class="card-body">
                <?php
                $testFiles = [
                    'config.php' => file_exists('config.php'),
                    'forms_list.php' => file_exists('forms_list.php'),
                    '.htaccess' => file_exists('.htaccess'),
                ];
                ?>
                <table class="table">
                    <?php foreach ($testFiles as $file => $exists): ?>
                    <tr>
                        <td><?= $file ?>:</td>
                        <td><?= $exists ? '<span class="status-ok">✓ קיים</span>' : '<span class="status-error">✗ לא נמצא</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>6. בדיקת התחברות</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <p class="status-ok">✓ משתמש מחובר</p>
                    <table class="table">
                        <tr><td>User ID:</td><td><?= $_SESSION['user_id'] ?></td></tr>
                        <tr><td>Username:</td><td><?= $_SESSION['username'] ?? 'לא הוגדר' ?></td></tr>
                        <tr><td>Permission Level:</td><td><?= $_SESSION['permission_level'] ?? 'לא הוגדר' ?></td></tr>
                    </table>
                <?php else: ?>
                    <p class="status-warning">⚠ משתמש לא מחובר</p>
                    <a href="auth/login.php" class="btn btn-primary">עבור להתחברות</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3>7. המלצות לתיקון</h3>
            </div>
            <div class="card-body">
                <ol>
                    <?php if (!isHTTPS()): ?>
                        <li class="status-error">עבור לחיבור HTTPS מאובטח</li>
                    <?php endif; ?>
                    
                    <?php if (!isset($_COOKIE[session_name()])): ?>
                        <li class="status-error">בדוק שה-Cookies מופעלים במכשיר</li>
                    <?php endif; ?>
                    
                    <?php if (isMobile()): ?>
                        <li>נקה את מטמון הדפדפן במכשיר</li>
                        <li>נסה במצב גלישה רגילה (לא פרטית)</li>
                        <li>בדוק שאין חוסם פרסומות שחוסם cookies</li>
                    <?php endif; ?>
                    
                    <li>נסה לגשת דרך: <br>
                        <code>https://vaadma.cemeteries.mbe-plus.com/project_form_levayot/forms_list.php</code>
                    </li>
                </ol>
            </div>
        </div>

        <div class="mt-4 mb-5 text-center">
            <a href="forms_list.php" class="btn btn-primary">נסה לגשת לרשימת טפסים</a>
            <a href="auth/login.php" class="btn btn-secondary">עבור להתחברות</a>
        </div>
    </div>
</body>
</html>