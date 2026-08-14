<?php

session_start();
require_once "includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit;
}

require_once "config/database.php";

$stmt = $pdo->prepare("
    UPDATE users
    SET last_seen = CURRENT_TIMESTAMP
    WHERE id = ?
");

$stmt->execute([
    $_SESSION["user_id"]
]);

http_response_code(204);