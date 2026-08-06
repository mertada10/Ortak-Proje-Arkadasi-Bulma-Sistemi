<?php

require_once "config/database.php";

header("Content-Type: application/json");

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {

    echo json_encode([
        "status"=>"offline"
    ]);

    exit;
}

$stmt = $pdo->prepare("
    SELECT last_seen
    FROM users
    WHERE id=?
");

$stmt->execute([$id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

$status = "offline";

if ($user && $user["last_seen"]) {

    $last_seen = strtotime($user["last_seen"]);

    // 2 dakika aktif kabul edilir
    if ($last_seen > time() - 120) {

        $status = "online";

    }
}

echo json_encode([
    "status"=>$status
]);
