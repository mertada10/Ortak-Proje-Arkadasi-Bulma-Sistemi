<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

$stmt = $pdo->prepare("
SELECT team_id
FROM team_members
WHERE user_id=?
LIMIT 1
");
$stmt->execute([$user_id]);

if ($stmt->fetch()) {
    header("Location: team.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $team_name = trim($_POST["team_name"]);
    $description = trim($_POST["description"]);

    if ($team_name == "") {

        $error = "Takım adı zorunludur.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id 
            FROM teams 
            WHERE team_name = ?
            LIMIT 1
        ");

        $stmt->execute([
            $team_name
        ]);

        if ($stmt->fetch()) {

            $error = "Bu isimde bir takım zaten mevcut.";

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO teams(owner_id,team_name,description)
                VALUES(?,?,?)
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

        <form method="POST">

            <div class="input-group">
                <label>Takım Adı</label>
                <input
                    type="text"
                    name="team_name"
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