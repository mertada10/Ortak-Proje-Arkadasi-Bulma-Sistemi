<?php
require_once __DIR__ . "/../includes/cors.php";
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

if (!isset($_FILES["avatar"]) || $_FILES["avatar"]["error"] != 0) {
    $_SESSION["error"] = "Fotoğraf seçilemedi.";
    header("Location: user-profile.php");
    exit;
}

$allowed = ["jpg", "jpeg", "png", "webp"];

$extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));

if (!in_array($extension, $allowed)) {
    $_SESSION["error"] = "Sadece JPG, JPEG, PNG ve WEBP yükleyebilirsiniz.";
    header("Location: user-profile.php");
    exit;
}

$fileName = uniqid("avatar_") . "." . $extension;
$uploadPath = "../assets/uploads/" . $fileName;

if (!move_uploaded_file($_FILES["avatar"]["tmp_name"], $uploadPath)) {
    $_SESSION["error"] = "Fotoğraf yüklenemedi.";
    header("Location: user-profile.php");
    exit;
}

$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id=?");
$stmt->execute([$_SESSION["user_id"]]);
$user = $stmt->fetch();

if (!empty($user["profile_image"])) {

    $oldFile = "../assets/uploads/" . $user["profile_image"];

    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

$update = $pdo->prepare("
UPDATE users
SET profile_image=?
WHERE id=?
");

$update->execute([
    $fileName,
    $_SESSION["user_id"]
]);

$_SESSION["profile_image"] = $fileName;

$_SESSION["success"] = "Profil fotoğrafı güncellendi.";

header("Location: /teammate-finder/pages/user-profile.php");
exit;