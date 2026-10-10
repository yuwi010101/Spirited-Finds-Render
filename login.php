<?php
session_start();

// Include DB configuration
include 'config/db.php';

// Safe Helper Function para sa Image Paths
if (!function_exists('get_image_path')) {
    function get_image_path(string $image_name = '') {
        if (empty($image_name)) {
            return 'assets/images/logo.png';
        }
        $image_name = trim($image_name);
        if (strpos($image_name, 'assets/') === 0) {
            return $image_name;
        }
        if (strpos($image_name, 'images/') === 0) {
            return 'assets/' . $image_name;
        }
        return 'assets/images/' . $image_name;
    }
}

// Redirect kung nakalog-in na
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error_msg = "";
$success_msg = "";

if (isset($_GET['signup']) && $_GET['signup'] === 'success') {
    $success_msg = "Account created successfully! Please log in.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verifies hashed password or fallback plain text
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_msg = "Incorrect password. Please try again.";
            }
        } else {
            $error_msg = "No account is registered with this email address.";
        }
        $stmt->close();
    } else {
        $error_msg = "Please fill in all fields.";
    }
}

// Include Header
include 'includes/header.php';
?>

<!-- Naka-link ang hiwalay na Authentication CSS -->
<link rel="stylesheet" href="assets/global/auth.css">

<style>
    .auth-input-group {
        position: relative;
    }
    /* Style para sa password toggle icon */
    .password-toggle-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #6c757d;
        z-index: 10;
        transition: color 0.2s ease;
    }
    .password-toggle-icon:hover {
        color: #2c3e50;
    }
</style>

<!-- AUTHENTICATION HERO SECTION -->
<div class="auth-wrapper" style="background-image: url('<?php echo get_image_path('bg1.jpg'); ?>');">
    <div class="auth-card">
        
        <!-- Animated Header & Standalone Logo -->
        <div class="text-center">
            <div class="auth-logo-standalone">
                <img src="<?php echo get_image_path('logo.png'); ?>" alt="Spirited Finds Logo">
            </div>
            <h2 class="auth-title">Welcome Back!</h2>
            <p class="auth-subtitle">Embark on another magical Ghibli adventure.</p>
        </div>

        <!-- Success Alert Message -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show auth-alert text-center mb-4" role="alert">
                <i class="fas fa-check-circle me-1"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Error Alert Message -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show auth-alert text-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Form Elements -->
        <form action="login.php" method="POST" class="auth-form">
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="auth-input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="auth-input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('password', this)"></i>
                </div>
            </div>

            <button type="submit" class="btn-auth-submit mt-2">
                <span>Login</span>
                <i class="fas fa-sign-in-alt ms-1"></i>
            </button>
        </form>

        <div class="auth-quote-embedded">
            <p class="auth-quote-text">"The wind is rising! ... We must try to live!"</p>
        </div>

        <div class="auth-footer-text">
            Don't have an account? <a href="signup.php">Sign up here</a>
        </div>

    </div>
</div>

<!-- JavaScript para sa Show/Hide Password Toggle -->
<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

<?php
include 'includes/footer.php';
?>