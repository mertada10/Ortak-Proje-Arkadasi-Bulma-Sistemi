<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login = trim($_POST["login"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1
    ");

    $stmt->execute([$login, $login]);

    $user = $stmt->fetch();

    if (!$user) {

        $error = "Kullanıcı bulunamadı.";

    } elseif (!password_verify($password, $user["password"])) {

        $error = "Şifre hatalı.";

    } else {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["profile_image"] = $user["profile_image"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["surname"] = $user["surname"];
        $_SESSION["role"] = $user["role"] ?? "user";

        header("Location: index.php");
        exit;
    }
}

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<div class="register-container login-container">

    <h2>Giriş Yap</h2>

    <?php if(isset($_GET["registered"])): ?>
        <div class="alert success-alert">
            <i class="fa-solid fa-circle-check"></i>
            Kayıt başarılı. Giriş yapabilirsiniz.
        </div>
    <?php endif; ?>

    <?php if(isset($_GET["deleted"])): ?>
        <div class="alert success-alert">
            <i class="fa-solid fa-circle-check"></i>
            Hesabınız başarıyla silindi.
        </div>
    <?php endif; ?>

    <?php if($error != ""): ?>
        <div class="alert error-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Kullanıcı Adı veya E-posta</label>
            <input
                type="text"
                name="login"
                value="<?= htmlspecialchars($_POST["login"] ?? "") ?>"
                required>
        </div>

        <div class="form-group">
            <label>Şifre</label>
            
            <div class="password-input">
            
                <input
                    type="password"
                    id="loginPassword"
                    name="password"
                    required
                    oninput="this.value=this.value.replace(/\s/g,'')">
            
                <i class="fa-solid fa-eye toggle-password"></i>
            
            </div>
        </div>

        <button type="submit">
            Giriş Yap
        </button>

    </form>

    <div class="login-link">
        Hesabın yok mu?
        <a href="register.php">Kayıt Ol</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>