<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $required_skills = isset($_POST["skills"]) && is_array($_POST["skills"])
    ? implode(", ", $_POST["skills"])
    : "";
    $members_needed = (int)($_POST["members_needed"] ?? 1);
    $user_id = $_SESSION["user_id"];

    if (empty($title) || empty($description) || empty($required_skills)) {
        $error = "Lütfen tüm zorunlu alanları doldurunuz!";
    } 

    elseif ($members_needed < 1 || $members_needed > 20) {
        $error = "Aranan kişi sayısı 1 ile 20 arasında olmalıdır!";
    } 
    else {

        $stmt = $pdo->prepare("
            INSERT INTO projects (user_id, title, description, required_skills, members_needed)
            VALUES (?, ?, ?, ?, ?)
        ");

        $inserted = $stmt->execute([
            $user_id,
            $title,
            $description,
            $required_skills,
            $members_needed
        ]);

        if ($inserted) {
            $_SESSION["success"] = "Proje ilanı başarıyla oluşturuldu.";
            header("Location: ../index.php");
            exit;
        } else {
            $error = "İlan oluşturulurken bir hata oluştu. Lütfen tekrar deneyiniz.";
        }
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <h2>Proje İlanı Oluştur</h2>

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

        <div class="row">
            <div class="form-group">
                <label>Aranan Teknolojiler / Diller *</label>

                <div class="custom-select">

                    <div class="select-box" id="skillsBtn">
                        <span id="skillsText">Teknoloji Seçiniz</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>

                    <div class="select-options" id="skillsMenu">

                        <label><input type="checkbox" name="skills[]" value="HTML"> HTML</label>
                        <label><input type="checkbox" name="skills[]" value="CSS"> CSS</label>
                        <label><input type="checkbox" name="skills[]" value="JavaScript"> JavaScript</label>
                        <label><input type="checkbox" name="skills[]" value="PHP"> PHP</label>
                        <label><input type="checkbox" name="skills[]" value="Python"> Python</label>
                        <label><input type="checkbox" name="skills[]" value="Java"> Java</label>
                        <label><input type="checkbox" name="skills[]" value="C#"> C#</label>
                        <label><input type="checkbox" name="skills[]" value="C++"> C++</label>
                        <label><input type="checkbox" name="skills[]" value="MySQL"> MySQL</label>
                        <label><input type="checkbox" name="skills[]" value="Flutter"> Flutter</label>
                        <label><input type="checkbox" name="skills[]" value="React"> React</label>

                    </div>

                </div>
            </div>

            <div class="form-group">
                <label for="members_needed">Aranan Kişi Sayısı *</label>
                <input 
                    type="number"
                    id="members_needed"
                    name="members_needed"
                    min="1"
                    max="20"
                    value="<?= htmlspecialchars($_POST['members_needed'] ?? '1') ?>"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Proje Açıklaması ve Detaylar *</label>
            <textarea
                id="description"
                name="description"
                placeholder="Projenizin amacını, hedeflerini ve takım arkadaşında aradığınız nitelikleri detaylıca açıklayınız."
                rows="6"
                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <button type="submit">İlanı Yayınla</button>

    </form>

</div>

<?php require_once "../includes/footer.php"; ?>