<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] > 0) {
    return;
}

if (!isset($_COOKIE["remember_me"])) {
    return;
}

require_once __DIR__ . "/../config/database.php";

$token = $_COOKIE["remember_me"];

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE remember_token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {

    setcookie("remember_me", "", time() - 3600, "/");
    return;
}

session_regenerate_id(true);

$_SESSION["user_id"] = $user["id"];
$_SESSION["profile_image"] = $user["profile_image"];
$_SESSION["username"] = $user["username"];
$_SESSION["name"] = $user["name"];
$_SESSION["surname"] = $user["surname"];
$_SESSION["role"] = $user["role"] ?? "user";