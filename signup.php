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
    header("Location: index.php");
    exit();
}

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password =$_POST['password'];
    $confirm_password =$_POST['confirm_password'];

    // Validations
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error_msg = "Please fill in all required fields.";
    } elseif (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) {
        // Validation para sa eksaktong 11-digit number
        $error_msg = "Phone number must be exactly 11 digits and contain numbers only.";
    } elseif ($password !== $confirm_password) {$error_msg = "Passwords do not match!";
    } elseif (strlen($password) < 6) {$error_msg = "Password must be at least 6 characters long.";
    } else {
        // Suriin kung may kaparehong email sa database
        $check_stmt =$conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);$check_stmt->execute();
        $check_result =$check_stmt->get_result();

        if ($check_result->num_rows > 0) {$error_msg = "An account with this email already exists.";
        } else {
            // Hash the password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);$role = 'customer';

            // Insert bagong user sa `users` table
            $stmt =$conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $first_name,$last_name, $email,$phone, $hashed_password,$role);

            if ($stmt->execute()) {
                header("Location: login.php?signup=success");
                exit();
            } else {
                $error_msg = "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

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
    <div class="auth-card" style="max-width: 520px;">
        
        <div class="text-center">
            <div class="auth-logo-standalone">
                <img src="<?php echo get_image_path('logo.png'); ?>" alt="Spirited Finds Logo">
            </div>
            <h2 class="auth-title">Create an Account</h2>
            <p class="auth-subtitle">Join us and explore the world of Studio Ghibli.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show auth-alert text-center mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <form action="signup.php" method="POST" class="auth-form">
            <!-- First Name and Last Name Row (Ghibli Character Placeholders) -->
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label for="first_name" class="form-label">First Name *</label>
                    <div class="auth-input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Chihiro" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <div class="auth-input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Ogino" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                </div>
            </div>

            <!-- Email & Phone -->
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address *</label>
                <div class="auth-input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" class="form-control" id="email" name="email" placeholder="chihiro@spiritedfinds.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <div class="auth-input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <!-- Filtered to 11 digits and numeric only -->
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="09123456789" maxlength="11" pattern="[0-9]{11}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                <small class="text-muted">Must be exactly 11 digits (e.g., 09123456789)</small>
            </div>

            <!-- Passwords with Toggle -->
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <div class="auth-input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <div class="auth-input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                        <i class="fas fa-eye password-toggle-icon" onclick="togglePassword('confirm_password', this)"></i>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-auth-submit mt-2">
                <span>Sign Up</span>
                <i class="fas fa-user-plus ms-1"></i>
            </button>
        </form>

        <div class="auth-footer-text mt-3">
            Already have an account? <a href="login.php">Log in here</a>
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