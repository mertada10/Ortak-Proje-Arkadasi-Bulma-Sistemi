<?php
require_once __DIR__ . "/../includes/session.php";
session_secure_start();
require_once "../includes/auto-login.php";
require_once "../config/database.php";
require_once "../includes/pagination.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete" && isset($_POST["id"])) {
    $deleteId = (int)$_POST["id"];
    
    if ($deleteId !== (int)$_SESSION["user_id"]) {
        $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDelete->execute([$deleteId]);
    }
    
    header("Location: users.php");
    exit;
}

$search = trim($_GET["search"] ?? "");
$limit  = 15;
$page   = isset($_GET["p"]) && is_numeric($_GET["p"]) ? (int)$_GET["p"] : 1;

$filterCond = "";
$params     = [];

if ($search !== "") {
    $searchTerm = "%{$search}%";
    $filterCond = " WHERE name LIKE ? OR surname LIKE ? OR username LIKE ? OR email LIKE ?";
    $params     = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$countSql = $search !== ""
    ? "SELECT COUNT(*) FROM users" . $filterCond
    : "SELECT COUNT(*) FROM users";

$pagination = paginate_query($pdo, $countSql, $params, $limit, $page);
$page       = $pagination["page"];
$totalPages = $pagination["totalPages"];
$totalUsers = $pagination["total"];

// LIMIT/OFFSET güvenli tamsayılar olduğundan doğrudan eklenir.
$stmt = $pdo->prepare("
    SELECT id, name, surname, username, email, department, role
    FROM users
" . $filterCond . "
    ORDER BY id DESC
    LIMIT " . $limit . " OFFSET " . $pagination["offset"] . "
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - TeamMate Finder Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

    <div class="admin-layout">
        
        <?php include "admin-sidebar.php"; ?>

        <main class="main-content">
            
            <div class="content-header">
                <h1>Kullanıcı Yönetimi</h1>
                <p>Platforma kayıtlı tüm kullanıcıları görüntüleyin, arayın veya yönetin.</p>
            </div>

            <div class="filter-section">
                <form action="users.php" method="GET" class="search-form">
                    <div class="search-input-group">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Ad, soyad, kullanıcı adı veya e-posta ile ara..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-search">Ara</button>
                    <?php if ($search !== ""): ?>
                        <a href="users.php" class="btn btn-reset">Sıfırla</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="content-section">
                
                <div class="section-header">
                    <h2><i class="fa-solid fa-users"></i> Toplam Kullanıcı (<?= $totalUsers ?>)</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>Kullanıcı Adı</th>
                                <th>E-Posta</th>
                                <th>Bölüm</th>
                                <th>Rol</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($u["name"] . " " . $u["surname"]) ?></strong>
                                        </td>
                                        <td>@<?= htmlspecialchars($u["username"]) ?></td>
                                        <td><?= htmlspecialchars($u["email"]) ?></td>
                                        <td><?= htmlspecialchars($u["department"] ?: "Belirtilmemiş") ?></td>
                                        <td>
                                            <span class="badge badge-<?= $u["role"] === 'admin' ? 'admin' : 'user' ?>">
                                                <?= ucfirst($u["role"] ?? 'user') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../pages/user-profile.php?id=<?= $u["id"] ?>" target="_blank" class="btn btn-view" title="Profili Gör">
                                                    <i class="fa-solid fa-user"></i>
                                                </a>
                                                <?php if ((int)$u["id"] !== (int)$_SESSION["user_id"]): ?>
                                                    <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                                                        <button type="submit" class="btn btn-delete" title="Kullanıcıyı Sil">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 30px;">
                                        Aramanıza uygun kullanıcı bulunamadı.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?= render_pagination_links($page, $totalPages, $search) ?>
                <?php endif; ?>

            </div>

        </main>

    </div>

</body>
</html>