<?php
require_once '../config.php';

echo "<h1>בדיקה מהירה - Google Auth</h1>";

// בדיקה 1: Google Client
echo "<h2>1. Google Client Library</h2>";
if (class_exists('Google_Client')) {
    echo "<p style='color: green;'>✓ Google_Client זמין</p>";
} else {
    echo "<p style='color: orange;'>⚠ Google_Client לא זמין - נשתמש באימות ידני</p>";
}

// בדיקה 2: טסט אימות Google Token
echo "<h2>2. בדיקת פונקציית אימות</h2>";

function verifyGoogleToken($idToken, $clientId) {
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $idToken;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => ['User-Agent: PHP Google Auth']
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || isset($data['error'])) {
        return false;
    }
    
    if ($data['aud'] !== $clientId) {
        return false;
    }
    
    if (!isset($data['exp']) || $data['exp'] < time()) {
        return false;
    }
    
    return $data;
}

// טסט עם טוקן מזויף
$testResult = verifyGoogleToken('invalid_token', '453102975463-3fhe60iqfqh7bgprufpkddv4v29cobfb.apps.googleusercontent.com');
if ($testResult === false) {
    echo "<p style='color: green;'>✓ פונקציית אימות עובדת (דחה טוקן מזויף)</p>";
} else {
    echo "<p style='color: red;'>✗ פונקציית אימות לא עובדת כמו שצריך</p>";
}

// בדיקה 3: בדיקת עמודות במסד נתונים
echo "<h2>3. בדיקת מסד נתונים</h2>";
try {
    $db = getDbConnection();
    
    // בדוק עמודת google_id
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($stmt->fetch()) {
        echo "<p style='color: green;'>✓ עמודה google_id קיימת</p>";
    } else {
        echo "<p style='color: orange;'>⚠ עמודה google_id לא קיימת</p>";
        echo "<p>הוסף עם: <code>ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL;</code></p>";
        
        // נסה להוסיף אוטומטית
        try {
            $db->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL");
            echo "<p style='color: green;'>✓ עמודה google_id נוספה אוטומטית</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ לא הצליח להוסיף עמודה: " . $e->getMessage() . "</p>";
        }
    }
    
    // בדוק עמודת profile_picture
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->fetch()) {
        echo "<p style='color: green;'>✓ עמודה profile_picture קיימת</p>";
    } else {
        echo "<p style='color: orange;'>⚠ עמודה profile_picture לא קיימת</p>";
        
        // נסה להוסיף אוטומטית
        try {
            $db->exec("ALTER TABLE users ADD COLUMN profile_picture TEXT NULL");
            echo "<p style='color: green;'>✓ עמודה profile_picture נוספה אוטומטית</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ לא הצליח להוסיף עמודה: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ שגיאה במסד נתונים: " . $e->getMessage() . "</p>";
}

// בדיקה 4: בדיקת קובץ google_auth.php
echo "<h2>4. בדיקת google_auth.php</h2>";
if (file_exists('google_auth.php')) {
    echo "<p style='color: green;'>✓ קובץ google_auth.php קיים</p>";
    
    // בדיקת גודל קובץ
    $fileSize = filesize('google_auth.php');
    echo "<p>גודל קובץ: " . number_format($fileSize) . " בתים</p>";
    
    if ($fileSize > 1000) {
        echo "<p style='color: green;'>✓ גודל קובץ נראה תקין</p>";
    } else {
        echo "<p style='color: orange;'>⚠ קובץ קטן מדי</p>";
    }
} else {
    echo "<p style='color: red;'>✗ קובץ google_auth.php לא נמצא</p>";
}

// בדיקה 5: סימולציה של בקשת POST
echo "<h2>5. סימולציה של בקשת Google Auth</h2>";
echo "<form id='testForm' onsubmit='return false;'>";
echo "<button type='button' onclick='testGoogleAuth()'>בדוק Google Auth</button>";
echo "</form>";
echo "<div id='testResult'></div>";

?>

<script>
function testGoogleAuth() {
    const testData = {
        credential: 'test_invalid_token',
        action: 'login',
        redirect: 'test'
    };
    
    fetch('google_auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(testData)
    })
    .then(response => response.text())
    .then(text => {
        document.getElementById('testResult').innerHTML = 
            '<h3>תגובה מהשרת:</h3><pre>' + text + '</pre>';
        
        try {
            const json = JSON.parse(text);
            if (json.success === false && json.message) {
                document.getElementById('testResult').innerHTML += 
                    '<p style="color: green;">✓ השרת מחזיר JSON תקין</p>';
            }
        } catch (e) {
            document.getElementById('testResult').innerHTML += 
                '<p style="color: red;">✗ השרת לא מחזיר JSON תקין</p>';
        }
    })
    .catch(error => {
        document.getElementById('testResult').innerHTML = 
            '<p style="color: red;">שגיאה: ' + error.message + '</p>';
    });
}
</script>

<style>
body {
    font-family: Arial, sans-serif;
    direction: rtl;
    padding: 20px;
    background-color: #f5f5f5;
}
h1, h2 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}
pre, code {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    direction: ltr;
}
button {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
}
</style>