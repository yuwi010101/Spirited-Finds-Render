<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ikinonekta ang DB Config base sa Tree Structure (customer/cart.php)
include '../config/db.php';

// Safe Helper Function: Automatic image path resolver para sa customer folder
if (!function_exists('get_image_path')) {
    function get_image_path(?string $image_name = '') {
        if (empty($image_name)) {
            return '../assets/images/logo.png';
        }
        $filename = basename(trim($image_name));
        return '../assets/images/' . $filename;
    }
}

// Suriin kung Naka-login ang User
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? intval($_SESSION['user_id']) : 0;
$user_role = $_SESSION['role'] ?? '';

// RBAC IMPLEMENTATION: Pigilan ang admin na ma-access ang customer cart
if ($is_logged_in && $user_role === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit();
}

// Maximum Item Limit bawat produkto
$max_item_limit = 10;

if ($is_logged_in) {
    // ACTION HANDLER: Magdagdag ng Item sa Database Cart (GET action=add)
    if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
        $product_id = intval($_GET['id']);
        
        $chk = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $chk->bind_param("ii", $user_id, $product_id);
        $chk->execute();
        $chk_res = $chk->get_result();
        
        if ($chk_res && $chk_res->num_rows > 0) {
            $row = $chk_res->fetch_assoc();
            $new_qty = min($row['quantity'] + 1, $max_item_limit);
            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $upd->bind_param("iii", $new_qty, $user_id, $product_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $ins->bind_param("ii", $user_id, $product_id);
            $ins->execute();
            $ins->close();
        }
        $chk->close();
        
        header("Location: cart.php");
        exit();
    }

    // ACTION HANDLER: Baguhin ang Dami / Quantity (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        if ($quantity > 0) {
            if ($quantity > $max_item_limit) {
                $quantity = $max_item_limit;
            }
            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $upd->bind_param("iii", $quantity, $user_id, $product_id);
            $upd->execute();
            $upd->close();
        } else {
            $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $del->bind_param("ii", $user_id, $product_id);
            $del->execute();
            $del->close();
        }
        header("Location: cart.php");
        exit();
    }

    // ACTION HANDLER: Alisin ang Item sa Database Cart (GET action=remove)
    if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
        $remove_id = intval($_GET['id']);
        $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $del->bind_param("ii", $user_id, $remove_id);
        $del->execute();
        $del->close();
        header("Location: cart.php");
        exit();
    }
}

// Kuhanin ang mga Items sa Cart mula sa Database Table na 'cart' at 'products'
$cart_products = [];
$total_amount = 0;

if ($is_logged_in) {
    $stmt = $conn->prepare("
        SELECT c.cart_id, c.quantity, c.product_id, p.title, p.price, p.image, p.stock 
        FROM cart c 
        JOIN products p ON c.product_id = p.product_id 
        WHERE c.user_id = ?
        ORDER BY c.cart_id DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $qty = intval($row['quantity']);
        $subtotal = $row['price'] * $qty;
        $total_amount += $subtotal;

        $cart_products[] = [
            'cart_id'    => $row['cart_id'],
            'product_id' => $row['product_id'],
            'title'      => $row['title'] ?? 'Untitled Product',
            'image'      => get_image_path($row['image']),
            'price'      => $row['price'],
            'quantity'   => $qty,
            'stock'      => $row['stock'],
            'subtotal'   => $subtotal
        ];
    }
    $stmt->close();
}

// Isama ang Header
include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/global/customer-style.css">

<div class="cart-page-wrapper py-5">
    <div class="container" style="min-height: 65vh;">
        <!-- SECTION TITLE -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2 class="cart-title mb-0 d-flex align-items-center">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="var(--ghibli-green, #2c4c38)" class="mr-2">
                    <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                </svg>
                Your Shopping Cart
            </h2>
            <?php if (!empty($cart_products)): ?>
                <span class="badge badge-ghibli-pill p-2"><?php echo count($cart_products); ?> Item(s)</span>
            <?php endif; ?>
        </div>

        <?php if (!$is_logged_in): ?>
            <!-- GUEST STATE: KAILANGAN MAG-LOGIN -->
            <div class="card cart-auth-card p-5 text-center my-5 mx-auto">
                <div class="mb-3">
                    <svg viewBox="0 0 24 24" width="60" height="60" fill="#6c757d">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>
                <h3 class="font-weight-bold mb-2">Authentication Required</h3>
                <p class="text-muted mb-4">Please log in or create an account to view your shopping cart and proceed to checkout.</p>
                <div class="d-flex flex-column gap-2 max-width-300 mx-auto">
                    <a href="../login.php?redirect=customer/cart.php" class="btn btn-ghibli-primary py-2 font-weight-bold mb-2">Log In</a>
                    <a href="../signup.php" class="btn btn-outline-secondary py-2 font-weight-bold">Create an Account</a>
                </div>
            </div>

        <?php elseif (!empty($cart_products)): ?>
            <!-- LOGGED-IN STATE: CART ITEMS -->
            <div class="row">
                <!-- CART ITEMS TABLE -->
                <div class="col-lg-8 mb-4">
                    <div class="card cart-table-card border-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table cart-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_products as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                                         class="cart-item-img rounded mr-3"
                                                         style="width: 65px; height: 65px; object-fit: cover; flex-shrink: 0;"
                                                         onerror="this.onerror=null; this.src='../assets/images/logo.png';">
                                                    <span class="font-weight-bold cart-item-title"><?php echo htmlspecialchars($item['title']); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-nowrap">₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <form action="cart.php" method="POST" class="d-flex align-items-center qty-form">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                    <input type="number" 
                                                           name="quantity" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" 
                                                           max="<?php echo min($max_item_limit, $item['stock']); ?>" 
                                                           class="form-control form-control-sm text-center cart-qty-input" 
                                                           onchange="this.form.submit()">
                                                </form>
                                            </td>
                                            <td class="font-weight-bold text-ghibli-accent text-nowrap">
                                                ₱<?php echo number_format($item['subtotal'], 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="cart.php?action=remove&id=<?php echo $item['product_id']; ?>" 
                                                   class="btn btn-sm btn-cart-delete" 
                                                   title="Remove item"
                                                   onclick="return confirm('Are you sure you want to remove this item from your cart?');">
                                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ORDER SUMMARY CARD -->
                <div class="col-lg-4">
                    <div class="card cart-summary-card p-4">
                        <h4 class="font-weight-bold mb-3 cart-summary-header">Order Summary</h4>
                        <hr class="my-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span class="font-weight-bold">₱<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Shipping Fee</span>
                            <span class="text-muted small">Calculated at Checkout</span>
                        </div>
                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <strong class="h5 font-weight-bold mb-0">Total</strong>
                            <strong class="h4 font-weight-bold text-ghibli-accent mb-0">₱<?php echo number_format($total_amount, 2); ?></strong>
                        </div>

                        <a href="checkout.php" class="btn btn-ghibli-primary w-100 py-2 font-weight-bold btn-block shadow-sm">
                            Proceed to Checkout
                        </a>
                        <a href="../index.php" class="btn btn-link w-100 mt-2 text-decoration-none text-muted small text-center d-flex align-items-center justify-content-center">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" class="mr-1">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- EMPTY CART STATE -->
            <div class="text-center py-5 bg-white rounded-lg shadow-sm cart-empty-card">
                <svg viewBox="0 0 24 24" width="60" height="60" fill="#6c757d" class="mb-3 opacity-50">
                    <path d="M17.21 9l-4.38-6.56c-.19-.28-.51-.42-.83-.42s-.64.14-.83.43L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.92 1.46h13c.92 0 1.69-.62 1.93-1.46l2.54-9.27L23 10c0-.55-.45-1-1-1h-4.79zM9 9l3-4.5L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                </svg>
                <h3 class="font-weight-bold">Your Cart is Currently Empty</h3>
                <p class="text-muted">Discover and add your favorite Studio Ghibli merchandise!</p>
                <a href="../index.php" class="btn btn-ghibli-primary mt-3 font-weight-bold px-4">Start Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Isama ang Footer
include '../includes/footer.php'; 
?>