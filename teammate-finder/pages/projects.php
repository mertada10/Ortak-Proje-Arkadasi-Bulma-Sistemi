<?php
session_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)$_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT team_id
    FROM team_members
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$myTeamId = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT projects.*, users.name, users.surname, users.department, users.profile_image
    FROM projects
    JOIN users ON projects.user_id = users.id
    WHERE projects.user_id = ?
    ORDER BY projects.created_at DESC
");
$stmt->execute([$user_id]);
$myProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$projectMemberCounts = [];
$projectMemberAvatars = [];
if (count($myProjects) > 0) {
    $myProjectIds = array_map(static fn($p) => (int)$p["id"], $myProjects);
    $projectIdPlaceholders = implode(",", array_fill(0, count($myProjectIds), "?"));

    $stmt = $pdo->prepare("
        SELECT tr.project_id, u.profile_image
        FROM requests tr
        JOIN users u ON u.id = tr.sender_id
        WHERE tr.project_id IN ($projectIdPlaceholders)
          AND tr.status = 'accepted'
    ");
    $stmt->execute($myProjectIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row["project_id"];
        $projectMemberCounts[$pid] = ($projectMemberCounts[$pid] ?? 0) + 1;

        if (count($projectMemberAvatars[$pid] ?? []) < 4) {
            $projectMemberAvatars[$pid][] = $row["profile_image"];
        }
    }
}

$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

$joinedProjects = [];

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
$joinedProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

    <div class="page-header mb-20" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Projelerim</h1>
            <p>Oluşturduğunuz ve katıldığınız projeleri buradan yönetebilirsiniz.</p>
        </div>
        <a href="create-project.php" class="btn-detail" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-plus"></i> Yeni Proje Oluştur
        </a>
    </div>

    <?php if ($success != ""): ?>
        <div class="alert success-alert mb-20">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <h2 class="projects-section-title">
        <i class="fa-solid fa-folder-open"></i>
        Oluşturduğum Projeler
    </h2>

    <div class="project-grid">
        <?php if (count($myProjects) > 0): ?>
            <?php foreach ($myProjects as $project): ?>
                <div class="project-card">
                    <div class="card-header">
                        <a href="user-profile.php?id=<?= $project["user_id"] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">
                            <?php if (!empty($project["profile_image"]) && file_exists("../assets/uploads/" . $project["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="author-avatar-img" alt="Profil">
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
                    </div>

                    <div class="card-footer">
                        <?php
                            $memberCount = $projectMemberCounts[(int)$project["id"]] ?? 0;
                            $memberAvatars = $projectMemberAvatars[(int)$project["id"]] ?? [];
                        ?>
                        <div class="members-needed">
                            <i class="fa-solid fa-users"></i>
                            <span><?= (int)$memberCount ?> Kişi Katıldı</span>
                        </div>

                        <?php if (count($memberAvatars) > 0): ?>
                            <div class="project-members-stack">
                                <?php foreach ($memberAvatars as $avatar): ?>
                                    <?php if (!empty($avatar) && file_exists("../assets/uploads/" . $avatar)): ?>
                                        <img src="../assets/uploads/<?= htmlspecialchars($avatar) ?>" class="project-member-mini" alt="Üye">
                                    <?php else: ?>
                                        <span class="project-member-mini project-member-mini-default"><i class="fa-solid fa-user"></i></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a href="project-detail.php?id=<?= (int)$project["id"] ?>&from=projects" class="btn-detail">İncele</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-projects">
                <i class="fa-solid fa-folder-open"></i>
                <p>Henüz oluşturduğunuz bir proje bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

    <h2 class="projects-section-title" style="margin-top:50px;">
        <i class="fa-solid fa-user-group"></i>
        Katıldığım Projeler
    </h2>

    <div class="project-grid">
        <?php if (count($joinedProjects) > 0): ?>
            <?php foreach ($joinedProjects as $project): ?>
                <div class="project-card">
                    <div class="card-header">
                        <a href="user-profile.php?id=<?= $project["user_id"] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">
                            <?php if (!empty($project["profile_image"]) && file_exists("../assets/uploads/" . $project["profile_image"])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="author-avatar-img" alt="Profil">
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
                            <span>Aranan: <strong><?= (int)($project["members_needed"] ?? 1) ?> Kişi</strong></span>
                        </div>

                        <a href="project-detail.php?id=<?= (int)$project["id"] ?>&from=joined" class="btn-detail">İncele</a>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-projects">
                <i class="fa-solid fa-user-group"></i>
                <p>Henüz katıldığınız bir proje bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>