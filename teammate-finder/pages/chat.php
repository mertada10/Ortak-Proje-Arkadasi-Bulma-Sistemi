<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$receiver_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($receiver_id <= 0 || $receiver_id == $_SESSION["user_id"]) {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id,name,surname,profile_image,last_seen FROM users WHERE id=?");
$stmt->execute([$receiver_id]);
$receiver = $stmt->fetch();

if (!$receiver) {
    header("Location: ../index.php");
    exit;
}

$receiver_last_seen = strtotime($receiver["last_seen"] ?? null);
$receiver_online = $receiver_last_seen !== false && $receiver_last_seen > time() - 120;
$statusText = $receiver_online ? 'Çevrimiçi' : 'Çevrimdışı';

$stmt = $pdo->prepare("
UPDATE messages
SET is_read = 1
WHERE receiver_id = ?
AND sender_id = ?
AND is_read = 0
");

$stmt->execute([
    $_SESSION["user_id"],
    $receiver_id
]);

$stmt = $pdo->prepare("
SELECT *
FROM messages
WHERE
(sender_id=? AND receiver_id=?)
OR
(sender_id=? AND receiver_id=?)
ORDER BY created_at
");

$stmt->execute([
    $_SESSION["user_id"],
    $receiver_id,
    $receiver_id,
    $_SESSION["user_id"]
]);

$messages = $stmt->fetchAll();

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

    <div class="chat-container">

        <div class="chat-header">
            <div class="chat-recipient">
                <?php if (!empty($receiver["profile_image"])): ?>
                    <img src="../assets/uploads/<?= htmlspecialchars($receiver["profile_image"]) ?>" alt="<?= htmlspecialchars($receiver["name"]." ".$receiver["surname"]) ?>" class="chat-recipient-avatar">
                <?php else: ?>
                    <div class="chat-recipient-default-avatar">
                        <?= strtoupper(mb_substr($receiver["name"], 0, 1) . mb_substr($receiver["surname"], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div class="chat-recipient-info">
                    <h1><?= htmlspecialchars($receiver["name"]." ".$receiver["surname"]) ?></h1>
                    <p>Özel sohbet</p>
                </div>
            </div>

            <div class="chat-header-actions">
                <span class="chat-status-badge <?= $receiver_online ? 'online' : 'offline' ?>" id="chatStatusBadge" data-receiver-id="<?= $receiver_id ?>"><?= htmlspecialchars($statusText) ?></span>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">

            <?php if (empty($messages)): ?>
                <div class="chat-empty-state">Henüz mesaj yok. İlk mesajı gönder!</div>
            <?php endif; ?>

            <?php foreach($messages as $msg): ?>

                <div class="<?= $msg["sender_id"] == $_SESSION["user_id"] ? "my-message" : "their-message"; ?>">
                    <span class="message-content">
                        <?= nl2br(htmlspecialchars($msg["message"])) ?>
                    </span>

                    <span class="message-time">
                        <?= date("H:i", strtotime($msg["created_at"])) ?>
                    </span>
                </div>

            <?php endforeach; ?>

        </div>

        <form id="chatForm" class="chat-form">

            <textarea
                id="messageInput"
                name="message"
                placeholder="Mesaj yaz..."
                required></textarea>

            <input
                type="hidden"
                id="receiverId"
                value="<?= $receiver_id ?>">

            <button type="submit">
                Gönder
            </button>

        </form>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>