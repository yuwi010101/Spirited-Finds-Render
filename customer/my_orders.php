<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ikinonekta base sa Tree Structure (my_orders.php ay nasa customer/ folder)
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
$user_role = $_SESSION['role'] ?? '';

// RBAC IMPLEMENTATION:
// 1. Pigilan ang Admin na ma-access ang pahina ng order ng customer
if ($is_logged_in && $user_role === 'admin') {
    header("Location: ../admin/orders.php");
    exit();
}

$orders = [];

if ($is_logged_in) {
    // RBAC SECURITY: Kuhanin LAMANG ang orders na pagmamay-ari ng kasalukuyang logged-in user_id
    $stmt = $conn->prepare("
        SELECT order_id, total_amount, status, created_at 
        FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $order_id = $row['order_id'];

        // Kuhanin ang mga items para sa bawat order
        $item_stmt = $conn->prepare("
            SELECT oi.quantity, oi.price, p.title, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = ?
        ");
        $item_stmt->bind_param("i", $order_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();

        $items = [];
        while ($item = $item_result->fetch_assoc()) {
            $items[] = [
                'title'    => $item['title'] ?? 'Untitled Product',
                'image'    => get_image_path($item['image']),
                'quantity' => $item['quantity'],
                'price'    => $item['price']
            ];
        }
        $item_stmt->close();

        $row['items'] = $items;
        $orders[] = $row;
    }
    $stmt->close();
}

// Isinama base sa Tree Structure
include '../includes/header.php';
?>

<!-- CUSTOMER STYLE LINK -->
<link rel="stylesheet" href="../assets/global/customer-style.css">

<div class="container cart-page-wrapper" style="min-height: 60vh;">
    <h2 class="cart-title mb-4"><i class="fas fa-box text-ghibli-accent mr-2"></i> My Orders</h2>

    <?php if (!$is_logged_in): ?>
        <!-- GUEST STATE: KAILANGAN MAG-LOGIN -->
        <div class="card cart-auth-card p-5 text-center my-5 mx-auto" style="max-width: 500px;">
            <div class="mb-3">
                <i class="fas fa-user-lock fa-4x text-muted"></i>
            </div>
            <h3 class="font-weight-bold mb-2" style="font-family: 'Fredoka', cursive; color: var(--ghibli-forest);">Authentication Required</h3>
            <p class="text-muted mb-4">Please log in to view your order history.</p>
            <div class="d-grid gap-2">
                <a href="../login.php?redirect=customer/my_orders.php" class="btn btn-ghibli-primary py-2 font-weight-bold mb-2">Log In</a>
                <a href="../signup.php" class="btn btn-outline-secondary py-2 font-weight-bold">Create an Account</a>
            </div>
        </div>

    <?php elseif (!empty($orders)): ?>
        <!-- LOGGED-IN STATE: IPAPAKITA ANG LISTAHAN NG ORDERS -->
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-12 mb-4">
                    <div class="card cart-table-card">
                        <!-- ORDER HEADER -->
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-3" style="background-color: var(--ghibli-warm-paper, #F5EFE0); border-bottom: 2px solid var(--ghibli-soft-border, #E8DFCE);">
                            <div>
                                <span class="text-muted small">Order ID:</span>
                                <strong class="text-dark">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <span class="text-muted mx-2">|</span>
                                <span class="text-muted small">Date Placed:</span>
                                <small class="text-muted"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></small>
                            </div>
                            <div>
                                <?php
                                $status_class = 'bg-secondary';
                                switch ($order['status']) {
                                    case 'Pending':    $status_class = 'bg-warning text-dark'; break;
                                    case 'Processing': $status_class = 'bg-info text-white'; break;
                                    case 'Shipped':    $status_class = 'bg-primary text-white'; break;
                                    case 'Completed':  $status_class = 'bg-success text-white'; break;
                                    case 'Cancelled':  $status_class = 'bg-danger text-white'; break;
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?> px-3 py-2 rounded-pill font-weight-normal">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- ORDER ITEMS -->
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($order['items'] as $item): ?>
                                    <li class="list-group-item d-flex align-items-center justify-content-between p-3 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                                 class="cart-item-img mr-3"
                                                 onerror="this.onerror=null; this.src='../assets/images/logo.png';">
                                            <div>
                                                <h6 class="mb-1 font-weight-bold text-dark"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                <small class="text-muted">Qty: <?php echo $item['quantity']; ?> × ₱<?php echo number_format($item['price'], 2); ?></small>
                                            </div>
                                        </div>
                                        <div class="font-weight-bold text-dark">
                                            ₱<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- ORDER FOOTER -->
                        <div class="card-footer bg-white text-right py-3 border-top-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-shipping-fast text-success mr-1"></i> Standard Shipping Included</span>
                            <div>
                                <span class="text-muted mr-2">Total Amount:</span>
                                <strong class="h5 font-weight-bold text-ghibli-accent mb-0">₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- EMPTY ORDERS STATE -->
        <div class="text-center py-5 cart-empty-card p-4">
            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
            <h3 style="font-family: 'Fredoka', cursive; color: var(--ghibli-forest);">No Orders Found</h3>
            <p class="text-muted">You haven't placed any orders yet.</p>
            <a href="../index.php" class="btn btn-ghibli-primary mt-2">Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<?php 
// Isinama base sa Tree Structure
include '../includes/footer.php'; 
?>