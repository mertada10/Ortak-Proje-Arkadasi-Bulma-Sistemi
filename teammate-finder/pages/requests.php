<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)$_SESSION["user_id"];
$teamRequestError = "";

$stmt = $pdo->prepare("
    UPDATE requests
    SET sender_seen = 1
    WHERE sender_id = ?
      AND status IN ('accepted', 'rejected')
      AND sender_seen = 0
");
$stmt->execute([$user_id]);

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
                        $stmt = $pdo->prepare("SELECT COALESCE(members_needed, 1) FROM projects WHERE id = ? LIMIT 1");
                        $stmt->execute([$projectId]);
                        $needed = (int)$stmt->fetchColumn();

                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE project_id = ? AND status = 'accepted'");
                        $stmt->execute([$projectId]);
                        $acceptedCount = (int)$stmt->fetchColumn();

                        if ($acceptedCount >= $needed) {
                            $stmt = $pdo->prepare("UPDATE projects SET expires_at = NULL WHERE id = ?");
                            $stmt->execute([$projectId]);
                        }
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
                    error_log("[İstek işleme] SQL hatası: " . $e->getMessage() . " (Satır: " . $e->getLine() . ")");
                    $teamRequestError = "İstek işlenirken bir hata oluştu. Lütfen tekrar deneyin.";
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

$outPerPage = 5;

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    WHERE sender_id = ?
      AND status IN ('pending', 'accepted', 'rejected')
");
$stmt->execute([$user_id]);
$outgoingTotal = (int)$stmt->fetchColumn();

$outTotalPages = max(1, (int)ceil($outgoingTotal / $outPerPage));

$outPage = isset($_GET['out_page']) && is_numeric($_GET['out_page']) ? (int)$_GET['out_page'] : 1;
if ($outPage < 1) $outPage = 1;
if ($outPage > $outTotalPages) $outPage = $outTotalPages;
$outOffset = ($outPage - 1) * $outPerPage;

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
    WHERE sender_id = :uid
      AND status IN ('pending', 'accepted', 'rejected')
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $outPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $outOffset, PDO::PARAM_INT);
$stmt->execute();
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

                        <?php if ($request["status"] === "accepted"): ?>
                            <span class="request-status accepted">
                                <i class="fa-solid fa-check"></i> Kabul Edildi
                            </span>
                        <?php elseif ($request["status"] === "rejected"): ?>
                            <span class="request-status rejected">
                                <i class="fa-solid fa-xmark"></i> Reddedildi
                            </span>
                        <?php else: ?>
                            <span class="request-status pending">
                                <i class="fa-solid fa-hourglass-half"></i> Bekliyor
                            </span>
                        <?php endif; ?>
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

        <?php if ($outTotalPages > 1): ?>
            <div class="pagination">
                <?php if ($outPage > 1): ?>
                    <a href="?out_page=<?= $outPage - 1 ?>" class="page-link">&laquo; Önceki</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $outTotalPages; $i++): ?>
                    <a href="?out_page=<?= $i ?>" class="page-link <?= $i === $outPage ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($outPage < $outTotalPages): ?>
                    <a href="?out_page=<?= $outPage + 1 ?>" class="page-link">Sonraki &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>