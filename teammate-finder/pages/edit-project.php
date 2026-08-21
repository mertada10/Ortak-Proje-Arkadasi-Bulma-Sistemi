<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)$_SESSION["user_id"];
$error = "";
$project = null;

$project_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($project_id <= 0) {
    header("Location: projects.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, user_id, title, description FROM projects WHERE id = ? LIMIT 1");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || (int)$project["user_id"] !== $user_id) {
    $_SESSION["success"] = "Bu projeyi düzenleme yetkiniz yok.";
    header("Location: projects.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    csrf_guard();

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($title === "") {
        $error = "Proje başlığı zorunludur.";
    } elseif ($description === "") {
        $error = "Proje açıklaması zorunludur.";
    } else {
        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $description, $project_id, $user_id]);

        $_SESSION["success"] = "Proje başarıyla düzenlendi.";
        header("Location: projects.php");
        exit;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <div style="position: relative; margin-bottom: 30px;">
        <a href="projects.php" class="btn-back" style="position: absolute; left: 0; top: 50%; transform: translateY(-50%);">
            <i class="fa-solid fa-arrow-left"></i> Vazgeç
        </a>
        <h2 style="margin: 0;"><i class="fa-solid fa-pen"></i> Projeyi Düzenle</h2>
    </div>

    <?php if ($error != ""): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="editProjectForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">

        <div class="form-group">
            <label for="title">Proje Başlığı *</label>
            <input
                type="text"
                id="title"
                name="title"
                placeholder="Örn: Mobil E-Ticaret Uygulaması"
                value="<?= htmlspecialchars($_POST['title'] ?? $project['title'] ?? '') ?>"
                required>
        </div>

        <div class="form-group">
            <label for="description">Proje Açıklaması *</label>
            <textarea
                id="description"
                name="description"
                placeholder="Projenizin amacını, hedeflerini ve kapsamını kısaca açıklayınız."
                rows="6"
                required><?= htmlspecialchars($_POST['description'] ?? $project['description'] ?? '') ?></textarea>
            <small class="action-note">Sadece proje başlığını ve açıklamasını düzenleyebilirsiniz. İlan düzenlemek için ilan sayfasına gidin.</small>
        </div>

        <div style="display: flex; align-items: center; gap: 12px;">
            <button type="submit">Değişiklikleri Kaydet</button>
        </div>

    </form>

</div>

<?php require_once "../includes/footer.php"; ?>