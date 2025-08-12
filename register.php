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
    // NEW: read checkbox
    $acceptedTerms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1';

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
    }
    // NEW: Must accept AGB & cookies
    elseif (!$acceptedTerms) {
        $registerError = "You must accept the AGB and Cookies notice to create an account.";
    }
    else {
        // 5) If all checks pass, hash the password and store it
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // You might want a unique user ID
        $newUserID = uniqid('user_');  // or any other scheme

        // Insert the new user
        $userData[$username] = [
            'userid' => $newUserID,
            'password' => $hashedPassword
            // Intentionally not storing consent metadata per your request
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
    <title>Term Planner - Register</title>
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
                    <input type="text" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ; ?>">
                </label>
                <label>Password:
                    <input type="password" name="password" required>
                </label>
                <label>Confirm Password:
                    <input type="password" name="password2" required>
                </label>

                <!-- NEW: Consent checkbox (client-side required) -->
                <div class="consent-block" style="margin: 0.75rem 0;">
                    <label style="display:flex;align-items:flex-start;gap:0.5rem;">
                        <input
                            type="checkbox"
                            name="accept_terms"
                            value="1"
                            required
                            <?php echo isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1' ? 'checked' : ''; ?>
                        >
                        <span>
                            I have read and accept the
                            <a href="/legal/terms-and-cookies.html" target="_blank" rel="noopener">
                                AGB & Cookies Policy
                            </a>.
                        </span>
                    </label>
                    <!-- Small explanatory text + link -->
                    <small style="display:block;color:#666;margin-top:0.25rem;line-height:1.4;">
                        By creating an account you agree that this service uses cookies necessary for operation.
                        We may also use Microsoft services (e.g., hosting/analytics) that may set cookies.
                        Details are in our
                        <a href="/legal/terms-and-cookies.html" target="_blank" rel="noopener">
                            Terms & Cookies Policy
                        </a>.
                    </small>
                </div>

                <button type="submit" name="register_submit">Register</button>
            </form>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        <?php endif; ?>
    </div>
</body>

</html>
