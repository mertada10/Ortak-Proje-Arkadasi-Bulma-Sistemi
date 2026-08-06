<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$project_id = isset($_GET["project"]) ? (int)$_GET["project"] : 0;
$target_user_id = isset($_GET["user"]) ? (int)$_GET["user"] : 0;

if ($project_id <= 0 && $target_user_id <= 0) {
    header("Location: ../index.php");
    exit;
}

$target_user = null;
$redirect_page = "../index.php";
$request_project_id = 0;
$return_page = trim($_GET["return"] ?? "");

if ($project_id > 0) {
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
    $request_project_id = $project_id;
    $redirect_page = "project-detail.php?id=".$project_id;
} elseif ($target_user_id > 0) {
    $request_project_id = 0;
    $redirect_page = "user-profile.php?id=".$target_user_id;
}

if ($return_page === "search-teammates.php") {
    $redirect_page = "search-teammates.php";
}

$redirect_separator = (strpos($redirect_page, "?") !== false) ? "&" : "?";

if ($target_user_id > 0) {
    $stmt = $pdo->prepare("SELECT id, name, surname FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch();
}

if (!$target_user && $target_user_id > 0) {
    header("Location: ../index.php");
    exit;
}

if ($target_user_id == $_SESSION["user_id"]) {
    header("Location: " . ($project_id > 0 ? "project-detail.php?id=".$project_id : "user-profile.php?id=".$target_user_id));
    exit;
}

$stmt = $pdo->prepare("
SELECT id
FROM team_requests
WHERE status='pending'
AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
LIMIT 1
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $target_user_id,
    $_SESSION["user_id"]
]);

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . $redirect_separator . "alreadypending=1");
    exit;
}

$stmt = $pdo->prepare("
SELECT id
FROM team_requests
WHERE sender_id=?
AND receiver_id=?
AND project_id=?
AND status='pending'
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $request_project_id
]);

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . $redirect_separator . "already=1");
    exit;
}

$stmt = $pdo->prepare("
SELECT team_id
FROM team_members
WHERE user_id=?
");

$stmt->execute([
    $_SESSION["user_id"]
]);

$senderTeam = $stmt->fetch();

$stmt = $pdo->prepare("
SELECT team_id
FROM team_members
WHERE user_id=?
");

$stmt->execute([
    $target_user_id
]);

$receiverTeam = $stmt->fetch();

if ($senderTeam) {

    if ($receiverTeam) {

        header("Location: " . $redirect_page . $redirect_separator . "alreadyteam=1");
        exit;

    }

}

if ($senderTeam && !$receiverTeam) {

    $type = "join_team";
    $actionType = "invite";

} elseif (!$senderTeam && $receiverTeam) {

    $type = "join_team";
    $actionType = "join";

} else {
    $type = "create_team";
    $actionType = ($project_id > 0 ? "invite" : "create");
}

$final_project_id = (!empty($request_project_id) && (int)$request_project_id > 0) 
    ? (int)$request_project_id 
    : null;

$stmt = $pdo->prepare("
    INSERT INTO team_requests
    (sender_id, receiver_id, project_id, request_type)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $final_project_id,
    $type
]);

header("Location: " . $redirect_page . $redirect_separator . "success=1&sent=" . urlencode($actionType));
exit;