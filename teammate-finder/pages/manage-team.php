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
$team_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($team_id <= 0) {
    header("Location: team.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, tm.role 
    FROM teams t
    JOIN team_members tm ON tm.team_id = t.id
    WHERE t.id = ? AND tm.user_id = ?
    LIMIT 1
");
$stmt->execute([$team_id, $user_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team || $team["owner_id"] != $user_id) {
    header("Location: team.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_team_name"])) {
    $new_team_name = trim($_POST["team_name"]);
    
    if (!empty($new_team_name)) {
        $stmt = $pdo->prepare("UPDATE teams SET team_name = ? WHERE id = ?");
        $stmt->execute([$new_team_name, $team["id"]]);
        
        $team["team_name"] = $new_team_name;
        $message = "Takım adı başarıyla güncellendi.";
    } else {
        $error = "Takım adı boş olamaz.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_role"])) {
    $member_id = (int)$_POST["member_id"];
    $new_role = $_POST["new_role"] === 'co_leader' ? 'co_leader' : 'member';

    if ($member_id !== (int)$team["owner_id"] && $member_id !== (int)$user_id) {
        $stmt = $pdo->prepare("UPDATE team_members SET role=? WHERE team_id=? AND user_id=?");
        $stmt->execute([
            $new_role,
            $team["id"],
            $member_id
        ]);
        $message = "Üyenin rolü başarıyla güncellendi.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["transfer_leadership"])) {
    $member_id = (int)$_POST["member_id"];

    if ($member_id !== (int)$user_id) {
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

            header("Location: team.php");
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_member"])) {
    $member_id = (int)$_POST["member_id"];

    if ($member_id !== (int)$user_id && $member_id !== (int)$team["owner_id"]) {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id=? AND user_id=?");
        $stmt->execute([$team["id"], $member_id]);
        $message = "Üye takımdan çıkarıldı.";
    }
}

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
WHERE tm.team_id = ? AND u.id != ?
ORDER BY tm.role DESC, u.name ASC
");
$stmt->execute([$team["id"], $user_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">
    <div class="manage-team-shell">

        <div class="manage-header">
            <div>
                <h1>Kullanıcıları Yönet</h1>
                <p><strong><?= htmlspecialchars($team["team_name"]) ?></strong> takımı üyelerinin rollerini düzenleyebilir, takım adını güncelleyebilir veya üyeleri yönetebilirsiniz.</p>
            </div>
            <a href="team-detail.php?id=<?= $team["id"] ?>" class="back-team-btn">
                <i class="fa-solid fa-arrow-left"></i> Takım Sayfasına Dön
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="team-name-editor">
            <div class="editor-header">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Takım Adını Güncelle</span>
            </div>
            <form method="POST" class="editor-form">
                <input type="text" name="team_name" value="<?= htmlspecialchars($team["team_name"]) ?>" required>
                <button type="submit" name="update_team_name" class="btn-red">
                    <i class="fa-solid fa-floppy-disk"></i> Kaydet
                </button>
            </form>
        </div>

        <?php if (count($members) === 0): ?>
            <div class="team-empty-card">
                <p>Takımınızda henüz yönetebileceğiniz başka bir üye bulunmuyor.</p>
            </div>
        <?php else: ?>
            <div class="manage-members-list">
                <?php foreach ($members as $member): ?>
                    <div class="manage-member-card">
                        <div class="member-info-group">
                            <?php if (!empty($member["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($member["profile_image"]) ?>" class="manage-avatar">
                            <?php else: ?>
                                <div class="manage-avatar manage-avatar-default">
                                    <?= strtoupper(mb_substr($member["name"], 0, 1) . mb_substr($member["surname"], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="member-details">
                                <h3><?= htmlspecialchars($member["name"] . " " . $member["surname"]) ?></h3>
                                <p><?= htmlspecialchars($member["department"] ?? "Bölüm Yok") ?></p>
                                <span class="role-badge <?= $member["role"] === 'co_leader' ? 'co-leader' : 'member' ?>">
                                    <?= $member["role"] === 'co_leader' ? '<i class="fa-solid fa-star"></i> Yardımcı Lider' : '<i class="fa-solid fa-user"></i> Üye' ?>
                                </span>
                            </div>
                        </div>

                        <div class="manage-actions-group">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="member_id" value="<?= $member["id"] ?>">
                                <?php if ($member["role"] === 'co_leader'): ?>
                                    <input type="hidden" name="new_role" value="member">
                                    <button type="submit" name="change_role" class="btn-action btn-demote">
                                        <i class="fa-solid fa-user-minus"></i> Üye Yap
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="new_role" value="co_leader">
                                    <button type="submit" name="change_role" class="btn-action btn-promote">
                                        <i class="fa-solid fa-star"></i> Yardımcı Lider Yap
                                    </button>
                                <?php endif; ?>
                            </form>

                            <form method="POST" class="inline-form">
                                <input type="hidden" name="member_id" value="<?= $member["id"] ?>">
                                <button type="submit" name="transfer_leadership" class="btn-action btn-transfer" 
                                        onclick="return confirm('Liderliği bu kullanıcıya devretmek istediğinizden emin misiniz? Liderlik yetkilerinizi kaybedeceksiniz.')">
                                    <i class="fa-solid fa-crown"></i> Liderliği Devret
                                </button>
                            </form>

                            <form method="POST" class="inline-form">
                                <input type="hidden" name="member_id" value="<?= $member["id"] ?>">
                                <button type="submit" name="remove_member" class="btn-action btn-remove" 
                                        onclick="return confirm('Bu kullanıcıyı takımdan çıkarmak istediğinize emin misiniz?')">
                                    <i class="fa-solid fa-trash"></i> Çıkar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>