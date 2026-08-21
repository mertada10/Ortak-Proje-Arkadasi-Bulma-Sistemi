<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

$stmt = $pdo->prepare("
SELECT
    u.id,
    u.name,
    u.surname,
    u.profile_image,
    m.message,
    m.created_at,
    (SELECT COUNT(*) FROM messages 
     WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
FROM messages m
JOIN users u
ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
WHERE m.sender_id = ? OR m.receiver_id = ?
ORDER BY m.created_at DESC
");

$stmt->execute([$user_id, $user_id, $user_id, $user_id]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = [];

foreach ($rows as $row) {
    if (!isset($users[$row["id"]])) {
        $users[$row["id"]] = $row;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

    <div class="page-header">
        <h1>Mesajlar</h1>
        <p>Konuşmalarınız</p>
    </div>

    <div class="message-list">

        <?php if(count($users)>0): ?>

            <?php foreach($users as $user): ?>

                <a class="message-user" href="chat.php?id=<?= $user["id"] ?>">

                    <?php if(!empty($user["profile_image"])): ?>

                        <img src="../assets/uploads/<?= htmlspecialchars($user["profile_image"]) ?>" class="author-avatar-img">

                    <?php else: ?>

                        <div class="author-avatar">
                            <?= strtoupper(mb_substr($user["name"],0,1).mb_substr($user["surname"],0,1)) ?>
                        </div>

                    <?php endif; ?>

                    <div class="message-info" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div>
                            <h3><?= htmlspecialchars($user["name"]." ".$user["surname"]) ?></h3>
                            <p><?= htmlspecialchars(mb_strimwidth($user["message"],0,50,"...")) ?></p>
                        </div>

                        <!-- Sağ Taraftaki Okunmamış Mesaj Rozeti -->
                        <?php if(isset($user["unread_count"]) && $user["unread_count"] > 0): ?>
                            <span class="unread-badge" style="background-color: #dc2626; color: white; padding: 4px 10px; border-radius: 50%; font-size: 12px; font-weight: bold;"><?= $user["unread_count"] ?></span>
                        <?php endif; ?>
                    </div>

                </a>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="no-projects">
                Henüz mesajınız bulunmuyor.
            </div>

        <?php endif; ?>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>