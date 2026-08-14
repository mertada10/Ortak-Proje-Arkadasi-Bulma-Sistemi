<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

require_once "../config/database.php";

$currentUserId = $_SESSION["user_id"] ?? 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_ad"])) {
    if ($currentUserId <= 0) {
        header("Location: ../login.php");
        exit;
    }

    $delete_id = (int)($_POST["id"] ?? 0);

    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$delete_id]);
    $delete_project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($delete_project && (int)$delete_project["user_id"] === $currentUserId) {
        $stmt = $pdo->prepare("
            UPDATE projects
            SET required_skills = '',
                ad_description = NULL, -- İlan kaldırıldığında silinir
                members_needed = 1,
                expires_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$delete_id]);

        $_SESSION["success"] = "İlan kaldırıldı. Proje silinmedi.";
        header("Location: projects.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_project"])) {
    if ($currentUserId <= 0) {
        header("Location: ../login.php");
        exit;
    }

    $delete_id = (int)($_POST["id"] ?? 0);

    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$delete_id]);
    $delete_project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($delete_project && (int)$delete_project["user_id"] === $currentUserId) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM requests WHERE project_id = ?");
            $stmt->execute([$delete_id]);

            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$delete_id, $currentUserId]);

            $pdo->commit();

            $_SESSION["success"] = "Proje ve projeye bağlı ilan/istekler tamamen silindi.";
            header("Location: projects.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION["success"] = "Proje silinirken bir hata oluştu.";
            header("Location: project-detail.php?id=" . $delete_id . "&from=projects");
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["leave_project"])) {
    if ($currentUserId <= 0) {
        header("Location: ../login.php");
        exit;
    }

    $leave_project_id = (int)($_POST["project_id"] ?? 0);

    if ($leave_project_id <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM requests
        WHERE project_id = ?
          AND sender_id = ?
          AND status = 'accepted'
    ");
    $stmt->execute([$leave_project_id, $currentUserId]);

    $_SESSION["success"] = "Projeden ayrıldınız.";

    header("Location: project-detail.php?id=" . $leave_project_id);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_member"])) {

    if ($currentUserId <= 0) {
        header("Location: ../login.php");
        exit;
    }

    $remove_project_id = (int)$_POST["project_id"];
    $remove_member_id = (int)$_POST["member_id"];

    if ($remove_project_id <= 0 || $remove_member_id <= 0) {
        header("Location: project-detail.php?id=" . $remove_project_id);
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id=?");
    $stmt->execute([$remove_project_id]);
    $owner_check = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($owner_check && $owner_check["user_id"] == $currentUserId) {
        $stmt = $pdo->prepare("
            DELETE FROM requests
            WHERE project_id = ?
              AND status = 'accepted'
              AND sender_id = ?
        ");
        $stmt->execute([$remove_project_id, $remove_member_id]);
        $_SESSION["success"] = "Kullanıcı projeden çıkarıldı.";
    }

    header("Location: project-detail.php?id=" . $remove_project_id);
    exit;
}

$project_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$from = $_GET["from"] ?? "home";

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

if (!$project) {
    header("Location: ../index.php");
    exit;
}

$has_pending_request = false;
$already_in_project = false;

if ($currentUserId > 0) {
    $stmt = $pdo->prepare("
        SELECT id FROM requests 
        WHERE status='pending'
        AND project_id=?
        AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
        LIMIT 1
    ");
    $stmt->execute([
        $project_id,
        $currentUserId,
        (int)$project["author_id"],
        (int)$project["author_id"],
        $currentUserId
    ]);
    $has_pending_request = (bool)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id FROM requests 
        WHERE status='accepted' 
        AND project_id=?
        AND sender_id=?
        LIMIT 1
    ");
    $stmt->execute([$project_id, $currentUserId]);
    $already_in_project = (bool)$stmt->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.surname,
        u.department,
        u.profile_image
    FROM requests tr
    INNER JOIN users u ON u.id = tr.sender_id
    WHERE tr.project_id = ?
      AND tr.status = 'accepted'
      AND tr.sender_id != ?
    ORDER BY u.name ASC, u.surname ASC
");
$stmt->execute([$project_id, (int)$project["author_id"]]);
$project_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_owner = ($currentUserId > 0 && $currentUserId == $project["author_id"]);

$ad_is_active = !empty($project["expires_at"])
    && (strtotime($project["expires_at"]) >= time())
    && (count($project_members) < (int)($project["members_needed"] ?? 1));

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
                        Projeye katılma isteğiniz gönderildi.
                    </span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET["already"])): ?>
                <div class="error-message alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Bu projeye zaten katılma isteği gönderdiniz.</span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET["alreadypending"])): ?>
                <div class="error-message alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Bu kullanıcıyla zaten bekleyen bir istek var.</span>
                </div>
            <?php endif; ?>

            <div class="detail-header">

                <?php if ($from === "projects"): ?>
                    <a href="projects.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Projelerime Dön
                    </a>
                <?php elseif ($from === "joined"): ?>
                    <a href="projects.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Projelerime Dön
                    </a>
                <?php else: ?>
                    <a href="../index.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> İlanlara Dön
                    </a>
                <?php endif; ?>

                <div class="project-dates">
                    <span class="project-date-badge">
                        <i class="fa-regular fa-calendar"></i>
                        Oluşturulma: <?= date("d.m.Y H:i", strtotime($project["created_at"])) ?>
                    </span>
                    <?php if (!empty($project["expires_at"])): ?>
                        <span class="project-date-badge">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            Bitiş: <?= date("d.m.Y", strtotime($project["expires_at"])) ?>
                        </span>
                    <?php endif; ?>
                </div>
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

            <?php if (!empty($project["ad_description"])): ?>
                <div class="detail-section ad-description-section" style="margin-top: 20px; background-color: rgba(99, 102, 241, 0.05); border-left: 4px solid #6366f1; padding: 15px; border-radius: 6px;">
                    <h3><i class="fa-solid fa-bullhorn"></i> İlan Açıklaması / Ek Notlar</h3>
                    <p class="detail-description"><?= nl2br(htmlspecialchars($project["ad_description"])) ?></p>
                </div>
            <?php endif; ?>

            <div class="detail-meta">
                <?php if (!empty($project["expires_at"])): ?>
                    <div class="meta-item">
                        <i class="fa-solid fa-user-group"></i>
                        <div>
                            <strong>Aranan Kişi Sayısı</strong>
                            <p><?= (int)($project["members_needed"] ?? $project["required_people"] ?? 1) ?> Kişi</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="detail-section project-members-section">
                <h3><i class="fa-solid fa-users"></i> Projeye Katılan Kullanıcılar</h3>

                <div class="project-members-list">
                    <a href="user-profile.php?id=<?= (int)$project["author_id"] ?>" class="project-member-row">
                        <?php if (!empty($project["profile_image"])): ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($project["profile_image"]) ?>" class="project-member-avatar" alt="Profil">
                        <?php else: ?>
                            <span class="project-member-avatar project-member-avatar-default">
                                <?= strtoupper(mb_substr($project["name"], 0, 1) . mb_substr($project["surname"], 0, 1)) ?>
                            </span>
                        <?php endif; ?>

                        <span class="project-member-info">
                            <strong class="project-member-name"><?= htmlspecialchars($project["name"] . " " . $project["surname"]) ?></strong>
                            <span class="project-member-role">
                                <i class="fa-solid fa-crown"></i> Proje Sahibi
                            </span>
                        </span>

                        <i class="fa-solid fa-chevron-right project-member-arrow"></i>
                    </a>

                    <?php if (count($project_members) > 0): ?>
                        <?php foreach ($project_members as $member): ?>
                            <div class="project-member-item">
                                <a href="user-profile.php?id=<?= (int)$member["id"] ?>" class="project-member-row">
                                    <?php if (!empty($member["profile_image"])): ?>
                                        <img src="../assets/uploads/<?= htmlspecialchars($member["profile_image"]) ?>" class="project-member-avatar" alt="Profil">
                                    <?php else: ?>
                                        <span class="project-member-avatar project-member-avatar-default">
                                            <?= strtoupper(mb_substr($member["name"], 0, 1) . mb_substr($member["surname"], 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="project-member-info">
                                        <strong class="project-member-name"><?= htmlspecialchars($member["name"] . " " . $member["surname"]) ?></strong>
                                        <span class="project-member-role"><?= htmlspecialchars($member["department"] ?? "Bölüm Belirtilmedi") ?></span>
                                    </span>

                                    <i class="fa-solid fa-chevron-right project-member-arrow"></i>
                                </a>

                                <?php if ($is_owner): ?>
                                    <form method="post" class="project-member-remove-form" onsubmit="return confirm('Bu kullanıcıyı projeden çıkarmak istediğinize emin misiniz?');">
                                        <input type="hidden" name="remove_member" value="1">
                                        <input type="hidden" name="project_id" value="<?= (int)$project["id"] ?>">
                                        <input type="hidden" name="member_id" value="<?= (int)$member["id"] ?>">
                                        <button type="submit" class="btn-remove-member" title="Projeden Çıkar">
                                            <i class="fa-solid fa-user-minus"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (count($project_members) === 0): ?>
                    <div class="project-members-empty">
                        <i class="fa-solid fa-users"></i>
                        <span>Henüz projeye katılan başka bir kullanıcı yok.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="project-detail-sidebar">
            <div class="author-card">
                <h3>İlan Sahibi</h3>

                <?php if ($currentUserId > 0): ?>
                    <a href="user-profile.php?id=<?= $project["author_id"] ?>" class="detail-author-link">
                <?php else: ?>
                    <div class="detail-author-link" style="cursor: default;">
                <?php endif; ?>

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
                        
                <?php if ($currentUserId > 0): ?></a><?php else: ?></div><?php endif; ?>

                <div class="author-contact-info">

                    <?php if ($currentUserId > 0): ?>

                        <?php if ($currentUserId != $project["author_id"]): ?>
                            <a href="chat.php?id=<?= $project["author_id"] ?>" class="btn-message">
                                <i class="fa-solid fa-paper-plane"></i> Mesaj Gönder
                            </a>
                        <?php endif; ?>

                        <?php if ($currentUserId != $project["author_id"]): ?>
                            <?php if ($has_pending_request): ?>
                                <button class="edit-btn disabled-btn" disabled>
                                    <i class="fa-solid fa-lock"></i> <i class="fa-solid fa-clock"></i> İstek Beklemede
                                </button>
                                <small class="button-warning">Bu ilan sahibiyle zaten bekleyen bir istek var.</small>

                            <?php elseif ($already_in_project): ?>
                                <button class="edit-btn disabled-btn" disabled>
                                    <i class="fa-solid fa-check"></i> <i class="fa-solid fa-user-check"></i> Bu Projedesiniz
                                </button>

                            <?php elseif (!empty($project["expires_at"])): ?>
                                <button type="button" class="edit-btn" onclick="openProjectJoinModal()">
                                    <i class="fa-solid fa-user-plus"></i> Projeye Katılma İsteği Gönder
                                </button>
                            <?php else: ?>
                                <small class="button-warning">Bu proje henüz ilan edilmedi.</small>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php else: ?>

                        <div class="login-warning">
                            <i class="fa-solid fa-lock"></i>
                            <p>İlan sahibiyle iletişime geçmek, projeye katılmak veya mesaj göndermek için lütfen <a href="../login.php">giriş yapın</a>.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_owner): ?>
                    <div class="owner-actions">
                        <?php if ($ad_is_active): ?>
                            <form method="post" onsubmit="return confirm('Bu ilanı kaldırmak istediğinizden emin misiniz? Proje silinmeyecek.');">
                                <input type="hidden" name="id" value="<?= (int)$project["id"] ?>">
                                <input type="hidden" name="remove_ad" value="1">
                                <button type="submit" class="btn-delete-project">
                                    <i class="fa-solid fa-bullhorn"></i> İlanı Kaldır
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="post-ad.php?project=<?= (int)$project["id"] ?>" class="btn-advert">
                                <i class="fa-solid fa-bullhorn"></i> İlan Ver
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($from === "projects"): ?>
                            <form method="post" onsubmit="return confirm('DİKKAT! Bu projeyi tamamen silmek istediğinize emin misiniz? Proje, ilan ve projeye bağlı tüm istekler silinecek. Bu işlem geri alınamaz.');">
                                <input type="hidden" name="id" value="<?= (int)$project["id"] ?>">
                                <input type="hidden" name="delete_project" value="1">
                                <button type="submit" class="btn-delete-project">
                                    <i class="fa-solid fa-trash"></i> Projeyi Sil
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$is_owner && $already_in_project): ?>
                    <div class="owner-actions">
                        <form method="POST" onsubmit="return confirm('Bu projeden ayrılmak istediğinize emin misiniz?');">
                            <input type="hidden" name="project_id" value="<?= (int)$project["id"] ?>">
                            <input type="hidden" name="leave_project" value="1">
                            <button type="submit" class="btn-detail" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
                                <i class="fa-solid fa-right-from-bracket"></i> Ayrıl
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="projectJoinModal" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="projectJoinModalTitle">
        <div class="modal-header">
            <h3 id="projectJoinModalTitle"><i class="fa-solid fa-user-plus"></i> Projeye Katılma İsteği Gönder</h3>
            <button type="button" class="modal-close" aria-label="Kapat" onclick="closeProjectJoinModal()">&times;</button>
        </div>

        <p class="modal-subtitle">
            "<strong><?= htmlspecialchars($project["title"]) ?></strong>" projesine katılma isteği göndereceksiniz.
            İsterseniz ilan sahibinize bir not ekleyebilirsiniz.
        </p>

        <form method="POST" action="/teammate-finder/api/send-project-request.php" id="projectJoinForm">
            <input type="hidden" name="project" value="<?= (int)$project["id"] ?>">

            <div class="form-group">
                <label for="projectJoinNote"><i class="fa-solid fa-note-sticky"></i> Not (İsteğe Bağlı)</label>
                <textarea id="projectJoinNote" name="note" rows="4" maxlength="500"
                    placeholder="Örn: Projenin backend kısmında deneyimim var ve ekibe katılmak istiyorum..."></textarea>
                <small class="action-note">En fazla 500 karakter.</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="edit-btn" onclick="closeProjectJoinModal()">Vazgeç</button>
                <button type="submit" class="edit-btn">İsteği Gönder</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openProjectJoinModal() {
        var modal = document.getElementById('projectJoinModal');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        var note = document.getElementById('projectJoinNote');
        if (note) { note.value = ''; note.focus(); }
    }

    function closeProjectJoinModal() {
        var modal = document.getElementById('projectJoinModal');
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('projectJoinModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeProjectJoinModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('open')) closeProjectJoinModal();
            });
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>