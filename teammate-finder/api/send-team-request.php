<?php
session_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$project_id = isset($_GET["project"]) ? (int)$_GET["project"] : 0;
$target_user_id = isset($_GET["user"]) ? (int)$_GET["user"] : 0;
$selected_team_id = isset($_GET["team"]) ? (int)$_GET["team"] : 0;
$note = trim($_GET["note"] ?? "");
if (mb_strlen($note) > 500) {
    $note = mb_substr($note, 0, 500);
}

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
    $redirect_page = "/teammate-finder/pages/user-profile.php?id=".$target_user_id;
}

if ($return_page === "/teammate-finder/pages/find-teammates.php") {
    $redirect_page = "/teammate-finder/pages/find-teammates.php";
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

if ($request_project_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE status='pending'
          AND project_id = ?
          AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
        LIMIT 1
    ");

    $stmt->execute([
        $request_project_id,
        $_SESSION["user_id"],
        $target_user_id,
        $target_user_id,
        $_SESSION["user_id"]
    ]);
} elseif ($selected_team_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE status='pending'
          AND project_id IS NULL
          AND team_id = ?
          AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
        LIMIT 1
    ");

    $stmt->execute([
        $selected_team_id,
        $_SESSION["user_id"],
        $target_user_id,
        $target_user_id,
        $_SESSION["user_id"]
    ]);
} else {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE status='pending'
          AND project_id IS NULL
          AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION["user_id"],
        $target_user_id,
        $target_user_id,
        $_SESSION["user_id"]
    ]);
}

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . $redirect_separator . "alreadypending=1");
    exit;
}

if ($request_project_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE sender_id=?
          AND receiver_id=?
          AND project_id=?
          AND status='pending'
        LIMIT 1
    ");
    $stmt->execute([
        $_SESSION["user_id"],
        $target_user_id,
        $request_project_id
    ]);
} elseif ($selected_team_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE sender_id=?
          AND receiver_id=?
          AND team_id=?
          AND project_id IS NULL
          AND status='pending'
        LIMIT 1
    ");
    $stmt->execute([
        $_SESSION["user_id"],
        $target_user_id,
        $selected_team_id
    ]);
} else {
    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE sender_id=?
          AND receiver_id=?
          AND project_id IS NULL
          AND status='pending'
        LIMIT 1
    ");
    $stmt->execute([
        $_SESSION["user_id"],
        $target_user_id
    ]);
}

if ($stmt->fetch()) {
    header("Location: " . $redirect_page . $redirect_separator . "already=1");
    exit;
}

$senderTeam = null;

if ($target_user_id > 0 && $selected_team_id > 0) {
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM team_members
        WHERE team_id = ? AND user_id = ? AND role IN ('leader', 'co_leader')
        LIMIT 1
    ");
    $stmt->execute([$selected_team_id, $_SESSION["user_id"]]);

    if ($stmt->fetch()) {
        $senderTeam = ["team_id" => $selected_team_id];
    } else {
        header("Location: " . $redirect_page);
        exit;
    }
} else {
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM team_members
        WHERE user_id=?
    ");
    $stmt->execute([
        $_SESSION["user_id"]
    ]);
    $senderTeam = $stmt->fetch();
}

$stmt = $pdo->prepare("
    SELECT team_id
    FROM team_members
    WHERE user_id=?
");

$stmt->execute([
    $target_user_id
]);

$receiverTeam = $stmt->fetch();

if ($selected_team_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM team_members
        WHERE team_id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$selected_team_id, $target_user_id]);
    if ($stmt->fetch()) {
        header("Location: " . $redirect_page . $redirect_separator . "alreadyteam=1");
        exit;
    }
}

if ($project_id > 0) {
    $type = "project_request";
    
    if ($selected_team_id > 0) {
        $actionType = "invite";
    } elseif ($senderTeam && !$receiverTeam) {
        $actionType = "join_project";
    } elseif (!$senderTeam && $receiverTeam) {
        $actionType = "join_project";
    } else {
        $actionType = "join_project";
    }
} else {
    $type = "team_request";
    
    if ($selected_team_id > 0) {
        $actionType = "invite";
    } elseif ($senderTeam && !$receiverTeam) {
        $actionType = "invite";
    } elseif (!$senderTeam && $receiverTeam) {
        $actionType = "join";
    } else {
        $actionType = "create";
    }
}

$final_project_id = (!empty($request_project_id) && (int)$request_project_id > 0) 
    ? (int)$request_project_id 
    : null;

if ($final_project_id > 0 && $senderTeam) {
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE user_id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM team_members WHERE team_id = ?
        ");
        $stmt->execute([$senderTeam["team_id"]]);
        $remainingMembers = $stmt->fetchColumn();
        
        if ($remainingMembers == 0) {
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$senderTeam["team_id"]]);
        }
        
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

$stmt = $pdo->prepare("
    INSERT INTO requests
    (sender_id, receiver_id, team_id, project_id, request_type, message)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION["user_id"],
    $target_user_id,
    $selected_team_id > 0 ? $selected_team_id : null,
    $final_project_id,
    $type,
    $note
]);

header("Location: " . $redirect_page . $redirect_separator . "success=1&sent=" . urlencode($actionType));
exit;