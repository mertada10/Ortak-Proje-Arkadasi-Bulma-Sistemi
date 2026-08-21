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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    csrf_guard();

    $team_name = trim($_POST["team_name"]);
    $description = trim($_POST["description"]);

    if ($team_name == "") {
        $error = "Takım adı zorunludur.";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO teams(owner_id, team_name, description)
            VALUES(?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $team_name,
            $description
        ]);

        $team_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO team_members(team_id, user_id, role)
            VALUES(?, ?, 'leader')
        ");
        $stmt->execute([
            $team_id,
            $user_id
        ]);

        header("Location: team.php");
        exit;
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="register-container">
    <div class="register-box">
        <h2>Takım Oluştur</h2>

        <?php if($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="createTeamForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="input-group">
                <label>Takım Adı</label>
                <input
                    type="text"
                    name="team_name"
                    placeholder="Takımınıza bir isim verin..."
                    value="<?= htmlspecialchars($_POST["team_name"] ?? "") ?>"
                    required
                >
            </div>

            <div class="input-group">
                <label>Takım Açıklaması</label>
                <textarea
                    name="description"
                    rows="6"
                    placeholder="Takımınız hakkında kısa bir açıklama..."
                ><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>
            </div>

            <button type="submit" class="register-btn">
                Takımı Oluştur
            </button>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>