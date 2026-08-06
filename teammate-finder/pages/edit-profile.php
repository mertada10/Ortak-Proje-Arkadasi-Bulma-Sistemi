<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

$error = "";
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

// Mevcut kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Prepare user's current skills/interests as arrays for pre-checking
$userSkills = array_filter(array_map('trim', explode(',', $user['skills'] ?? '')));
$userInterests = array_filter(array_map('trim', explode(',', $user['interests'] ?? '')));
// Determine which department should be selected (POST overrides stored value)
$selectedDepartment = $_POST['department'] ?? $user['department'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $surname = trim($_POST["surname"]);
    $department = trim($_POST["department"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $skills = isset($_POST["skills"]) ? implode(", ", $_POST["skills"]) : "";
    $interests = isset($_POST["interests"]) ? implode(", ", $_POST["interests"]) : "";
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

    <form method="POST">

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
                        "Yazılım Mühendisliği",
                        "Yapay Zeka Operatörlüğü",
                        "Bilgisayar Programcılığı",
                        "Yönetim Bilişim Sistemleri",
                        "Bilişim Sistemleri Mühendisliği",
                        "Elektrik-Elektronik Mühendisliği",
                        "Elektronik ve Haberleşme Mühendisliği",
                        "Mekatronik Mühendisliği",
                        "Endüstri Mühendisliği",
                        "Makine Mühendisliği",
                        "İnşaat Mühendisliği",
                        "Matematik",
                        "İstatistik",
                        "Fizik",
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

                <div class="custom-select">

                    <div class="select-box" id="skillsBtn">
                        <span id="skillsText">Teknoloji Seçiniz</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>

                    <div class="select-options" id="skillsMenu">

                        <label><input type="checkbox" name="skills[]" value="HTML" <?php if(in_array('HTML', $userSkills)) echo 'checked'; ?>> HTML</label>
                        <label><input type="checkbox" name="skills[]" value="CSS" <?php if(in_array('CSS', $userSkills)) echo 'checked'; ?>> CSS</label>
                        <label><input type="checkbox" name="skills[]" value="JavaScript" <?php if(in_array('JavaScript', $userSkills)) echo 'checked'; ?>> JavaScript</label>
                        <label><input type="checkbox" name="skills[]" value="PHP" <?php if(in_array('PHP', $userSkills)) echo 'checked'; ?>> PHP</label>
                        <label><input type="checkbox" name="skills[]" value="Python" <?php if(in_array('Python', $userSkills)) echo 'checked'; ?>> Python</label>
                        <label><input type="checkbox" name="skills[]" value="Java" <?php if(in_array('Java', $userSkills)) echo 'checked'; ?>> Java</label>
                        <label><input type="checkbox" name="skills[]" value="C#" <?php if(in_array('C#', $userSkills)) echo 'checked'; ?>> C#</label>
                        <label><input type="checkbox" name="skills[]" value="C++" <?php if(in_array('C++', $userSkills)) echo 'checked'; ?>> C++</label>
                        <label><input type="checkbox" name="skills[]" value="MySQL" <?php if(in_array('MySQL', $userSkills)) echo 'checked'; ?>> MySQL</label>
                        <label><input type="checkbox" name="skills[]" value="Flutter" <?php if(in_array('Flutter', $userSkills)) echo 'checked'; ?>> Flutter</label>
                        <label><input type="checkbox" name="skills[]" value="React" <?php if(in_array('React', $userSkills)) echo 'checked'; ?>> React</label>

                    </div>

                </div>
            </div>

           <div class="form-group">
                <label>İlgi Alanları</label>
                                
                <div class="custom-select">
                                
                    <div class="select-box" id="interestsBtn">
                        <span id="interestsText">İlgi Alanı Seçiniz</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                                
                    <div class="select-options" id="interestsMenu">
                                
                        <label><input type="checkbox" name="interests[]" value="Yapay Zeka" <?php if(in_array('Yapay Zeka', $userInterests)) echo 'checked'; ?>> Yapay Zeka</label>
                        <label><input type="checkbox" name="interests[]" value="Web Geliştirme" <?php if(in_array('Web Geliştirme', $userInterests)) echo 'checked'; ?>> Web Geliştirme</label>
                        <label><input type="checkbox" name="interests[]" value="Mobil Uygulama" <?php if(in_array('Mobil Uygulama', $userInterests)) echo 'checked'; ?>> Mobil Uygulama</label>
                        <label><input type="checkbox" name="interests[]" value="Oyun Geliştirme" <?php if(in_array('Oyun Geliştirme', $userInterests)) echo 'checked'; ?>> Oyun Geliştirme</label>
                        <label><input type="checkbox" name="interests[]" value="Veri Bilimi" <?php if(in_array('Veri Bilimi', $userInterests)) echo 'checked'; ?>> Veri Bilimi</label>
                        <label><input type="checkbox" name="interests[]" value="Siber Güvenlik" <?php if(in_array('Siber Güvenlik', $userInterests)) echo 'checked'; ?>> Siber Güvenlik</label>
                        <label><input type="checkbox" name="interests[]" value="Bulut Teknolojileri" <?php if(in_array('Bulut Teknolojileri', $userInterests)) echo 'checked'; ?>> Bulut Teknolojileri</label>
                        <label><input type="checkbox" name="interests[]" value="IoT" <?php if(in_array('IoT', $userInterests)) echo 'checked'; ?>> IoT</label>
                        <label><input type="checkbox" name="interests[]" value="Robotik" <?php if(in_array('Robotik', $userInterests)) echo 'checked'; ?>> Robotik</label>
                        <label><input type="checkbox" name="interests[]" value="UI/UX Tasarım" <?php if(in_array('UI/UX Tasarım', $userInterests)) echo 'checked'; ?>> UI/UX Tasarım</label>
                        <label><input type="checkbox" name="interests[]" value="Blockchain" <?php if(in_array('Blockchain', $userInterests)) echo 'checked'; ?>> Blockchain</label>
                                
                    </div>
                                
                </div>
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