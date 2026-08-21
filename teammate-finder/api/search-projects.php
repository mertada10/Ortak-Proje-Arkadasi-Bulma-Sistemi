<?php
require_once __DIR__ . "/../includes/cors.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/project_filters.php";

$search  = trim($_GET["search"] ?? "");
$limit   = 50; // Canlı arama yanıtını ve HTML çıktısını sınırlar.
$keyword = "%{$search}%";
$params  = [];

$clauses = active_project_clauses();
$where   = $clauses["where"];

if ($search !== "") {
    $where .= " AND (projects.title LIKE ? OR projects.description LIKE ? OR projects.required_skills LIKE ?)";
    $params = [$keyword, $keyword, $keyword];
}

// LIMIT güvenli bir sabit olduğundan doğrudan eklenir.
$stmt = $pdo->prepare("
    SELECT projects.*, users.name, users.surname, users.department, users.profile_image
    FROM projects
    JOIN users ON projects.user_id = users.id
    " . $clauses["join"] . "
    " . $where . "
    ORDER BY projects.updated_at DESC
    LIMIT " . $limit . "
");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (count($projects) > 0): ?>

    <?php foreach ($projects as $project): ?>

        <div class="project-card">

            <div class="card-header">

                <a href="pages/user-profile.php?id=<?= $project["user_id"] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">

                    <?php if (!empty($project["profile_image"]) && file_exists("../assets/uploads/" . $project["profile_image"])): ?>

                        <img src="assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="author-avatar-img">

                    <?php else: ?>

                        <div class="author-avatar">
                            <?= strtoupper(mb_substr($project["name"],0,1).mb_substr($project["surname"],0,1)) ?>
                        </div>

                    <?php endif; ?>

                    <div class="author-info">
                        <h4><?= htmlspecialchars($project["name"]." ".$project["surname"]) ?></h4>
                        <p><?= htmlspecialchars($project["department"] ?? "Bölüm Belirtilmedi") ?></p>
                    </div>

                </a>

            </div>

            <div class="card-body">

                <h3 class="project-title"><?= htmlspecialchars($project["title"]) ?></h3>

                <p class="project-desc"><?= htmlspecialchars($project["description"]) ?></p>

                <?php if(!empty($project["required_skills"])): ?>

                    <div class="project-skills">

                        <strong><i class="fa-solid fa-code"></i> Teknolojiler:</strong>

                        <span><?= htmlspecialchars($project["required_skills"]) ?></span>

                    </div>

                <?php endif; ?>

            </div>

            <div class="card-footer">

                <div class="members-needed">

                    <i class="fa-solid fa-user-group"></i>

                    <span>
                        Aranan:
                        <strong><?= (int)($project["required_people"] ?? $project["members_needed"] ?? 1) ?> Kişi</strong>
                    </span>

                </div>

                <a href="pages/project-detail.php?id=<?= $project["id"] ?>" class="btn-detail">
                    İncele
                </a>

            </div>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="no-projects">
        <i class="fa-solid fa-folder-open"></i>
        <p>Sonuç bulunamadı.</p>
    </div>

<?php endif; ?>