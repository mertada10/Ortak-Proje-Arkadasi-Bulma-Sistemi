<?php
session_start();
require_once "../includes/auto-login.php";

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$requested_user_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$viewing_own_profile = ($requested_user_id <= 0 || $requested_user_id === (int)$_SESSION["user_id"]);

$user_id = $viewing_own_profile ? (int)$_SESSION["user_id"] : $requested_user_id;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../index.php");
    exit;
}

$userProjects = [];
$stmt = $pdo->prepare("
    SELECT projects.*, users.name, users.surname, users.department, users.profile_image
    FROM projects
    JOIN users ON projects.user_id = users.id
    WHERE projects.user_id = ?
    ORDER BY projects.created_at DESC
");
$stmt->execute([$user_id]);
$userProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userJoinedProjects = [];
$stmt = $pdo->prepare("
    SELECT projects.*, users.name, users.surname, users.department, users.profile_image
    FROM projects
    JOIN users ON projects.user_id = users.id
    JOIN requests ON requests.project_id = projects.id
    WHERE requests.status = 'accepted'
      AND requests.sender_id = ?
      AND projects.user_id != ?
    ORDER BY projects.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$userJoinedProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);


$current_user_team = null;
$target_user_team = null;

if (!$viewing_own_profile) {
    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"]]);
    $current_user_team = $stmt->fetchColumn();

    if ($current_user_team) {
        $stmt = $pdo->prepare("SELECT owner_id FROM teams WHERE id = ? LIMIT 1");
        $stmt->execute([$current_user_team]);
        $current_team_owner = $stmt->fetchColumn();
        $is_team_leader = ($current_team_owner == $_SESSION["user_id"]);
    } else {
        $is_team_leader = false;
    }

    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $target_user_team = $stmt->fetchColumn();

    $stmt = $pdo->prepare("\n    SELECT id\n    FROM requests\n    WHERE status='pending'\n      AND project_id IS NULL\n      AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))\n    LIMIT 1\n    ");
    $stmt->execute([
        (int)$_SESSION["user_id"],
        $user_id,
        $user_id,
        (int)$_SESSION["user_id"]
    ]);
    $has_pending_request = (bool)$stmt->fetchColumn();
} else {
    $has_pending_request = false;
}

$currentUserTeams = [];
$stmt = $pdo->prepare("
    SELECT t.id, t.team_name
    FROM teams t
    JOIN team_members tm ON tm.team_id = t.id
    WHERE tm.user_id = ? AND tm.role IN ('leader', 'co_leader')
    ORDER BY t.id DESC
");
$stmt->execute([$_SESSION["user_id"]]);
$currentUserTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$alreadyMemberMap = [];
if (!$viewing_own_profile && count($currentUserTeams) > 0) {
    $myTeamIds = array_column($currentUserTeams, 'id');
    $teamPlaceholders = implode(",", array_fill(0, count($myTeamIds), "?"));
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM team_members
        WHERE user_id = ?
          AND team_id IN ($teamPlaceholders)
    ");
    $stmt->execute(array_merge([$user_id], $myTeamIds));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $teamId) {
        $alreadyMemberMap[(int)$teamId] = true;
    }
}

$pendingTeamMap = [];
if (!$viewing_own_profile) {
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM requests
        WHERE status = 'pending'
          AND project_id IS NULL
          AND team_id IS NOT NULL
          AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ");
    $stmt->execute([
        (int)$_SESSION["user_id"],
        $user_id,
        $user_id,
        (int)$_SESSION["user_id"]
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $teamId) {
        $pendingTeamMap[(int)$teamId] = true;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<?php

$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

?>

<?php if($success): ?>

<div class="success-message alert">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($success) ?></span>
</div>

<?php endif; ?>

<?php
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);
?>

<div class="profile-container">

    <div class="profile-card">

        <div class="profile-sidebar">

            <div class="avatar-wrapper">

                <?php if (!empty($user["profile_image"])): ?>

                    <img src="../assets/uploads/<?= htmlspecialchars($user["profile_image"]) ?>" class="profile-avatar-lg">

                <?php else: ?>

                    <div class="profile-avatar-placeholder">
                        <?= strtoupper(mb_substr($user["name"],0,1).mb_substr($user["surname"],0,1)) ?>
                    </div>

                <?php endif; ?>

            </div>

            <h2 style="margin-top:15px;text-align:center;">
                <?= htmlspecialchars($user["name"]." ".$user["surname"]) ?>
            </h2>

            <p style="color:#b3b9c4;text-align:center;">
                <?= htmlspecialchars($user["department"]) ?>
            </p>

            <?php if ($viewing_own_profile): ?>
                <form
                    id="avatar-form"
                    action="/teammate-finder/api/upload-avatar.php"
                    method="POST"
                    enctype="multipart/form-data">

                    <input
                        type="file"
                        name="avatar"
                        id="avatar-input"
                        accept="image/*"
                        style="display:none;"
                        onchange="this.form.submit();">

                </form>

                <div class="profile-action-buttons">

                    <button type="button" class="change-photo-btn" onclick="document.getElementById('avatar-input').click();">
                        <i class="fa-solid fa-camera"></i>
                        Fotoğrafı Değiştir
                    </button>

                    <a href="edit-profile.php" class="edit-btn">
                        <i class="fa-solid fa-pen"></i>
                        Profili Düzenle
                    </a>

                    <a href="change-password.php" class="edit-btn">
                        <i class="fa-solid fa-key"></i>
                        Şifre Değiştir
                    </a>

                    <a href="delete-account.php" class="delete-btn">
                        <i class="fa-solid fa-trash"></i>
                        Hesabı Sil
                    </a>

                </div>
            <?php endif; ?>

        </div>

        <div class="profile-details">

            <?php if ($viewing_own_profile): ?>

                <?php if ($success): ?>
                    <div class="success-message alert">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
                
                <h2>Profilim</h2>
                
            <?php else: ?>
                <?php if (isset($_GET["success"])): ?>
                    <div class="success-message alert">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>
                            <?php if(isset($_GET["sent"]) && $_GET["sent"] === "invite"): ?>
                                Takıma davet isteğiniz gönderildi.
                            <?php elseif(isset($_GET["sent"]) && $_GET["sent"] === "join"): ?>
                                Takıma katılma isteğiniz gönderildi.
                            <?php else: ?>
                                Ekip kurma isteğiniz gönderildi.
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["already"])): ?>
                    <div class="error-message">
                        Bu kullanıcıya zaten bir davet gönderdiniz.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["alreadyteam"])): ?>
                    <div class="error-message">
                        Bu kullanıcıyla zaten takım ilişkisi bulunduğu için yeni istek gönderilemez.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["alreadypending"])): ?>
                    <div class="error-message">
                        Bu kullanıcıya bu takım için zaten bekleyen bir davet var.
                    </div>
                <?php endif; ?>

                <h2>Kullanıcı Profili</h2>
            <?php endif; ?>

            <div class="profile-row">
                <span>Bildiği Teknolojiler</span>
                <strong><?= htmlspecialchars($user["skills"]) ?></strong>
            </div>

            <div class="profile-row">
                <span>İlgi Alanları</span>
                <strong><?= htmlspecialchars($user["interests"]) ?></strong>
            </div>

            <div class="profile-row profile-about">
                <span>Hakkımda</span>
                <p><?= nl2br(htmlspecialchars($user["about"])) ?></p>
            </div>

            <div class="profile-row profile-projects">
                <span>Projeler</span>
                <div class="profile-projects-content">
                    <?php if (count($userProjects) > 0): ?>
                        <div class="profile-projects-sub">
                            <strong class="profile-projects-sub-title">Oluşturduğu Projeler</strong>
                            <div class="profile-projects-scroll">
                                <?php foreach ($userProjects as $project): ?>
                                    <div class="profile-project-item">
                                        <div class="profile-project-info">
                                            <a href="project-detail.php?id=<?= (int)$project["id"] ?>" class="profile-project-title">
                                                <?= htmlspecialchars($project["title"]) ?>
                                            </a>
                                            <?php $desc = $project["description"] ?? ""; ?>
                                            <p class="profile-project-desc"><?= htmlspecialchars(mb_substr($desc, 0, 140)) ?><?= mb_strlen($desc) > 140 ? "..." : "" ?></p>
                                        </div>
                                        <a href="project-detail.php?id=<?= (int)$project["id"] ?>" class="btn-detail">İncele</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                                
                    <?php if (count($userJoinedProjects) > 0): ?>
                        <div class="profile-projects-sub">
                            <strong class="profile-projects-sub-title">Katıldığı Projeler</strong>
                            <div class="profile-projects-scroll">
                                <?php foreach ($userJoinedProjects as $jproject): ?>
                                    <div class="profile-project-item">
                                        <div class="profile-project-info">
                                            <a href="project-detail.php?id=<?= (int)$jproject["id"] ?>" class="profile-project-title">
                                                <?= htmlspecialchars($jproject["title"]) ?>
                                            </a>
                                            <?php $jdesc = $jproject["description"] ?? ""; ?>
                                            <p class="profile-project-desc"><?= htmlspecialchars(mb_substr($jdesc, 0, 140)) ?><?= mb_strlen($jdesc) > 140 ? "..." : "" ?></p>
                                        </div>
                                        <a href="project-detail.php?id=<?= (int)$jproject["id"] ?>" class="btn-detail">İncele</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                                
                    <?php if (count($userProjects) === 0 && count($userJoinedProjects) === 0): ?>
                        <p class="profile-projects-empty">Henüz bir projesi bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$viewing_own_profile): ?>
                <?php if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] != $user_id): ?>
                    <div class="profile-actions">
                        <a href="chat.php?id=<?= $user["id"] ?>" class="edit-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                            Mesaj Gönder
                        </a>

                        <?php if (!empty($currentUserTeams)): ?>
                            <button type="button" class="edit-btn invite-profile-btn" data-target="<?= (int)$user["id"] ?>" onclick="openInviteModal(this)">
                                <i class="fa-solid fa-user-plus"></i>
                                Takıma Davet Et
                            </button>
                        <?php else: ?>
                            <button type="button" class="edit-btn disabled-btn invite-profile-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                Takıma Davet Et
                            </button>
                            <small class="action-note">Takıma davet göndermek için önce bir takım oluşturmanız gerekiyor.</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="profile-actions" style="margin-top:14px;">
                <a href="javascript:history.back()" class="edit-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>

        </div>

    </div>

</div>

<div class="modal-overlay" id="inviteModal" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="inviteModalTitle">
        <div class="modal-header">
            <h3 id="inviteModalTitle"><i class="fa-solid fa-user-group"></i> Takıma Davet Et</h3>
            <button type="button" class="modal-close" onclick="closeInviteModal()" aria-label="Kapat">&times;</button>
        </div>

        <p class="modal-subtitle">Bu kullanıcıyı hangi takımınıza davet etmek istiyorsunuz?</p>

        <div class="invite-team-list">
            <?php if (empty($currentUserTeams)): ?>
                <p class="invite-teams-empty">
                    Davet gönderebileceğiniz takımınız yok. Lider veya yardımcı lideri olduğunuz bir <a href="create-team.php">takım oluşturun</a>.
                </p>
            <?php else: ?>
                <?php foreach ($currentUserTeams as $inviteTeam): ?>
                    <div class="invite-team-option-wrap" data-team-id="<?= (int)$inviteTeam['id'] ?>">
                        <a href="#" class="invite-team-option" data-team-id="<?= (int)$inviteTeam['id'] ?>">
                            <i class="fa-solid fa-users"></i>
                            <span><?= htmlspecialchars($inviteTeam['team_name']) ?></span>
                            <span class="invite-team-pending">Beklemede</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="inviteNote"><i class="fa-solid fa-note-sticky" style="margin-top: 8px;"></i> Not (İsteğe Bağlı)</label>
            <textarea id="inviteNote" rows="4" maxlength="500"
                placeholder="Davet isteğinize bir not ekleyiniz."></textarea>
            <small class="team-action-note">En fazla 500 karakter.</small>
        </div>

        <div class="modal-footer">
            <button type="button" class="edit-btn" onclick="closeInviteModal()">Vazgeç</button>
        </div>
    </div>
</div>

<script>
var inviteTargetUserId = null;
var invitePendingTeams = <?= json_encode($pendingTeamMap); ?>;
var inviteMemberTeams = <?= json_encode($alreadyMemberMap); ?>;

function buildInviteLinks() {
    var userId = inviteTargetUserId;
    if (!userId) return;
    var note = document.getElementById('inviteNote') ? document.getElementById('inviteNote').value : '';
    var encodedNote = encodeURIComponent(note);

    var pendingForUser = invitePendingTeams || {};
    var memberForUser = inviteMemberTeams || {};

    document.querySelectorAll('.invite-team-option-wrap').forEach(function (wrap) {
        var teamId = wrap.getAttribute('data-team-id');
        var isPending = !!pendingForUser[teamId];
        var isMember = !!memberForUser[teamId];
        var disabled = isPending || isMember;

        wrap.classList.toggle('is-pending', isPending);
        wrap.classList.toggle('is-member', isMember);

        var badge = wrap.querySelector('.invite-team-pending');
        if (isPending) {
            badge.textContent = 'Beklemede';
        } else if (isMember) {
            badge.textContent = 'Zaten bu takımda';
        }

        var link = wrap.querySelector('.invite-team-option');
        if (disabled) {
            link.removeAttribute('href');
            link.setAttribute('aria-disabled', 'true');
        } else {
            link.href = '/teammate-finder/api/send-team-request.php?user=' + userId
                + '&team=' + teamId
                + '&note=' + encodedNote;
            link.removeAttribute('aria-disabled');
        }
    });
}

function openInviteModal(btn) {
    inviteTargetUserId = btn.getAttribute('data-target');
    if (!inviteTargetUserId) return;

    if (document.getElementById('inviteNote')) {
        document.getElementById('inviteNote').value = '';
    }
    buildInviteLinks();

    document.getElementById('inviteModal').classList.add('open');
    document.getElementById('inviteModal').setAttribute('aria-hidden', 'false');
}

function closeInviteModal() {
    var modal = document.getElementById('inviteModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('inviteModal');
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeInviteModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeInviteModal();
    });
    var noteField = document.getElementById('inviteNote');
    if (noteField) {
        noteField.addEventListener('input', buildInviteLinks);
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>