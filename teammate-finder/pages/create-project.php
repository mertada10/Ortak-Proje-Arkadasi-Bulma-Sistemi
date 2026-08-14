<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($title === "") {
        $error = "Proje başlığı zorunludur.";
    } elseif ($description === "") {
        $error = "Proje açıklaması zorunludur.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO projects (user_id, title, description, required_skills)
            VALUES (?, ?, ?, '')
        ");
        $stmt->execute([
            $_SESSION["user_id"],
            $title,
            $description
        ]);

        $_SESSION["success"] = "Proje başarıyla oluşturuldu.";
        header("Location: projects.php");
        exit;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <h2><i class="fa-solid fa-folder-open"></i> Proje Oluştur</h2>

    <?php if ($error != ""): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="title">Proje Başlığı *</label>
            <input
                type="text"
                id="title"
                name="title"
                placeholder="Örn: Mobil E-Ticaret Uygulaması"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                required>
        </div>

        <div class="form-group">
            <label for="description">Proje Açıklaması *</label>
            <textarea
                id="description"
                name="description"
                placeholder="Projenizin amacını, hedeflerini ve kapsamını kısaca açıklayınız."
                rows="6"
                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            <small class="action-note">Oluşturduğunuz proje "Projelerim" sayfanızda listelenir. İlan verebilmek için ilan oluştur bölümünden bu projeyi seçeceksiniz.</small>
        </div>

        <button type="submit">Projeyi Oluştur</button>

    </form>

</div>

<?php require_once "../includes/footer.php"; ?>