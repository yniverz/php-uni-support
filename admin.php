<?php
// public/admin.php
session_start();

/**
 * admin.php:
 * Provides an interface for the "admin" user (userid=0) to manage other users:
 *   - List all users
 *   - Delete a user
 *   - Reset a user's password
 */

// 3) Include config & helpers
require __DIR__ . '/app/config.php';   // loads $userData
require __DIR__ . '/app/helpers.php';  // if needed for any function

// 1) Check if logged in
if (!$isLoggedIn) {
    // Not logged in => redirect to login
    header("Location: login.php");
    exit;
}

// 2) Check if current user is admin (userid=0)
if ($_SESSION['userid'] !== '0') {
    // Not admin => no permission
    exit("<p>Access Denied. You are not the administrator.</p>");
}


// If we want to do operations on $userData, note that $userData is loaded in config.php
// from /app/users.json

$feedbackMsg = '';

// 4) Handle Admin Actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $username = trim($_POST['username'] ?? '');

    // Make sure user is in $userData
    if (!isset($userData[$username]) && $username !== '') {
        $feedbackMsg = "User '$username' not found.";
    } else {
        switch ($action) {
            case 'delete':
                // Protect from self-delete (admin can't remove themselves)
                if ($username === $_SESSION['username']) {
                    $feedbackMsg = "You cannot delete your own admin account.";
                } else {
                    // Remove from userData
                    unset($userData[$username]);
                    // Save changes
                    saveUserData($userDataFile, $userData);

                    // Also optionally remove that user's data file
                    $userID = $_POST['userid'] ?? '';
                    if ($userID !== '') {
                        $userJsonFile = __DIR__ . '/app/users/' . $userID . '.json';
                        if (file_exists($userJsonFile)) {
                            unlink($userJsonFile);
                        }
                    }
                    $feedbackMsg = "User '$username' deleted.";
                }
                break;

            case 'reset':
                // We'll reset to a random password or a default
                $newPass = generateRandomPassword(8);  // e.g. 8 chars
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $userData[$username]['password'] = $hashed;
                saveUserData($userDataFile, $userData);

                $feedbackMsg = "Password for user '$username' reset to: <strong>$newPass</strong>";
                break;
        }
    }
}

/**
 * 5) Helper function to generate random password
 */
function generateRandomPassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $pwd = '';
    for ($i = 0; $i < $length; $i++) {
        $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pwd;
}

// We'll define a function to save $userData
function saveUserData($file, $dataArr)
{
    file_put_contents($file, json_encode($dataArr, JSON_PRETTY_PRINT));
}

// 6) HTML Output
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <div class="container">
        <h1>Admin User Management</h1>
        <p><a href="index.php">Back to Modules</a></p>

        <?php if ($feedbackMsg): ?>
            <div style="padding:10px; margin:10px 0; background:#eef;">
                <?php echo $feedbackMsg; ?>
            </div>
        <?php endif; ?>

        <h2>Existing Users</h2>
        <?php if (empty($userData)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <table border="1" cellpadding="5" cellspacing="0">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>User ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userData as $uname => $info): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($uname); ?></td>
                            <td><?php echo htmlspecialchars($info['userid'] ?? ''); ?></td>
                            <td>
                                <!-- Delete Form -->
                                <?php if ($uname !== $_SESSION['username']): // Don't show delete for admin ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($uname); ?>">
                                        <input type="hidden" name="userid"
                                            value="<?php echo htmlspecialchars($info['userid'] ?? ''); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                                <!-- Reset Password Form -->
                                <form method="post" style="display:inline;"
                                    onsubmit="return confirm('Reset password for this user?');">
                                    <input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($uname); ?>">
                                    <button type="submit">Reset Password</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>