<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET["action"]) && $_GET["action"] === "delete" && isset($_GET["id"])) {
    $deleteId = (int)$_GET["id"];
    
    $stmtDelete = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmtDelete->execute([$deleteId]);
    
    header("Location: projects.php");
    exit;
}

$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.description, p.created_at, u.username, u.name, u.surname 
        FROM projects p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.title LIKE ? OR p.description LIKE ? OR u.username LIKE ?
        ORDER BY p.id DESC
    ");
    $searchTerm = "%{$search}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.description, p.created_at, u.username, u.name, u.surname 
        FROM projects p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.id DESC
    ");
    $stmt->execute();
}

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proje İlanları Yönetimi - TeamMate Finder Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

    <div class="admin-layout">
        
        <?php include "admin-sidebar.php"; ?>

        <main class="main-content">
            
            <div class="content-header">
                <h1>Proje İlanları Yönetimi</h1>
                <p>Platform üzerinde öğrenciler tarafından açılmış tüm proje takımı arama ilanları.</p>
            </div>

            <div class="filter-section">
                <form action="projects.php" method="GET" class="search-form">
                    <div class="search-input-group">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Proje başlığı, açıklaması veya ilan sahibi ile ara..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-search">Ara</button>
                    <?php if ($search !== ""): ?>
                        <a href="projects.php" class="btn btn-reset">Sıfırla</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="content-section">
                
                <div class="section-header">
                    <h2><i class="fa-solid fa-folder-open"></i> Toplam İlan (<?= count($projects) ?>)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Proje Başlığı</th>
                                <th>İlan Sahibi</th>
                                <th>Oluşturulma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($projects) > 0): ?>
                                <?php foreach ($projects as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="project-title-cell">
                                                <strong><?= htmlspecialchars($p["title"]) ?></strong>
                                                <small><?= htmlspecialchars(mb_strimwidth($p["description"], 0, 60, "...")) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($p["name"] . " " . $p["surname"]) ?></strong>
                                            <br><small style="color: #9ca3af;">@<?= htmlspecialchars($p["username"]) ?></small>
                                        </td>
                                        <td><?= date("d.m.Y H:i", strtotime($p["created_at"])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../pages/project-detail.php?id=<?= $p["id"] ?>" target="_blank" class="btn btn-view" title="İlanı Gör">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="projects.php?action=delete&id=<?= $p["id"] ?>" class="btn btn-delete" onclick="return confirm('Bu proje ilanını silmek istediğinize emin misiniz?')" title="İlanı Sil">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #9ca3af; padding: 30px;">
                                        Aramanıza uygun proje ilanı bulunamadı.
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