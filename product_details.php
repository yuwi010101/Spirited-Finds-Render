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

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? intval($_SESSION['user_id']) : 0;
$max_item_limit = 10; // Maximum limit bawat item

// AUTH GUARD HANDLER: Kapag pinindot ang action nang hindi naka-login
if (isset($_GET['action']) && $_GET['action'] === 'require_login') {
    $_SESSION['toast_message'] = "Please log in or create an account to perform this action.";
    $_SESSION['toast_type'] = "warning";
    header("Location: login.php");
    exit();
}

// Suriin ang Product ID sa URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = intval($_GET['id']);

// POST HANDLER: ADD TO CART (Para sa naka-login na user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!$is_logged_in) {
        $_SESSION['toast_message'] = "Please log in or create an account to perform this action.";
        $_SESSION['toast_type'] = "warning";
        header("Location: login.php");
        exit();
    }

    $pid = intval($_POST['product_id']);
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // I-clamp ang quantity sa pagitan ng 1 at max_item_limit (10)
    if ($qty < 1) $qty = 1;
    if ($qty > $max_item_limit) $qty = $max_item_limit;

    // Suriin kung ang item ay nasa cart na ng user sa database
    $chk = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $chk->bind_param("ii", $user_id, $pid);
    $chk->execute();
    $chk_res = $chk->get_result();

    if ($chk_res && $chk_res->num_rows > 0) {
        $row = $chk_res->fetch_assoc();
        $new_qty = min($row['quantity'] + $qty, $max_item_limit);
        $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $upd->bind_param("iii", $new_qty, $user_id, $pid);
        $upd->execute();
        $upd->close();
    } else {
        $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $ins->bind_param("iii", $user_id, $pid, $qty);
        $ins->execute();
        $ins->close();
    }
    $chk->close();

    header("Location: customer/cart.php");
    exit();
}

// Kuhanin ang Detalye ng Main Product
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE p.product_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Product not found!'); window.location.href='index.php';</script>";
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// Kuhanin ang Related Products (hanggang 4 items)
$related_query = "SELECT * FROM products WHERE category_id = ? AND product_id != ? LIMIT 4";
$related_stmt = $conn->prepare($related_query);
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Dynamic Wishlist at Cart Links para sa Main Product
$main_wishlist_link = $is_logged_in ? "customer/wishlist.php?action=add&id={$product_id}" : "product_details.php?id={$product_id}&action=require_login";

include 'includes/header.php';
?>

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

<!-- Main Product Details Section -->
<section class="product-details-section py-5">
    <div class="container" data-aos="fade-up" data-aos-duration="1000">
        <div class="product-details-container bg-white p-4 p-md-5 rounded shadow-sm">
            <div class="row align-items-center">
                <!-- Image Display -->
                <div class="col-lg-6 mb-4 mb-lg-0 text-center">
                    <div class="product-main-image-box">
                        <img src="<?php echo htmlspecialchars(get_image_path($product['image'])); ?>" 
                             alt="<?php echo htmlspecialchars($product['title']); ?>" 
                             class="img-fluid rounded" 
                             style="max-height: 450px; object-fit: contain;"
                             onerror="this.onerror=null; this.src='assets/images/logo.png';">
                    </div>
                </div>

                <!-- Product Info -->
                <div class="col-lg-6">
                    <span class="badge badge-info mb-2 px-3 py-2">
                        <?php echo htmlspecialchars($product['category_name'] ?? 'Ghibli Treasure'); ?>
                    </span>
                    
                    <h1 class="product-details-title font-weight-bold mb-3"><?php echo htmlspecialchars($product['title']); ?></h1>

                    <!-- Ratings -->
                    <div class="rating-stars mb-3 text-warning">
                        <?php 
                        $rating = (int)$product['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                        <span class="text-muted ml-2">(<?php echo $rating; ?>.0)</span>
                    </div>

                    <!-- Price and Stock Status -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="h3 font-weight-bold text-success mb-0 mr-3">₱<?php echo number_format($product['price'], 2); ?></div>
                        <?php if ($product['stock'] > 0): ?>
                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> In Stock (<?php echo $product['stock']; ?>)</span>
                        <?php else: ?>
                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <p class="product-description-text text-muted mb-4">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?>
                    </p>

                    <!-- Quantity & Action Form -->
                    <form action="product_details.php?id=<?php echo $product_id; ?>" method="POST" class="d-flex align-items-center flex-wrap gap-3">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <input type="hidden" name="action" value="add_to_cart">
                        
                        <div class="d-flex align-items-center mb-3 mb-sm-0 mr-3">
                            <label for="quantity" class="font-weight-bold mb-0 mr-2">Qty:</label>
                            <input type="number" 
                                   id="quantity" 
                                   name="quantity" 
                                   value="1" 
                                   min="1" 
                                   max="<?php echo min($max_item_limit, $product['stock']); ?>" 
                                   class="form-control text-center" 
                                   style="width: 80px;" 
                                   <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                        </div>

                        <button type="submit" class="btn btn-ghibli-primary px-4 py-2 mr-2" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart mr-1"></i> Add to Cart
                        </button>

                        <a href="<?php echo $main_wishlist_link; ?>" class="btn btn-outline-danger px-3 py-2" title="Add to Wishlist">
                            <i class="fas fa-heart"></i>
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Related Products Section -->
        <?php if ($related_result && $related_result->num_rows > 0): ?>
            <h3 class="font-weight-bold my-4">You Might Also Like</h3>
            <div class="row">
                <?php while ($related = $related_result->fetch_assoc()): ?>
                    <?php 
                        $rel_id = $related['product_id'];
                        $rel_wishlist = $is_logged_in ? "customer/wishlist.php?action=add&id={$rel_id}" : "product_details.php?id={$product_id}&action=require_login";
                        $rel_cart     = $is_logged_in ? "customer/cart.php?action=add&id={$rel_id}" : "product_details.php?id={$product_id}&action=require_login";
                    ?>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="product-card">
                            <div class="product-img-wrapper">
                                <img src="<?php echo htmlspecialchars(get_image_path($related['image'])); ?>" 
                                     alt="<?php echo htmlspecialchars($related['title']); ?>"
                                     onerror="this.onerror=null; this.src='assets/images/logo.png';">
                                <div class="product-actions">
                                    <a href="product_details.php?id=<?php echo $rel_id; ?>" class="action-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $rel_wishlist; ?>" class="action-btn" title="Add to Wishlist">
                                        <i class="fas fa-heart"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="product-card-body">
                                <h3 class="text-truncate"><?php echo htmlspecialchars($related['title']); ?></h3>
                                <div class="star-rating">
                                    <?php 
                                    $rel_rating = (int)$related['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rel_rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <p class="product-desc"><?php echo htmlspecialchars($related['description'] ?? ''); ?></p>
                                <div class="product-footer">
                                    <span class="price-tag">₱<?php echo number_format($related['price'], 2); ?></span>
                                    <a href="<?php echo $rel_cart; ?>" class="btn-add-cart-sm text-decoration-none">
                                        <i class="fas fa-shopping-cart"></i> Add Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
if (isset($related_stmt)) {
    $related_stmt->close();
}
include 'includes/footer.php';
?>