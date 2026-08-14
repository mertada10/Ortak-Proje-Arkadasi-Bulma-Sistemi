<?php
session_start();

require_once "config/database.php";

if (isset($_COOKIE["remember_me"])) {
    $token = $_COOKIE["remember_me"];

    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);

    setcookie("remember_me", "", time() - 3600, "/");
}

session_unset();
session_destroy();

header("Location: login.php");
exit;