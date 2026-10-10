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

// 1. AUTHENTICATION GUARD: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=customer/checkout.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// 2. ROLE GUARD: Only 'customer' role is permitted to checkout
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    $_SESSION['error'] = "Admins cannot perform checkout operations.";
    header("Location: ../index.php");
    exit();
}

// Fetch Customer Account Details gamit ang first_name at last_name
$user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Pagsamahin ang pangalan bilang Full Name
$fullname_display = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));

// 3. FETCH CART PRODUCTS DIRECTLY FROM DATABASE TABLE 'cart'
$cart_products = [];
$total_amount = 0;

$stmt = $conn->prepare("
    SELECT c.quantity, c.product_id, p.title, p.price, p.image, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $qty = intval($row['quantity']);
    $subtotal = $row['price'] * $qty;
    $total_amount += $subtotal;

    $cart_products[] = [
        'product_id' => $row['product_id'],
        'title'      => $row['title'] ?? 'Untitled Product',
        'image'      => get_image_path($row['image']),
        'price'      => $row['price'],
        'quantity'   => $qty,
        'subtotal'   => $subtotal
    ];
}
$stmt->close();

// 4. CART GUARD: Kung walang laman ang Cart sa Database, ibalik sa cart.php
if (empty($cart_products)) {
    header("Location: cart.php");
    exit();
}

$shipping_fee = 100.00; // Fixed shipping rate
$grand_total = $total_amount + $shipping_fee;

$error_msg = '';

// PLACE ORDER PROCESSOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $payment  = trim($_POST['payment_method'] ?? 'COD');

    if (empty($fullname) || empty($phone) || empty($address)) {
        $error_msg = "Please fill in all required shipping fields.";
    } else {
        // Database Transaction for Safe Order Creation
        $conn->begin_transaction();

        try {
            // 1. Insert into 'orders' table
            $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'Pending')");
            $order_stmt->bind_param("id", $user_id, $grand_total);
            $order_stmt->execute();
            $order_id = $conn->insert_id;
            $order_stmt->close();

            // 2. Insert items & Update Stock in 'products' table
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stock_stmt = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE product_id = ?");

            foreach ($cart_products as $item) {
                // Save Order Item
                $item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $item_stmt->execute();

                // Deduct Inventory Stock
                $stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                $stock_stmt->execute();
            }
            $item_stmt->close();
            $stock_stmt->close();

            // 3. Clear DB Cart Table for this user
            $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            if ($clear_cart) {
                $clear_cart->bind_param("i", $user_id);
                $clear_cart->execute();
                $clear_cart->close();
            }

            // Commit Transaction
            $conn->commit();

            // Redirect to Success / Confirmation Page
            header("Location: order_success.php?order_id=" . $order_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "An error occurred while placing your order. Please try again.";
        }
    }
}

// Include Header
include '../includes/header.php';
?>

<!-- CUSTOMER STYLE LINK -->
<link rel="stylesheet" href="../assets/global/customer-style.css">

<div class="container my-5" style="min-height: 60vh;">
    <h2 class="section-title mb-4"><i class="fas fa-credit-card"></i> Checkout</h2>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger rounded-lg alert-dismissible fade show mb-4" role="alert">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST">
        <div class="row">
            <!-- LEFT COLUMN: SHIPPING & PAYMENT INFORMATION -->
            <div class="col-lg-7 mb-4">
                <!-- SHIPPING ADDRESS -->
                <div class="card shadow-sm border-0 rounded-lg p-4 mb-4">
                    <h4 class="font-weight-bold mb-3"><i class="fas fa-truck text-success mr-2"></i> Shipping Details</h4>
                    <hr>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname_display); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Delivery Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Street, Building, Barangay, City, Postal Code" required></textarea>
                    </div>
                </div>

                <!-- PAYMENT METHOD -->
                <div class="card shadow-sm border-0 rounded-lg p-4">
                    <h4 class="font-weight-bold mb-3"><i class="fas fa-wallet text-warning mr-2"></i> Payment Method</h4>
                    <hr>

                    <div class="custom-control custom-radio mb-3">
                        <input class="custom-control-input" type="radio" name="payment_method" id="payment_cod" value="COD" checked>
                        <label class="custom-control-label font-weight-bold" for="payment_cod">
                            Cash on Delivery (COD)
                        </label>
                        <div class="text-muted small">Pay with cash upon arrival of your package.</div>
                    </div>

                    <div class="custom-control custom-radio mb-3">
                        <input class="custom-control-input" type="radio" name="payment_method" id="payment_gcash" value="GCash">
                        <label class="custom-control-label font-weight-bold" for="payment_gcash">
                            GCash / E-Wallet
                        </label>
                        <div class="text-muted small">Scan QR code or send payment directly upon order placement.</div>
                    </div>

                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" name="payment_method" id="payment_card" value="Card">
                        <label class="custom-control-label font-weight-bold" for="payment_card">
                            Credit / Debit Card
                        </label>
                        <div class="text-muted small">Secure online payment via card gateway.</div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: ORDER SUMMARY -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-lg p-4 sticky-top" style="top: 20px;">
                    <h4 class="font-weight-bold mb-3">Your Order</h4>
                    <hr>

                    <!-- PRODUCT ITEMS PREVIEW -->
                    <div class="order-items-preview mb-3" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($cart_products as $item): ?>
                            <div class="d-flex align-items-center justify-content-between mb-3 pr-2">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; flex-shrink: 0;" 
                                         class="rounded mr-3"
                                         onerror="this.onerror=null; this.src='../assets/images/logo.png';">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold small"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                </div>
                                <span class="font-weight-bold text-dark small">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <!-- TOTALS BREAKDOWN -->
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span>₱<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Shipping Fee</span>
                        <span>₱<?php echo number_format($shipping_fee, 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <strong class="h5 font-weight-bold mb-0">Grand Total</strong>
                        <strong class="h5 font-weight-bold text-success mb-0">₱<?php echo number_format($grand_total, 2); ?></strong>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-ghibli-primary w-100 py-3 font-weight-bold h5 mb-0">
                        Place Order Now
                    </button>
                    <a href="cart.php" class="btn btn-link w-100 mt-2 text-decoration-none text-muted">Return to Cart</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php 
// Include Footer
include '../includes/footer.php'; 
?>