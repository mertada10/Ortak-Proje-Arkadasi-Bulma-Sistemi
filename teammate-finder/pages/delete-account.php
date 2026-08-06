<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("
        SELECT password
        FROM users
        WHERE id=?
    ");

    $stmt->execute([$_SESSION["user_id"]]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user["password"])) {

        $error = "Şifreniz hatalı.";

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id=?
        ");

        $stmt->execute([$_SESSION["user_id"]]);

        session_destroy();

        header("Location: ../login.php?deleted=1");
        exit;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">

    <div class="register-box">

        <h2>Hesabı Sil</h2>

        <?php if($error): ?>
            <div class="alert error-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Dikkat!</strong><br><br>
            Hesabınızı sildiğinizde;

            <ul>
                <li>Profiliniz silinir.</li>
                <li>Projeleriniz silinir.</li>
                <li>Takım bilgileriniz silinir.</li>
                <li>Mesajlarınız silinir.</li>
                <li>Bu işlem geri alınamaz.</li>
            </ul>
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Şifrenizi Giriniz</label>

                <input
                    type="password"
                    name="password"
                    required>
            </div>

            <button
                type="submit"
                class="delete-account-btn"
                onclick="return confirm('Hesabınızı kalıcı olarak silmek istediğinize emin misiniz?');">

                Hesabımı Kalıcı Olarak Sil

            </button>

        </form>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>