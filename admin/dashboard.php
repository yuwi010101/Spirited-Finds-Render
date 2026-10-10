<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// AUTH CHECK: Admin Access Only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. Total Sales
$sales_query = "SELECT SUM(total_amount) AS total_sales FROM orders WHERE status != 'Cancelled'";
$sales_res = $conn->query($sales_query);
$total_sales = ($sales_res && $row = $sales_res->fetch_assoc()) ? ($row['total_sales'] ?? 0) : 0;

// 2. Total Orders
$orders_query = "SELECT COUNT(*) AS total_orders FROM orders";
$orders_res = $conn->query($orders_query);
$total_orders = ($orders_res && $row = $orders_res->fetch_assoc()) ? ($row['total_orders'] ?? 0) : 0;

// 3. Total Products
$products_query = "SELECT COUNT(*) AS total_products FROM products";
$products_res = $conn->query($products_query);
$total_products = ($products_res && $row = $products_res->fetch_assoc()) ? ($row['total_products'] ?? 0) : 0;

// 4. Total Messages / Inquiries
$messages_query = "SELECT COUNT(*) AS total_messages FROM contact_messages";
$messages_res = $conn->query($messages_query);
$total_messages = ($messages_res && $row = $messages_res->fetch_assoc()) ? ($row['total_messages'] ?? 0) : 0;

// Recent Orders (Top 5)
$recent_orders_query = "
    SELECT 
        o.order_id, 
        o.total_amount, 
        o.status, 
        o.created_at,
        u.email,
        TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS customer_name
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    ORDER BY o.order_id DESC 
    LIMIT 5
";
$recent_orders = $conn->query($recent_orders_query);

// Admin Display Name
$admin_display = 'Admin';
if (!empty($_SESSION['first_name'])) {
    $admin_display = trim($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? ''));
} elseif (!empty($_SESSION['email'])) {
    $admin_display = $_SESSION['email'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Spirited Finds</title>
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap">
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Admin Style -->
    <link rel="stylesheet" href="../assets/global/admin-style.css">
</head>
<body>

<div class="container-fluid p-0">
    <!-- REUSABLE FLOATING SIDEBAR -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT FLOATING CANVAS CONTAINER -->
    <div class="main-content-wrapper">
        <!-- TOP BAR / HEADER -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom" style="border-color: var(--ghibli-soft-border) !important;">
            <h1 class="admin-header-title">Dashboard Overview</h1>
            <div class="admin-badge-user">
                Welcome back, <strong><?php echo htmlspecialchars($admin_display); ?></strong> <span>Admin</span>
            </div>
        </div>

        <!-- METRIC CARDS -->
        <div class="row mb-4">
            <!-- Total Sales -->
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-val mt-1">₱<?php echo number_format($total_sales, 2); ?></div>
                        </div>
                        <div class="stat-icon-wrapper sales-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-val mt-1"><?php echo number_format($total_orders); ?></div>
                        </div>
                        <div class="stat-icon-wrapper orders-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Products -->
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Products</div>
                            <div class="stat-val mt-1"><?php echo number_format($total_products); ?></div>
                        </div>
                        <div class="stat-icon-wrapper products-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Messages</div>
                            <div class="stat-val mt-1"><?php echo number_format($total_messages); ?></div>
                        </div>
                        <div class="stat-icon-wrapper messages-icon">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT ORDERS TABLE CARD -->
        <div class="ghibli-card-table">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 font-weight-bold" style="font-size: 1.4rem;">
                    <i class="far fa-clock mr-2" style="color: var(--ghibli-terracotta);"></i> Recent Orders
                </h4>
                <a href="orders.php" class="btn-ghibli-outline">View All Orders</a>
            </div>

            <div class="table-responsive rounded-lg">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <?php 
                                    $display_name = !empty($order['customer_name']) ? $order['customer_name'] : (!empty($order['email']) ? $order['email'] : 'Guest Customer');
                                ?>
                                <tr>
                                    <td><strong style="color: var(--ghibli-forest);">#<?php echo $order['order_id']; ?></strong></td>
                                    <td class="font-weight-600"><?php echo htmlspecialchars($display_name); ?></td>
                                    <td class="font-weight-bold" style="color: var(--ghibli-terracotta); font-family: 'Fredoka', cursive; font-size: 1.05rem;">
                                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = strtolower($order['status'] ?? 'pending');
                                        $badge_class = 'badge-pending';
                                        if ($status === 'completed') $badge_class = 'badge-completed';
                                        elseif ($status === 'cancelled') $badge_class = 'badge-cancelled';
                                        elseif ($status === 'shipped' || $status === 'processing') $badge_class = 'badge-info-ghibli';
                                        ?>
                                        <span class="badge-ghibli-pill <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($order['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></small></td>
                                    <td>
                                        <a href="orders.php?id=<?php echo $order['order_id']; ?>" class="btn-action-icon" title="Manage Order">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <em>No recent orders found.</em>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>