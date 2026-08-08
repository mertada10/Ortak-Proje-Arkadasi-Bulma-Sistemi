<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

try {
    $totalTeams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
} catch (Exception $e) {
    $totalTeams = 0; 
}

$latestUsers = $pdo->query("
    SELECT id, name, surname, username, department 
    FROM users 
    ORDER BY id DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TeamMate Finder Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

    <div class="admin-layout">
        
        <?php include "admin-sidebar.php"; ?>

        <main class="main-content">
            
            <div class="content-header">
                <h1>Yönetim Paneli Özeti</h1>
                <p>TeamMate Finder platformunun anlık durumu ve istatistikleri.</p>
            </div>

            <div class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($totalUsers) ?></h3>
                        <p>Toplam Öğrenci</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($totalProjects) ?></h3>
                        <p>Proje İlanı</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($totalTeams) ?></h3>
                        <p>Aktif Takım</p>
                    </div>
                </div>

            </div>

            <div class="content-section">
                
                <div class="section-header">
                    <h2><i class="fa-solid fa-clock-rotate-left"></i> Son Kayıt Olan Kullanıcılar</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>Kullanıcı Adı</th>
                                <th>Bölüm</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($latestUsers) > 0): ?>
                                <?php foreach ($latestUsers as $u): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($u["name"] . " " . $u["surname"]) ?></strong>
                                        </td>
                                        <td>@<?= htmlspecialchars($u["username"]) ?></td>
                                        <td><?= htmlspecialchars($u["department"] ?: "Belirtilmemiş") ?></td>
                                        <td>
                                            <a href="../pages/user-profile.php?id=<?= $u["id"] ?>" target="_blank" class="btn btn-view">
                                                <i class="fa-solid fa-user"></i> Profili Gör
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #9ca3af;">
                                        Henüz kayıtlı bir kullanıcı bulunmuyor.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </main>

    </div>

</body>
</html>