<?php
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Linisin ang lahat ng session variables
$_SESSION = array();

// 2. Burahin ang session cookie kung umiiral ito
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. I-destroy ang session
session_destroy();

// 4. I-redirect sa login o home page
header("Location: login.php");
exit();
?>