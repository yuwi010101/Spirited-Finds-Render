<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';

if (!function_exists('get_image_path')) {
    function get_image_path(?string $path) {
        return '../assets/images/' . (empty($path) ? 'logo.png' : basename(trim($path)));
    }
}
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    exit('Only customer accounts can place orders.');
}
$user_id = (int) $_SESSION['user_id'];
$user_stmt = $conn->prepare('SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?');
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();
if (!$user_data) {
    http_response_code(403);
    exit('Account no longer exists. Please sign in again.');
}
$fullname_display = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
$shipping_fee = 100.00;
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    csrf_verify();
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $payment = (string) ($_POST['payment_method'] ?? '');

    if (strlen($fullname) < 2 || strlen($fullname) > 150 ||
        !preg_match('/^[0-9+() -]{7,20}$/', $phone) ||
        strlen($address) < 10 || strlen($address) > 1000) {
        $error_msg = 'Enter a valid recipient name, contact number and complete delivery address.';
    } elseif ($payment !== 'COD') {
        $error_msg = 'Only Cash on Delivery is available. No online payment gateway is connected.';
    } else {
        $conn->begin_transaction();
        try {
            // Recalculate the cart and lock inventory rows; never trust stale HTML totals.
            $select = $conn->prepare('SELECT c.product_id, c.quantity, p.price, p.stock FROM cart c JOIN products p ON p.product_id = c.product_id WHERE c.user_id = ? ORDER BY c.product_id FOR UPDATE');
            $select->bind_param('i', $user_id);
            $select->execute();
            $lockedItems = $select->get_result()->fetch_all(MYSQLI_ASSOC);
            $select->close();
            if (!$lockedItems) {
                throw new DomainException('Your cart is empty. Please add a product first.');
            }

            $freshTotal = 0.0;
            foreach ($lockedItems as $item) {
                $qty = (int) $item['quantity'];
                $stock = (int) $item['stock'];
                if ($qty < 1 || $qty > 10 || $qty > $stock) {
                    throw new DomainException('An item is out of stock or has an invalid quantity. Please update your cart.');
                }
                $freshTotal += ((float) $item['price']) * $qty;
            }
            $freshTotal = round($freshTotal, 2);
            $grandTotal = round($freshTotal + $shipping_fee, 2);

            $orderStmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, recipient_name, contact_phone, delivery_address, payment_method, shipping_fee) VALUES (?, ?, 'Pending', ?, ?, ?, 'COD', ?)");
            $orderStmt->bind_param('idsssd', $user_id, $grandTotal, $fullname, $phone, $address, $shipping_fee);
            $orderStmt->execute();
            $order_id = $conn->insert_id;
            $orderStmt->close();

            $stockStmt = $conn->prepare('UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?');
            $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            foreach ($lockedItems as $item) {
                $pid = (int) $item['product_id'];
                $qty = (int) $item['quantity'];
                $price = (float) $item['price'];
                $stockStmt->bind_param('iii', $qty, $pid, $qty);
                $stockStmt->execute();
                if ($stockStmt->affected_rows !== 1) {
                    throw new DomainException('Stock changed while placing the order. Please review your cart.');
                }
                $itemStmt->bind_param('iiid', $order_id, $pid, $qty, $price);
                $itemStmt->execute();
            }
            $stockStmt->close();
            $itemStmt->close();
            $clear = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
            $clear->bind_param('i', $user_id);
            $clear->execute();
            $clear->close();
            $conn->commit();

            $_SESSION['last_order_id'] = $order_id;
            header('Location: order_success.php?order_id=' . $order_id, true, 303);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Checkout failed: ' . $e->getMessage());
            $error_msg = $e instanceof DomainException ? $e->getMessage() :
                'Unable to place your order right now. Please try again or contact the shop.';
        }
    }
}

$cart_products = [];
$total_amount = 0.0;
$cartStmt = $conn->prepare('SELECT c.quantity, c.product_id, p.title, p.price, p.image, p.stock FROM cart c JOIN products p ON p.product_id = c.product_id WHERE c.user_id = ? ORDER BY c.cart_id');
$cartStmt->bind_param('i', $user_id);
$cartStmt->execute();
$res = $cartStmt->get_result();
while ($row = $res->fetch_assoc()) {
    $qty = (int) $row['quantity'];
    $subtotal = (float) $row['price'] * $qty;
    $total_amount += $subtotal;
    $cart_products[] = [
        'product_id' => (int) $row['product_id'],
        'title' => $row['title'],
        'image' => get_image_path($row['image']),
        'price' => (float) $row['price'],
        'stock' => (int) $row['stock'],
        'quantity' => $qty,
        'subtotal' => $subtotal,
    ];
}
$cartStmt->close();
if (!$cart_products) {
    $_SESSION['cart_notice'] = 'Your cart is empty.';
    header('Location: cart.php');
    exit();
}
$grand_total = $total_amount + $shipping_fee;
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
        <?php echo csrf_field(); ?>
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
                        <input class="custom-control-input" type="radio" name="payment_method" id="payment_cod" value="COD" checked required>
                        <label class="custom-control-label font-weight-bold" for="payment_cod">Cash on Delivery (COD)</label>
                        <div class="text-muted small">Pay in cash when your order arrives. Online payment is not yet available.</div>
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

                    <button type="submit" name="place_order" value="1" class="btn btn-ghibli-primary w-100 py-3 font-weight-bold h5 mb-0">
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