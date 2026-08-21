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
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

$stmt = $pdo->prepare("SELECT id, name, surname, username, department, email, phone, skills, interests, about, profile_image FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$selectedDepartment = $_POST['department'] ?? $user['department'] ?? '';

function splitTagsUi($input) {
    $input = trim($input ?? "");
    $input = preg_replace('/[\/;|]+/', ',', $input);
    $input = str_replace('-', ',', $input);
    return array_values(array_filter(array_map('trim', explode(',', $input))));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    csrf_guard();

    $name = trim($_POST["name"]);
    $surname = trim($_POST["surname"]);
    $department = trim($_POST["department"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $skills = implode(", ", splitTagsUi($_POST["skills"] ?? ""));
    $interests = implode(", ", splitTagsUi($_POST["interests"] ?? ""));
    $about = trim($_POST["about"]);

    $update = $pdo->prepare("
        UPDATE users SET
        name = ?,
        surname = ?,
        department = ?,
        email = ?,
        phone = ?,
        skills = ?,
        interests = ?,
        about = ?
        WHERE id = ?
    ");

    $update->execute([
        $name,
        $surname,
        $department,
        $email,
        $phone,
        $skills,
        $interests,
        $about,
        $user_id
    ]);

    $_SESSION["name"] = $name;
    $_SESSION["surname"] = $surname;
    $_SESSION["success"] = "Profil başarıyla güncellendi.";

    header("Location: user-profile.php");
    exit;
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <h2>Profili Düzenle</h2>

    <?php if ($success != ""): ?>
        <div class="alert success-alert">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="editProfileForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="row">
            <div class="form-group">
                <label>Ad</label>
                <input 
                    type="text"
                    name="name"
                    value="<?= htmlspecialchars($user["name"]) ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Soyad</label>
                <input 
                    type="text"
                    name="surname"
                    value="<?= htmlspecialchars($user["surname"]) ?>"
                    required>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input 
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($user["username"]) ?>"
                    disabled>
            </div>

            <div class="form-group">
                <label for="department">Bölüm *</label>
                
                <select id="department" name="department" required>
                    <option value="">Bölüm Seçiniz</option>
                
                    <?php
                    $departments = [
                        "Bilgisayar Mühendisliği",
                        "Çevre Mühendisliği",
                        "Elektrik-Elektronik Mühendisliği",
                        "Endüstri Mühendisliği",
                        "Gıda Mühendisliği",
                        "İnşaat Mühendisliği",
                        "Makine Mühendisliği",
                        "Biyoloji",
                        "Coğrafya",
                        "Fizik",
                        "Kimya",
                        "Matematik",
                        "Moleküler Biyoloji ve Genetik",
                        "Psikoloji",
                        "Sosyoloji",
                        "Tarih",
                        "Türk Dili ve Edebiyatı",
                        "İktisat",
                        "İşletme",
                        "Maliye",
                        "Siyaset Bilimi ve Kamu Yönetimi",
                        "Uluslararası Ticaret ve Lojistik",
                        "Biyoloji Öğretmenliği",
                        "Fen Bilgisi Öğretmenliği",
                        "İlköğretim Matematik Öğretmenliği",
                        "İngilizce Öğretmenliği",
                        "Kimya Öğretmenliği",
                        "Matematik Öğretmenliği",
                        "Okul Öncesi Öğretmenliği",
                        "Rehberlik ve Psikolojik Danışmanlık",
                        "Sınıf Öğretmenliği",
                        "Sosyal Bilgiler Öğretmenliği",
                        "Türkçe Öğretmenliği",
                        "Gastronomi ve Mutfak Sanatları",
                        "Rekreasyon Yönetimi",
                        "Turizm İşletmeciliği",
                        "Turizm Rehberliği",
                        "Bankacılık ve Finans",
                        "Uluslararası Ticaret",
                        "Mimarlık",
                        "Hukuk",
                        "İlahiyat",
                        "Tıp",
                        "Veteriner",
                        "Sağlık Bilimleri (Ebelik / Hemşirelik)",
                        "Güzel Sanatlar (Resim, Grafik, Baskı Sanatları)",
                        "Diğer"
                    ];
                
                    foreach ($departments as $dep):
                    ?>
                        <option
                            value="<?= $dep ?>"
                            <?= ($selectedDepartment == $dep) ? "selected" : "" ?>>
                            <?= $dep ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>E-posta</label>
                <input 
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($user["email"]) ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Telefon</label>
                <input 
                    type="tel"
                    name="phone"
                    value="<?= htmlspecialchars($user["phone"]) ?>">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Bildiği Teknolojiler</label>

                <input type="text" name="skills" value="<?= htmlspecialchars($user['skills'] ?? '') ?>" placeholder="Örnek: PHP, JavaScript, MySQL">
                <small class="action-note">Teknolojileri virgülle ayırarak yazınız.</small>
            </div>

           <div class="form-group">
                <label>İlgi Alanları</label>
                <input type="text" name="interests" value="<?= htmlspecialchars($user['interests'] ?? '') ?>" placeholder="Örnek: Yapay Zeka, Web Geliştirme">
                <small class="action-note">İlgi alanlarını virgülle ayırarak yazınız.</small>
            </div>
        </div>

        <div class="form-group">
            <label>Hakkımda</label>
            <textarea
                name="about"
                rows="5"><?= htmlspecialchars($user["about"]) ?></textarea>
        </div>

        <button type="submit">Kaydet</button>

    </form>
</div>

<?php require_once "../includes/footer.php"; ?>