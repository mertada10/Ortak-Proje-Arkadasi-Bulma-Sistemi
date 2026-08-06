<?php
session_start();
require_once "config/database.php";

$currentUserId = $_SESSION["user_id"] ?? 0;

if ($currentUserId <= 0) {
    header("Location: login.php");
    exit;
}

// Giriş yapan kullanıcı
$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$currentUserId]);

$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Kullanıcının takımı
$stmt = $pdo->prepare("
    SELECT team_id
    FROM team_members
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$currentUserId]);

$myTeamId = $stmt->fetchColumn();

// Diğer kullanıcılar
$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id <> ?
");
$stmt->execute([$currentUserId]);

$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recommendedUsers = [];

foreach ($allUsers as $candidate) {

    // Aynı takımdaysa önerme
    if ($myTeamId) {

        $q = $pdo->prepare("
            SELECT 1
            FROM team_members
            WHERE team_id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $q->execute([
            $myTeamId,
            $candidate["id"]
        ]);

        if ($q->fetch()) {
            continue;
        }
    }

    // Bekleyen takım isteği varsa önerme
    $q = $pdo->prepare("
        SELECT 1
        FROM team_requests
        WHERE status='pending'
        AND (
            (sender_id=? AND receiver_id=?)
            OR
            (sender_id=? AND receiver_id=?)
        )
        LIMIT 1
    ");

    $q->execute([
        $currentUserId,
        $candidate["id"],
        $candidate["id"],
        $currentUserId
    ]);

    if ($q->fetch()) {
        continue;
    }

    $score = 0;

    // Aynı bölüm
    if (
        strtolower(trim($currentUser["department"] ?? "")) ===
        strtolower(trim($candidate["department"] ?? ""))
    ) {
        $score += 15;
    }

    // Ortak teknolojiler
    $mySkills = array_filter(array_map(
        "trim",
        explode(",", strtolower($currentUser["skills"] ?? ""))
    ));

    $hisSkills = array_filter(array_map(
        "trim",
        explode(",", strtolower($candidate["skills"] ?? ""))
    ));

    $score += count(array_intersect($mySkills, $hisSkills)) * 5;

    // Ortak ilgi alanları
    $myInterests = array_filter(array_map(
        "trim",
        explode(",", strtolower($currentUser["interests"] ?? ""))
    ));

    $hisInterests = array_filter(array_map(
        "trim",
        explode(",", strtolower($candidate["interests"] ?? ""))
    ));

    $score += count(array_intersect($myInterests, $hisInterests)) * 4;

    // Profil fotoğrafı
    if (!empty($candidate["profile_image"])) {
        $score += 2;
    }

    // Hakkımda
    if (!empty(trim($candidate["about"] ?? ""))) {
        $score += 2;
    }

    $candidate["score"] = $score;

    // Sadece puanı olan kullanıcıları öner
    if ($score > 0) {
        $recommendedUsers[] = $candidate;
    }
}

usort($recommendedUsers, function ($a, $b) {
    return $b["score"] <=> $a["score"];
});

$recommendedUsers = array_slice($recommendedUsers, 0, 5);

$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

$search = trim($_GET["search"] ?? "");

if ($search != "") {

    $stmt = $pdo->prepare("
        SELECT
            projects.*,
            users.name,
            users.surname,
            users.department,
            users.profile_image
        FROM projects
        JOIN users ON projects.user_id = users.id
        WHERE projects.title LIKE ?
           OR projects.description LIKE ?
           OR projects.required_skills LIKE ?
        ORDER BY projects.created_at DESC
    ");

    $keyword = "%{$search}%";

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            projects.*,
            users.name,
            users.surname,
            users.department,
            users.profile_image
        FROM projects
        JOIN users ON projects.user_id = users.id
        ORDER BY projects.created_at DESC
    ");

}

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<div class="main-container">

    <?php if ($success != ""): ?>
        <div class="alert success-alert mb-20">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Proje İlanları</h1>
        <p>Takım arkadaşı arayan projeleri inceleyebilir veya kendi projenizi oluşturabilirsiniz.</p>
    </div>

    <div class="search-container">

        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>
            
            <input
                type="search"
                id="searchInput"
                placeholder="Proje ara..."
                autocomplete="off">
            
        </div>

    </div>

    <div class="project-grid" id="projectList">
        <?php if (count($projects) > 0): ?>
            <?php foreach ($projects as $project): ?>
                <div class="project-card">
                    <div class="card-header">

                        <a href="pages/user-profile.php?id=<?= $project["user_id"] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">
                                
                            <?php if (!empty($project["profile_image"]) && file_exists("assets/uploads/" . $project["profile_image"])): ?>
                                <img src="assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="author-avatar-img" alt="Profil">
                            <?php else: ?>
                                <div class="author-avatar">
                                    <?= strtoupper(mb_substr($project["name"], 0, 1) . mb_substr($project["surname"], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="author-info">
                                <h4><?= htmlspecialchars($project["name"] . " " . $project["surname"]) ?></h4>
                                <p><?= htmlspecialchars($project["department"] ?? 'Bölüm Belirtilmedi') ?></p>
                            </div>
                            
                        </a>
                            
                    </div>

                    <div class="card-body">
                        <h3 class="project-title"><?= htmlspecialchars($project["title"]) ?></h3>
                        
                        <p class="project-desc"><?= htmlspecialchars($project["description"]) ?></p>
                        
                        <?php if (!empty($project["required_skills"])): ?>
                            <div class="project-skills">
                                <strong><i class="fa-solid fa-code"></i> Teknolojiler:</strong>
                                <span><?= htmlspecialchars($project["required_skills"]) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <div class="members-needed">
                            <i class="fa-solid fa-user-group"></i>
                            <span>Aranan: <strong><?= (int)($project["required_people"] ?? $project["members_needed"] ?? 1) ?> Kişi</strong></span>
                        </div>
                        <a href="pages/project-detail.php?id=<?= $project["id"] ?>" class="btn-detail">İncele</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-projects">
                <i class="fa-solid fa-folder-open"></i>
                <p>Henüz yayınlanmış bir proje ilanı bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Önerilen Takım Arkadaşları -->
    <div class="recommended-section">

        <div class="recommended-header">
            <h2>Önerilen Takım Arkadaşları</h2>
            <p>Teknolojileriniz, ilgi alanlarınız ve bölümünüze göre sizin için en uygun ekip arkadaşları.</p>
        </div>

        <div class="recommended-grid">

            <?php if(count($recommendedUsers)>0): ?>

                <?php foreach($recommendedUsers as $user): ?>

                    <div class="recommended-card">

                        <a href="pages/user-profile.php?id=<?= $user["id"] ?>" class="recommended-user">

                            <?php if(!empty($user["profile_image"]) && file_exists("assets/uploads/".$user["profile_image"])): ?>

                                <img
                                    src="assets/uploads/<?= htmlspecialchars($user["profile_image"]) ?>"
                                    class="recommended-avatar"
                                    alt="Profil">

                            <?php else: ?>

                                <div class="recommended-avatar-letter">
                                    <?= strtoupper(
                                        mb_substr($user["name"],0,1).
                                        mb_substr($user["surname"],0,1)
                                    ) ?>
                                </div>

                            <?php endif; ?>

                            <h3>
                                <?= htmlspecialchars($user["name"]." ".$user["surname"]) ?>
                            </h3>

                        </a>

                        <div class="recommended-info">

                            <div class="recommended-item">
                                <strong>Bölüm</strong>
                                <span><?= htmlspecialchars($user["department"] ?: "Belirtilmemiş") ?></span>
                            </div>
                                                        
                            <div class="recommended-item">
                                <strong>Teknolojiler</strong>
                                <span><?= htmlspecialchars($user["skills"] ?: "-") ?></span>
                            </div>
                                                        
                            <div class="recommended-item">
                                <strong>İlgi Alanları</strong>
                                <span><?= htmlspecialchars($user["interests"] ?: "-") ?></span>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="no-projects">
                    Henüz önerilecek kullanıcı bulunamadı.
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once "includes/footer.php"; ?>