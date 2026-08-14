<?php
require_once __DIR__ . "/includes/session.php";
session_secure_start();
require_once "config/database.php";
require_once "includes/auto-login.php";

if (isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] > 0) {
    header("Location: index.php");
    exit;
}

$error = "";

function splitTagsUi($input) {
    $input = trim($input ?? "");
    $input = preg_replace('/[\/;|]+/', ',', $input);
    $input = str_replace('-', ',', $input);
    return array_values(array_filter(array_map('trim', explode(',', $input))));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $surname = trim($_POST["surname"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $password_confirm = $_POST["password_confirm"] ?? "";
    
    $skills = implode(", ", splitTagsUi($_POST["skills"] ?? ""));
    $interests = implode(", ", splitTagsUi($_POST["interests"] ?? ""));

    $about = trim($_POST["about"] ?? "");

    if (empty($name) || empty($surname) || empty($username) || empty($department) || empty($email) || empty($password)) {
        $error = "Lütfen zorunlu alanları sadece boşluk bırakmadan doldurunuz!";
    }

    elseif (strlen($password) < 6) {
        $error = "Şifre en az 6 karakter olmalıdır!";
    }

    elseif ($password !== $password_confirm) {
        $error = "Şifreler uyuşmuyor!";
    } 

    elseif (preg_match('/\s/', $username)) {
        $error = "Kullanıcı adı boşluk içeremez!";
    }
    
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Kullanıcı adı sadece harf, rakam ve alt çizgi (_) içerebilir!";
    }
    else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);

        if ($check->rowCount() > 0) {
            $error = "Bu kullanıcı adı veya e-posta zaten kayıtlı!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users
            (name, surname, username, department, email, phone, password, skills, interests, about)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $surname,
                $username,
                $department,
                $email,
                $phone,
                $hashedPassword,
                $skills,
                $interests,
                $about
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<div class="register-container">

    <h2>Kayıt Ol</h2>

    <?php if ($error != ""): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">

        <div class="row">
            <div class="form-group">
                <label for="name">Ad *</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Adınızı giriniz"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    pattern="^[A-Za-zÇĞİIÖŞÜçğıöşü]+$"
                    title="Bu alana sadece harf girebilirsiniz."
                    required>
            </div>

            <div class="form-group">
                <label for="surname">Soyad *</label>
                <input
                    type="text"
                    id="surname"
                    name="surname"
                    placeholder="Soyadınızı giriniz"
                    value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>"
                    pattern="^[A-Za-zÇĞİIÖŞÜçğıöşü]+$"
                    title="Bu alana sadece harf girebilirsiniz."
                    required>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label for="username">Kullanıcı Adı *</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Kullanıcı adınızı giriniz"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    pattern="^[a-zA-Z0-9_]+$"
                    title="Kullanıcı adı boşluk içeremez. Sadece harf, rakam ve alt çizgi (_) kullanabilirsiniz."
                    required>
            </div>

            <div class="form-group">
                <label for="department">Bölüm *</label>
                
                <select id="department" name="department" required>
                    <option value="">Bölüm Seçiniz</option>
                
                    <?php
                    $departmentss = [
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
                            <?= (($_POST["department"] ?? "") == $dep) ? "selected" : "" ?>>
                            <?= $dep ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label for="email">E-posta *</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="ornek@eposta.com"
                    required
                    oninput="this.value=this.value.replace(/\s/g,'')">
            </div>

            <div class="form-group">
                <label for="phone">Telefon</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    pattern="[0-9]{10,11}"
                    inputmode="numeric"
                    maxlength="11"
                    placeholder="05XXXXXXXXX"
                    oninput="this.value=this.value.replace(/\s/g,'')">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label for="password">Şifre *</label>

                <div class="password-input">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Şifrenizi giriniz"
                        required
                        minlength="6"
                        onkeydown="if(event.key === ' ') return false;">

                    <i class="fa-solid fa-eye toggle-password"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Şifre Tekrar *</label>
                                
                <div class="password-input">
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        placeholder="Şifrenizi tekrar giriniz"
                        required
                        minlength="6">
                                
                    <i class="fa-solid fa-eye toggle-password"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Bildiği Teknolojiler</label>

                <input type="text" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? '') ?>" placeholder="Örnek: PHP, JavaScript, MySQL">
                <small class="action-note">Teknolojileri virgülle ayırarak yazınız.</small>
            </div>

           <div class="form-group">
                <label>İlgi Alanları</label>
                <input type="text" name="interests" value="<?= htmlspecialchars($_POST['interests'] ?? '') ?>" placeholder="Örnek: Yapay Zeka, Web Geliştirme">
                <small class="action-note">İlgi alanlarını virgülle ayırarak yazınız.</small>
            </div>
        </div>

        <div class="form-group">
            <label for="about">Hakkımda</label>
            <textarea
                id="about"
                name="about"
                placeholder="Kendiniz hakkında kısa bir bilgi yazabilirsiniz."
                rows="5"><?= htmlspecialchars($_POST['about'] ?? '') ?></textarea>
        </div>

        <button type="submit">Kayıt Ol</button>

    </form>

</div>

<?php require_once "includes/footer.php"; ?>