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
// CRUD OPERATIONS (MySQLi / PDO Compatible)
// ==========================================

// Helper function for DB query handling
function db_query(string $query, array $params = [], string $types = "") {
    global $conn, $pdo;
    if (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare($query);
        if ($params && $types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    } elseif (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }
    return false;
}

// 1. ADD NEW PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $rating = intval($_POST['rating']);
    $description = trim($_POST['description']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_special = isset($_POST['is_special']) ? 1 : 0;
    
    // Image Upload Handling
    $image_path = './images/default.png';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img_name = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "../images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . $img_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = './images/' . $img_name;
        }
    } elseif (!empty($_POST['image_url'])) {
        $image_path = trim($_POST['image_url']);
    }

    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("INSERT INTO products (category_id, title, description, price, image, rating, stock, is_featured, is_special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdsiiii", $category_id, $title, $description, $price, $image_path, $rating, $stock, $is_featured, $is_special);
            $stmt->execute();
        } elseif (isset($pdo)) {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, title, description, price, image, rating, stock, is_featured, is_special) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $title, $description, $price, $image_path, $rating, $stock, $is_featured, $is_special]);
        }
        
        $_SESSION['flash_message'] = "Product added successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: products.php");
        exit();
    } catch (Exception $e) {
        $message = "Error adding product: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 2. EDIT PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = intval($_POST['product_id']);
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $rating = intval($_POST['rating']);
    $description = trim($_POST['description']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_special = isset($_POST['is_special']) ? 1 : 0;
    
    // Fetch existing image path
    $existing_img = './images/default.png';
    if (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query("SELECT image FROM products WHERE product_id = $product_id");
        if ($res && $row = $res->fetch_assoc()) $existing_img = $row['image'];
    } elseif (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT image FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $row = $stmt->fetch();
        if ($row) $existing_img = $row['image'];
    }
    $image_path = $existing_img;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img_name = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "../images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . $img_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = './images/' . $img_name;
        }
    } elseif (!empty($_POST['image_url'])) {
        $image_path = trim($_POST['image_url']);
    }

    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, image = ?, rating = ?, stock = ?, is_featured = ?, is_special = ? WHERE product_id = ?");
            $stmt->bind_param("issdsiiiii", $category_id, $title, $description, $price, $image_path, $rating, $stock, $is_featured, $is_special, $product_id);
            $stmt->execute();
        } elseif (isset($pdo)) {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, image = ?, rating = ?, stock = ?, is_featured = ?, is_special = ? WHERE product_id = ?");
            $stmt->execute([$category_id, $title, $description, $price, $image_path, $rating, $stock, $is_featured, $is_special, $product_id]);
        }
        
        $_SESSION['flash_message'] = "Product updated successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: products.php");
        exit();
    } catch (Exception $e) {
        $message = "Error updating product: " . $e->getMessage();
        $message_type = "danger";
    }
}

// 3. DELETE PRODUCT
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->query("DELETE FROM products WHERE product_id = $product_id");
        } elseif (isset($pdo)) {
            $pdo->prepare("DELETE FROM products WHERE product_id = ?")->execute([$product_id]);
        }
        
        $_SESSION['flash_message'] = "Product deleted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: products.php");
        exit();
    } catch (Exception $e) {
        $message = "Cannot delete product. It may be linked to an existing order.";
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
$filter_cat = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Fetch Categories
$categories = [];
if (isset($conn) && $conn instanceof mysqli) {
    $cat_res = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
    if ($cat_res) {
        while ($row = $cat_res->fetch_assoc()) $categories[] = $row;
    }
} elseif (isset($pdo)) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Products
$products = [];
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($filter_cat > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $filter_cat;
    $types .= "i";
}

$query .= " ORDER BY p.product_id DESC";

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare($query);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $products[] = $row;
} elseif (isset($pdo)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics Cards Calculation
$total_products = count($products);
$total_stock = 0;
$featured_count = 0;
$low_stock_count = 0;

foreach ($products as $p) {
    $total_stock += intval($p['stock']);
    if (!empty($p['is_featured'])) $featured_count++;
    if (intval($p['stock']) <= 5) $low_stock_count++;
}

// Admin Display Name (Pareho sa Dashboard)
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
    <title>Products Management - Spirited Admin</title>

    <!-- FAVICON (Gaya ng sa index.php) -->
    <link rel="icon" type="image/png" href="../assets/image/spirited_logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/image/spirited_logo.png">
    
    <!-- Google Fonts (Gaya ng sa Dashboard Reference) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap">
    <!-- Bootstrap 4.6 / 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Admin Style -->
    <link rel="stylesheet" href="../assets/global/admin-style.css">
</head>
<body>

<div class="container-fluid p-0">
    <!-- REUSABLE FLOATING SIDEBAR (Gaya ng sa Dashboard Reference) -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT FLOATING CANVAS CONTAINER -->
    <div class="main-content-wrapper">
        
        <!-- TOP BAR / HEADER -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom" style="border-color: var(--ghibli-soft-border) !important;">
            <div>
                <h1 class="admin-header-title mb-0">Products Management</h1>
                <small class="text-muted">Manage your Ghibli merchandise catalog and inventory.</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-ghibli-outline mr-3" data-toggle="modal" data-target="#addProductModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Product
                </button>
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

        <!-- METRIC CARDS (Gaya ng sa Dashboard Reference) -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Products</div>
                            <div class="stat-val mt-1"><?= number_format($total_products) ?></div>
                        </div>
                        <div class="stat-icon-wrapper products-icon">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Stock</div>
                            <div class="stat-val mt-1"><?= number_format($total_stock) ?></div>
                        </div>
                        <div class="stat-icon-wrapper orders-icon">
                            <i class="fa-solid fa-cubes"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Featured Items</div>
                            <div class="stat-val mt-1"><?= number_format($featured_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper sales-icon">
                            <i class="fa-solid fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Low Stock (≤5)</div>
                            <div class="stat-val mt-1 text-danger"><?= number_format($low_stock_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper messages-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
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
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Search product name..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4 my-1">
                    <select name="category" class="form-control">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $filter_cat == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 my-1 d-flex gap-2">
                    <button type="submit" class="btn btn-ghibli-outline w-100"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="products.php" class="btn btn-light border ml-1" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>

        <!-- PRODUCTS TABLE CARD -->
        <div class="ghibli-card-table">
            <div class="table-responsive rounded-lg">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Badges</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td><strong style="color: var(--ghibli-forest);">#<?= $prod['product_id'] ?></strong></td>
                                    <td>
                                        <?php 
                                            $img_src = $prod['image'];
                                            if (strpos($img_src, './image/') === 0) {
                                                $img_src = '../' . substr($img_src, 2);
                                            }
                                        ?>
                                        <img src="<?= htmlspecialchars($img_src) ?>" alt="Product" style="width: 45px; height: 45px; object-fit: cover;" class="rounded border" onerror="this.src='https://placehold.co/50x50?text=No+Img'">
                                    </td>
                                    <td>
                                        <div class="font-weight-600 text-dark"><?= htmlspecialchars($prod['title']) ?></div>
                                        <small class="text-muted d-block text-truncate" style="max-width: 220px;"><?= htmlspecialchars($prod['description']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-ghibli-pill badge-info-ghibli">
                                            <?= htmlspecialchars($prod['category_name'] ?? 'No Category') ?>
                                        </span>
                                    </td>
                                    <td class="font-weight-bold" style="color: var(--ghibli-terracotta);">₱<?= number_format($prod['price'], 2) ?></td>
                                    <td>
                                        <?php if ($prod['stock'] > 10): ?>
                                            <span class="badge-ghibli-pill badge-completed"><?= $prod['stock'] ?> in stock</span>
                                        <?php elseif ($prod['stock'] > 0): ?>
                                            <span class="badge-ghibli-pill badge-pending"><?= $prod['stock'] ?> low stock</span>
                                        <?php else: ?>
                                            <span class="badge-ghibli-pill badge-cancelled">Out of stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['is_featured'])): ?>
                                            <span class="badge badge-warning text-dark"><i class="fa-solid fa-star"></i> Featured</span>
                                        <?php endif; ?>
                                        <?php if (!empty($prod['is_special'])): ?>
                                            <span class="badge badge-danger"><i class="fa-solid fa-wand-magic-sparkles"></i> Special</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-action-icon edit-btn border-0 bg-transparent" 
                                                data-toggle="modal" 
                                                data-target="#editProductModal"
                                                data-id="<?= $prod['product_id'] ?>"
                                                data-title="<?= htmlspecialchars($prod['title']) ?>"
                                                data-category="<?= $prod['category_id'] ?>"
                                                data-price="<?= $prod['price'] ?>"
                                                data-stock="<?= $prod['stock'] ?>"
                                                data-rating="<?= $prod['rating'] ?>"
                                                data-featured="<?= $prod['is_featured'] ?>"
                                                data-special="<?= $prod['is_special'] ?>"
                                                data-image="<?= htmlspecialchars($prod['image']) ?>"
                                                data-description="<?= htmlspecialchars($prod['description']) ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <a href="products.php?action=delete&id=<?= $prod['product_id'] ?>" 
                                           class="btn-action-icon text-danger ml-1" 
                                           onclick="return confirm('Are you sure you want to delete this product?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <em>No products found in the database.</em>
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
     MODAL: ADD NEW PRODUCT
     ============================================================ -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-plus-circle mr-2 text-warning"></i>Add New Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="products.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Product Name</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Totoro Plushie">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control" required>
                                <option value="" disabled selected>Select...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Price (₱)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock" class="form-control" required value="10">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Rating (1-5)</label>
                            <input type="number" min="1" max="5" name="rating" class="form-control" value="5">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Upload Image File</label>
                            <input type="file" name="image" class="form-control-file" accept="image/*">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Or Image Relative Path / URL</label>
                            <input type="text" name="image_url" class="form-control" placeholder="./image/sample.png">
                        </div>
                        <div class="col-12 form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Enter product details..."></textarea>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="ghibli-checkbox-card">
                                <input type="checkbox" name="is_featured" id="add_featured" value="1">
                                <span class="font-weight-600"><i class="fa-solid fa-star text-warning mr-1"></i> Featured Product</span>
                            </label>
                            <label class="ghibli-checkbox-card">
                                <input type="checkbox" name="is_special" id="add_special" value="1">
                                <span class="font-weight-600"><i class="fa-solid fa-wand-magic-sparkles text-danger mr-1"></i> Special Edition</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ghibli-submit"><i class="fa-solid fa-floppy-disk mr-1"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: EDIT PRODUCT
     ============================================================ -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square mr-2 text-warning"></i>Edit Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="products.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Product Name</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Category</label>
                            <select name="category_id" id="edit_category_id" class="form-control" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Price (₱)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Rating (1-5)</label>
                            <input type="number" min="1" max="5" name="rating" id="edit_rating" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Replace Image File</label>
                            <input type="file" name="image" class="form-control-file" accept="image/*">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Or Image Relative Path / URL</label>
                            <input type="text" name="image_url" id="edit_image_url" class="form-control">
                        </div>
                        <div class="col-12 form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="ghibli-checkbox-card">
                                <input type="checkbox" name="is_featured" id="edit_featured" value="1">
                                <span class="font-weight-600"><i class="fa-solid fa-star text-warning mr-1"></i> Featured Product</span>
                            </label>
                            <label class="ghibli-checkbox-card">
                                <input type="checkbox" name="is_special" id="edit_special" value="1">
                                <span class="font-weight-600"><i class="fa-solid fa-wand-magic-sparkles text-danger mr-1"></i> Special Edition</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ghibli-submit"><i class="fa-solid fa-rotate mr-1"></i> Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editButtons = document.querySelectorAll('.edit-btn');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('edit_product_id').value = this.dataset.id;
                document.getElementById('edit_title').value = this.dataset.title;
                document.getElementById('edit_category_id').value = this.dataset.category;
                document.getElementById('edit_price').value = this.dataset.price;
                document.getElementById('edit_stock').value = this.dataset.stock;
                document.getElementById('edit_rating').value = this.dataset.rating;
                document.getElementById('edit_image_url').value = this.dataset.image;
                document.getElementById('edit_description').value = this.dataset.description;
                
                document.getElementById('edit_featured').checked = this.dataset.featured == 1;
                document.getElementById('edit_special').checked = this.dataset.special == 1;
            });
        });
    });
</script>
</body>
</html>