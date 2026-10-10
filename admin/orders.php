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

$message = '';
$message_type = '';

// ==========================================
// CRUD / ORDER STATUS OPERATIONS
// ==========================================

// 1. UPDATE ORDER STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);

    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            $stmt->execute();
        } elseif (isset($pdo)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);
        }

        $_SESSION['flash_message'] = "Order #{$order_id} status updated to successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $message = "Error updating order status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 2. DELETE ORDER
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            // Delete order items first if applicable
            $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
            $conn->query("DELETE FROM orders WHERE order_id = $order_id");
        } elseif (isset($pdo)) {
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$order_id]);
        }

        $_SESSION['flash_message'] = "Order #{$order_id} deleted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $message = "Error deleting order: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Flash Session Messages
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// ==========================================
// FETCH DATA FOR DISPLAY
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch Orders Query
$orders = [];
$query = "SELECT o.*, 
                 CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, '')) AS customer_name,
                 u.email AS customer_email
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.user_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (o.order_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_status)) {
    $query .= " AND o.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query .= " ORDER BY o.order_id DESC";

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare($query);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
} elseif (isset($pdo)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics Cards Calculation
$total_orders = count($orders);
$total_revenue = 0;
$pending_count = 0;
$completed_count = 0;

foreach ($orders as $ord) {
    $status_lower = strtolower($ord['status'] ?? '');
    $total_revenue += floatval($ord['total_amount'] ?? 0);

    if ($status_lower === 'pending') {
        $pending_count++;
    } elseif ($status_lower === 'completed' || $status_lower === 'delivered') {
        $completed_count++;
    }
}

// Admin Display Name (Pareho sa Products)
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
    <title>Orders Management - Spirited Admin</title>

    <!-- FAVICON -->
    <link rel="icon" type="image/png" href="../assets/image/spirited_logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/image/spirited_logo.png">

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
            <div>
                <h1 class="admin-header-title mb-0">Orders Management</h1>
                <small class="text-muted">Track, process, and manage customer merchandise orders.</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="admin-badge-user">
                    Welcome back, <strong><?php echo htmlspecialchars($admin_display); ?></strong> <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show rounded-lg shadow-sm mb-4" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- METRIC CARDS -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-val mt-1"><?= number_format($total_orders) ?></div>
                        </div>
                        <div class="stat-icon-wrapper orders-icon">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-val mt-1" style="color: var(--ghibli-terracotta);">₱<?= number_format($total_revenue, 2) ?></div>
                        </div>
                        <div class="stat-icon-wrapper sales-icon">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Pending Orders</div>
                            <div class="stat-val mt-1 text-warning"><?= number_format($pending_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper messages-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Completed Orders</div>
                            <div class="stat-val mt-1 text-success"><?= number_format($completed_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper products-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="ghibli-card-table mb-4 p-3">
            <form method="GET" class="form-row align-items-center">
                <div class="col-md-6 my-1">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Search by Order ID or Customer Name..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4 my-1">
                    <select name="status" class="form-control">
                        <option value="">All Order Statuses</option>
                        <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $filter_status === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped" <?= $filter_status === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 my-1 d-flex gap-2">
                    <button type="submit" class="btn btn-ghibli-outline w-100"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="orders.php" class="btn btn-light border ml-1" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>

        <!-- ORDERS TABLE CARD -->
        <div class="ghibli-card-table">
            <div class="table-responsive rounded-lg">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td><strong style="color: var(--ghibli-forest);">#<?= htmlspecialchars($ord['order_id']) ?></strong></td>
                                    <td>
                                        <div class="font-weight-600 text-dark">
                                            <?= !empty(trim($ord['customer_name'])) ? htmlspecialchars($ord['customer_name']) : 'Guest Customer' ?>
                                        </div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($ord['customer_email'] ?? 'No email') ?></small>
                                    </td>
                                    <td>
                                        <small class="text-dark font-weight-500">
                                            <?= date('M d, Y', strtotime($ord['created_at'] ?? $ord['order_date'] ?? 'now')) ?>
                                        </small>
                                        <small class="text-muted d-block"><?= date('h:i A', strtotime($ord['created_at'] ?? $ord['order_date'] ?? 'now')) ?></small>
                                    </td>
                                    <td class="font-weight-bold" style="color: var(--ghibli-terracotta);">
                                        ₱<?= number_format($ord['total_amount'] ?? 0, 2) ?>
                                    </td>
                                    <td>
                                        <span class="badge-ghibli-pill badge-info-ghibli">
                                            <?= htmlspecialchars($ord['payment_method'] ?? 'COD') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $st = strtolower($ord['status'] ?? 'pending');
                                            $badge_class = 'badge-pending';
                                            if ($st === 'completed' || $st === 'delivered') $badge_class = 'badge-completed';
                                            elseif ($st === 'cancelled') $badge_class = 'badge-cancelled';
                                            elseif ($st === 'processing' || $st === 'shipped') $badge_class = 'badge-info-ghibli';
                                        ?>
                                        <span class="badge-ghibli-pill <?= $badge_class ?>">
                                            <?= ucfirst(htmlspecialchars($ord['status'] ?? 'Pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <!-- Edit Status Button -->
                                        <button class="btn-action-icon edit-status-btn border-0 bg-transparent" 
                                                data-toggle="modal" 
                                                data-target="#editStatusModal"
                                                data-id="<?= $ord['order_id'] ?>"
                                                data-status="<?= htmlspecialchars($ord['status'] ?? 'Pending') ?>">
                                            <i class="fa-solid fa-pen-to-square" title="Update Status"></i>
                                        </button>
                                        
                                        <!-- Delete Order -->
                                        <a href="orders.php?action=delete&id=<?= $ord['order_id'] ?>" 
                                           class="btn-action-icon text-danger ml-1" 
                                           onclick="return confirm('Are you sure you want to delete Order #<?= $ord['order_id'] ?>?');">
                                            <i class="fa-solid fa-trash" title="Delete Order"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <em>No orders found in the database.</em>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     MODAL: EDIT ORDER STATUS
     ============================================================ -->
<div class="modal fade" id="editStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-lg border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold" style="color: var(--ghibli-forest);">
                    <i class="fa-solid fa-truck-ramp-box mr-2 text-warning"></i>Update Order Status
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="orders.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="status_order_id">
                
                <div class="modal-body p-4">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Order Status</label>
                        <select name="status" id="status_select" class="form-control" required>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pr-4 pb-4">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ghibli-outline"><i class="fa-solid fa-floppy-disk mr-1"></i> Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editButtons = document.querySelectorAll('.edit-status-btn');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('status_order_id').value = this.dataset.id;
                document.getElementById('status_select').value = this.dataset.status;
            });
        });
    });
</script>
</body>
</html>