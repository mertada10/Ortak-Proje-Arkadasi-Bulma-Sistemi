<?php
require_once __DIR__ . "/includes/session.php";
session_secure_start();

require_once "config/database.php";

if (isset($_COOKIE["remember_me"])) {
    $token = $_COOKIE["remember_me"];

    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);

    setcookie("remember_me", "", [
        "expires"  => time() - 3600,
        "path"     => "/",
        "httponly" => true,
        "secure"   => is_https_request(),
        "samesite" => "Lax"
    ]);
}

session_unset();
session_destroy();

header("Location: login.php");
exit;