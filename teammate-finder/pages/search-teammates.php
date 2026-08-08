<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$selectedSkills = isset($_GET["skills"]) ? array_values(array_filter(array_map("trim", (array)$_GET["skills"]))) : [];
$selectedInterests = isset($_GET["interests"]) ? array_values(array_filter(array_map("trim", (array)$_GET["interests"]))) : [];

$availableSkills = [
    "HTML",
    "CSS",
    "JavaScript",
    "PHP",
    "Python",
    "Java",
    "C#",
    "C++",
    "MySQL",
    "Flutter",
    "React",
    "Node.js"
];

$availableInterests = [
    "Yapay Zeka",
    "Web Geliştirme",
    "Mobil Uygulama",
    "Oyun Geliştirme",
    "Veri Bilimi",
    "Siber Güvenlik",
    "Bulut Teknolojileri",
    "IoT",
    "Robotik",
    "UI/UX Tasarım",
    "Blockchain"
];

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

$currentUserTeamId = null;
$currentUserTeamOwner = null;
$currentUserIsTeamLeader = false;

if (isset($_SESSION["user_id"])) {
    $stmt = $pdo->prepare("SELECT tm.team_id, t.owner_id FROM team_members tm JOIN teams t ON t.id = tm.team_id WHERE tm.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"]]);
    $currentUserTeam = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUserTeam) {
        $currentUserTeamId = (int)$currentUserTeam["team_id"];
        $currentUserTeamOwner = (int)$currentUserTeam["owner_id"];
        $currentUserIsTeamLeader = ($currentUserTeamOwner === (int)$_SESSION["user_id"]);
    }
}

$pendingRequestMap = [];
if (isset($_SESSION["user_id"]) && count($users) > 0) {
    $targetUserIds = array_map(static fn($user) => (int)$user["id"], $users);
    $placeholders = implode(",", array_fill(0, count($targetUserIds), "?"));

    $stmt = $pdo->prepare("\n        SELECT sender_id, receiver_id\n        FROM team_requests\n        WHERE status = 'pending'\n          AND (sender_id = ? OR receiver_id = ?)\n          AND (sender_id IN ($placeholders) OR receiver_id IN ($placeholders))\n    ");

    $stmt->execute(array_merge([$_SESSION["user_id"], $_SESSION["user_id"]], $targetUserIds, $targetUserIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $request) {
        $otherUserId = ((int)$request['sender_id'] === (int)$_SESSION['user_id'])
            ? (int)$request['receiver_id']
            : (int)$request['sender_id'];
        $pendingRequestMap[$otherUserId] = true;
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

    <div class="search-controls">
        <div class="search-sticky-bar">
            <div class="search-input-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="search" form="teamSearchForm" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="İsim, kullanıcı adı veya bölüm ara" />
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
                        <?php foreach ($availableSkills as $skill): ?>
                            <label class="chip-option">
                                <input type="checkbox" name="skills[]" value="<?= htmlspecialchars($skill) ?>" <?= in_array($skill, $selectedSkills, true) ? "checked" : "" ?>>
                                <span><?= htmlspecialchars($skill) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-column">
                        <h3><i class="fa-solid fa-heart"></i> İlgi Alanları</h3>
                        <?php foreach ($availableInterests as $interest): ?>
                            <label class="chip-option">
                                <input type="checkbox" name="interests[]" value="<?= htmlspecialchars($interest) ?>" <?= in_array($interest, $selectedInterests, true) ? "checked" : "" ?>>
                                <span><?= htmlspecialchars($interest) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-detail">Filtrele</button>
                    <a href="search-teammates.php" class="btn-secondary">Temizle</a>
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

                $teamActionLabel = null;
                $teamActionUrl = null;
                $teamActionDisabled = false;
                $teamActionNote = null;
                $hasPendingRequest = isset($pendingRequestMap[(int)$user['id']]);

                if (isset($_SESSION["user_id"])) {
                    if ($hasPendingRequest) {
                        $teamActionLabel = "İstek Beklemede";
                        $teamActionDisabled = true;
                        $teamActionNote = "Bu kullanıcıyla bekleyen bir ekip isteği var.";
                    } elseif ($currentUserTeamId && !$targetUserTeamId) {
                        if ($currentUserIsTeamLeader) {
                            $teamActionLabel = "Takıma Davet Et";
                            $teamActionUrl = "send-team-request.php?user=" . (int)$user['id'] . "&return=search-teammates.php";
                        } else {
                            $teamActionLabel = "Takıma Davet Et";
                            $teamActionDisabled = true;
                            $teamActionNote = "Takıma üye ekleme yetkiniz bulunmuyor.";
                        }
                    } elseif (!$currentUserTeamId && $targetUserTeamId) {
                        $teamActionLabel = "Takıma Katıl";
                        $teamActionUrl = "send-team-request.php?user=" . (int)$user['id'] . "&return=search-teammates.php";
                    } elseif (!$currentUserTeamId && !$targetUserTeamId) {
                        $teamActionLabel = "Takım Kur";
                        $teamActionDisabled = true;
                        $teamActionNote = "Önce bir takım oluşturmanız gerekiyor.";
                    } else {
                        $teamActionLabel = "Takımda";
                        $teamActionDisabled = true;
                        $teamActionNote = "Her iki kullanıcı da takımda.";
                    }
                }
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
                        <?php if ($teamActionLabel): ?>
                            <?php if ($teamActionDisabled): ?>
                                <button type="button" class="btn-secondary team-action-btn disabled-btn" disabled>
                                    <i class="fa-solid fa-lock"></i>
                                    <?= htmlspecialchars($teamActionLabel) ?>
                                </button>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($teamActionUrl) ?>" class="btn-detail team-action-btn"><?= htmlspecialchars($teamActionLabel) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($teamActionNote)): ?>
                        <small class="team-action-note"><?= htmlspecialchars($teamActionNote) ?></small>
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

<?php require_once "../includes/footer.php"; ?>
