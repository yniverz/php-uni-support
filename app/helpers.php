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

// function getUsersWithModuleID($originUsername, $moduleID, $term, $userData)
// {
//     // iterate through all users with origin username in their shared usernames list
//     // load the respective data file
//     // add user to list if has module with same id in same term
//     $usersWithModule = [];
//     foreach ($userData as $username => $user) {
//         if ($username !== $originUsername && (in_array($originUsername, $user['share_usernames'] ?? []) || in_array("*", $user['share_usernames'] ?? []))) {
//             $userJsonFile = __DIR__ . '/users/' . $user['userid'] . '.json';
//             if (file_exists($userJsonFile)) {
//                 $data = json_decode(file_get_contents($userJsonFile), true);
//                 foreach ($data['modules'] as $module) {
//                     if (isset($module['id']) && $module["id"] == $moduleID && $module['term'] == $term) {
//                         $usersWithModule[] = $username;
//                         break; // No need to check further for this user
//                     }
//                 }
//             }
//         }
//     }

//     return $usersWithModule;
// }

/**
 * Return all users that share data with $originUsername and have the
 * given module ID, together with every term in which they take it.
 *
 * Example return value:
 * [
 *     'alice' => [1, 2],
 *     'bob'   => [3],
 * ]
 *
 * @param string $originUsername  Username of the current user
 * @param int|string $moduleID    Module ID to look for
 * @param array $userData         Master user table (username => user record)
 * @return array<string, int[]>   username => list of terms
 */
function getUsersWithModuleTerms(string $originUsername, $moduleID, array $userData): array
{
    $result = [];

    foreach ($userData as $username => $user) {

        // 1. Skip the origin user
        if ($username === $originUsername) {
            continue;
        }

        // 2. Check sharing rules
        $sharesWithOrigin = in_array($originUsername, $user['share_usernames'] ?? [], true)
                         || in_array('*', $user['share_usernames'] ?? [], true);

        if (!$sharesWithOrigin) {
            continue;
        }

        // 3. Load that user’s on-disk data
        $jsonPath = __DIR__ . '/users/' . $user['userid'] . '.json';
        if (! is_readable($jsonPath)) {
            continue;        // silently skip if missing/unreadable
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (! isset($data['modules']) || ! is_array($data['modules'])) {
            continue;        // malformed file; skip
        }

        // 4. Collect every term in which the module appears
        $terms = [];
        foreach ($data['modules'] as $module) {
            if (($module['id'] ?? null) == $moduleID) {
                $terms[] = (int)$module['term'];
            }
        }

        if ($terms) {
            $result[$username] = array_values(array_unique($terms, SORT_NUMERIC));
            sort($result[$username], SORT_NUMERIC);   // optional: always ascending
        }
    }

    return $result;
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