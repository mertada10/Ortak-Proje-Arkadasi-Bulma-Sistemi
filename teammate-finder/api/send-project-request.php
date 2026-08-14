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

$project_id = isset($_POST["project"]) ? (int)$_POST["project"] : (isset($_GET["project"]) ? (int)$_GET["project"] : 0);
$note = trim($_POST["note"] ?? $_GET["note"] ?? "");
if (mb_strlen($note) > 500) {
    $note = mb_substr($note, 0, 500);
}

if ($project_id <= 0) {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT projects.*, users.id AS owner_id
    FROM projects
    JOIN users ON projects.user_id = users.id
    WHERE projects.id = ?
");

$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: ../index.php");
    exit;
}

$target_user_id = $project["owner_id"];
$redirect_page = "/teammate-finder/pages/project-detail.php?id=" . $project_id;
$redirect_separator = "?";

if ($target_user_id == $_SESSION["user_id"]) {
    header("Location: " . $redirect_page);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM requests
    WHERE status='pending'
    AND project_id = ?
    AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
    LIMIT 1
");

$stmt->execute([
    $project_id,
    $_SESSION["user_id"],
    $target_user_id,
    $target_user_id,
    $_SESSION["user_id"]
]);

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . "&alreadypending=1");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM requests
    WHERE sender_id=?
    AND receiver_id=?
    AND project_id=?
    AND status='pending'
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $project_id
]);

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . "&already=1");
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO requests
    (sender_id, receiver_id, project_id, request_type, message)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $project_id,
    "project_request",
    $note
]);

header("Location: " . $redirect_page . "&success=1&sent=project_request");
exit;