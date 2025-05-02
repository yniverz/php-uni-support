<?php
// public/register.php
session_start();

/**
 * This file handles user registration.
 * If user is already logged in, we redirect to index.php
 */

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

// If user is already logged in, redirect to main
if ($isLoggedIn) {
    header("Location: index.php");
    exit;
}

$registerError = '';
$registerSuccess = '';

// If the registration form is submitted
if (isset($_POST['register_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    // 1) Check if username is empty
    if ($username === '') {
        $registerError = "Username is required.";
    }
    // 2) Check if password is empty
    elseif ($password === '' || $password2 === '') {
        $registerError = "Password fields cannot be empty.";
    }
    // 3) Check if both passwords match
    elseif ($password !== $password2) {
        $registerError = "Passwords do not match.";
    }
    // 4) Check if username already exists in $userData
    elseif (isset($userData[$username])) {
        $registerError = "Username already taken. Please choose another.";
    }
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $registerError = "Username can only contain letters, numbers, and underscores.";
    } else {
        // 5) If all checks pass, hash the password and store it
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // You might want a unique user ID
        $newUserID = uniqid('user_');  // or any other scheme

        // Insert the new user
        $userData[$username] = [
            'userid' => $newUserID,
            'password' => $hashedPassword
        ];

        saveData($userData, $userDataFile);

        // 7) Indicate success
        $registerSuccess = "Registration successful! You can now log in.";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>University Module Support System - Register</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <div class="login-container">
        <h1>Register</h1>
        <?php if ($registerError): ?>
            <p class="error"><?php echo htmlspecialchars($registerError); ?></p>
        <?php endif; ?>

        <?php if ($registerSuccess): ?>
            <p class="success"><?php echo htmlspecialchars($registerSuccess); ?></p>
            <p><a href="login.php">Back to Login</a></p>
        <?php else: ?>
            <!-- Show the Registration Form -->
            <form method="post" action="">
                <label>Username:
                    <input type="text" name="username" required>
                </label>
                <label>Password:
                    <input type="password" name="password" required>
                </label>
                <label>Confirm Password:
                    <input type="password" name="password2" required>
                </label>
                <button type="submit" name="register_submit">Register</button>
            </form>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        <?php endif; ?>
    </div>
</body>

</html>