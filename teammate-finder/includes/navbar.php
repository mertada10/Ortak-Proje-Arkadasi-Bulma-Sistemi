<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";

if (isset($_SESSION["user_id"])) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'last_seen'");
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL");
    }

    $stmt = $pdo->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
}

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM messages
WHERE receiver_id = ?
AND is_read = 0
");

$stmt->execute([$_SESSION["user_id"] ?? 0]);

$unreadCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM requests  
WHERE receiver_id = ?
AND status = 'pending'
");

$stmt->execute([$_SESSION["user_id"] ?? 0]);

$teamRequestCount = $stmt->fetchColumn();
?>

<nav class="navbar">

    <a href="/teammate-finder/index.php" class="logo">
        <i class="fa-solid fa-users"></i>
        TeamMate Finder
    </a>

    <button class="menu-toggle" id="menuToggle">
        <i class="fa-solid fa-bars"></i>
    </button>

    <ul id="navbarMenu">
    <?php if(isset($_SESSION["user_id"])): ?>

        <li>
            <button id="themeToggle" class="theme-toggle-btn" type="button">
                <i class="fa-solid fa-moon"></i>
            </button>
        </li>

        <li><a href="/teammate-finder/index.php">Ana Sayfa</a></li>

        <li>
            <a href="/teammate-finder/pages/projects.php">
                <i class="fa-solid fa-folder-open"></i>&nbsp;Projelerim
            </a>
        </li>
        
        <li>
            <a href="/teammate-finder/pages/team.php">
                <i class="fa-solid fa-user-group"></i>&nbsp;Takımlarım
            </a>
        </li>

        <li>
            <a href="/teammate-finder/pages/find-teammates.php">
                <i class="fa-solid fa-magnifying-glass"></i>&nbsp;Takım Arkadaşı Bul
            </a>
        </li>

        <li>
            <a href="/teammate-finder/pages/post-ad.php" class="btn-create-project">
                <i class="fa-solid fa-plus"></i>  İlan Oluştur
            </a>
        </li>

        <li class="dropdown">

            <a href="#" id="profileBtn">

                <?php if(!empty($_SESSION["profile_image"])): ?>
                
                <img src="/teammate-finder/assets/uploads/<?= htmlspecialchars($_SESSION["profile_image"]) ?>"
                     class="profile-avatar">

                <?php else: ?>
                
                <span class="profile-avatar-default">
                    <?= strtoupper(substr($_SESSION["name"],0,1)) ?>
                </span>
                
                <?php endif; ?>
                
                <span class="profile-name">
                    <?= htmlspecialchars($_SESSION["name"] . " " . $_SESSION["surname"]) ?>
                </span>
                
                <?php $notificationCount = (int)$unreadCount + (int)$teamRequestCount; ?>
                <?php if ($notificationCount > 0): ?>
                    <span class="profile-notification-badge"><?= $notificationCount ?></span>
                <?php endif; ?>

                <i class="fa-solid fa-chevron-down"></i>
                
            </a>

            <ul class="dropdown-menu" id="profileMenu">
                <li><a href="/teammate-finder/pages/user-profile.php">Profil</a></li>
                <li>
                    <a href="/teammate-finder/pages/messages.php">
                        Mesajlar
                                
                        <?php if($unreadCount>0): ?>
                        
                            <span class="notification-badge">
                                <?= $unreadCount ?>
                            </span>
                        
                        <?php endif; ?>
                        
                    </a>
                </li>
                <li>
                    <a href="/teammate-finder/pages/requests.php">
                        Gelen İstekler
                                        
                        <?php if($teamRequestCount > 0): ?>
                            <span class="notification-badge">
                                <?= $teamRequestCount ?>
                            </span>
                        <?php endif; ?>
                        
                    </a>
                </li>

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li>
                        <a href="/teammate-finder/admin/dashboard.php" style="color: #ff0000; font-weight: bold;">
                            Admin Paneli
                        </a>
                    </li>
                <?php endif; ?>

                <li><a href="/teammate-finder/logout.php">Çıkış Yap</a></li>
            </ul>

        </li>

    <?php else: ?>

        <li>
            <button id="themeToggle" class="theme-toggle-btn" type="button">
                <i class="fa-solid fa-moon"></i>
            </button>
        </li>
            
        <li><a href="/teammate-finder/index.php">Ana Sayfa</a></li>
        <li><a href="/teammate-finder/login.php">Giriş Yap</a></li>
        <li><a href="/teammate-finder/register.php">Kayıt Ol</a></li>

    <?php endif; ?>

    </ul>

</nav>