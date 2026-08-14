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

$stmt = $pdo->prepare("
    SELECT t.*, tm.role as user_role,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
    FROM team_members tm
    JOIN teams t ON t.id = tm.team_id
    WHERE tm.user_id = ?
    ORDER BY t.id DESC
");
$stmt->execute([$user_id]);
$my_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

<?php if (empty($my_teams)): ?>

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

    <div class="page-header mb-20" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Mevcut Takımlarım</h1>
            <p>Dahil olduğunuz veya lideri olduğunuz takımların listesi.</p>
        </div>
        <a href="create-team.php" class="btn-detail" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-plus"></i> Yeni Takım Oluştur
        </a>
    </div>

    <div class="project-grid">
        <?php foreach ($my_teams as $team): ?>
            <div class="project-card">
                <div class="card-body">
                    <div class="team-badge" style="margin-bottom: 12px;">
                        <i class="fa-solid fa-users"></i>
                        <span>Takım</span>
                    </div>
                    <h2 class="project-title" style="white-space: normal; overflow: visible;"><?= htmlspecialchars($team["team_name"]) ?></h2>
                    <p class="project-desc">
                        <?= !empty($team["description"]) ? htmlspecialchars($team["description"]) : "Takım açıklaması bulunmuyor." ?>
                    </p>
                </div>

                <div class="card-footer" style="margin-top: 15px;">
                    <div class="members-needed">
                        <i class="fa-solid fa-user-group"></i>
                        <span><?= $team["member_count"] ?> Üye</span>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="team-role-badge <?= $team["owner_id"] == $user_id ? 'leader-badge' : '' ?>" style="margin: 0;">
                            <?php if ($team["owner_id"] == $user_id): ?>
                                <i class="fa-solid fa-crown" style="color: #eab308;"></i> Lider
                            <?php elseif ($team["user_role"] === 'co_leader'): ?>
                                <i class="fa-solid fa-star" style="color: #c084fc;"></i> Y. Lider
                            <?php else: ?>
                                <i class="fa-solid fa-user"></i> Üye
                            <?php endif; ?>
                        </span>
                        
                        <a href="team-detail.php?id=<?= $team["id"] ?>" class="btn-detail">
                            Detay <i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-left: 4px;"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<?php require_once "../includes/footer.php"; ?>