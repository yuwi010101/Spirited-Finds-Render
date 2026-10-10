<?php
require_once __DIR__ . '/../includes/security.php';

// Ikinonekta pabalik sa root directory base sa Tree Structure
include '../config/db.php';

// Safe Helper Function para sa Image Paths
if (!function_exists('get_image_path')) {
    function get_image_path(?string $path) {
        if (empty($path)) {
            return '../assets/images/logo.png';
        }
        $filename = basename(trim($path));
        return '../assets/images/' . $filename;
    }
}

// Suriin kung Naka-login ang User
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? intval($_SESSION['user_id']) : 0;
$max_item_limit = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$is_logged_in || ($_SESSION['role'] ?? '') !== 'customer') {
        http_response_code(403);
        exit('Customer sign-in required.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($product_id === false || !in_array($action, ['add', 'remove', 'move_to_cart'], true)) {
        http_response_code(400);
        exit('Invalid wishlist action.');
    }
    $conn->begin_transaction();
    try {
        if ($action === 'remove') {
            $query = $conn->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
            $query->bind_param('ii', $user_id, $product_id);
            $query->execute();
            $query->close();
        } else {
            $stockQuery = $conn->prepare('SELECT stock FROM products WHERE product_id = ? FOR UPDATE');
            $stockQuery->bind_param('i', $product_id);
            $stockQuery->execute();
            $product = $stockQuery->get_result()->fetch_assoc();
            $stockQuery->close();
            if (!$product) throw new DomainException('Product is no longer available.');
            if ($action === 'add') {
                $exists = $conn->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?');
                $exists->bind_param('ii', $user_id, $product_id);
                $exists->execute();
                $found = (bool) $exists->get_result()->fetch_row();
                $exists->close();
                if (!$found) {
                    $save = $conn->prepare('INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)');
                    $save->bind_param('ii', $user_id, $product_id);
                    $save->execute();
                    $save->close();
                }
            } else {
                if ((int) $product['stock'] < 1) {
                    throw new DomainException('Product is currently out of stock.');
                }
                $cartCheck = $conn->prepare('SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? FOR UPDATE');
                $cartCheck->bind_param('ii', $user_id, $product_id);
                $cartCheck->execute();
                $inCart = $cartCheck->get_result()->fetch_assoc();
                $cartCheck->close();
                if ($inCart) {
                    $quantity = (int) $inCart['quantity'] + 1;
                    if ($quantity > 10 || $quantity > (int) $product['stock']) {
                        throw new DomainException('Maximum cart quantity or stock reached.');
                    }
                    $update = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
                    $update->bind_param('iii', $quantity, $user_id, $product_id);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conn->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)');
                    $insert->bind_param('ii', $user_id, $product_id);
                    $insert->execute();
                    $insert->close();
                }
                $delete = $conn->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
                $delete->bind_param('ii', $user_id, $product_id);
                $delete->execute();
                $delete->close();
            }
        }
        $conn->commit();
        $_SESSION['wishlist_flash'] = ($action === 'move_to_cart') ? 'Item moved to cart.' : 'Wishlist updated.';
        header('Location: ' . ($action === 'move_to_cart' ? 'cart.php' : 'wishlist.php'), true, 303);
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Wishlist action failed: ' . $e->getMessage());
        $_SESSION['wishlist_flash'] = $e instanceof DomainException ? $e->getMessage() : 'Unable to update wishlist.';
        header('Location: wishlist.php', true, 303);
        exit();
    }
}

// Kuhanin ang Wishlist Items mula sa Database Table na 'wishlist' at 'products'
$wishlist_items = [];
if ($is_logged_in) {
    $stmt = $conn->prepare("
        SELECT w.wishlist_id, p.product_id, p.title, p.price, p.image, p.stock, p.rating, p.description 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.product_id 
        WHERE w.user_id = ? 
        ORDER BY w.wishlist_id DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $wishlist_items[] = [
            'wishlist_id' => $row['wishlist_id'],
            'product_id'  => $row['product_id'],
            'title'       => $row['title'] ?? 'Untitled Product',
            'image'       => get_image_path($row['image']),
            'price'       => $row['price'],
            'stock'       => $row['stock'],
            'rating'      => (int)$row['rating'],
            'description' => $row['description']
        ];
    }
    $stmt->close();
}

// Isinama base sa Tree Structure
include '../includes/header.php';
?>

<?php if (isset($_SESSION['wishlist_flash'])): ?>
<div class="container mt-3"><div class="alert alert-info"><?php echo esc($_SESSION['wishlist_flash']); unset($_SESSION['wishlist_flash']); ?></div></div>
<?php endif; ?>
<!-- CUSTOMER STYLE LINK -->
<link rel="stylesheet" href="../assets/global/customer-style.css">

<div class="container cart-page-wrapper" style="min-height: 60vh;">
    <h2 class="cart-title mb-4"><i class="fas fa-heart text-ghibli-accent mr-2"></i> Your Wishlist</h2>

    <?php if (!$is_logged_in): ?>
        <!-- GUEST STATE: KAILANGAN MAG-LOGIN -->
        <div class="card cart-auth-card p-5 text-center my-5 mx-auto" style="max-width: 500px;">
            <div class="mb-3">
                <i class="fas fa-user-lock fa-4x text-muted"></i>
            </div>
            <h3 class="font-weight-bold mb-2" style="font-family: 'Fredoka', cursive; color: var(--ghibli-forest);">Authentication Required</h3>
            <p class="text-muted mb-4">Please log in or create an account to view and save items in your wishlist.</p>
            <div class="d-grid gap-2">
                <a href="../login.php?redirect=customer/wishlist.php" class="btn btn-ghibli-primary py-2 font-weight-bold mb-2">Log In</a>
                <a href="../signup.php" class="btn btn-outline-secondary py-2 font-weight-bold" style="border-radius: var(--radius-pill, 50px);">Create an Account</a>
            </div>
        </div>

    <?php elseif (!empty($wishlist_items)): ?>
        <!-- LOGGED-IN STATE: IPAPAKITA ANG WISHLIST ITEMS -->
        <div class="row">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card cart-table-card h-100 position-relative p-2">
                        <!-- Remove Button Overlay -->
                        <form method="POST" action="wishlist.php" class="position-absolute" style="top:15px;right:15px;z-index:10;" onsubmit="return confirm('Remove this item from your wishlist?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo (int)$item['product_id']; ?>">
                            <button type="submit" class="btn btn-cart-delete" aria-label="Remove from wishlist"><i class="fas fa-times"></i></button>
                         </form>

                        <!-- Product Image -->
                        <div class="text-center p-3 rounded" style="background-color: var(--ghibli-warm-paper, #F5EFE0);">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                 class="img-fluid rounded" 
                                 style="height: 180px; object-fit: contain;"
                                 onerror="this.onerror=null; this.src='../assets/images/logo.png';">
                        </div>

                        <!-- Card Body -->
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <h5 class="card-title font-weight-bold text-truncate mb-1" style="font-size: 1rem;">
                                    <a href="../product_details.php?id=<?php echo $item['product_id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                </h5>

                                <!-- Ratings -->
                                <div class="text-warning small mb-2">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $item['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>

                                <div class="h6 font-weight-bold text-ghibli-accent mb-3">
                                    ₱<?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div>
                                <?php if ($item['stock'] > 0): ?>
                                    <form method="POST" action="wishlist.php">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="move_to_cart">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$item['product_id']; ?>">
                                        <button type="submit" class="btn btn-ghibli-primary btn-block btn-sm py-2 font-weight-bold"><i class="fas fa-shopping-cart mr-1"></i> Move to Cart</button>
                                     </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-block btn-sm py-2" disabled style="border-radius: var(--radius-pill, 50px);">
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- EMPTY WISHLIST STATE -->
        <div class="text-center py-5 cart-empty-card p-4">
            <i class="fas fa-heart-broken fa-4x text-muted mb-3"></i>
            <h3 style="font-family: 'Fredoka', cursive; color: var(--ghibli-forest);">Your Wishlist is Empty</h3>
            <p class="text-muted">Explore our shop and save your favorite Ghibli treasures!</p>
            <a href="../index.php" class="btn btn-ghibli-primary mt-2">Explore Products</a>
        </div>
    <?php endif; ?>
</div>

<?php 
// Isinama base sa Tree Structure
include '../includes/footer.php'; 
?>