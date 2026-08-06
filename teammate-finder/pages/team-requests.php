<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)$_SESSION["user_id"];
$teamRequestError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestId = (int)($_POST["request_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    if (in_array($status, ["accepted", "rejected"], true)) {
        $stmt = $pdo->prepare("\n            SELECT *\n            FROM team_requests\n            WHERE id = ?\n              AND receiver_id = ?\n              AND status = 'pending'\n        ");
        $stmt->execute([$requestId, $user_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            if ($status === "rejected") {
                $stmt = $pdo->prepare("UPDATE team_requests SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$requestId]);
            } else {
                    $sender = (int)$request["sender_id"];
                    $receiver = (int)$request["receiver_id"];

                    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
                    $stmt->execute([$sender]);
                    $senderTeam = $stmt->fetchColumn();

                    $stmt->execute([$receiver]);
                    $receiverTeam = $stmt->fetchColumn();

                    $pdo->beginTransaction();

                    try {
                        if (!$senderTeam && !$receiverTeam) {
                            $stmt = $pdo->prepare("INSERT INTO teams(owner_id, team_name) VALUES(?, ?)");
                            $stmt->execute([$receiver, "Yeni Takım"]);

                            $teamId = (int)$pdo->lastInsertId();

                            $stmt = $pdo->prepare("INSERT INTO team_members(team_id, user_id) VALUES(?, ?)");
                            $stmt->execute([$teamId, $sender]);
                            $stmt->execute([$teamId, $receiver]);
                        } elseif ($senderTeam && !$receiverTeam) {
                            $stmt = $pdo->prepare("INSERT INTO team_members(team_id, user_id) VALUES(?, ?)");
                            $stmt->execute([(int)$senderTeam, $receiver]);
                        } elseif (!$senderTeam && $receiverTeam) {
                            $stmt = $pdo->prepare("INSERT INTO team_members(team_id, user_id) VALUES(?, ?)");
                            $stmt->execute([(int)$receiverTeam, $sender]);
                        } else {
                            throw new Exception("İki kullanıcı da farklı takımlarda olduğu için istek kabul edilemez.");
                        }

                        $stmt = $pdo->prepare("UPDATE team_requests SET status = 'accepted' WHERE id = ?");
                        $stmt->execute([$requestId]);

                        $stmt = $pdo->prepare("\n                            UPDATE team_requests\n                            SET status = 'locked'\n                            WHERE receiver_id = ?\n                              AND status = 'pending'\n                              AND id <> ?\n                        ");
                        $stmt->execute([$user_id, $requestId]);

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $teamRequestError = "İstek kabul edilemedi.";
                    }
            }

            if (empty($teamRequestError)) {
                header("Location: team-requests.php");
                exit;
            }
        }
    }
}

$stmt = $pdo->prepare("\n    SELECT\n        team_requests.*,\n        users.name,\n        users.surname,\n        projects.title\n    FROM team_requests\n    JOIN users ON users.id = team_requests.sender_id\n    LEFT JOIN projects ON projects.id = team_requests.project_id\n    WHERE receiver_id = ?\n      AND status IN ('pending', 'locked')\n    ORDER BY created_at DESC\n");
$stmt->execute([$user_id]);
$incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("\n    SELECT\n        team_requests.*,\n        users.name,\n        users.surname,\n        projects.title\n    FROM team_requests\n    JOIN users ON users.id = team_requests.receiver_id\n    LEFT JOIN projects ON projects.id = team_requests.project_id\n    WHERE sender_id = ?\n      AND status = 'pending'\n    ORDER BY created_at DESC\n");
$stmt->execute([$user_id]);
$outgoing = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="main-container">
    <div class="requests-container">

        <?php if (!empty($teamRequestError)): ?>
            <div class="error-message alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($teamRequestError) ?></span>
            </div>
        <?php endif; ?>

        <h1>Ekip Kurma İstekleri</h1>

        <h2>Gelen İstekler</h2>

        <?php if (count($incoming)): ?>
            <?php foreach ($incoming as $request): ?>
                <div class="request-card">
                    <h3><?= htmlspecialchars($request["name"] . " " . $request["surname"]) ?></h3>
                    <p>
                        <strong>Davet:</strong>
                                
                        <?php if (!empty($request["project_id"])): ?>
                        
                            Proje üzerinden (<?= htmlspecialchars($request["title"]) ?>)
                        
                        <?php else: ?>
                        
                            Profil üzerinden
                        
                        <?php endif; ?>
                        
                    </p>
                    <p><strong>Durum:</strong> <?= ucfirst($request["status"]) ?></p>

                    <?php if ($request["status"] === "locked"): ?>
                        <div class="request-actions">
                            <button type="button" class="reject-btn disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                Kilitlendi
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="request-actions">
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= (int)$request["id"] ?>">
                                <input type="hidden" name="status" value="accepted">
                                <button type="submit" class="accept-btn">Kabul Et</button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?= (int)$request["id"] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="reject-btn">Reddet</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Henüz gelen isteğiniz yok.</p>
        <?php endif; ?>

        <h2 style="margin-top:40px;">Gönderilen İstekler</h2>

        <?php if (count($outgoing)): ?>
            <?php foreach ($outgoing as $request): ?>
                <div class="request-card">
                    <h3><?= htmlspecialchars($request["name"] . " " . $request["surname"]) ?></h3>
                    <p>
                        <strong>Davet:</strong>

                        <?php if (!empty($request["project_id"])): ?>
                        
                            Proje üzerinden (<?= htmlspecialchars($request["title"]) ?>)
                        
                        <?php else: ?>
                        
                            Profil üzerinden
                        
                        <?php endif; ?>
                        
                    </p>
                    <p><strong>Durum:</strong> <?= ucfirst($request["status"]) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Henüz gönderdiğiniz istek yok.</p>
        <?php endif; ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
