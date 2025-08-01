<?php
// require_once '../config.php';

// $error = '';
// $debug = [];

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = trim($_POST['username'] ?? '');
//     $password = $_POST['password'] ?? '';

//     $debug[] = "Username entered: $username";
//     $debug[] = "Password entered: $password";

//     try {
//         $db = getDbConnection();
//         $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
//         $stmt->execute([$username]);
//         $user = $stmt->fetch();

//         if ($user) {
//             $hashInDb = $user['password'];
//             $debug[] = "Hash from DB: $hashInDb";
            
//             // הדפסת hash חדש שיווצר מהסיסמה הנוכחית (לא אמור להיות תואם, אבל להראות פורמט)
//             $hashGenerated = password_hash($password, PASSWORD_DEFAULT);
//             $debug[] = "Hash generated from entered password: $hashGenerated";
//             $debug[] = "Length of DB hash: " . strlen($hashInDb) . ", Length of generated hash: " . strlen($hashGenerated);

//             // בדיקת התאמה
//             if (password_verify($password, $hashInDb)) {
//                 $debug[] = "<b>SUCCESS: password_verify returned TRUE!</b>";
//             } else {
//                 $debug[] = "<b>FAIL: password_verify returned FALSE.</b>";
//             }

//             // השוואה תו לתו
//             $diff = [];
//             $len = max(strlen($hashInDb), strlen($hashGenerated));
//             for ($i = 0; $i < $len; $i++) {
//                 $dbChar = $hashInDb[$i] ?? '';
//                 $genChar = $hashGenerated[$i] ?? '';
//                 if ($dbChar !== $genChar) {
//                     $diff[] = "Pos $i: DB='" . addslashes($dbChar) . "' / NEW='" . addslashes($genChar) . "'";
//                 }
//             }
//             if ($diff) {
//                 $debug[] = "<b>Diff between DB hash and generated hash (should be different):</b><br>" . implode("<br>", $diff);
//             } else {
//                 $debug[] = "<b>No char differences found (unexpected!)</b>";
//             }

//         } else {
//             $debug[] = "No user found for username: $username";
//         }

//     } catch (Exception $e) {
//         $debug[] = "DB ERROR: " . $e->getMessage();
//     }
// }
?>
<!-- <!DOCTYPE html> -->
<!-- <html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Login Debug</title>
    <style>
        body { font-family: Arial; background: #f8f8ff; padding: 30px;}
        form { background: #fff; padding: 20px; border-radius: 10px; max-width: 400px; margin: auto; box-shadow: 0 3px 12px #0002;}
        .debug { background: #eee; border-radius: 6px; margin: 20px auto; padding: 12px 20px; font-size: 15px; direction: ltr; }
        label { display: block; margin: 10px 0 4px; }
        input { width: 100%; padding: 6px 8px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #bbb;}
        button { padding: 10px 20px; border: none; background: #667eea; color: #fff; border-radius: 6px; font-size: 16px; }
    </style>
</head>
<body>
    <form method="post">
        <h2>דף לוגין לדיבוג</h2>
        <label>Username:</label>
        <input type="text" name="username" required autofocus>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit">בדוק סיסמה</button>
    </form>

    <?php if ($debug): ?>
        <div class="debug">
            <b>DEBUG INFO:</b><br>
            <?= implode('<br>', $debug) ?>
        </div>
    <?php endif; ?>
</body>
</html> -->
