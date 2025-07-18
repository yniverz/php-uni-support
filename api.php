<?php
/**
 * api.php
 *
 * Generic JSON API for the Module Tracker.
 * Add new endpoints by extending the $endpoints array or the switch block below.
 *
 * Usage example (GET):
 *   /api.php?username=alice&password=secret&type=daily-notification
 */

session_start();

/* -----------------------------------------------------------
 * 1.  ── Login / session bootstrap ───────────────────────────
 * ----------------------------------------------------------- */
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
    // Fallback to credentials supplied in the query string.
    $username = trim($_GET['username'] ?? '');
    $password = trim($_GET['password'] ?? '');

    // Load user store
    $userDataFile = __DIR__ . '/app/users.json';
    if (!file_exists($userDataFile)) {
        http_response_code(500);
        exit(json_encode(['error' => 'User data file missing.']));
    }
    $users = json_decode(file_get_contents($userDataFile), true);
    if (!is_array($users)) {
        http_response_code(500);
        exit(json_encode(['error' => 'User data file invalid.']));
    }

    // Validate credentials
    if (
        !isset($users[$username]) ||
        !password_verify($password, $users[$username]['password'])
    ) {
        http_response_code(403);
        exit(json_encode(['error' => 'Access denied.']));
    }

    // Seed session so the rest of the app works as usual
    $_SESSION['logged_in'] = true;
    $_SESSION['userid']    = $users[$username]['userid'];
    $_SESSION['username']  = $username;
}

/* -----------------------------------------------------------
 * 2.  ── Load application data ───────────────────────────────
 * ----------------------------------------------------------- */
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/logic.php';   // populates $data

/* -----------------------------------------------------------
 * 3.  ── Endpoint dispatcher  ────────────────────────────────
 * ----------------------------------------------------------- */
header('Content-Type: application/json; charset=utf-8');

$type = trim($_GET['type'] ?? '');

switch ($type) {

    /* ---------- daily-notification --------------------------------------
     * Returns all requirements that…
     *   – have a date within the next 0‑2 days (inclusive)
     *   – are **not** marked as done
     * ------------------------------------------------------------------ */
    case 'daily-notification':
        echo json_encode(getDailyNotifications($data), JSON_PRETTY_PRINT);
        break;

    /* -- add new endpoints below --------------------------------------- */
    default:
        http_response_code(400);
        echo json_encode([
            'error'            => 'Unknown or missing "type" parameter.',
            'supported_types'  => ['daily-notification']
        ], JSON_PRETTY_PRINT);
        break;
}

/* -----------------------------------------------------------
 * 4.  ── Helper: build the notification list  ────────────────
 * ----------------------------------------------------------- */
function getDailyNotifications(array $data): array
{
    $today      = new DateTime('today');
    $upperLimit = (clone $today)->modify('+3 days');  // “less than 3 days”

    $result = [];

    foreach ($data['modules'] as $module) {
        $moduleName = $module['name'];
        foreach ($module['requirements'] as $req) {
            // Skip if already done or no date
            if (!empty($req['done']) || empty($req['date'])) {
                continue;
            }

            $reqDate = DateTime::createFromFormat('Y-m-d', $req['date']);
            if (!$reqDate) {
                continue; // invalid date format
            }

            $inDays = $reqDate->diff($today)->days;

            // within [today … today+3]
            if ($reqDate >= $today && $reqDate <= $upperLimit) {
                $result[] = [
                    'module'      => $moduleName,
                    'description' => $req['description'],
                    'date'        => $req['date'],
                    'in_days'     => $inDays,
                    'credits'     => $req['credits'],
                ];
            }
        }
    }

    // Sort ascending by date, then module name
    usort($result, function ($a, $b) {
        $cmp = strcmp($a['date'], $b['date']);
        return $cmp !== 0 ? $cmp : strnatcasecmp($a['module'], $b['module']);
    });

    return $result;
}
