<?php
session_start();

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_project"])) {

    if (!isset($_SESSION["user_id"])) {
        header("Location: ../login.php");
        exit;
    }

    $delete_id = (int)$_POST["id"];

    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id=?");
    $stmt->execute([$delete_id]);

    $delete_project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($delete_project && $delete_project["user_id"] == $_SESSION["user_id"]) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM team_requests 
                WHERE project_id=?
            ");

            $stmt->execute([$delete_id]);

            $stmt = $pdo->prepare("
                DELETE FROM projects 
                WHERE id=?
            ");

            $stmt->execute([$delete_id]);

            $pdo->commit();
            
            $_SESSION["success"] = "Proje ilanı başarıyla silindi.";
            
            header("Location: ../index.php");
            exit;

        } catch(Exception $e){

            $pdo->rollBack();

            header("Location: project-detail.php?id=".$delete_id);
            exit;

        }
    }
}

$project_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($project_id <= 0) {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        projects.*, 
        users.id AS author_id,
        users.name, 
        users.surname, 
        users.department,
        users.email,
        users.phone,
        users.profile_image
    FROM projects 
    JOIN users ON projects.user_id = users.id 
    WHERE projects.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
SELECT team_id
FROM team_members
WHERE user_id=?
LIMIT 1
");

$stmt->execute([
    $_SESSION["user_id"]
]);

$userTeam = $stmt->fetchColumn();

$is_project_user_team_leader = false;
if ($userTeam) {
    $stmt = $pdo->prepare("
    SELECT owner_id
    FROM teams
    WHERE id=?
    LIMIT 1
    ");

    $stmt->execute([
        $userTeam
    ]);

    $current_user_team_owner = $stmt->fetchColumn();
    $is_project_user_team_leader = ($current_user_team_owner == $_SESSION["user_id"]);
}

$stmt = $pdo->prepare("
SELECT team_id
FROM team_members
WHERE user_id=?
LIMIT 1
");

$stmt->execute([
    $project["author_id"]
]);

$ownerTeam = $stmt->fetchColumn();

$has_pending_request = false;
if (isset($_SESSION["user_id"])) {
    $stmt = $pdo->prepare("\nSELECT id\nFROM team_requests\nWHERE status='pending'\nAND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))\nLIMIT 1\n");
    $stmt->execute([
        (int)$_SESSION["user_id"],
        (int)$project["author_id"],
        (int)$project["author_id"],
        (int)$_SESSION["user_id"]
    ]);
    $has_pending_request = (bool)$stmt->fetchColumn();
}

if (!$project) {
    header("Location: ../index.php");
    exit;
}

$is_owner = isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $project["author_id"];

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

    <div class="project-detail-container">

        <div class="project-detail-main">

            <?php if(isset($_GET["success"])): ?>

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

            <?php if(isset($_GET["already"])): ?>
            
            <div class="error-message alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Bu projeye zaten ekip kurma isteği gönderdiniz.</span>
            </div>
            
            <?php endif; ?>

            <?php if(isset($_GET["alreadypending"])): ?>

            <div class="error-message alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Bu kullanıcıyla zaten bekleyen bir istek var.</span>
            </div>

            <?php endif; ?>

            <div class="detail-header">
                <a href="../index.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> İlanlara Dön
                </a>
                <span class="created-date">
                    <i class="fa-regular fa-calendar"></i> 
                    <?= date("d.m.Y H:i", strtotime($project["created_at"])) ?>
                </span>
            </div>

            <h1 class="detail-title"><?= htmlspecialchars($project["title"]) ?></h1>

            <?php if (!empty($project["required_skills"])): ?>
                <div class="detail-skills-box">
                    <strong><i class="fa-solid fa-code"></i> Aranan Teknolojiler:</strong>
                    <span><?= htmlspecialchars($project["required_skills"]) ?></span>
                </div>
            <?php endif; ?>

            <div class="detail-section">
                <h3><i class="fa-solid fa-align-left"></i> Proje Açıklaması</h3>
                <p class="detail-description"><?= nl2br(htmlspecialchars($project["description"])) ?></p>
            </div>

            <div class="detail-meta">
                <div class="meta-item">
                    <i class="fa-solid fa-user-group"></i>
                    <div>
                        <strong>Aranan Kişi Sayısı</strong>
                        <p><?= (int)($project["members_needed"] ?? $project["required_people"] ?? 1) ?> Kişi</p>
                    </div>
                </div>
            </div>

            <?php if ($is_owner): ?>
                <div class="owner-actions">
                    <form method="post" onsubmit="return confirm('Bu projeyi silmek istediğinizden emin misiniz?');">

                        <input type="hidden" name="id" value="<?= (int)$project["id"] ?>">

                        <input type="hidden" name="delete_project" value="1">

                        <button type="submit" class="btn-delete-project">
                            <i class="fa-solid fa-trash"></i> İlanı Sil
                        </button>

                    </form>
                </div>
            <?php endif; ?>

        </div>

        <div class="project-detail-sidebar">

            <div class="author-card">
                <h3>İlan Sahibi</h3>

                <a href="user-profile.php?id=<?= $project["author_id"] ?>" class="detail-author-link">

                    <div class="author-profile">

                        <?php if (!empty($project["profile_image"])): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="author-avatar">
                        <?php else: ?>
                            <div class="author-avatar">
                                <?= strtoupper(mb_substr($project["name"], 0, 1) . mb_substr($project["surname"], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <h4><?= htmlspecialchars($project["name"] . " " . $project["surname"]) ?></h4>
                        <p class="author-dept"><?= htmlspecialchars($project["department"] ?? 'Bölüm Belirtilmedi') ?></p>
                        
                    </div>
                        
                </a>

                <div class="author-contact-info">
                    <?php if (isset($_SESSION["user_id"])): ?>

                        <?php if (!isset($_SESSION["user_id"]) || $_SESSION["user_id"] != $project["author_id"]): ?>
                            <a href="chat.php?id=<?= $project["author_id"] ?>" class="btn-message">
                                <i class="fa-solid fa-paper-plane"></i> Mesaj Gönder
                            </a>
                        <?php endif; ?>

                        <?php if(isset($_SESSION["user_id"]) && $_SESSION["user_id"] != $project["author_id"]): ?>

                        <?php if ($has_pending_request): ?>

                            <button class="edit-btn disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                <i class="fa-solid fa-clock"></i>
                                İstek Beklemede
                            </button>

                            <small class="button-warning">
                                Bu ilan sahibiyle zaten bekleyen bir istek var.
                            </small>

                        <?php elseif ($userTeam && !$ownerTeam): ?>

                            <!-- Kullanıcı takımda, ilan sahibi takımda değil -->
                            <?php if ($is_project_user_team_leader): ?>
                                <a href="/teammate-finder/pages/send-team-request.php?project=<?= $project["id"] ?>" class="edit-btn">
                                    <i class="fa-solid fa-user-plus"></i>
                                    Takıma Davet Et
                                </a>
                            <?php else: ?>
                                <button class="edit-btn disabled-btn" disabled>
                                    <i class="fa-solid fa-lock"></i>
                                    <i class="fa-solid fa-user-plus"></i>
                                    Takıma Davet Et
                                </button>
                                <small class="button-warning">
                                    Takıma üye ekleme yetkiniz bulunmuyor.
                                </small>
                            <?php endif; ?>

                        <?php elseif (!$userTeam && $ownerTeam): ?>
                        
                            <!-- Kullanıcı takımda değil, ilan sahibi takımda -->
                            <a href="/teammate-finder/pages/send-team-request.php?project=<?= $project["id"] ?>" class="edit-btn">
                                <i class="fa-solid fa-people-group"></i>
                                Takıma Katılma İsteği Yolla
                            </a>
                        
                        
                        <?php elseif (!$userTeam && !$ownerTeam): ?>

                            <!-- İkisi de takımda değil -->
                                                
                            <button class="edit-btn disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                <i class="fa-solid fa-users"></i>
                                Takım Kurma İsteği Yolla
                            </button>
                                                
                            <small class="button-warning">
                                Önce bir takım oluşturmanız gerekiyor.
                                Takım kurduktan sonra ekip isteği gönderebilirsiniz.
                            </small>
                                                
                        <?php endif; ?>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="login-warning">
                            <i class="fa-solid fa-lock"></i>
                            <p>İlan sahibiyle iletişime geçmek için lütfen <a href="../login.php">giriş yapın</a>.</p>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>