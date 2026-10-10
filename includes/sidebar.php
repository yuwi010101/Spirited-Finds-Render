<?php
// Kunin ang kasalukuyang filename para sa dynamic active state ng menu
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- FLOATING GHIBLI SIDEBAR -->
<nav class="admin-sidebar p-3">
    <div class="sidebar-brand-box text-center">
        <img src="../assets/images/logo.png" alt="Logo" class="sidebar-logo-img mb-2">
        <h5 class="font-weight-bold text-white mb-0" style="font-family: 'Fredoka', cursive;">Spirited Admin</h5>
        <small style="color: var(--ghibli-gold); font-weight: 500;">Management Panel</small>
    </div>
    
    <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i> Dashboard
    </a>
    <a href="products.php" class="<?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
        <i class="fas fa-box"></i> Products
    </a>
    <a href="orders.php" class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
        <i class="fas fa-shopping-bag"></i> Orders
    </a>
    <a href="messages.php" class="<?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
        <i class="fas fa-envelope"></i> Messages
    </a>
    
    <div style="border-bottom: 2px dashed rgba(232, 168, 56, 0.3);" class="my-3"></div>

    <a href="../index.php" target="_blank"><i class="fas fa-store"></i> View Storefront</a>
    <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
</nav>