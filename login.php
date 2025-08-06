<?php
// public/login.php
session_start();

/**
 * This file ONLY handles the login form.
 * If user is already logged in, we redirect to index.php
 */

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

// If user is already logged in, redirect to main
if ($isLoggedIn) {
    header("Location: index.php");
    exit;
}

$loginError = '';

if (isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    // check if username and hashed password in userData
    if (isset($userData[$username]) && password_verify($password, $userData[$username]['password'])) {
        // Set session variables
        $_SESSION['logged_in'] = true;
        $_SESSION['userid'] = $userData[$username]['userid'];
        $_SESSION['username'] = $username;
        // Redirect to main page
        header("Location: index.php");
        exit;
    } else {
        $loginError = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Term Planner - Login</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if ($loginError): ?>
            <p class="error"><?php echo htmlspecialchars($loginError); ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label>Username:
                <input type="text" name="username" required>
            </label>
            <label>Password:
                <input type="password" name="password" required>
            </label>
            <button type="submit" name="login_submit">Login</button>
        </form>
        <!-- Registration link -->
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>

</html>