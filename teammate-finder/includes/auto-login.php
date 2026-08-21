<?php
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/csrf.php"; // CSRF doğrulama fonksiyonlar
session_secure_start();

require_once __DIR__ . "/../config/database.php";

$stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'is_active'");
$stmt->execute();
if ($stmt->rowCount() === 0) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

if (!isset($_SESSION["user_id"]) && isset($_COOKIE["remember_me"])) {
    $token = $_COOKIE["remember_me"];

    $stmt = $pdo->prepare("
        SELECT id, profile_image, username, name, surname, role, is_active
        FROM users
        WHERE remember_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user || (int)$user["is_active"] !== 1) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
        $stmt->execute([$token]);

        setcookie("remember_me", "", [
            "expires"  => time() - 3600,
            "path"     => "/",
            "httponly" => true,
            "secure"   => is_https_request(),
            "samesite" => "Lax"
        ]);
        return;
    }

    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["profile_image"] = $user["profile_image"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["name"] = $user["name"];
    $_SESSION["surname"] = $user["surname"];
    $_SESSION["role"] = $user["role"] ?? "user";
    return;
}

if (isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] > 0) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION["user_id"]]);

    if (!$stmt->fetch()) {
        session_unset();
        session_destroy();
        setcookie(session_name(), "", [
            "expires"  => time() - 3600,
            "path"     => "/",
            "httponly" => true,
            "secure"   => is_https_request(),
            "samesite" => "Lax"
        ]);
    }
    return;
}