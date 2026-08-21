<?php
require_once __DIR__ . "/../includes/cors.php";

require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once __DIR__ . "/../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . "/../config/database.php";

$stmt = $pdo->prepare("
    UPDATE users
    SET last_seen = CURRENT_TIMESTAMP
    WHERE id = ?
");

$stmt->execute([
    $_SESSION["user_id"]
]);

http_response_code(204);