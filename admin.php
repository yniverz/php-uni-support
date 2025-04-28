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
                    saveData($userData, $userDataFile);

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
                saveData($userData, $userDataFile);

                $feedbackMsg = "Password for user '$username' reset to: <strong>$newPass</strong>";
                break;
            case 'edit_module_id':
                // Assuming you have a function to edit module ID
                $moduleID = $_POST['moduleID'] ?? '';
                $newName = $_POST['newName'] ?? '';
                if ($moduleID !== '' && $newName !== '') {
                    // Check if the module ID exists in the globalModuleIDs
                    if (!in_array($moduleID, $globalModuleIDs)) {
                        $feedbackMsg = "Module ID '$moduleID' not found.";
                        break;
                    }
                    // Check if the new name is valid
                    if (in_array($newName, $globalModuleIDs)) {
                        $feedbackMsg = "New name '$newName' already exists.";
                        break;
                    }


                    // iterate through all user files and adjust the module ID
                    foreach ($userData as $username => $user) {
                        $userJsonFile = __DIR__ . '/app/users/' . $user['userid'] . '.json';
                        if (file_exists($userJsonFile)) {
                            $temp_data = json_decode(file_get_contents($userJsonFile), true);

                            $didChange = false;
                            foreach ($temp_data['modules'] as $moduleIndex => $module) {
                                if (isset($module['id']) && $module['id'] == $moduleID) {
                                    // Update the module ID
                                    $temp_data['modules'][$moduleIndex]['id'] = $newName;
                                    $didChange = true;
                                }
                            }

                            if ($didChange) {
                                saveData($temp_data, $userJsonFile);
                            }
                        }
                    }

                    // change in the globalModuleIDs array
                    $globalModuleIDs = array_map(function($module) use ($moduleID, $newName) {
                        return $module === $moduleID ? $newName : $module;
                    }, $globalModuleIDs);
                    // Save the updated globalModuleIDs
                    saveData($globalModuleIDs, $globalModuleIDFile);
                    
                    $feedbackMsg = "Module ID '$moduleID' updated to '$newName'.";
                } else {
                    $feedbackMsg = "Invalid module ID or new name.";
                }
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

        <h2>Module ID's</h2>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Assuming you have a function to get module data
                // $globalModuleIDs
                foreach ($globalModuleIDs as $moduleID):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($moduleID); ?></td>
                        <td>
                            <?php $uuid = uniqid('btn_'); ?>
                            <button id="<?php echo $uuid; ?>">Edit</button>
                            <script>
                                document.getElementById('<?php echo $uuid; ?>').addEventListener('click', function() {
                                    // Your edit logic here
                                    var newName = prompt('Edit Module ID:', '<?php echo htmlspecialchars($moduleID); ?>');
                                    if (newName) {
                                        // redirect post data
                                        var form = document.createElement('form');
                                        form.method = 'POST';
                                        var input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'action';
                                        input.value = 'edit_module_id';
                                        form.appendChild(input);
                                        input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'moduleID';
                                        input.value = '<?php echo htmlspecialchars($moduleID); ?>';
                                        form.appendChild(input);
                                        input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'newName';
                                        input.value = newName;
                                        form.appendChild(input);
                                        document.body.appendChild(form);
                                        form.submit();
                                    }
                                });
                            </script>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    </div>
</body>

</html>