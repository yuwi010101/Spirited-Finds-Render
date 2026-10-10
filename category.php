<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config/db.php';

// Safe Helper Function para sa Image Paths
if (!function_exists('get_image_path')) {
    function get_image_path(?string $path) {
        if (empty($path)) {
            return 'assets/images/logo.png';
        }
        $filename = basename(trim($path));
        return 'assets/images/' . $filename;
    }
}

// Check User Authentication Status
$is_logged_in = isset($_SESSION['user_id']);

// Kunin ang Category ID sa URL (Default sa ID 1 kung walang nalagay)
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// AUTH GUARD HANDLER: Redirects guest users to login page with a Toast message
if (isset($_GET['action']) && $_GET['action'] === 'require_login') {
    $_SESSION['toast_message'] = "Please log in or create an account to perform this action.";
    $_SESSION['toast_type'] = "warning";
    header("Location: login.php");
    exit();
}

// MAPPING NG GIF, HEADINGS, AT PROMO PER CATEGORY ID
$category_details = [
    1 => [
        'sub_title' => 'Whimsical Ghibli Tales',
        'promo'     => 'SAVE UP TO 50% TODAY!',
        'gif'       => 'blue1.gif',
        'bg_page'   => 'bg1.jpg'
    ],
    2 => [
        'sub_title' => 'Stylish Ghibli Wear',
        'promo'     => 'ENJOY UP TO 50% OFF YOUR FAVORITES!',
        'gif'       => 'blue3.gif',
        'bg_page'   => 'bg2.jpg'
    ],
    3 => [
        'sub_title' => 'Cuddly Ghibli Companions',
        'promo'     => 'WARM YOUR HEART WITH PLUSHIES!',
        'gif'       => 'blue2.gif',
        'bg_page'   => 'bg3.jpg'
    ],
    4 => [
        'sub_title' => 'Enchanted Accessories',
        'promo'     => 'GET UP TO 50% OFF TRINKETS!',
        'gif'       => 'blue.gif',
        'bg_page'   => 'bg4.jpg'
    ],
    5 => [
        'sub_title' => 'Rare Collectibles & Gems',
        'promo'     => 'EXCLUSIVE DEALS FOR COLLECTORS!',
        'gif'       => 'blue4.gif',
        'bg_page'   => 'bg5.jpg'
    ]
];

$current_banner = isset($category_details[$category_id]) ? $category_details[$category_id] : $category_details[1];

// Kuhanin ang Pangalan ng Kategorya
$category_name = "Enchanted Collection";
$cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
if ($cat_stmt) {
    $cat_stmt->bind_param("i", $category_id);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result();
    if ($cat_res && $cat_res->num_rows > 0) {
        $cat_row = $cat_res->fetch_assoc();
        $category_name = $cat_row['category_name'];
    }
    $cat_stmt->close();
}

// Kuhanin ang mga Produkto mula sa Database
$stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY product_id DESC");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$products = $stmt->get_result();

include 'includes/header.php';
?>

<!-- Custom Style sheet para sa Category Page -->
<link rel="stylesheet" href="assets/global/category.css">

<style>
    body {
        background-image: url('<?php echo get_image_path($current_banner['bg_page']); ?>');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        background-attachment: fixed;
    }
</style>

<!-- TOAST NOTIFICATION CONTAINER -->
<?php if (isset($_SESSION['toast_message'])): ?>
    <div style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
        <div class="toast show bg-warning text-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-delay="4000">
            <div class="toast-header bg-warning text-dark border-0">
                <strong class="mr-auto"><i class="fas fa-exclamation-circle mr-1"></i> Account Required</strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body bg-white text-dark rounded-bottom">
                <?php echo htmlspecialchars($_SESSION['toast_message']); ?>
            </div>
        </div>
    </div>
    <?php 
        unset($_SESSION['toast_message']);
        unset($_SESSION['toast_type']);
    ?>
<?php endif; ?>

<!-- HERO BANNER WITH CATEGORY GIF -->
<div class="category-hero" style="background-image: url('<?php echo get_image_path($current_banner['gif']); ?>');">
    <div class="hero-content" data-aos="fade-up" data-aos-duration="1200">
        <h3><?php echo htmlspecialchars($current_banner['sub_title']); ?></h3>
        <h1><?php echo htmlspecialchars(strtoupper($category_name)); ?></h1>
        <p><?php echo htmlspecialchars($current_banner['promo']); ?></p>
        <div>
            <a href="#product-section" class="btn-ghibli-hero">Explore Collection</a>
        </div>
    </div>
</div>

<!-- PRODUCT CARDS SECTION -->
<section id="product-section" class="py-5" data-aos="fade-up" data-aos-duration="1500">
    <div class="container">
        <div class="row">
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while ($row = $products->fetch_assoc()): ?>
                    <?php 
                        $pid          = $row['product_id'];
                        $imgPath      = get_image_path($row['image']);
                        $rating       = isset($row['rating']) ? intval($row['rating']) : 5;
                        $productTitle = $row['title'] ?? $row['product_name'] ?? 'Untitled Product';

                        // Dynamic Links based on Authentication
                        $wishlist_link = $is_logged_in ? "customer/wishlist.php?action=add&id={$pid}" : "category.php?id={$category_id}&action=require_login";
                        $cart_link     = $is_logged_in ? "customer/cart.php?action=add&id={$pid}" : "category.php?id={$category_id}&action=require_login";
                    ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 py-3 mb-2">
                        <div class="product-card">
                            
                            <!-- Image Wrapper with Global Gradient & Dotted Line -->
                            <div class="product-img-wrapper">
                                
                                <!-- Floating Action Buttons -->
                                <div class="product-actions">
                                    <!-- EYE BUTTON: Opens product_details.php -->
                                    <a href="product_details.php?id=<?php echo $pid; ?>" class="action-btn" title="Quick View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <!-- HEART BUTTON: Add to Wishlist or Redirect to Login -->
                                    <a href="<?php echo $wishlist_link; ?>" class="action-btn" title="Add to Wishlist">
                                        <i class="fas fa-heart"></i>
                                    </a>
                                </div>

                                <!-- Product Image with Fallback -->
                                <img src="<?php echo htmlspecialchars($imgPath); ?>" 
                                     alt="<?php echo htmlspecialchars($productTitle); ?>" 
                                     onerror="this.onerror=null; this.src='assets/images/logo.png';">
                            </div>

                            <!-- Card Body -->
                            <div class="product-card-body">
                                <h3 class="text-truncate"><?php echo htmlspecialchars($productTitle); ?></h3>
                                
                                <!-- Star Rating -->
                                <div class="star-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo ($i <= $rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>

                                <!-- Description -->
                                <p class="product-desc"><?php echo htmlspecialchars($row['description'] ?? ''); ?></p>

                                <!-- Footer (Price + Add Cart Button) -->
                                <div class="product-footer">
                                    <span class="price-tag">₱<?php echo number_format($row['price'], 2); ?></span>
                                    <!-- ADD TO CART BUTTON: Adds to Cart or Redirects to Login -->
                                    <a href="<?php echo $cart_link; ?>" class="btn-add-cart-sm text-decoration-none">
                                        <i class="fas fa-shopping-cart"></i> Add Cart
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="p-4 bg-white rounded shadow-sm d-inline-block">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h3 class="text-secondary font-weight-bold">No products found in this category.</h3>
                        <p class="text-muted">Check out our other collections!</p>
                        <a href="index.php" class="btn btn-ghibli-primary mt-2">Back to Home</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
$stmt->close();
include 'includes/footer.php';
?>