<?php
// calendar.ics.php
session_start();

// 1. Manually load and verify login from GET parameters
$username = trim($_GET['username'] ?? '');
$password = trim($_GET['password'] ?? '');

// Path to user data JSON file
$userDataFile = __DIR__ . '/app/users.json';
if (!file_exists($userDataFile)) {
    exit("User data file not found.");
}
// Load user data
$userData = json_decode(file_get_contents($userDataFile), true);
if (!$userData) {
    exit("Invalid user data file.");
}

if (
    !isset($userData[$username]) ||
    !password_verify($password, $userData[$username]['password'])
) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. Invalid username or password.";
    exit;
}

// 2. Set session so app thinks we're logged in
$_SESSION['logged_in'] = true;
$_SESSION['userid'] = $userData[$username]['userid'];
$_SESSION['username'] = $username;

// 3. Now load the rest of the app
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/logic.php'; // This loads $data

// 4. Prepare and serve the .ics file
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="requirements.ics"');

function generateUID($moduleName, $requirement, $credits, $date)
{
    return md5($moduleName . $requirement . $credits . $date) . "@ums.local";
}

function formatDateICS($date)
{
    return str_replace('-', '', $date);
}

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Term Planner//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";

foreach ($data['modules'] as $mod) {
    $moduleName = $mod['name'];
    $notes = $mod['notes'] ?? '';
    foreach ($mod['requirements'] as $req) {
        if (!empty($req['date'])) {
            $desc = $req['description'];
            $credits = $req['credits'];
            $date = $req['date']; // YYYY-MM-DD

            $summary = "$desc, $moduleName ($credits cr)";
            $uid = generateUID($moduleName, $desc, $credits, $date);
            $dt = formatDateICS($date);

            echo "BEGIN:VEVENT\r\n";
            echo "UID:$uid\r\n";
            echo "SUMMARY:" . addcslashes($summary, ",;\\") . "\r\n";
            echo "DTSTART;VALUE=DATE:$dt\r\n";
            echo "DTEND;VALUE=DATE:$dt\r\n"; // Full-day
            echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";

            if (!empty($notes)) {
                echo "DESCRIPTION:Module Notes: " . addcslashes($notes, ",;\\") . "\r\n";
            }

            echo "END:VEVENT\r\n";
        }
    }
}

echo "END:VCALENDAR\r\n";