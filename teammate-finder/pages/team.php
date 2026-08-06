<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

// Kullanıcının takımı
$stmt = $pdo->prepare("
SELECT t.*
FROM teams t
JOIN team_members tm ON tm.team_id = t.id
WHERE tm.user_id = ?
LIMIT 1
");

$stmt->execute([$user_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

// Üye çıkarma işlemi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_member"])) {

    $member_id = (int)$_POST["member_id"];

    if ($team) {
        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([
            $team["id"],
            $member_id
        ]);
        $target_role = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([
            $team["id"],
            $user_id
        ]);
        $current_role = $stmt->fetchColumn() ?: 'member';

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
            $stmt->execute([
                $team["id"],
                $member_id
            ]);
        }
    }

    header("Location: team.php");
    exit;
}

// Üye rol değişikliği
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_role"])) {

    $member_id = (int)$_POST["member_id"];
    $new_role = $_POST["new_role"] === 'co_leader' ? 'co_leader' : 'member';

    if ($team && $member_id !== $team["owner_id"] && $member_id !== $user_id) {
        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([
            $team["id"],
            $member_id
        ]);
        $target_role = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([
            $team["id"],
            $user_id
        ]);
        $current_role = $stmt->fetchColumn() ?: 'member';

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
            $stmt->execute([
                $new_role,
                $team["id"],
                $member_id
            ]);
        }
    }

    header("Location: team.php");
    exit;
}

// Liderliği devretme
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["transfer_leadership"])) {

    $member_id = (int)$_POST["member_id"];

    if ($team && $team["owner_id"] == $user_id && $member_id !== $user_id) {
        $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
        $stmt->execute([
            $team["id"],
            $member_id
        ]);
        $target_role = $stmt->fetchColumn();

        if ($target_role !== false) {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE teams SET owner_id=? WHERE id=?");
            $stmt->execute([
                $member_id,
                $team["id"]
            ]);

            $stmt = $pdo->prepare("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?");
            $stmt->execute([
                $team["id"],
                $user_id
            ]);

            $stmt = $pdo->prepare("UPDATE team_members SET role='member' WHERE team_id=? AND user_id=?");
            $stmt->execute([
                $team["id"],
                $member_id
            ]);

            $pdo->commit();
        }
    }

    header("Location: team.php");
    exit;
}

// Takımı bozma
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dissolve_team"])) {

    if ($team && $team["owner_id"] == $user_id) {

        $stmt = $pdo->prepare("
        DELETE FROM team_members
        WHERE team_id=?
        ");

        $stmt->execute([$team["id"]]);

        $stmt = $pdo->prepare("
        DELETE FROM teams
        WHERE id=?
        ");

        $stmt->execute([$team["id"]]);
    }

    header("Location: team.php");
    exit;
}

// Takımdan ayrılma
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["leave_team"])) {

    if ($team) {

        // Üyelikten çıkar
        $stmt = $pdo->prepare("
        DELETE FROM team_members
        WHERE team_id=?
        AND user_id=?
        ");

        $stmt->execute([
            $team["id"],
            $user_id
        ]);

        // Takım boş kaldıysa sil
        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM team_members
        WHERE team_id=?
        ");

        $stmt->execute([$team["id"]]);

        if ($stmt->fetchColumn() == 0) {

            $stmt = $pdo->prepare("
            DELETE FROM teams
            WHERE id=?
            ");

            $stmt->execute([$team["id"]]);
        }
    }

    header("Location: team.php");
    exit;
}

$members = [];

if ($team) {

    $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id=? AND user_id=? LIMIT 1");
    $stmt->execute([
        $team["id"],
        $user_id
    ]);
    $current_member_role = $stmt->fetchColumn() ?: 'member';
    $is_co_leader = $current_member_role === 'co_leader';

    $stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.surname,
        u.department,
        u.profile_image,
        tm.role
    FROM team_members tm
    JOIN users u ON u.id = tm.user_id
    WHERE tm.team_id = ?
    ORDER BY
        CASE
            WHEN u.id = ? THEN 0
            WHEN tm.role = 'co_leader' THEN 1
            ELSE 2
        END,
        u.name,
        u.surname
    ");

    $stmt->execute([$team["id"], $team["owner_id"]]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

<?php if (!$team): ?>

    <div class="team-empty-state">
        <div class="team-empty-card">
            <div class="team-empty-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <h2>Henüz bir takımınız yok.</h2>
            <p>Takım oluşturarak ekip arkadaşlarınızı düzenleyebilir ve iletişim kurabilirsiniz.</p>
            <a href="create-team.php" class="team-primary-btn">
                <i class="fa-solid fa-plus"></i>
                Takım Oluştur
            </a>
        </div>
    </div>

<?php else: ?>

    <div class="team-shell">

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
                        <i class="fa-solid fa-crown"></i>
                        Lidersiniz
                    <?php elseif (!empty($is_co_leader) && $is_co_leader): ?>
                        <i class="fa-solid fa-star"></i>
                        Yardımcı Lider
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                        Üyesiniz
                    <?php endif; ?>
                </div>

                <?php if ($team["owner_id"] == $_SESSION["user_id"]): ?>
                    <form method="POST" class="team-action-form">
                        <button
                            type="submit"
                            name="dissolve_team"
                            class="remove-member-btn"
                            onclick="return confirm('Takımı tamamen silmek istediğinize emin misiniz? Bu işlem geri alınamaz.')">
                            <i class="fa-solid fa-trash"></i>
                            Takımı Boz
                        </button>
                    </form>

                    <a href="manage-team.php" class="manage-team-hero-btn">
                        <i class="fa-solid fa-sliders"></i>
                        Takımı Yönet
                    </a>
                <?php else: ?>
                    <form method="POST" class="team-action-form">
                        <button
                            type="submit"
                            name="leave_team"
                            class="leave-team-btn"
                            onclick="return confirm('Takımdan ayrılmak istediğinize emin misiniz?')">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Takımdan Ayrıl
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($team["description"])): ?>
            <section class="team-info-card">
                <div class="section-heading">
                    <p class="section-eyebrow">Hakkında</p>
                    <h2>Takım açıklaması</h2>
                </div>
                <p><?= nl2br(htmlspecialchars($team["description"])) ?></p>
            </section>
        <?php endif; ?>

        <section class="team-section">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Kadro</p>
                    <h2>Takım üyeleri</h2>
                </div>
            </div>

            <div class="team-members-grid">
                <?php foreach ($members as $member): ?>
                    <article class="team-member-card">
                        <div class="team-member-top">
                            <?php if (!empty($member["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($member["profile_image"]) ?>"
                                     class="team-member-avatar">
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
                                $isCoLeader = !$isLeader && $member["role"] === 'co_leader';
                            ?>
                            <span class="team-role-badge<?= $isLeader ? ' leader-badge' : ($isCoLeader ? ' co-leader-badge' : '') ?>">
                                <?php if ($isLeader): ?>
                                    <i class="fa-solid fa-crown"></i>
                                <?php elseif ($isCoLeader): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php endif; ?>
                                <?= $isLeader ? "Lider" : ($isCoLeader ? "Yardımcı Lider" : "Üye") ?>
                            </span>

                            <div class="team-member-actions">
                                <a href="user-profile.php?id=<?= $member["id"] ?>" class="profile-btn full-width-btn">
                                    <i class="fa-solid fa-user"></i>
                                    Profil
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

                <form id="teamChatForm" class="team-chat-form"method="POST"action="../api/messages-api.php">
                    <textarea
                        id="teamMessageInput"
                        name="message"
                        placeholder="Mesaj yaz..."
                        required></textarea>

                    <input type="hidden" name="type" value="team">

                    <input type="hidden" id="teamId" name="team_id" value="<?= $team["id"] ?>">

                    <button type="submit">Gönder</button>
                </form>
            </div>
        </div>

    </div>

<?php endif; ?>

</div>

<?php require_once "../includes/footer.php"; ?>