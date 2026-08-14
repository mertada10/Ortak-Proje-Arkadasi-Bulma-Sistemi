<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

function splitTagsUi($input) {
    $input = trim($input ?? "");
    $input = preg_replace('/[\/;|]+/', ',', $input);
    $input = str_replace('-', ',', $input);
    return array_values(array_filter(array_map('trim', explode(',', $input))));
}

$search = trim($_GET["search"] ?? "");
$skillsQuery = trim($_GET["skills"] ?? "");
$interestsQuery = trim($_GET["interests"] ?? "");
$selectedSkills = splitTagsUi($skillsQuery);
$selectedInterests = splitTagsUi($interestsQuery);

$conditions = ["id != ?"];
$params = [$_SESSION["user_id"]];

if ($search !== "") {
    $likeSearch = "%{$search}%";
    $conditions[] = "(name LIKE ? OR surname LIKE ? OR username LIKE ? OR department LIKE ? OR about LIKE ?)";
    $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch]);
}

if (!empty($selectedSkills)) {
    $skillClauses = [];
    foreach ($selectedSkills as $skill) {
        $skillClauses[] = "skills LIKE ?";
        $params[] = "%{$skill}%";
    }
    $conditions[] = "(" . implode(" OR ", $skillClauses) . ")";
}

if (!empty($selectedInterests)) {
    $interestClauses = [];
    foreach ($selectedInterests as $interest) {
        $interestClauses[] = "interests LIKE ?";
        $params[] = "%{$interest}%";
    }
    $conditions[] = "(" . implode(" OR ", $interestClauses) . ")";
}

$sql = "
    SELECT id, name, surname, username, department, email, phone, skills, interests, about, profile_image
    FROM users
    WHERE " . implode(" AND ", $conditions) . "
    ORDER BY name ASC, surname ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$currentUserTeamId = null;

$stmt = $pdo->prepare("
    SELECT team_id
    FROM team_members
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION["user_id"]]);
$currentUserTeamId = $stmt->fetchColumn();

$pendingRequestMap = [];
$pendingTeamMap = [];
if (isset($_SESSION["user_id"]) && count($users) > 0) {
    $targetUserIds = array_map(static fn($user) => (int)$user["id"], $users);
    $placeholders = implode(",", array_fill(0, count($targetUserIds), "?"));

    $stmt = $pdo->prepare("\n        SELECT sender_id, receiver_id, team_id\n        FROM requests\n        WHERE status = 'pending'\n          AND project_id IS NULL\n          AND (sender_id = ? OR receiver_id = ?)\n          AND (sender_id IN ($placeholders) OR receiver_id IN ($placeholders))\n    ");

    $stmt->execute(array_merge([$_SESSION["user_id"], $_SESSION["user_id"]], $targetUserIds, $targetUserIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $request) {
        $otherUserId = ((int)$request['sender_id'] === (int)$_SESSION['user_id'])
            ? (int)$request['receiver_id']
            : (int)$request['sender_id'];
        $pendingRequestMap[$otherUserId] = true;

        if (!empty($request['team_id'])) {
            $pendingTeamMap[$otherUserId][(int)$request['team_id']] = true;
        }
    }
}

$alreadyMemberMap = [];
$myTeamIds = array_column($currentUserTeams, 'id');
if (count($users) > 0 && count($myTeamIds) > 0) {
    $memberUserIds = array_map(static fn($user) => (int)$user["id"], $users);
    $memberUserPlaceholders = implode(",", array_fill(0, count($memberUserIds), "?"));
    $memberTeamPlaceholders = implode(",", array_fill(0, count($myTeamIds), "?"));

    $stmt = $pdo->prepare("
        SELECT user_id, team_id
        FROM team_members
        WHERE user_id IN ($memberUserPlaceholders)
          AND team_id IN ($memberTeamPlaceholders)
    ");
    $stmt->execute(array_merge($memberUserIds, $myTeamIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $alreadyMemberMap[(int)$row['user_id']][(int)$row['team_id']] = true;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">
    <div class="page-header">
        <h1><i class="fa-solid fa-users-rays"></i> Takım Arkadaşı Bul</h1>
        <p>Teknoloji ve ilgi alanlarına göre uygun ekip arkadaşlarını keşfedin.</p>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="success-message alert">
            <i class="fa-solid fa-circle-check"></i>
            <span>Takıma davet isteğiniz gönderildi.</span>
        </div>
    <?php elseif (isset($_GET["alreadypending"])): ?>
        <div class="error-message">
            Bu kullanıcıya bu takım için zaten bekleyen bir davet var.
        </div>
    <?php elseif (isset($_GET["already"])): ?>
        <div class="error-message">
            Bu kullanıcıya zaten bir davet gönderdiniz.
        </div>
    <?php elseif (isset($_GET["alreadyteam"])): ?>
        <div class="error-message">
            Bu kullanıcı zaten seçilen takımın üyesi.
        </div>
    <?php endif; ?>

    <div class="search-controls">
        <div class="search-sticky-bar">
            <div class="search-input-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="search" form="teamSearchForm" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="İsim, kullanıcı adı veya bölüm ara" autocomplete="off" />
            </div>
            <button type="submit" form="teamSearchForm" class="btn-detail search-submit-btn">Ara</button>
        </div>

        <form method="get" id="teamSearchForm" class="filter-panel">
            <button type="button" class="filter-toggle" id="filterToggle" aria-expanded="false" aria-controls="filterContent">
                <span><i class="fa-solid fa-sliders"></i> Filtreleri Aç / Kapat</span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div id="filterContent" class="filter-content">
                <div class="filter-grid">
                    <div class="filter-column">
                        <h3><i class="fa-solid fa-code"></i> Teknolojiler</h3>
                        <div class="search-input-wrap">
                            <i class="fa-solid fa-code"></i>
                            <input type="text" name="skills" value="<?= htmlspecialchars($skillsQuery) ?>" placeholder="Örnek: php, javascript, mysql" autocomplete="off">
                        </div>
                    </div>

                    <div class="filter-column">
                        <h3><i class="fa-solid fa-heart"></i> İlgi Alanları</h3>
                        <div class="search-input-wrap">
                            <i class="fa-solid fa-heart"></i>
                            <input type="text" name="interests" value="<?= htmlspecialchars($interestsQuery) ?>" placeholder="Örnek: yapay zeka, web geliştirme" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-detail">Filtrele</button>
                    <a href="find-teammates.php" class="btn-secondary">Temizle</a>
                </div>
            </div>
        </form>
    </div>

    <div class="results-summary">
        <strong><?= count($users) ?></strong> kullanıcı bulundu.
    </div>

    <?php if (count($users) > 0): ?>
        <div class="user-grid">
            <?php foreach ($users as $user): ?>
                <?php
                $userSkills = array_values(array_filter(array_map('trim', explode(',', $user['skills'] ?? ''))));
                $userInterests = array_values(array_filter(array_map('trim', explode(',', $user['interests'] ?? ''))));
                $targetUserTeamId = null;

                $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
                $stmt->execute([(int)$user['id']]);
                $targetUserTeamId = $stmt->fetchColumn();
                ?>

                <div class="user-card">
                    <a href="user-profile.php?id=<?= (int)$user['id'] ?>" class="user-card-header">
                        <?php if (!empty($user['profile_image']) && file_exists("../assets/uploads/" . $user['profile_image'])): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($user['profile_image']) ?>" class="author-avatar-img">
                        <?php else: ?>
                            <div class="author-avatar">
                                <?= strtoupper(mb_substr($user['name'], 0, 1) . mb_substr($user['surname'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <h3><?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?></h3>
                            <p><?= htmlspecialchars($user['department'] ?? 'Bölüm Belirtilmedi') ?></p>
                        </div>
                    </a>

                    <p class="user-bio">
                        <?= htmlspecialchars(!empty($user['about']) ? $user['about'] : 'Bu kullanıcı henüz kısa bir açıklama eklemedi.') ?>
                    </p>

                    <?php if (!empty($userSkills)): ?>
                        <div class="user-tags">
                            <strong>Teknolojiler</strong>
                            <?php foreach ($userSkills as $skill): ?>
                                <span><?= htmlspecialchars($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($userInterests)): ?>
                        <div class="user-tags">
                            <strong>İlgi Alanları</strong>
                            <?php foreach ($userInterests as $interest): ?>
                                <span><?= htmlspecialchars($interest) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="user-actions">
                        <a href="chat.php?id=<?= (int)$user['id'] ?>" class="btn-detail">Mesaj Gönder</a>
                        <a href="user-profile.php?id=<?= (int)$user['id'] ?>" class="btn-secondary">Profili Gör</a>

                        <?php if (isset($_SESSION["user_id"])): ?>
                        <?php if (!empty($currentUserTeams)): ?>
                            <button type="button" class="btn-detail team-action-btn invite-team-btn" data-target="<?= (int)$user['id'] ?>" onclick="openInviteModal(this)">
                                <i class="fa-solid fa-user-plus"></i> Takıma Davet Et
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-detail disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                Takıma Davet Et
                            </button>
                            <small class="action-note">Takıma davet göndermek için önce bir takım oluşturmanız gerekiyor.</small>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>

                    <?php if (!empty($teamActionNote)): ?>
                        <small class="action-note"><?= htmlspecialchars($teamActionNote) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-projects">
            <i class="fa-solid fa-user-slash"></i>
            <p>Seçilen filtrelere uygun kullanıcı bulunamadı.</p>
        </div>
    <?php endif; ?>
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
                        <button type="button" class="invite-team-option" data-team-id="<?= (int)$inviteTeam['id'] ?>" onclick="selectInviteTeam(this)">
                            <i class="fa-solid fa-users"></i>
                            <span><?= htmlspecialchars($inviteTeam['team_name']) ?></span>
                            <span class="invite-team-pending">Beklemede</span>
                            <i class="fa-solid fa-circle-check invite-team-check" aria-hidden="true"></i>
                        </button>
                    </div>
                            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="inviteNote"><i class="fa-solid fa-note-sticky" style="margin-top: 8px;"></i> Not (İsteğe Bağlı)</label>
            <textarea id="inviteNote" rows="4" maxlength="500"
                placeholder="Davet isteğinize bir not ekleyiniz."></textarea>
            <small class="action-note">En fazla 500 karakter.</small>
        </div>

        <div class="modal-footer">
            <button type="button" class="edit-btn" onclick="closeInviteModal()">Vazgeç</button>
            <button type="button" class="edit-btn invite-send-btn" id="sendInviteBtn" onclick="sendInvite()" disabled>
                <i class="fa-solid fa-paper-plane"></i> Gönder
            </button>
        </div>
    </div>
</div>

<script>
var inviteTargetUserId = null;
var selectedInviteTeamId = null;
var invitePendingTeams = <?= json_encode($pendingTeamMap); ?>;
var inviteMemberTeams = <?= json_encode($alreadyMemberMap); ?>;

function isInviteTeamDisabled(teamId) {
    if (!inviteTargetUserId) return true;
    var pendingForUser = invitePendingTeams[inviteTargetUserId] || {};
    var memberForUser = inviteMemberTeams[inviteTargetUserId] || {};
    return !!pendingForUser[teamId] || !!memberForUser[teamId];
}

function refreshInviteTeams() {
    var userId = inviteTargetUserId;
    if (!userId) return;
    var pendingForUser = invitePendingTeams[userId] || {};
    var memberForUser = inviteMemberTeams[userId] || {};

    document.querySelectorAll('.invite-team-option-wrap').forEach(function (wrap) {
        var teamId = wrap.getAttribute('data-team-id');
        var isPending = !!pendingForUser[teamId];
        var isMember = !!memberForUser[teamId];
        var disabled = isPending || isMember;

        wrap.classList.toggle('is-pending', isPending);
        wrap.classList.toggle('is-member', isMember);

        var btn = wrap.querySelector('.invite-team-option');
        var badge = wrap.querySelector('.invite-team-pending');
        if (isPending) {
            badge.textContent = 'İstek Beklemede';
        } else if (isMember) {
            badge.textContent = 'Zaten bu takımda';
        }

        btn.disabled = disabled;
        if (disabled) {
            btn.setAttribute('aria-disabled', 'true');
            if (teamId === selectedInviteTeamId) {
                selectedInviteTeamId = null;
            }
            wrap.classList.remove('selected');
        } else {
            btn.removeAttribute('aria-disabled');
        }
    });

    updateSendButton();
}

function selectInviteTeam(btn) {
    if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;
    selectedInviteTeamId = btn.getAttribute('data-team-id');

    document.querySelectorAll('.invite-team-option-wrap').forEach(function (wrap) {
        wrap.classList.toggle('selected', wrap.getAttribute('data-team-id') === selectedInviteTeamId);
    });
    updateSendButton();
}

function updateSendButton() {
    var sendBtn = document.getElementById('sendInviteBtn');
    if (!sendBtn) return;

    if (!selectedInviteTeamId || isInviteTeamDisabled(selectedInviteTeamId)) {
        sendBtn.disabled = true;
        sendBtn.setAttribute('aria-disabled', 'true');
    } else {
        sendBtn.disabled = false;
        sendBtn.removeAttribute('aria-disabled');
    }
}

function sendInvite() {
    if (!inviteTargetUserId) return;
    if (!selectedInviteTeamId) {
        alert('Davet göndermek için önce yukarıdan bir takım seçin.');
        return;
    }
    if (isInviteTeamDisabled(selectedInviteTeamId)) {
        alert('Bu takıma davet gönderilemez.');
        return;
    }
    var note = document.getElementById('inviteNote') ? document.getElementById('inviteNote').value : '';
    var encodedNote = encodeURIComponent(note);

    window.location.href = '/teammate-finder/api/send-team-request.php?user=' + inviteTargetUserId
        + '&team=' + selectedInviteTeamId
        + '&note=' + encodedNote
        + '&return=/teammate-finder/pages/find-teammates.php';
}

function openInviteModal(btn) {
    inviteTargetUserId = btn.getAttribute('data-target');
    if (!inviteTargetUserId) return;

    selectedInviteTeamId = null;
    document.querySelectorAll('.invite-team-option-wrap').forEach(function (wrap) {
        wrap.classList.remove('selected');
    });

    if (document.getElementById('inviteNote')) {
        document.getElementById('inviteNote').value = '';
    }
    refreshInviteTeams();

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
        noteField.addEventListener('input', function () {
            updateSendButton();
        });
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
