<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

if (!function_exists('get_image_path')) {
    function get_image_path(?string $path) {
        if (empty($path)) return 'assets/images/logo.png';
        return 'assets/images/' . basename(trim($path));
    }
}
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : 0;
$max_item_limit = 10;
$product_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($product_id === false) {
    http_response_code(404);
    exit('Product not found.');
}
if (($_GET['action'] ?? '') === 'require_login') {
    $_SESSION['toast_message'] = 'Please log in to shop or review products.';
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$is_logged_in || ($_SESSION['role'] ?? '') !== 'customer') {
        http_response_code(403);
        exit('Please sign in as a customer.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'submit_review') {
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $reviewText = trim((string) ($_POST['review_text'] ?? ''));
        if ($rating === false || $rating < 1 || $rating > 5 || strlen($reviewText) > 1500) {
            $_SESSION['toast_message'] = 'Choose 1–5 stars and write no more than 1,500 characters.';
        } else {
            $eligibleStmt = $conn->prepare("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'Completed' LIMIT 1");
            $eligibleStmt->bind_param('ii', $user_id, $product_id);
            $eligibleStmt->execute();
            $eligible = (bool) $eligibleStmt->get_result()->fetch_row();
            $eligibleStmt->close();
            if (!$eligible) {
                $_SESSION['toast_message'] = 'You can review this product after your order is completed.';
            } else {
                $conn->begin_transaction();
                try {
                    $reviewStmt = $conn->prepare('INSERT INTO product_reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), updated_at=CURRENT_TIMESTAMP');
                    $reviewStmt->bind_param('iiis', $product_id, $user_id, $rating, $reviewText);
                    $reviewStmt->execute();
                    $reviewStmt->close();
                    $ratingStmt = $conn->prepare('UPDATE products SET rating = (SELECT COALESCE(ROUND(AVG(rating)),0) FROM product_reviews WHERE product_id = ?) WHERE product_id = ?');
                    $ratingStmt->bind_param('ii', $product_id, $product_id);
                    $ratingStmt->execute();
                    $ratingStmt->close();
                    $conn->commit();
                    $_SESSION['toast_message'] = 'Your verified customer review has been saved.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Save review failed: ' . $e->getMessage());
                    $_SESSION['toast_message'] = 'Could not save your review. Please try again later.';
                }
            }
        }
        header('Location: product_details.php?id=' . $product_id . '#customer-reviews', true, 303);
        exit();
    }
    if ($action === 'add_to_cart') {
        $pid = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($pid !== $product_id || $qty === false || $qty < 1 || $qty > $max_item_limit) {
            http_response_code(400);
            exit('Invalid product or quantity.');
        }
        $conn->begin_transaction();
        try {
            $available = $conn->prepare('SELECT stock FROM products WHERE product_id = ? FOR UPDATE');
            $available->bind_param('i', $pid);
            $available->execute();
            $found = $available->get_result()->fetch_assoc();
            $available->close();
            if (!$found || (int) $found['stock'] < $qty) {
                throw new DomainException('This quantity is not currently in stock.');
            }
            $previous = $conn->prepare('SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? FOR UPDATE');
            $previous->bind_param('ii', $user_id, $pid);
            $previous->execute();
            $cart = $previous->get_result()->fetch_assoc();
            $previous->close();
            $newQty = ($cart ? (int) $cart['quantity'] : 0) + $qty;
            if ($newQty > $max_item_limit || $newQty > (int) $found['stock']) {
                throw new DomainException('Cart quantity exceeds the available stock or 10-item limit.');
            }
            if ($cart) {
                $update = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
                $update->bind_param('iii', $newQty, $user_id, $pid);
            } else {
                $update = $conn->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)');
                $update->bind_param('iii', $user_id, $pid, $newQty);
            }
            $update->execute();
            $update->close();
            $conn->commit();
            header('Location: customer/cart.php', true, 303);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Add to cart failed: ' . $e->getMessage());
            $_SESSION['toast_message'] = $e instanceof DomainException ? $e->getMessage() : 'Unable to add this item.';
            header('Location: product_details.php?id=' . $product_id, true, 303);
            exit();
        }
    }
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
    http_response_code(404);
    exit('Product not found.');
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();
$reviewStat = $conn->prepare('SELECT COUNT(*) AS count_reviews, AVG(rating) AS average_rating FROM product_reviews WHERE product_id = ?');
$reviewStat->bind_param('i', $product_id);
$reviewStat->execute();
$reviewStats = $reviewStat->get_result()->fetch_assoc();
$reviewStat->close();
$averageRating = (float) ($reviewStats['average_rating'] ?? 0);
$reviewCount = (int) ($reviewStats['count_reviews'] ?? 0);

$canReview = false;
if ($is_logged_in && ($_SESSION['role'] ?? '') === 'customer') {
    $elig = $conn->prepare("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id=o.order_id WHERE o.user_id=? AND oi.product_id=? AND o.status='Completed' LIMIT 1");
    $elig->bind_param('ii', $user_id, $product_id);
    $elig->execute();
    $canReview = (bool) $elig->get_result()->fetch_row();
    $elig->close();
}
$reviewQuery = $conn->prepare('SELECT r.rating, r.review_text, r.created_at, u.first_name, u.last_name FROM product_reviews r JOIN users u ON u.user_id=r.user_id WHERE r.product_id = ? ORDER BY r.updated_at DESC LIMIT 30');
$reviewQuery->bind_param('i', $product_id);
$reviewQuery->execute();
$reviewRows = $reviewQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$reviewQuery->close();


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
                        $rating = (int) round($averageRating);
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                        <span class="text-muted ml-2">(<?php echo $reviewCount ? number_format($averageRating, 1) . ' / 5 · ' . $reviewCount . ' review(s)' : 'No reviews yet'; ?>)</span>
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
                        <?php echo csrf_field(); ?>
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

                        <?php if ($is_logged_in): ?>
                             <button type="submit" form="product-wishlist-form" class="btn btn-outline-danger px-3 py-2" title="Add to Wishlist"><i class="fas fa-heart"></i></button>
                          <?php else: ?>
                             <a href="login.php" class="btn btn-outline-danger px-3 py-2" title="Log in to add to wishlist"><i class="fas fa-heart"></i></a>
                          <?php endif; ?>
                    </form>
                    <?php if ($is_logged_in): ?>
                    <form action="customer/wishlist.php" method="POST" id="product-wishlist-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo (int)$product_id; ?>">
                    </form>
                    <?php endif; ?>
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
                                    <?php if ($is_logged_in): ?>
                                  <form action="customer/wishlist.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $rel_id; ?>">
                                    <button type="submit" class="action-btn border-0" title="Add to Wishlist">" class="action-btn" title="Add to Wishlist">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                  </form>
                                  <?php else: ?>
                                  <a href="login.php" class="action-btn" title="Log in to use wishlist"><i class="fas fa-heart"></i></a>
                                  <?php endif; ?>
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
                                    <?php if ($is_logged_in): ?>
                                    <form action="customer/cart.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$rel_id; ?>">
                                        <button type="submit" class="btn-add-cart-sm text-decoration-none border-0"><i class="fas fa-shopping-cart"></i> Add Cart</button>
                                    </form>
                                <?php else: ?>
                                    <a href="login.php" class="btn-add-cart-sm text-decoration-none"><i class="fas fa-shopping-cart"></i> Log in to buy</a>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>


<section class="container my-5" id="customer-reviews">
    <div class="bg-white rounded shadow-sm p-4 p-md-5">
        <h2 class="h3 font-weight-bold">Customer Ratings & Reviews</h2>
        <p class="text-muted"><?php echo $reviewCount ? number_format($averageRating, 1) . '/5 from ' . $reviewCount . ' verified review(s)' : 'No verified ratings yet.'; ?></p>
        <?php if ($canReview): ?>
            <form method="POST" action="product_details.php?id=<?php echo (int)$product_id; ?>#customer-reviews" class="border rounded p-3 mb-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="submit_review">
                <div class="form-group">
                    <label for="review-rating">Your rating</label>
                    <select id="review-rating" name="rating" class="form-control" required>
                        <option value="">Choose rating</option>
                        <option value="5">★★★★★ — Excellent</option>
                        <option value="4">★★★★☆ — Good</option>
                        <option value="3">★★★☆☆ — Average</option>
                        <option value="2">★★☆☆☆ — Below expectations</option>
                        <option value="1">★☆☆☆☆ — Poor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="review-comment">Share your experience (optional)</label>
                    <textarea id="review-comment" name="review_text" maxlength="1500" rows="3" class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-ghibli-primary">Submit / Update Review</button>
            </form>
        <?php elseif ($is_logged_in): ?>
            <p class="text-muted small">Reviews are available after you purchase this product and the order is marked Completed.</p>
        <?php else: ?>
            <p class="text-muted small"><a href="login.php">Log in</a> to review a completed purchase.</p>
        <?php endif; ?>
        <?php if (!$reviewRows): ?><p class="text-muted">No customer reviews yet.</p><?php endif; ?>
        <?php foreach ($reviewRows as $review): ?>
            <article class="border-top py-3">
                <div class="d-flex justify-content-between">
                    <strong><?php echo esc(trim($review['first_name'] . ' ' . $review['last_name'])); ?></strong>
                    <span class="text-warning"><?php echo str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                </div>
                <small class="text-success">Verified purchase</small>
                <small class="text-muted ml-2"><?php echo esc(date('M j, Y', strtotime($review['created_at']))); ?></small>
                <p class="mt-2"><?php echo nl2br(esc($review['review_text'])); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php
if (isset($related_stmt)) {
    $related_stmt->close();
}
include 'includes/footer.php';
?>