<?php
require_once '../config.php'; // חייב להיות לפני כל שורה אחרת
// אין צורך ב-session_start() כאן, זה כבר נטען מהקונפיג!

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"] ?? false,
        $params["httponly"] ?? false
    );
}

session_destroy();

header("Location: ../" . LOGIN_URL);
exit;
