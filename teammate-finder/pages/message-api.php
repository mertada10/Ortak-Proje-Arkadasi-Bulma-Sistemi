<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    exit;
}

require_once "../config/database.php";

$mode = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["type"] ?? "private")
    : ($_GET["type"] ?? "private");

if ($mode === "team" && $_SERVER["REQUEST_METHOD"] === "POST") {

    $team_id = isset($_POST["team_id"]) ? (int)$_POST["team_id"] : 0;
    $message = trim($_POST["message"] ?? "");
    $user_id = (int)$_SESSION["user_id"];

    if ($team_id <= 0 || $message === "") {
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$team_id, $user_id]);

    if ((int)$stmt->fetchColumn() === 0) {
        exit;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS team_messages (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        team_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        INDEX(team_id),
        INDEX(sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $pdo->prepare("INSERT INTO team_messages (team_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$team_id, $user_id, $message]);

    echo "success";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $receiver_id = isset($_POST["receiver_id"]) ? (int)$_POST["receiver_id"] : 0;
    $message = trim($_POST["message"] ?? "");

    if ($receiver_id <= 0 || $message === "") {
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO messages(sender_id, receiver_id, message) VALUES(?, ?, ?)");
    $stmt->execute([
        (int)$_SESSION["user_id"],
        $receiver_id,
        $message
    ]);

    echo "success";
    exit;
}

if ($mode === "team") {
    $team_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
    $user_id = (int)$_SESSION["user_id"];

    if ($team_id <= 0) {
        exit;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS team_messages (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        team_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        INDEX(team_id),
        INDEX(sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$team_id, $user_id]);

    if ((int)$stmt->fetchColumn() === 0) {
        exit;
    }

    $stmt = $pdo->prepare("SELECT tm.*, u.name, u.surname, CASE WHEN tm.sender_id = t.owner_id THEN 'owner' WHEN m.role = 'co_leader' THEN 'co_leader' ELSE 'member' END AS role FROM team_messages tm JOIN users u ON u.id = tm.sender_id JOIN teams t ON t.id = tm.team_id LEFT JOIN team_members m ON m.team_id = tm.team_id AND m.user_id = tm.sender_id WHERE tm.team_id = ? ORDER BY tm.created_at");
    $stmt->execute([$team_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) {
        echo '<div class="message-placeholder">Takım sohbeti henüz boş. İlk mesajı sen gönder!</div>';
        exit;
    }

    foreach ($messages as $msg) {
        $class = $msg["sender_id"] == $user_id ? "my-message" : "their-message";
        $senderName = $msg["sender_id"] == $user_id ? 'Sen' : htmlspecialchars($msg["name"] . " " . $msg["surname"]);
        $roleClass = $msg["role"] === 'co_leader' ? 'co-leader' : ($msg["role"] === 'owner' ? 'leader' : 'member');

        echo '<div class="' . $class . '">';
        echo '<span class="message-sender ' . $roleClass . '">' . $senderName . '</span>';
        echo '<span class="message-content">' . nl2br(htmlspecialchars($msg["message"])) . '</span>';
        echo '<span class="message-time">' . date("H:i", strtotime($msg["created_at"])) . '</span>';
        echo '</div>';
    }

    exit;
}

$receiver_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($receiver_id <= 0) {
    exit;
}

$stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
$stmt->execute([(int)$_SESSION["user_id"], $receiver_id]);

$stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at");
$stmt->execute([(int)$_SESSION["user_id"], $receiver_id, $receiver_id, (int)$_SESSION["user_id"]]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $msg) {
    $class = $msg["sender_id"] == $_SESSION["user_id"] ? "my-message" : "their-message";

    echo '<div class="' . $class . '">';
    echo '<span class="message-content">';
    echo nl2br(htmlspecialchars($msg["message"]));
    echo '</span>';
    echo '<span class="message-time">';
    echo date("H:i", strtotime($msg["created_at"]));
    echo '</span>';
    echo '</div>';
}
