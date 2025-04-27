<?php
// public/app/config.php
/**
 * Shared config and data loading logic.
 * This code replaces the top sections of your single-file version.
 */

// Path to user data JSON file
$userDataFile = __DIR__ . '/users.json';

$defaultUserData = [
    'admin' => [
        'password' => password_hash('secret', PASSWORD_DEFAULT), // For demo only
        'userid' => '0',
    ],
];

if (!file_exists($userDataFile)) {
    // Create a default user data file if it doesn't exist
    file_put_contents($userDataFile, json_encode($defaultUserData, JSON_PRETTY_PRINT));
}
// Load user data
$userData = json_decode(file_get_contents($userDataFile), true);
if (!$userData) {
    // If invalid, create a backup
    $backupFile = __DIR__ . '/users_backup_' . date('Ymd_His') . '.json';
    if (file_exists($userDataFile)) {
        copy($userDataFile, $backupFile);
    }

    // then reset to default
    $userData = $defaultUserData;
    file_put_contents($userDataFile, json_encode($userData, JSON_PRETTY_PRINT));
}

$isLoggedIn = false;

if (!isset($_POST['login_submit'])) {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['userid']) && isset($_SESSION['username'])) {
        // Check if the user ID exists in the user data
        if (isset($userData[$_SESSION['username']])) {
            $isLoggedIn = true;
        } else {
            // If user ID doesn't match, log out
            session_unset();
            session_destroy();
        }
    } else {
        // If not logged in, ensure session is clean
        session_unset();
        session_destroy();
    }
}


// only if user logged in get data from respective file
// Otherwise, exit
if ($isLoggedIn) {

    // make sure the users directory exists
    if (!is_dir(__DIR__ . '/users')) {
        mkdir(__DIR__ . '/users', 0777, true);
    }

    // Path to the JSON file
    $jsonFile = __DIR__ . '/users' . '/' . $_SESSION['userid'] . '.json';

    // Default data if file doesn't exist or is invalid
    $defaultData = [
        'totalNeededCredits' => 180,
        'semesterTargets' => [9, 6],
        'modules' => []
    ];

    // If JSON file doesn't exist, create it with defaults
    if (!file_exists($jsonFile)) {
        file_put_contents($jsonFile, json_encode($defaultData, JSON_PRETTY_PRINT));
    }

    // Attempt to load file
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);

    if (!$data) {
        // If invalid, create a backup
        $backupFile = __DIR__ . '/data_backup_' . $_SESSION['userid'] . date('Ymd_His') . '.json';
        if (file_exists($jsonFile)) {
            copy($jsonFile, $backupFile);
        }
        // then reset to default
        $data = $defaultData;
        file_put_contents($jsonFile, json_encode($defaultData, JSON_PRETTY_PRINT));
    }

    // load global module id file
    $globalModuleIDFile = __DIR__ . '/global_module_ids.json';
    if (!file_exists($globalModuleIDFile)) {
        file_put_contents($globalModuleIDFile, json_encode([], JSON_PRETTY_PRINT));
    }
    $globalModuleIDs = json_decode(file_get_contents($globalModuleIDFile), true);
}