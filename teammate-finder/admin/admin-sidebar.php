<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <h2>TMF Admin</h2>
    </div>
    
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i> 
            <span>Dashboard</span>
        </a>
        
        <a href="users.php" class="<?= $currentPage == 'users.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> 
            <span>Kullanıcılar</span>
        </a>
        
        <a href="projects.php" class="<?= $currentPage == 'projects.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-folder-open"></i> 
            <span>Proje İlanları</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <a href="../index.php" class="back-to-site">
            <i class="fa-solid fa-arrow-left"></i> 
            <span>Siteye Dön</span>
        </a>
    </nav>
</div>