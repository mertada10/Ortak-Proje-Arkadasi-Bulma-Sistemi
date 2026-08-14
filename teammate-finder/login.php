<?php
require_once __DIR__ . "/includes/session.php";
session_secure_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "config/database.php";
require_once "includes/auto-login.php";

if (isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] > 0) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login = trim($_POST["login"]);
    $password = $_POST["password"];
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $loginKey = mb_strtolower(mb_substr($login, 0, 191));

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            login_key VARCHAR(191) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_login_ip (login_key, ip)
        ) ENGINE=InnoDB
    ");
    $pdo->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");

    $MAX_ATTEMPTS  = 5;
    $LOCK_SECONDS  = 900;

    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM login_attempts WHERE login_key = ? AND ip = ? LIMIT 1");
    $stmt->execute([$loginKey, $ip]);
    $attemptRec = $stmt->fetch();
    $now = time();

    if ($attemptRec && !empty($attemptRec["locked_until"]) && strtotime($attemptRec["locked_until"]) > $now) {

        $error = "Çok fazla hatalı deneme yapıldı. Lütfen kısa bir süre sonra tekrar deneyin.";
    } else {

        $stmt = $pdo->prepare("
            SELECT id, password, profile_image, username, name, surname, role, is_active
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        $valid = $user && password_verify($password, $user["password"]);

        if (!$valid) {

            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (login_key, ip, attempts)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE attempts = attempts + 1
            ");
            $stmt->execute([$loginKey, $ip]);

            $stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE login_key = ? AND ip = ? LIMIT 1");
            $stmt->execute([$loginKey, $ip]);
            $newCount = (int)$stmt->fetchColumn();

            if ($newCount >= $MAX_ATTEMPTS) {
                $stmt = $pdo->prepare("UPDATE login_attempts SET locked_until = ? WHERE login_key = ? AND ip = ?");
                $stmt->execute([date("Y-m-d H:i:s", $now + $LOCK_SECONDS), $loginKey, $ip]);
                $error = "Çok fazla hatalı deneme yapıldı. Giriş geçici olarak kilitlendi.";
            } else {
                $error = "Kullanıcı adı veya şifre hatalı.";
            }
        }

        elseif (isset($user["is_active"]) && (int)$user["is_active"] !== 1) {
            $error = "Hesabınız devre dışı bırakılmış. Lütfen yöneticiyle iletişime geçin.";
        }

        else {

            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE login_key = ? AND ip = ?");
            $stmt->execute([$loginKey, $ip]);

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["profile_image"] = $user["profile_image"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["surname"] = $user["surname"];
            $_SESSION["role"] = $user["role"] ?? "user";

            if (isset($_POST["remember_me"])) {

                $token = bin2hex(random_bytes(32));

                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user["id"]]);

                setcookie("remember_me", $token, [
                    "expires"  => time() + (30 * 24 * 60 * 60),
                    "path"     => "/",
                    "httponly" => true,
                    "secure"   => is_https_request(),
                    "samesite" => "Lax"
                ]);

            } else {

                $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$user["id"]]);

                setcookie("remember_me", "", [
                    "expires"  => time() - 3600,
                    "path"     => "/",
                    "httponly" => true,
                    "secure"   => is_https_request(),
                    "samesite" => "Lax"
                ]);
            }

            header("Location: index.php");
            exit;
        }
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

        <div class="remember-me-row">
            <label class="remember-me-label">
                <input
                    type="checkbox"
                    name="remember_me"
                    value="1"
                    <?= isset($_POST["remember_me"]) ? "checked" : "" ?>>
                <span class="remember-me-checkbox"></span>
                Beni Hatırla
            </label>
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