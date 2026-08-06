<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$requested_user_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$viewing_own_profile = ($requested_user_id <= 0 || $requested_user_id === (int)$_SESSION["user_id"]);

$user_id = $viewing_own_profile ? (int)$_SESSION["user_id"] : $requested_user_id;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../index.php");
    exit;
}

$current_user_team = null;
$target_user_team = null;

if (!$viewing_own_profile) {
    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"]]);
    $current_user_team = $stmt->fetchColumn();

    if ($current_user_team) {
        $stmt = $pdo->prepare("SELECT owner_id FROM teams WHERE id = ? LIMIT 1");
        $stmt->execute([$current_user_team]);
        $current_team_owner = $stmt->fetchColumn();
        $is_team_leader = ($current_team_owner == $_SESSION["user_id"]);
    } else {
        $is_team_leader = false;
    }

    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $target_user_team = $stmt->fetchColumn();

    $stmt = $pdo->prepare("\n    SELECT id\n    FROM team_requests\n    WHERE status='pending'\n      AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))\n    LIMIT 1\n    ");
    $stmt->execute([
        (int)$_SESSION["user_id"],
        $user_id,
        $user_id,
        (int)$_SESSION["user_id"]
    ]);
    $has_pending_request = (bool)$stmt->fetchColumn();
} else {
    $has_pending_request = false;
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<?php

$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

?>

<?php if($success): ?>

<div class="success-message alert">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($success) ?></span>
</div>

<?php endif; ?>

<?php
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);
?>

<div class="profile-container">

    <div class="profile-card">

        <div class="profile-sidebar">

            <div class="avatar-wrapper">

                <?php if (!empty($user["profile_image"])): ?>

                    <img src="../assets/uploads/<?= htmlspecialchars($user["profile_image"]) ?>" class="profile-avatar-lg">

                <?php else: ?>

                    <div class="profile-avatar-placeholder">
                        <?= strtoupper(mb_substr($user["name"],0,1).mb_substr($user["surname"],0,1)) ?>
                    </div>

                <?php endif; ?>

            </div>

            <h2 style="margin-top:15px;text-align:center;">
                <?= htmlspecialchars($user["name"]." ".$user["surname"]) ?>
            </h2>

            <p style="color:#b3b9c4;text-align:center;">
                <?= htmlspecialchars($user["department"]) ?>
            </p>

            <?php if ($viewing_own_profile): ?>
                <form
                    id="avatar-form"
                    action="upload-avatar.php"
                    method="POST"
                    enctype="multipart/form-data">

                    <input
                        type="file"
                        name="avatar"
                        id="avatar-input"
                        accept="image/*"
                        style="display:none;"
                        onchange="this.form.submit();">

                </form>

                <div class="profile-action-buttons">

                    <button type="button" class="change-photo-btn" onclick="document.getElementById('avatar-input').click();">
                        <i class="fa-solid fa-camera"></i>
                        Fotoğrafı Değiştir
                    </button>

                    <a href="edit-profile.php" class="edit-btn">
                        <i class="fa-solid fa-pen"></i>
                        Profili Düzenle
                    </a>

                    <a href="change-password.php" class="edit-btn">
                        <i class="fa-solid fa-key"></i>
                        Şifre Değiştir
                    </a>

                    <a href="delete-account.php" class="delete-btn">
                        <i class="fa-solid fa-trash"></i>
                        Hesabı Sil
                    </a>

                </div>
            <?php endif; ?>

        </div>

        <div class="profile-details">

            <?php if ($viewing_own_profile): ?>

                <?php if ($success): ?>
                    <div class="success-message alert">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
                
                <h2>Profilim</h2>
                
            <?php else: ?>
                <?php if (isset($_GET["success"])): ?>
                    <div class="success-message alert">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>
                            <?php if(isset($_GET["sent"]) && $_GET["sent"] === "invite"): ?>
                                Takıma davet isteğiniz gönderildi.
                            <?php elseif(isset($_GET["sent"]) && $_GET["sent"] === "join"): ?>
                                Takıma katılma isteğiniz gönderildi.
                            <?php else: ?>
                                Ekip kurma isteğiniz gönderildi.
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["already"])): ?>
                    <div class="error-message">
                        Bu kullanıcıya zaten ekip kurma isteği gönderdiniz.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["alreadyteam"])): ?>
                    <div class="error-message">
                        Bu kullanıcıyla zaten takım ilişkisi bulunduğu için yeni istek gönderilemez.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET["alreadypending"])): ?>
                    <div class="error-message">
                        Bu kullanıcıyla zaten bekleyen bir istek var.
                    </div>
                <?php endif; ?>

                <h2>Kullanıcı Profili</h2>
            <?php endif; ?>

            <div class="profile-row">
                <span>Bildiği Teknolojiler</span>
                <strong><?= htmlspecialchars($user["skills"]) ?></strong>
            </div>

            <div class="profile-row">
                <span>İlgi Alanları</span>
                <strong><?= htmlspecialchars($user["interests"]) ?></strong>
            </div>

            <div class="profile-row profile-about">
                <span>Hakkımda</span>
                <p><?= nl2br(htmlspecialchars($user["about"])) ?></p>
            </div>

            <?php if (!$viewing_own_profile): ?>
                <?php if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] != $user_id): ?>
                    <div class="profile-actions">
                        <a href="chat.php?id=<?= $user["id"] ?>" class="edit-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                            Mesaj Gönder
                        </a>

                        <?php if ($has_pending_request): ?>
                            <button class="edit-btn disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                <i class="fa-solid fa-clock"></i>
                                İstek Beklemede
                            </button>
                            <small class="button-warning">
                                Bu kullanıcıyla bekleyen bir ekip isteği var.
                            </small>
                        <?php elseif ($current_user_team && !$target_user_team): ?>
                            <?php if ($is_team_leader): ?>
                                <a href="/teammate-finder/pages/send-team-request.php?user=<?= $user["id"] ?>" class="edit-btn">
                                    <i class="fa-solid fa-user-plus"></i>
                                    Takıma Davet Et
                                </a>
                            <?php else: ?>
                                <button class="edit-btn disabled-btn" disabled>
                                    <i class="fa-solid fa-lock"></i>
                                    <i class="fa-solid fa-user-plus"></i>
                                    Takıma Davet Et
                                </button>
                                <small class="button-warning">
                                    Takıma üye ekleme yetkiniz bulunmuyor.
                                </small>
                            <?php endif; ?>
                        <?php elseif (!$current_user_team && $target_user_team): ?>
                            <a href="/teammate-finder/pages/send-team-request.php?user=<?= $user["id"] ?>" class="edit-btn">
                                <i class="fa-solid fa-people-group"></i>
                                Takıma Katılma İsteği Yolla
                            </a>
                        <?php elseif (!$current_user_team && !$target_user_team): ?>
                            <button class="edit-btn disabled-btn" disabled>
                                <i class="fa-solid fa-lock"></i>
                                <i class="fa-solid fa-users"></i>
                                Takım Kurma İsteği Yolla
                            </button>
                            <small class="button-warning">
                                Önce bir takım oluşturmanız gerekiyor.
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="profile-actions" style="margin-top:14px;">
                <a href="javascript:history.back()" class="edit-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>

        </div>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>