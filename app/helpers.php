<?php
// public/app/helpers.php
/**
 * Helper functions for your module system.
 */

function saveData($data, $jsonFile)
{
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
}

function isLoggedIn()
{
    return (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);
}

function getAverageGrade($modules)
{
    $totalCredits = 0;
    $totalGradePoints = 0;

    foreach ($modules as $module) {
        foreach ($module['requirements'] as $req) {
            if (!empty($req['grade'])) {
                $totalCredits += $req['credits'];
                $totalGradePoints += $req['grade'] * $req['credits'];
            }
        }
    }

    return ($totalCredits > 0) ? round($totalGradePoints / $totalCredits, 2) : 0;
}

function getTotalCreditsSoFar($modules)
{
    $total = 0;
    foreach ($modules as $module) {
        foreach ($module['requirements'] as $req) {
            if (!empty($req['done'])) {
                $total += $req['credits'];
            }
        }
    }
    return $total;
}

function getCompletedCreditsUpToTerm($modules, $termNumber)
{
    $total = 0;
    foreach ($modules as $module) {
        if ($module['term'] <= $termNumber) {
            foreach ($module['requirements'] as $req) {
                if (!empty($req['done'])) {
                    $total += $req['credits'];
                }
            }
        }
    }
    return $total;
}

function getAllCreditsUpToTerm($modules, $termNumber)
{
    $total = 0;
    foreach ($modules as $module) {
        if ($module['term'] <= $termNumber) {
            foreach ($module['requirements'] as $req) {
                $total += $req['credits'];
            }
        }
    }
    return $total;
}

function getTermCredits($modules, $termNumber)
{
    $termTotal = 0;
    foreach ($modules as $module) {
        if ($module['term'] == $termNumber) {
            foreach ($module['requirements'] as $req) {
                $termTotal += $req['credits'];
            }
        }
    }
    return $termTotal;
}

function getUsersWithModuleID($originUsername, $moduleID, $term, $userData)
{
    // iterate through all users with origin username in their shared usernames list
    // load the respective data file
    // add user to list if has module with same id in same term
    $usersWithModule = [];
    foreach ($userData as $username => $user) {
        if ($username !== $originUsername && in_array($originUsername, $user['share_usernames'] ?? [])) {
            $userJsonFile = __DIR__ . '/users/' . $user['userid'] . '.json';
            if (file_exists($userJsonFile)) {
                $data = json_decode(file_get_contents($userJsonFile), true);
                foreach ($data['modules'] as $module) {
                    if (isset($module['id']) && $module["id"] == $moduleID && $module['term'] == $term) {
                        $usersWithModule[] = $username;
                        break; // No need to check further for this user
                    }
                }
            }
        }
    }

    return $usersWithModule;
}

function updateModuleCompletionStatus(&$module)
{
    $allDone = true;
    foreach ($module['requirements'] as $req) {
        if (empty($req['done'])) {
            $allDone = false;
            break;
        }
    }
    $module['allDone'] = $allDone;
}

function sortModules(&$modules)
{
    usort($modules, function ($a, $b) {
        if ($a['term'] == $b['term']) {
            return $a['idealTerm'] <=> $b['idealTerm'];
        }
        return $a['term'] <=> $b['term'];
    });
}