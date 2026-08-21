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

$success = "";
$error = "";

$stmt = $pdo->prepare("
    SELECT password
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_guard();

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $currentPassword === "" ||
        $newPassword === "" ||
        $confirmPassword === ""
    ) {

        $error = "Lütfen tüm alanları doldurun.";

    } elseif (!password_verify($currentPassword, $user["password"])) {

        $error = "Mevcut şifreniz hatalı.";

    } elseif ($newPassword !== $confirmPassword) {

        $error = "Yeni şifreler eşleşmiyor.";

    } elseif (strlen($newPassword) < 6) {

        $error = "Yeni şifre en az 6 karakter olmalıdır.";

    } elseif (password_verify($newPassword, $user["password"])) {

        $error = "Yeni şifre mevcut şifre ile aynı olamaz.";

    } else {

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newHash,
            $user_id
        ]);

        $success = "Şifreniz başarıyla değiştirildi.";
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">

    <div class="register-container">

        <div class="register-box">

            <h2>Şifre Değiştir</h2>

            <?php if ($success): ?>
                <div class="success-message alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="input-group">
                    <label>Mevcut Şifre</label>

                    <div class="password-input">

                        <input
                            type="password"
                            id="currentPassword"
                            name="current_password"
                            placeholder="Mevcut şifrenizi girin"
                            required>

                        <i class="fa-solid fa-eye toggle-password"></i>

                    </div>
                </div>

                <div class="input-group">
                    <label>Yeni Şifre</label>

                    <div class="password-input">

                        <input
                            type="password"
                            id="newPassword"
                            name="new_password"
                            placeholder="Yeni şifrenizi girin"
                            required>

                        <i class="fa-solid fa-eye toggle-password"></i>

                    </div>
                </div>

                <div class="input-group">
                    <label>Yeni Şifre (Tekrar)</label>

                    <div class="password-input">

                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirm_password"
                            placeholder="Yeni şifrenizi tekrar girin"
                            required>

                        <i class="fa-solid fa-eye toggle-password"></i>

                    </div>
                </div>

                <div class="form-buttons">

                    <button type="submit" class="register-btn">
                        <i class="fa-solid fa-key"></i>
                        Şifreyi Değiştir
                    </button>
                            
                    <a href="user-profile.php" class="edit-btn back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Profile Geri Dön
                    </a>
                            
                </div>

            </form>

        </div>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>