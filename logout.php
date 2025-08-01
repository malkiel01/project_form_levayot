<?php
// // logout.php - יציאה מהמערכת

// session_start();

// // ניקוי כל משתני הסשן
// $_SESSION = array();

// // מחיקת cookie הסשן
// if (ini_get("session.use_cookies")) {
//     $params = session_get_cookie_params();
//     setcookie(session_name(), '', time() - 42000,
//         $params["path"], $params["domain"],
//         $params["secure"], $params["httponly"]
//     );
// }

// // הרס הסשן
// session_destroy();

// // הפניה לדף ההתחברות
// header("Location: " . LOGIN_URL );
// exit;

?>