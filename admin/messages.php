<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// AUTH CHECK: Admin Access Only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) ||$_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';$message_type = '';

// ==========================================
// CRUD / MESSAGE ACTIONS
// ==========================================

// 1. UPDATE MESSAGE STATUS (Mark as Read / Replied / Unread)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'update_status') {
    $msg_id = intval($_POST['message_id']);
    $new_status = trim($_POST['status']);

    try {
        if (isset($conn) &&$conn instanceof mysqli) {
            $stmt =$conn->prepare("UPDATE contact_messages SET status = ? WHERE message_id = ?");
            if (!$stmt) {
                // Fallback kung 'id' ang pangalan ng column sa halip na 'message_id'
                $stmt =$conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            }
            $stmt->bind_param("si", $new_status, $msg_id);$stmt->execute();
        } elseif (isset($pdo)) {
            try {
                $stmt =$pdo->prepare("UPDATE contact_messages SET status = ? WHERE message_id = ?");
                $stmt->execute([$new_status,$msg_id]);
            } catch (Exception $ex) {
                $stmt =$pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
                $stmt->execute([$new_status,$msg_id]);
            }
        }

        $_SESSION['flash_message'] = "Message status updated to '{$new_status}' successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: messages.php");
        exit();
    } catch (Exception $e) {$message = "Error updating message: " . $e->getMessage();$message_type = "danger";
    }
}

// 2. DELETE MESSAGE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $msg_id = intval($_GET['id']);
    try {
        if (isset($conn) && $conn instanceof mysqli) {$conn->query("DELETE FROM contact_messages WHERE message_id = $msg_id OR id =$msg_id");
        } elseif (isset($pdo)) {$pdo->prepare("DELETE FROM contact_messages WHERE message_id = ? OR id = ?")->execute([$msg_id,$msg_id]);
        }

        $_SESSION['flash_message'] = "Message deleted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: messages.php");
        exit();
    } catch (Exception $e) {$message = "Error deleting message: " . $e->getMessage();$message_type = "danger";
    }
}

// Flash Session Messages
if (isset($_SESSION['flash_message'])) {
    $message =$_SESSION['flash_message'];
    $message_type =$_SESSION['flash_type'];
    unset($_SESSION['flash_message'],$_SESSION['flash_type']);
}

// ==========================================
// FETCH DATA FOR DISPLAY
// ==========================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch Messages Query
$messages_list = [];$query = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];$types = "";

if (!empty($search)) {$query .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "\%$search%";
    $params = [$search_param, $search_param,$search_param, $search_param];$types = "ssss";
}

if (!empty($filter_status)) {$query .= " AND status = ?";
    $params[] =$filter_status;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

if (isset($conn) && $conn instanceof mysqli) {$stmt = $conn->prepare($query);
    if ($params) $stmt->bind_param($types, ...$params);$stmt->execute();
    $res =$stmt->get_result();
    while ($row =$res->fetch_assoc()) $messages_list[] =$row;
} elseif (isset($pdo)) {$stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages_list =$stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics Cards Calculation
$total_messages = count($messages_list);$unread_count = 0;
$read_count = 0;
$replied_count = 0;

foreach ($messages_list as$msg) {
    $st = strtolower($msg['status'] ?? 'unread');
    if ($st === 'unread' || empty($st)) {$unread_count++;
    } elseif ($st === 'read') {$read_count++;
    } elseif ($st === 'replied') {$replied_count++;
    }
}

// Admin Display Name
$admin_display = 'Admin';
if (!empty($_SESSION['first_name'])) {$admin_display = trim($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? ''));
} elseif (!empty($_SESSION['email'])) {
    $admin_display =$_SESSION['email'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages & Inquiries - Spirited Admin</title>

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
                <h1 class="admin-header-title mb-0">Messages & Inquiries</h1>
                <small class="text-muted">Manage customer contact submissions, feedback, and support tickets.</small>
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
                            <div class="stat-label">Total Messages</div>
                            <div class="stat-val mt-1"><?= number_format($total_messages) ?></div>
                        </div>
                        <div class="stat-icon-wrapper messages-icon">
                            <i class="fa-solid fa-envelope-open-text"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Unread Messages</div>
                            <div class="stat-val mt-1 text-warning"><?= number_format($unread_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper orders-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Read Messages</div>
                            <div class="stat-val mt-1 text-info"><?= number_format($read_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper sales-icon">
                            <i class="fa-solid fa-envelope-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Replied</div>
                            <div class="stat-val mt-1 text-success"><?= number_format($replied_count) ?></div>
                        </div>
                        <div class="stat-icon-wrapper products-icon">
                            <i class="fa-solid fa-reply-all"></i>
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
                        <input type="text" name="search" class="form-control border-left-0" placeholder="Search sender, email, subject, or message..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4 my-1">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Unread" <?= $filter_status === 'Unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="Read" <?= $filter_status === 'Read' ? 'selected' : '' ?>>Read</option>
                        <option value="Replied" <?= $filter_status === 'Replied' ? 'selected' : '' ?>>Replied</option>
                    </select>
                </div>
                <div class="col-md-2 my-1 d-flex gap-2">
                    <button type="submit" class="btn btn-ghibli-outline w-100"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="messages.php" class="btn btn-light border ml-1" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>

        <!-- MESSAGES TABLE CARD -->
        <div class="ghibli-card-table">
            <div class="table-responsive rounded-lg">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sender Name</th>
                            <th>Subject</th>
                            <th>Date Sent</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($messages_list) > 0): ?>
                            <?php foreach ($messages_list as$msg): ?>
                                <?php 
                                    $id = $msg['message_id'] ?? $msg['id'] ?? 0;
                                    $st = strtolower($msg['status'] ?? 'unread');$is_unread = ($st === 'unread' || empty($st));
                                ?>
                                <tr class="<?= $is_unread ? 'font-weight-bold bg-light' : '' ?>">
                                    <td><strong style="color: var(--ghibli-forest);">#<?= $id ?></strong></td>
                                    <td>
                                        <div class="font-weight-600 text-dark"><?= htmlspecialchars($msg['name'] ?? 'Anonymous') ?></div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($msg['email'] ?? 'No email provided') ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 280px; color: var(--ghibli-forest);">
                                            <?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?>
                                        </div>
                                        <small class="text-muted d-block text-truncate" style="max-width: 280px;">
                                            <?= htmlspecialchars($msg['message'] ?? '') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-dark font-weight-500">
                                            <?= date('M d, Y', strtotime($msg['created_at'] ?? 'now')) ?>
                                        </small>
                                        <small class="text-muted d-block"><?= date('h:i A', strtotime($msg['created_at'] ?? 'now')) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($st === 'replied'): ?>
                                            <span class="badge-ghibli-pill badge-completed"><i class="fa-solid fa-check-double mr-1"></i> Replied</span>
                                        <?php elseif ($st === 'read'): ?>
                                            <span class="badge-ghibli-pill badge-info-ghibli"><i class="fa-solid fa-envelope-open mr-1"></i> Read</span>
                                        <?php else: ?>
                                            <span class="badge-ghibli-pill badge-pending"><i class="fa-solid fa-envelope mr-1"></i> Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- View Message Modal Trigger -->
                                        <button class="btn-action-icon view-msg-btn border-0 bg-transparent text-primary" 
                                                data-toggle="modal" 
                                                data-target="#viewMessageModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= htmlspecialchars($msg['name'] ?? 'Anonymous') ?>"
                                                data-email="<?= htmlspecialchars($msg['email'] ?? '') ?>"
                                                data-subject="<?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?>"
                                                data-date="<?= date('M d, Y h:i A', strtotime($msg['created_at'] ?? 'now')) ?>"
                                                data-status="<?= htmlspecialchars($msg['status'] ?? 'Unread') ?>"
                                                data-message="<?= htmlspecialchars($msg['message'] ?? '') ?>">
                                            <i class="fa-solid fa-eye" title="View Details"></i>
                                        </button>

                                        <!-- Delete Message -->
                                        <a href="messages.php?action=delete&id=<?= $id ?>" 
                                           class="btn-action-icon text-danger ml-1" 
                                           onclick="return confirm('Are you sure you want to delete this message?');">
                                            <i class="fa-solid fa-trash" title="Delete Message"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <em>No contact messages found.</em>
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
     MODAL: VIEW & REPLY TO MESSAGE
     ============================================================ -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-lg border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold" style="color: var(--ghibli-forest);">
                    <i class="fa-solid fa-envelope-open-text mr-2 text-warning"></i>Message Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Sender Name:</small>
                        <strong id="modal_sender_name" class="font-weight-600 text-dark"></strong>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Email Address:</small>
                        <a id="modal_sender_email_link" href="#" class="font-weight-600" style="color: var(--ghibli-terracotta);">
                            <span id="modal_sender_email"></span>
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Subject:</small>
                        <span id="modal_subject" class="font-weight-600 text-dark"></span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Date Received:</small>
                        <span id="modal_date" class="text-muted"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-600 text-muted">Message Content:</label>
                    <div id="modal_message_body" class="p-3 rounded border bg-light" style="white-space: pre-wrap; font-size: 0.95rem; min-height: 120px;"></div>
                </div>

                <hr style="border-color: var(--ghibli-soft-border);">

                <!-- Status Update Form -->
                <form action="messages.php" method="POST" class="form-inline justify-content-between align-items-center">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="message_id" id="modal_message_id">

                    <div class="d-flex align-items-center gap-2">
                        <label class="font-weight-600 mr-2 mb-0">Update Status:</label>
                        <select name="status" id="modal_status_select" class="form-control form-control-sm">
                            <option value="Unread">Unread</option>
                            <option value="Read">Read</option>
                            <option value="Replied">Replied</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-ghibli-outline ml-2">Save Status</button>
                    </div>

                    <a id="modal_mailto_btn" href="#" class="btn btn-sm btn-outline-success mt-2 mt-sm-0">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Send Reply via Email
                    </a>
                </form>
            </div>
            
            <div class="modal-footer border-0 pt-0 pr-4 pb-4">
                <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const viewButtons = document.querySelectorAll('.view-msg-btn');
        
        viewButtons.forEach(button => {
            button.addEventListener('click', function () {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const email = this.dataset.email;
                const subject = this.dataset.subject;
                const date = this.dataset.date;
                const status = this.dataset.status;
                const msgText = this.dataset.message;

                document.getElementById('modal_message_id').value = id;
                document.getElementById('modal_sender_name').textContent = name;
                document.getElementById('modal_sender_email').textContent = email;
                document.getElementById('modal_sender_email_link').href = 'mailto:' + email;
                document.getElementById('modal_subject').textContent = subject;
                document.getElementById('modal_date').textContent = date;
                document.getElementById('modal_message_body').textContent = msgText;
                document.getElementById('modal_status_select').value = status;
                
                // Set mailto subject and body prefill for quick email reply
                const mailtoUrl = `mailto:${email}?subject=Re: ${encodeURIComponent(subject)}&body=${encodeURIComponent("\n\n--- Original Message ---\n" + msgText)}`;
                document.getElementById('modal_mailto_btn').href = mailtoUrl;
            });
        });
    });
</script>
</body>
</html>