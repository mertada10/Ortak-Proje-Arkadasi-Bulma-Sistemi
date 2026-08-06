<?php
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $surname = trim($_POST["surname"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $password_confirm = $_POST["password_confirm"] ?? "";
    
    $skills = isset($_POST["skills"])
        ? implode(", ", $_POST["skills"])
        : "";

    $interests = isset($_POST["interests"])

    ? implode(", ", $_POST["interests"])
        : "";

    $about = trim($_POST["about"] ?? "");

    if (empty($name) || empty($surname) || empty($username) || empty($department) || empty($email) || empty($password)) {
        $error = "Lütfen zorunlu alanları sadece boşluk bırakmadan doldurunuz!";
    }

    elseif (strlen($password) < 8) {
        $error = "Şifre en az 8 karakter olmalıdır!";
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
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Şifrenizi giriniz"
                    required
                    minlength="8"
                    onkeydown="if(event.key === ' ') return false;">
            </div>

            <div class="form-group">
                <label for="password_confirm">Şifre Tekrar *</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    placeholder="Şifrenizi tekrar giriniz"
                    required
                    minlength="8">
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
                <label>İlgi Alanları</label>
                                
                <div class="custom-select">
                                
                    <div class="select-box" id="interestsBtn">
                        <span id="interestsText">İlgi Alanı Seçiniz</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                                
                    <div class="select-options" id="interestsMenu">
                                
                        <label><input type="checkbox" name="interests[]" value="Yapay Zeka"> Yapay Zeka</label>
                        <label><input type="checkbox" name="interests[]" value="Web Geliştirme"> Web Geliştirme</label>
                        <label><input type="checkbox" name="interests[]" value="Mobil Uygulama"> Mobil Uygulama</label>
                        <label><input type="checkbox" name="interests[]" value="Oyun Geliştirme"> Oyun Geliştirme</label>
                        <label><input type="checkbox" name="interests[]" value="Veri Bilimi"> Veri Bilimi</label>
                        <label><input type="checkbox" name="interests[]" value="Siber Güvenlik"> Siber Güvenlik</label>
                        <label><input type="checkbox" name="interests[]" value="Bulut Teknolojileri"> Bulut Teknolojileri</label>
                        <label><input type="checkbox" name="interests[]" value="IoT"> IoT</label>
                        <label><input type="checkbox" name="interests[]" value="Robotik"> Robotik</label>
                        <label><input type="checkbox" name="interests[]" value="UI/UX Tasarım"> UI/UX Tasarım</label>
                        <label><input type="checkbox" name="interests[]" value="Blockchain"> Blockchain</label>
                                
                    </div>
                                
                </div>
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