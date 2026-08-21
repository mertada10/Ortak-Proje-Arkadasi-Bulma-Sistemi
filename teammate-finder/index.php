<?php
require_once __DIR__ . "/includes/session.php";
session_secure_start();
require_once "config/database.php";
require_once "includes/auto-login.php";
require_once "includes/project_filters.php";
require_once "includes/recommendations.php";

$currentUserId = $_SESSION["user_id"] ?? 0;

// Süresi dolan veya üyeleri tamamlanan ilanları tek yazma işlemiyle kapat.
close_expired_project_ads($pdo);

$currentUser      = null;
$recommendedUsers = [];

// Oturumdaki kullanıcıyı doğrula, ardından takım arkadaşı önerilerini üret.
if ($currentUserId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, name, surname, username, department, skills, interests, profile_image
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

    $recommendedUsers = recommend_teammates($pdo, $currentUser);
}

$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

// Arama + sayfalama: istek parametrelerini oku ve ortak sorgu yardımcısını çağır.
$search  = trim($_GET["search"] ?? "");
$page    = isset($_GET["p"]) && is_numeric($_GET["p"]) ? (int)$_GET["p"] : 1;
$perPage = 12;

$projectResult = fetch_active_projects($pdo, $search, $page, $perPage);
$projects      = $projectResult["items"];
$totalPages    = $projectResult["totalPages"];
$page          = $projectResult["page"];

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
        <form method="get" action="" class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="search" id="searchInput" placeholder="Proje ara..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        </form>
    </div>

    <div class="project-grid" id="projectList">
        <?php if (count($projects) > 0): ?>
            <?php foreach ($projects as $project): ?>
                <div class="project-card">
                    <div class="card-header">

                        <?php if ($currentUserId > 0): ?>
                            <a href="pages/user-profile.php?id=<?= $project["user_id"] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">
                        <?php else: ?>
                            <div style="display:flex;align-items:center;gap:12px;">
                        <?php endif; ?>
                                
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
                            
                        <?php if ($currentUserId > 0): ?></a><?php else: ?></div><?php endif; ?>
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

                        <a href="pages/project-detail.php?id=<?= (int)$project["id"] ?>&from=home" class="btn-detail">İncele</a>
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

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?p=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="page-link">&laquo; Önceki</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?p=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
                   class="page-link <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?p=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="page-link">Sonraki &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($currentUserId > 0): ?>
        <div class="recommended-section">
            <div class="recommended-header">
                <h2>Önerilen Takım Arkadaşları</h2>
                <p>Teknolojileriniz, ilgi alanlarınız ve bölümünüze göre sizin için en uygun ekip arkadaşları.</p>
            </div>

            <div class="recommended-grid">
                <?php if(count($recommendedUsers) > 0): ?>
                    <?php foreach($recommendedUsers as $user): ?>
                        <div class="recommended-card">
                            <a href="pages/user-profile.php?id=<?= $user["id"] ?>" class="recommended-user">
                                <?php if(!empty($user["profile_image"]) && file_exists("assets/uploads/".$user["profile_image"])): ?>
                                    <img src="assets/uploads/<?= htmlspecialchars($user["profile_image"]) ?>" class="recommended-avatar" alt="Profil">
                                <?php else: ?>
                                    <div class="recommended-avatar-letter">
                                        <?= strtoupper(mb_substr($user["name"],0,1).mb_substr($user["surname"],0,1)) ?>
                                    </div>
                                <?php endif; ?>
                                <h3><?= htmlspecialchars($user["name"]." ".$user["surname"]) ?></h3>
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
                            <?php $compat = (int)($user["compatibility"] ?? 0); ?>
                            <div class="recommended-compat">
                                <div class="recommended-compat-top">
                                    <span class="recommended-compat-label"><i class="fa-solid fa-handshake"></i> Uyumluluk</span>
                                    <span class="recommended-compat-value">%<?= $compat ?></span>
                                </div>
                                <div class="recommended-compat-bar">
                                    <div class="recommended-compat-fill" style="width: <?= $compat ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-projects">Henüz önerilecek kullanıcı bulunamadı.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once "includes/footer.php"; ?>