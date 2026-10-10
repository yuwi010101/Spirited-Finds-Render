<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Awtomatikong titignan kung nasa Root Folder o Customer Subfolder
$base = file_exists('config/db.php') ? '' : '../';

// Updated Helper function gamit ang $base path
if (!function_exists('get_image_path')) {
    function get_image_path(?string $path, string $base_dir = '') {
        if (empty($path)) {
            return $base_dir . 'assets/images/logo.png';
        }
        if (strpos($path, 'assets/images/') === 0) {
            return $base_dir . $path;
        }
        return $base_dir . 'assets/images/' . ltrim($path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spirited Finds - Enchanted Studio Ghibli Shop</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/png" href="<?php echo get_image_path('logo.png', $base); ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&family=Uchen&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Boxicons & FontAwesome Icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Custom Stylesheet (Dynamic Path) -->
    <link rel="stylesheet" href="<?php echo $base; ?>assets/global/style.css">
</head>
<body>

    <!-- STORYBOOK GHIBLI NAVBAR -->
    <nav class="navbar navbar-expand-lg sticky-top" id="navbar">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand" href="<?php echo $base; ?>index.php" id="logo">
                <img src="<?php echo get_image_path('logo.png', $base); ?>" alt="Spirited Finds Logo" width="45" class="ghibli-logo-img"> 
                <span>Spirited Finds</span>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar" aria-label="Toggle navigation">
                <span class="fas fa-bars text-white"></span>
            </button>

            <div class="collapse navbar-collapse" id="collapsibleNavbar">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base; ?>index.php#home">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="navbardrop" data-toggle="dropdown">
                            Categories
                        </a>
                        <div class="dropdown-menu ghibli-dropdown-menu">
                            <?php
                            if (isset($conn)) {
                                $cat_sql = "SELECT * FROM categories";
                                $cat_res = $conn->query($cat_sql);
                                if ($cat_res && $cat_res->num_rows > 0) {
                                    while($cat = $cat_res->fetch_assoc()) {
                                        echo '<a href="' . $base . 'category.php?id=' . $cat['category_id'] . '" class="dropdown-item py-2">' . htmlspecialchars($cat['category_name']) . '</a>';
                                    }
                                } else {
                                    echo '<a href="#" class="dropdown-item py-2">No Categories Found</a>';
                                }
                            }
                            ?>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>index.php#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $base; ?>index.php#contact">Contact</a></li>
                </ul>

                <div class="nav-icons-wrapper">
                    <!-- WISHLIST ICON WITH SESSION CHECK -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Kung NAKA-LOGIN: Papunta sa Wishlist Page -->
                        <a href="<?php echo $base; ?>customer/wishlist.php" class="nav-icon-btn" title="Wishlist">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </a>
                    <?php else: ?>
                        <!-- Kung HINDI NAKA-LOGIN: Direct papuntang Login Page -->
                        <a href="<?php echo $base; ?>login.php?redirect=customer/wishlist.php" class="nav-icon-btn" title="Login to View Wishlist">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </a>
                    <?php endif; ?>

                    <!-- CART ICON WITH SESSION CHECK -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Kung NAKA-LOGIN: Bubukas ang Cart Page / Side Cart -->
                        <a href="<?php echo $base; ?>customer/cart.php" class="nav-icon-btn toggle-cart" title="Shopping Cart">
                            <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        </a>
                    <?php else: ?>
                        <!-- Kung HINDI NAKA-LOGIN: Direct papuntang Login Page -->
                        <a href="<?php echo $base; ?>login.php?redirect=customer/cart.php" class="nav-icon-btn" title="Login to View Cart">
                            <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        </a>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $base; ?>customer/my_orders.php" class="nav-icon-btn" title="My Orders">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                        </a>
                        <a href="<?php echo $base; ?>logout.php" class="btn-ghibli-outline btn-sm ml-2">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo $base; ?>login.php" class="btn-ghibli-primary btn-sm ml-2 px-4">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>