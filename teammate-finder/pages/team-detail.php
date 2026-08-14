<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$team_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$user_id = $_SESSION["user_id"];

if ($team_id <= 0) {
    header("Location: team.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, tm.role as user_role
    FROM teams t
    JOIN team_members tm ON tm.team_id = t.id
    WHERE t.id = ? AND tm.user_id = ?
    LIMIT 1
");
$stmt->execute([$team_id, $user_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(403);
    die("Hata: Bu takımı görüntüleme yetkiniz yok.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_member"])) {
    $member_id = (int)$_POST["member_id"];
    $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$team["id"], $member_id]);
    $target_role = $stmt->fetchColumn();

    $current_role = $team["user_role"] ?: 'member';
    $canRemove = false;

    if ($member_id !== $user_id && $member_id !== $team["owner_id"] && $target_role !== false) {
        if ($team["owner_id"] == $user_id) {
            $canRemove = true;
        } elseif ($current_role === 'co_leader' && $target_role === 'member') {
            $canRemove = true;
        }
    }

    if ($canRemove) {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id=? AND user_id=?");
        $stmt->execute([$team["id"], $member_id]);
    }

    header("Location: team-detail.php?id=" . $team["id"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_role"])) {
    $member_id = (int)$_POST["member_id"];
    $new_role = $_POST["new_role"] === 'co_leader' ? 'co_leader' : 'member';

    if ($member_id !== $team["owner_id"] && $member_id !== $user_id) {
        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([$team["id"], $member_id]);
        $target_role = $stmt->fetchColumn();

        $current_role = $team["user_role"] ?: 'member';
        $canChangeRole = false;

        if ($target_role !== false) {
            if ($team["owner_id"] == $user_id) {
                $canChangeRole = true;
            } elseif ($current_role === 'co_leader' && $target_role === 'member' && $new_role === 'co_leader') {
                $canChangeRole = true;
            }
        }

        if ($canChangeRole) {
            $stmt = $pdo->prepare("UPDATE team_members SET role=? WHERE team_id=? AND user_id=?");
            $stmt->execute([$new_role, $team["id"], $member_id]);
        }
    }

    header("Location: team-detail.php?id=" . $team["id"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["transfer_leadership"])) {
    $member_id = (int)$_POST["member_id"];

    if ($team["owner_id"] == $user_id && $member_id !== $user_id) {
        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([$team["id"], $member_id]);
        $target_role = $stmt->fetchColumn();

        if ($target_role !== false) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE teams SET owner_id=? WHERE id=?");
            $stmt->execute([$member_id, $team["id"]]);

            $stmt = $pdo->prepare("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?");
            $stmt->execute([$team["id"], $user_id]);

            $stmt = $pdo->prepare("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?");
            $stmt->execute([$team["id"], $member_id]);
            $pdo->commit();
        }
    }

    header("Location: team-detail.php?id=" . $team["id"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dissolve_team"])) {
    if ($team["owner_id"] == $user_id) {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id=?");
        $stmt->execute([$team["id"]]);

        $stmt = $pdo->prepare("DELETE FROM teams WHERE id=?");
        $stmt->execute([$team["id"]]);
    }

    header("Location: team.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["leave_team"])) {
    $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id=? AND user_id=?");
    $stmt->execute([$team["id"], $user_id]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id=?");
    $stmt->execute([$team["id"]]);

    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM teams WHERE id=?");
        $stmt->execute([$team["id"]]);
    }

    header("Location: team.php");
    exit;
}

$is_co_leader = isset($team["user_role"]) && $team["user_role"] === 'co_leader';
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.surname, u.department, u.profile_image, tm.role
    FROM team_members tm
    JOIN users u ON u.id = tm.user_id
    WHERE tm.team_id = ?
    ORDER BY
        CASE
            WHEN u.id = ? THEN 0
            WHEN tm.role = 'co_leader' THEN 1
            ELSE 2
        END,
        u.name, u.surname
");
$stmt->execute([$team["id"], $team["owner_id"]]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">
    <div class="team-shell">
        <a href="team.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Takımlarıma Dön</a>

        <section class="team-hero">
            <div class="team-hero-main">
                <div class="team-badge">
                    <i class="fa-solid fa-users"></i>
                    <span>Takım profili</span>
                </div>

                <h1><?= htmlspecialchars($team["team_name"]) ?></h1>
                <p class="team-hero-subtitle">
                    <?= count($members) ?> üye ile birlikte projelerinizi daha düzenli yürütebilirsiniz.
                </p>

                <div class="team-stats">
                    <div class="team-stat">
                        <span class="team-stat-value"><?= count($members) ?></span>
                        <span class="team-stat-label">Üye</span>
                    </div>
                </div>
            </div>

            <div class="team-hero-actions">
                <div class="team-role-pill">
                    <?php if ($team["owner_id"] == $_SESSION["user_id"]): ?>
                        <i class="fa-solid fa-crown"></i> Lidersiniz
                    <?php elseif ($is_co_leader): ?>
                        <i class="fa-solid fa-star"></i> Yardımcı Lider
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i> Üyesiniz
                    <?php endif; ?>
                </div>

                <?php if ($team["owner_id"] == $_SESSION["user_id"]): ?>
                    <form method="POST" class="team-action-form">
                        <button
                            type="submit"
                            name="dissolve_team"
                            class="remove-member-btn"
                            onclick="return confirm('Takımı tamamen silmek istediğinize emin misiniz? Bu işlem geri alınamaz.')">
                            <i class="fa-solid fa-trash"></i> Takımı Boz
                        </button>
                    </form>
                    <a href="manage-team.php?id=<?= $team["id"] ?>" class="manage-team-hero-btn">
                        <i class="fa-solid fa-sliders"></i> Takımı Yönet
                    </a>
                <?php else: ?>
                    <form method="POST" class="team-action-form">
                        <button
                            type="submit"
                            name="leave_team"
                            class="leave-team-btn"
                            onclick="return confirm('Takımdan ayrılmak istediğinize emin misiniz?')">
                            <i class="fa-solid fa-right-from-bracket"></i> Takımdan Ayrıl
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($team["description"])): ?>
            <section class="team-info-card">
                <div class="section-heading">
                    <p class="section-eyebrow">Hakkında</p>
                </div>
                <p><?= nl2br(htmlspecialchars($team["description"])) ?></p>
            </section>
        <?php endif; ?>

        <section class="team-section">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Kadro</p>
                </div>
            </div>

            <div class="team-members-grid">
                <?php foreach ($members as $member): ?>
                    <article class="team-member-card">
                        <div class="team-member-top">
                            <?php if (!empty($member["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($member["profile_image"]) ?>" class="team-member-avatar">
                            <?php else: ?>
                                <div class="team-member-avatar team-member-avatar-default">
                                    <?= strtoupper(mb_substr($member["name"], 0, 1) . mb_substr($member["surname"], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="team-member-info">
                                <h3><?= htmlspecialchars($member["name"] . " " . $member["surname"]) ?></h3>
                                <p><?= htmlspecialchars($member["department"] ?? "Bölüm Yok") ?></p>
                            </div>
                        </div>

                        <div class="team-member-footer">
                            <?php
                                $isLeader = $member["id"] == $team["owner_id"];
                                $isCoLeaderRow = !$isLeader && $member["role"] === 'co_leader';
                            ?>
                            <span class="team-role-badge<?= $isLeader ? ' leader-badge' : ($isCoLeaderRow ? ' co-leader-badge' : '') ?>">
                                <?php if ($isLeader): ?>
                                    <i class="fa-solid fa-crown"></i>
                                <?php elseif ($isCoLeaderRow): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php endif; ?>
                                <?= $isLeader ? "Lider" : ($isCoLeaderRow ? "Yardımcı Lider" : "Üye") ?>
                            </span>

                            <div class="team-member-actions">
                                <a href="user-profile.php?id=<?= $member["id"] ?>" class="profile-btn full-width-btn">
                                    <i class="fa-solid fa-user"></i> Profil
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="team-chat-widget" id="teamChatWidget">
            <button type="button" class="team-chat-widget-toggle" id="teamChatToggle">
                <span>Takım Sohbeti</span>
                <i class="fa-solid fa-comments"></i>
            </button>

            <div class="team-chat-widget-panel">
                <div class="team-chat-widget-header">
                    <div>
                        <h3>Takım Sohbeti</h3>
                        <p>Üyelerle hızlı iletişim</p>
                    </div>
                    <button type="button" class="team-chat-close-btn" id="teamChatClose">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="team-chat-messages" id="teamChatMessages">
                    <div class="message-placeholder">Sohbet yükleniyor...</div>
                </div>

                <form id="teamChatForm" class="team-chat-form" method="POST" action="/teammate-finder/api/message-api.php" onsubmit="return false;">
                    <textarea id="teamMessageInput" name="message" placeholder="Mesaj yaz..." required></textarea>
                    <input type="hidden" name="type" value="team">
                    <input type="hidden" id="teamId" name="team_id" value="<?= $team["id"] ?>">
                    <button type="submit">Gönder</button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>