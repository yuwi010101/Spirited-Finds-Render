<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if ($is_logged_in) {
    // ACTION HANDLER: Magdagdag ng Item sa Wishlist (GET action=add)
    if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
        $product_id = intval($_GET['id']);
        
        // Suriin kung umiiral na sa wishlist ng user
        $chk = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $chk->bind_param("ii", $user_id, $product_id);
        $chk->execute();
        $chk_res = $chk->get_result();
        
        if ($chk_res->num_rows === 0) {
            $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $product_id);
            $ins->execute();
            $ins->close();
        }
        $chk->close();

        header("Location: wishlist.php");
        exit();
    }

    // ACTION HANDLER: Burahin ang Item sa Wishlist (GET action=remove)
    if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
        $product_id = intval($_GET['id']);
        $del = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del->bind_param("ii", $user_id, $product_id);
        $del->execute();
        $del->close();

        header("Location: wishlist.php");
        exit();
    }

    // ACTION HANDLER: Ilipat ang Item sa Cart mula sa Wishlist (GET action=move_to_cart)
    if (isset($_GET['action']) && $_GET['action'] === 'move_to_cart' && isset($_GET['id'])) {
        $product_id = intval($_GET['id']);

        // 1. I-update ang Database Cart
        $chk_cart = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $chk_cart->bind_param("ii", $user_id, $product_id);
        $chk_cart->execute();
        $cart_res = $chk_cart->get_result();

        if ($cart_res && $cart_res->num_rows > 0) {
            $c_row = $cart_res->fetch_assoc();
            $new_qty = min($c_row['quantity'] + 1, $max_item_limit);
            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $upd->bind_param("iii", $new_qty, $user_id, $product_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins_cart = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $ins_cart->bind_param("ii", $user_id, $product_id);
            $ins_cart->execute();
            $ins_cart->close();
            $new_qty = 1;
        }
        $chk_cart->close();

        // 2. I-update din ang Session Cart para manatiling synchronized
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $_SESSION['cart'][$product_id] = isset($_SESSION['cart'][$product_id]) 
            ? min($_SESSION['cart'][$product_id] + 1, $max_item_limit) 
            : 1;

        // 3. Alisin sa Wishlist matapos maipaloob sa Cart
        $del_w = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del_w->bind_param("ii", $user_id, $product_id);
        $del_w->execute();
        $del_w->close();

        header("Location: cart.php");
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
                        <a href="wishlist.php?action=remove&id=<?php echo $item['product_id']; ?>" 
                           class="btn btn-cart-delete position-absolute" 
                           style="top: 15px; right: 15px; z-index: 10; width: 32px; height: 32px; border-radius: 50%; padding: 0;" 
                           title="Remove from wishlist"
                           onclick="return confirm('Remove this item from your wishlist?');">
                            <i class="fas fa-times"></i>
                        </a>

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
                                    <a href="wishlist.php?action=move_to_cart&id=<?php echo $item['product_id']; ?>" 
                                       class="btn btn-ghibli-primary btn-block btn-sm py-2 font-weight-bold">
                                        <i class="fas fa-shopping-cart mr-1"></i> Move to Cart
                                    </a>
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