<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Oturum bulunamadı."], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION["user_id"];

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages
        WHERE receiver_id = ?
          AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $unreadCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM requests
        WHERE receiver_id = ?
          AND status = 'pending'
    ");
    $stmt->execute([$userId]);
    $teamRequestCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM requests
        WHERE sender_id = ?
          AND status IN ('accepted', 'rejected')
          AND sender_seen = 0
    ");
    $stmt->execute([$userId]);
    $requestResponseCount = (int)$stmt->fetchColumn();

    $requestsTotal = $teamRequestCount + $requestResponseCount;
    $total = $unreadCount + $requestsTotal;

    echo json_encode([
        "success" => true,
        "messages" => $unreadCount,
        "team_requests" => $teamRequestCount,
        "request_responses" => $requestResponseCount,
        "requests_total" => $requestsTotal,
        "total" => $total
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Bildirimler alınırken bir hata oluştu."], JSON_UNESCAPED_UNICODE);
}