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

// function sortModules(&$modules)
// {
//     usort($modules, function ($a, $b) {
//         if ($a['term'] == $b['term']) {
//             return $a['idealTerm'] <=> $b['idealTerm'];
//         }
//         return $a['term'] <=> $b['term'];
//     });
// }

/**
 * Sort a modules[] array in-place by:
 *   1. earliest requirement date   (oldest first)
 *      • modules with no dated requirements come after those with dates
 *   2. idealTerm                   (ascending) – only if both lack dates
 *   3. term                        (ascending) – only if both lack dates
 *
 * @param array &$modules
 */
function sortModules(array &$modules): void
{
    // sort requirements first
    foreach ($modules as &$module) {
        sortRequirements($module['requirements']);
    }
    unset($module);   // break reference to last element
    
    // sort modules by earliest requirement date
    usort($modules, function ($a, $b) {
        $earliestA = getEarliestDate($a['requirements'] ?? []);
        $earliestB = getEarliestDate($b['requirements'] ?? []);

        /* -------- 1) primary key: earliest date -------- */
        if ($earliestA !== null || $earliestB !== null) {
            // Put modules *with* a date before those without
            if ($earliestA === null) {
                return 1;          // $a has no date → after $b
            }
            if ($earliestB === null) {
                return -1;         // $b has no date → after $a
            }
            return $earliestA <=> $earliestB;   // both dated → chronological
        }

        /* -------- 2) fallback: idealTerm, then term -------- */
        if ($a['idealTerm'] !== $b['idealTerm']) {
            return $a['idealTerm'] <=> $b['idealTerm'];
        }
        return $a['term'] <=> $b['term'];
    });
}

/**
 * Return the earliest YYYY-MM-DD date in a requirements array,
 * or null if none of the requirements have a valid date.
 *
 * @param array $requirements
 * @return int|null  Unix timestamp of the earliest date or null
 */
function getEarliestDate(array $requirements): ?int
{
    $earliest = null;
    foreach ($requirements as $req) {
        if (!empty($req['date'])) {
            $ts = strtotime($req['date']);
            if ($ts !== false && ($earliest === null || $ts < $earliest)) {
                $earliest = $ts;
            }
        }
    }
    return $earliest;
}


/**
 * Sort a requirements array in-place by ascending date.
 * Entries that lack a valid `date` field are moved to the end.
 *
 * @param array &$requirements  requirements[] array from a module
 */
function sortRequirements(array &$requirements): void
{
    usort($requirements, function ($a, $b) {
        $tsA = !empty($a['date']) ? strtotime($a['date']) : null;
        $tsB = !empty($b['date']) ? strtotime($b['date']) : null;

        // If both are missing dates, keep original relative order (return 0)
        if ($tsA === null && $tsB === null) {
            return 0;
        }

        // If only one is missing, the dated one comes first
        if ($tsA === null) {
            return 1;      // $a goes after $b
        }
        if ($tsB === null) {
            return -1;     // $a goes before $b
        }

        // Both have dates → chronological comparison
        return $tsA <=> $tsB;   // earlier date first
    });
}

/**
 * Build Chart.js datasets that plot the cumulative-earned credits
 * of every user who shares data with the logged-in user.
 *
 * @param bool  $showShared   comes from the checkbox / GET param
 * @param array $userData     master user table loaded in logic.php
 * @return array              ready-to-json Chart.js dataset objects
 */
function buildSharedProgressDatasets(bool $showShared, ?array $userData): array
{
    $datasets = [];

    if (!$showShared || !is_array($userData)) {
        return $datasets;
    }

    $origin    = $_SESSION['username'] ?? '';
    $usersDir  = __DIR__ . '/users/';

    /* One common label range so all lines have identical length */
    $labelRange = [1];

    foreach ($userData as $uname => $uRec) {
        if ($uname === $origin) {
            continue;
        }
        $shares = $uRec['share_usernames'] ?? [];
        if (!in_array('*', $shares, true) && !in_array($origin, $shares, true)) {
            continue;
        }

        $file = $usersDir . $uRec['userid'] . '.json';
        if (!is_readable($file)) {
            continue;
        }
        $uData = json_decode(file_get_contents($file), true);
        if (!is_array($uData)) {
            continue;
        }
        $uMods = $uData['modules'] ?? [];

        /* term-wise earned credits for that user */
        $termEarned = [];
        foreach ($uMods as $m) {
            foreach ($m['requirements'] as $r) {
                $cr = (float) ($r['credits'] ?? 0);
                if ($cr <= 0 || empty($r['done'])) {
                    continue;
                }
                $t = (int) $m['term'];
                $termEarned[$t] = ($termEarned[$t] ?? 0) + $cr;
            }
        }
        if (!$termEarned) {
            continue;                       // no earned credits yet
        }

        /* widen the common label range if necessary */
        $labelRange = range(1, max(end($labelRange), max(array_keys($termEarned))));

        /* build cumulative array matching the common label length */
        $cum = [];
        $run = 0;
        foreach ($labelRange as $t) {
            $run += $termEarned[$t] ?? 0;
            $cum[] = $run ?: null;          // keep gaps (null) for idle terms
        }

        $datasets[] = [
            'label'            => $uname,
            'data'             => $cum,
            'borderWidth'      => 1,
            'borderColor'      => 'rgba(128,128,128,0.65)',
            'backgroundColor'  => 'rgba(128,128,128,0.15)',
            'fill'             => false,
            'tension'          => 0,
            'pointRadius'      => 0,
            'pointHoverRadius' => 0,
            'spanGaps'         => false
        ];
    }

    return $datasets;
}
