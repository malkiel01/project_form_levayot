<?php
// debug_viewer.php - דף צפייה בלוגים
require_once 'config.php';

// בדיקת הרשאה - רק למנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die('Access denied');
}

$logFile = 'logs/ajax_save_' . date('Y-m-d') . '.log';
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>Debug Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-viewer {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 14px;
            padding: 20px;
            margin: 20px;
            border-radius: 5px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 10px;
            padding: 10px;
            border-left: 3px solid #007acc;
        }
        .log-error {
            border-left-color: #f44336;
            background-color: rgba(244, 67, 54, 0.1);
        }
        .log-success {
            border-left-color: #4caf50;
            background-color: rgba(76, 175, 80, 0.1);
        }
        .timestamp {
            color: #569cd6;
        }
        .refresh-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="text-center mt-3">Debug Log Viewer</h1>
        
        <button class="btn btn-primary refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync"></i> רענן
        </button>
        
        <div class="log-viewer">
            <?php
            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    $class = 'log-entry';
                    if (strpos($line, 'ERROR') !== false) {
                        $class .= ' log-error';
                    } elseif (strpos($line, 'SUCCESS') !== false) {
                        $class .= ' log-success';
                    }
                    
                    // הדגש timestamps
                    $line = preg_replace('/\[(.*?)\]/', '<span class="timestamp">[$1]</span>', $line);
                    
                    echo "<div class='{$class}'>" . nl2br(htmlspecialchars($line)) . "</div>";
                }
            } else {
                echo "<p>לא נמצא קובץ לוג להיום</p>";
            }
            ?>
        </div>
        
        <!-- רענון אוטומטי כל 5 שניות -->
        <script>
            setTimeout(() => location.reload(), 5000);
        </script>
    </div>
</body>
</html>