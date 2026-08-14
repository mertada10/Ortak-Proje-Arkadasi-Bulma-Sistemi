<?php
session_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)$_SESSION["user_id"];
$teamRequestError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestId = (int)($_POST["request_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    if (in_array($status, ["accepted", "rejected"], true)) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM requests
            WHERE id = ?
              AND receiver_id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$requestId, $user_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            if ($status === "rejected") {
                $stmt = $pdo->prepare("
                    UPDATE requests
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$requestId]);
            } else {
                $sender = (int)$request["sender_id"];
                $receiver = (int)$request["receiver_id"];
                $projectId = !empty($request["project_id"]) ? (int)$request["project_id"] : 0;
                $teamId = !empty($request["team_id"]) ? (int)$request["team_id"] : 0;

                $pdo->beginTransaction();

                try {

                    if ($projectId > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE requests
                            SET status = 'accepted'
                            WHERE id = ?
                        ");
                        $stmt->execute([$requestId]);
                    } 

                    else {

                        if ($teamId > 0) {

                            $check = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?");
                            $check->execute([$teamId, $receiver]);
                            
                            if (!$check->fetch()) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO team_members (team_id, user_id, role)
                                    VALUES (?, ?, 'member')
                                ");
                                $stmt->execute([$teamId, $receiver]);
                            }
                        }

                        $stmt = $pdo->prepare("
                            UPDATE requests
                            SET status = 'accepted'
                            WHERE id = ?
                        ");
                        $stmt->execute([$requestId]);
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $teamRequestError = "SQL Hatası: " . $e->getMessage() . " (Satır: " . $e->getLine() . ")";
                }
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        users.name,
        users.surname,
        users.profile_image,
        projects.title,
        teams.team_name
    FROM requests
    JOIN users ON users.id = requests.sender_id
    LEFT JOIN projects ON projects.id = requests.project_id
    LEFT JOIN teams ON teams.id = requests.team_id
    WHERE receiver_id = ?
      AND status IN ('pending', 'locked')
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        users.name,
        users.surname,
        users.profile_image,
        projects.title,
        teams.team_name
    FROM requests
    JOIN users ON users.id = requests.receiver_id
    LEFT JOIN projects ON projects.id = requests.project_id
    LEFT JOIN teams ON teams.id = requests.team_id
    WHERE sender_id = ?
      AND status = 'pending'
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$outgoing = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">
    <div class="requests-container">

        <?php if (!empty($teamRequestError)): ?>
            <div class="error-message alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($teamRequestError) ?></span>
            </div>
        <?php endif; ?>

        <div class="page-header requests-page-header">
            <h1><i class="fa-solid fa-inbox"></i> Gelen İstekler</h1>
            <p>Takım ve proje katılım isteklerinizi buradan yönetebilirsiniz.</p>
        </div>

        <h2 class="requests-section-title">
            <i class="fa-solid fa-arrow-down"></i> Gelen İstekler
        </h2>

        <?php if (count($incoming)): ?>
            <?php foreach ($incoming as $request): ?>
                <?php $isLocked = $request["status"] === "locked"; ?>

                <div class="request-card">
                    <div class="request-card-header">
                        <div class="request-user">
                            <?php if (!empty($request["profile_image"]) && file_exists("../assets/uploads/" . $request["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($request["profile_image"]) ?>" class="author-avatar-img" alt="Profil">
                            <?php else: ?>
                                <div class="author-avatar">
                                    <?= strtoupper(mb_substr($request["name"], 0, 1) . mb_substr($request["surname"], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="request-user-text">
                                <h3><?= htmlspecialchars($request["name"] . " " . $request["surname"]) ?></h3>

                                <span class="request-type-badge <?= !empty($request["project_id"]) ? "project" : "team" ?>">
                                    <?php if (!empty($request["project_id"])): ?>
                                        <i class="fa-solid fa-folder-open"></i> Projeye Katılma
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-group"></i> Takıma Katılma
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($request["project_id"]) && !empty($request["title"])): ?>
                            <span class="request-project-chip">
                                <i class="fa-solid fa-file-lines"></i> <?= htmlspecialchars($request["title"]) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($request["team_id"]) && !empty($request["team_name"])): ?>
                            <span class="request-project-chip">
                                <i class="fa-solid fa-users"></i> <?= htmlspecialchars($request["team_name"]) ?>
                            </span>
                        <?php endif; ?>

                        <span class="request-status <?= $isLocked ? "locked" : "pending" ?>">
                            <?php if ($isLocked): ?>
                                <i class="fa-solid fa-lock"></i> Kilitli
                            <?php else: ?>
                                <i class="fa-solid fa-hourglass-half"></i> Bekliyor
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (!empty($request["message"])): ?>
                        <div class="request-message-wrapper">
                            <div class="request-message-title">
                                <i class="fa-solid fa-comment-dots"></i> Kullanıcı Mesajı
                            </div>
                            <div class="request-note-block">
                                <i class="fa-solid fa-note-sticky"></i>
                                <span><?= nl2br(htmlspecialchars($request["message"])) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="request-actions">
                        <?php if ($isLocked): ?>
                            <button type="button" class="reject-btn" disabled>
                                <i class="fa-solid fa-lock"></i> Kilitlendi
                            </button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= (int)$request["id"] ?>">
                                <input type="hidden" name="status" value="accepted">
                                <button type="submit" class="accept-btn">
                                    <i class="fa-solid fa-check"></i> Kabul Et
                                </button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= (int)$request["id"] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="reject-btn">
                                    <i class="fa-solid fa-xmark"></i> Reddet
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="requests-empty">
                <i class="fa-solid fa-inbox"></i>
                <p>Henüz gelen isteğiniz yok.</p>
            </div>
        <?php endif; ?>

        <h2 class="requests-section-title">
            <i class="fa-solid fa-arrow-up"></i> Gönderdiğim İstekler
        </h2>

        <?php if (count($outgoing)): ?>
            <?php foreach ($outgoing as $request): ?>
                <div class="request-card">
                    <div class="request-card-header">
                        <div class="request-user">
                            <?php if (!empty($request["profile_image"]) && file_exists("../assets/uploads/" . $request["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($request["profile_image"]) ?>" class="author-avatar-img" alt="Profil">
                            <?php else: ?>
                                <div class="author-avatar">
                                    <?= strtoupper(mb_substr($request["name"], 0, 1) . mb_substr($request["surname"], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="request-user-text">
                                <h3><?= htmlspecialchars($request["name"] . " " . $request["surname"]) ?></h3>

                                <span class="request-type-badge <?= !empty($request["project_id"]) ? "project" : "team" ?>">
                                    <?php if (!empty($request["project_id"])): ?>
                                        <i class="fa-solid fa-folder-open"></i> Projeye Katılma
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-group"></i> Takım Daveti
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($request["project_id"]) && !empty($request["title"])): ?>
                            <span class="request-project-chip">
                                <i class="fa-solid fa-file-lines"></i> <?= htmlspecialchars($request["title"]) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($request["team_id"]) && !empty($request["team_name"])): ?>
                            <span class="request-project-chip">
                                <i class="fa-solid fa-users"></i> <?= htmlspecialchars($request["team_name"]) ?>
                            </span>
                        <?php endif; ?>

                        <span class="request-status pending">
                            <i class="fa-solid fa-hourglass-half"></i> Bekliyor
                        </span>
                    </div>

                    <?php if (!empty($request["message"])): ?>
                        <div class="request-message-wrapper">
                            <div class="request-message-title">
                                <i class="fa-solid fa-comment-dots"></i> Kullanıcı Mesajı
                            </div>
                            <div class="request-note-block">
                                <i class="fa-solid fa-note-sticky"></i>
                                <span><?= nl2br(htmlspecialchars($request["message"])) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="requests-empty">
                <i class="fa-solid fa-paper-plane"></i>
                <p>Henüz gönderdiğiniz istek yok.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>