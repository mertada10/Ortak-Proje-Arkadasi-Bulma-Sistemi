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
$error = "";

function splitTagsUi($input) {
    $input = trim($input ?? "");
    $input = preg_replace('/[\/;|]+/', ',', $input);
    $input = str_replace('-', ',', $input);
    return array_values(array_filter(array_map('trim', explode(',', $input))));
}

$stmt = $pdo->prepare("
    SELECT id, title, required_skills, members_needed, expires_at, ad_description
    FROM projects
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$myProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedProjectId = isset($_GET["project"]) ? (int)$_GET["project"] : 0;
$isEditMode = false;
$editProjectTitle = "";

$prefillSkills = [];
$prefillMembers = 1;
$prefillExpires = "";
$prefillAdDesc = "";

if ($selectedProjectId > 0) {
    foreach ($myProjects as $p) {
        if ((int)$p["id"] === $selectedProjectId) {
            $isEditMode = !empty($p["expires_at"]);
            $editProjectTitle = $p["title"];
            $prefillSkills = $p["required_skills"] ?? "";
            $prefillMembers = (int)($p["members_needed"] ?? 1);
            $prefillExpires = $p["expires_at"] ?? "";
            $prefillAdDesc = $p["ad_description"] ?? "";
            break;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    csrf_guard();

    $project_id = (int)($_POST["project_id"] ?? 0);
    $required_skills = implode(", ", splitTagsUi($_POST["skills"] ?? ""));
    $members_needed = (int)($_POST["members_needed"] ?? 1);
    $expires_at = trim($_POST["expires_at"] ?? "");
    $ad_description = trim($_POST["ad_description"] ?? "");

    $existingProject = false;
    if ($project_id > 0) {
        $stmt = $pdo->prepare("SELECT id, expires_at FROM projects WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$project_id, $user_id]);
        $existingProject = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $today = new DateTime("today");
    $max_date = (clone $today)->modify("+1 year");

    if ($project_id <= 0) {
        $error = "Lütfen mevcut projelerinizden birini seçiniz.";
    } elseif ($expires_at === "") {
        $error = "İlan bitiş tarihi zorunludur.";
    } elseif ($ad_description === "") {
        $error = "İlan açıklaması zorunludur.";
    } else {
        $expires_date = DateTime::createFromFormat("Y-m-d", $expires_at);

        if (!$expires_date || $expires_date->format("Y-m-d") !== $expires_at) {
            $error = "Geçersiz ilan bitiş tarihi formatı.";
        } elseif ($expires_date < $today) {
            $error = "İlan bitiş tarihi bugünden eski olamaz.";
        } elseif ($expires_date > $max_date) {
            $error = "İlan bitiş tarihi bugünden en fazla 1 yıl sonrası olabilir.";
        }
    }

    if ($error === "" && $required_skills === "") {
        $error = "Lütfen en az bir teknoloji yazınız.";
    }

    if ($error === "" && ($members_needed < 1 || $members_needed > 20)) {
        $error = "Aranan kişi sayısı 1 ile 20 arasında olmalıdır!";
    }

    if ($error === "") {

        if (!$existingProject) {
            $error = "Geçersiz proje seçimi.";
        } elseif (!empty($existingProject["expires_at"]) && !($isEditMode && $project_id === $selectedProjectId)) {
            $error = "Bu proje için zaten yayında bir ilan var. İlanı düzenlemek için proje detaylarındaki \"İlanı Düzenle\" butonunu kullanınız.";
        } else {

            $stmt = $pdo->prepare("
                UPDATE projects
                SET required_skills = ?, members_needed = ?, expires_at = ?, ad_description = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$required_skills, $members_needed, $expires_at, $ad_description, $project_id, $user_id]);

            $_SESSION["success"] = $isEditMode ? "İlan başarıyla güncellendi." : "Proje ilanı başarıyla yayınlandı.";
            header("Location: ../index.php");
            exit;
        }
    }

    $selectedProjectId = $project_id;
    $prefillMembers = $members_needed;
    $prefillExpires = $expires_at;
    $prefillSkills = $required_skills;
    $prefillAdDesc = $ad_description;

    if (!empty($existingProject["expires_at"])) {
        $isEditMode = true;
    }

    if ($isEditMode) {
        foreach ($myProjects as $p) {
            if ((int)$p["id"] === $project_id) {
                $editProjectTitle = $p["title"];
                break;
            }
        }
    }
}
require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <h2><i class="fa-solid <?= $isEditMode ? "fa-pen" : "fa-bullhorn" ?>"></i> <?= $isEditMode ? "İlanı Düzenle" : "Proje İlanı Oluştur" ?></h2>

    <?php if ($error != ""): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (count($myProjects) === 0): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            İlan verebilmek için önce bir 
            <a href="create-project.php" class="btn-text-link" style="text-decoration: underline;">proje</a> oluşturmalısınız.
        </div>
    <?php else: ?>

        <form method="POST" id="adForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <?php if ($isEditMode): ?>
                    <label>Proje</label>
                    <div class="project-name-display">
                        <i class="fa-solid fa-folder-open"></i>
                        <?= htmlspecialchars($editProjectTitle) ?>
                    </div>
                    <input type="hidden" name="project_id" value="<?= (int)$selectedProjectId ?>">
                <?php else: ?>
                    <label for="project_id">Proje *</label>
                    <select id="project_id" name="project_id" required>
                        <option value="">Proje Seçiniz</option>
                        <?php foreach ($myProjects as $p): ?>
                            <?php
                                $pid = (int)$p["id"];
                                $hasIlan = !empty($p["expires_at"]);
                            ?>
                            <option value="<?= $pid ?>" <?= ($selectedProjectId === $pid) ? "selected" : "" ?> <?= $hasIlan ? "disabled" : "" ?>>
                                <?= htmlspecialchars($p["title"]) . ($hasIlan ? "  (İlan Yayında)" : "") ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="action-note">İlanı hangi projeniz için yayınlamak istiyorsunuz?</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="ad_description">İlan Açıklaması *</label>
                <textarea 
                    id="ad_description" 
                    name="ad_description" 
                    rows="4" 
                    placeholder="Aradığınız takım arkadaşından beklentileriniz, çalışma stiliniz veya ilana özel detayları buraya yazınız." 
                    required><?= htmlspecialchars($prefillAdDesc) ?></textarea>
                <small class="action-note">Bu açıklama ana sayfadaki ilanda ve ilan detaylarında görüntülenecektir.</small>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Aranan Teknolojiler / Diller *</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars(is_array($prefillSkills) ? implode(", ", $prefillSkills) : $prefillSkills) ?>" placeholder="Örnek: PHP, Python, MySQL">
                    <small class="action-note">Aranan teknolojileri virgülle ayırarak yazınız.</small>
                </div>

                <div class="form-group">
                    <label for="members_needed">Aranan Kişi Sayısı *</label>
                    <input
                        type="number"
                        id="members_needed"
                        name="members_needed"
                        min="1"
                        max="20"
                        value="<?= (int)$prefillMembers ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="expires_at">İlan Bitiş Tarihi *</label>
                    <input
                        type="date"
                        id="expires_at"
                        name="expires_at"
                        value="<?= htmlspecialchars($prefillExpires) ?>"
                        min="<?= date('Y-m-d') ?>"
                        max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                        required>
                    <small class="action-note">İlan süresi dolduğunda otomatik olarak kapatılır.</small>
                </div>
            </div>

            <button type="submit" id="adSubmitBtn"><?= $isEditMode ? "İlanı Güncelle" : "İlanı Yayınla" ?></button>

        </form>

    <?php endif; ?>

</div>

<?php require_once "../includes/footer.php"; ?>