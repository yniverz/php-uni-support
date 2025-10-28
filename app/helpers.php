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
            if (!empty($req['grade']) && !empty($req['done'])) {
                $totalCredits += $req['credits'];
                $totalGradePoints += $req['grade'] * $req['credits'];
            }
        }
    }

    return ($totalCredits > 0) ? round($totalGradePoints / $totalCredits, 2) : 0;
}

/**
 * Compute the weighted average grade for all finished exams in a specific term.
 * Uses the module's 'term' field for filtering (works with display copies too).
 */
function getAverageGradeForTerm(array $modules, int $termNumber)
{
    $totalCredits = 0.0;
    $totalGradePoints = 0.0;

    foreach ($modules as $module) {
        if ((int)($module['term'] ?? 0) !== $termNumber) continue;
        foreach ($module['requirements'] as $req) {
            $credits = (float)($req['credits'] ?? 0);
            $grade   = isset($req['grade']) ? (float)$req['grade'] : null;
            if ($credits > 0 && $grade !== null && !empty($req['done'])) {
                $totalCredits     += $credits;
                $totalGradePoints += $grade * $credits;
            }
        }
    }

    return ($totalCredits > 0) ? round($totalGradePoints / $totalCredits, 2) : 0;
}

/**
 * Compute the weighted average grade for all finished exams up to and including a term.
 * Uses the module's 'term' field for filtering (works with display copies too).
 */
function getAverageGradeUpToTerm(array $modules, int $termNumber)
{
    $totalCredits = 0.0;
    $totalGradePoints = 0.0;

    foreach ($modules as $module) {
        if ((int)($module['term'] ?? 0) > $termNumber) continue;
        foreach ($module['requirements'] as $req) {
            $credits = (float)($req['credits'] ?? 0);
            $grade   = isset($req['grade']) ? (float)$req['grade'] : null;
            if ($credits > 0 && $grade !== null && !empty($req['done'])) {
                $totalCredits     += $credits;
                $totalGradePoints += $grade * $credits;
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

        $startingTerm = $data['startingTerm'] ?? 1;

        // 4. Collect every term in which the module appears
        $terms = [];
        foreach ($data['modules'] as $module) {
            if (($module['id'] ?? null) == $moduleID) {
                $terms[] = (int)$module['term'] - ($startingTerm - 1);
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
/**
 * Sort modules in‑place by
 *   0.  modules with OPEN requirements first;
 *       – order those by earliest‑dated OPEN requirement (oldest → newest)
 *       – open requirements without a date come after dated ones
 *   1.  modules where **all** requirements are DONE
 *       – order those by earliest‑dated DONE requirement (oldest → newest)
 *       – if none of the done reqs have dates, they’re grouped at the end
 *   2.  idealTerm   (ascending, as final fallback)
 *   3.  term        (ascending, as final fallback)
 */
function sortModules(array &$modules): void
{
    /* keep requirement date & open‑first ordering inside each module */
    foreach ($modules as &$module) {
        sortRequirements($module['requirements']);
    }
    unset($module);

    usort($modules, function ($a, $b) {

        $openA = hasOpenReq($a['requirements'] ?? []);
        $openB = hasOpenReq($b['requirements'] ?? []);

        /* -------- 0) open vs done -------- */
        if ($openA !== $openB) {
            return $openA ? -1 : 1;          // open first
        }

        /* -------- 1) both OPEN: compare earliest open dates -------- */
        if ($openA && $openB) {
            $eoA = getEarliestOpenDate($a['requirements']);
            $eoB = getEarliestOpenDate($b['requirements']);

            if ($eoA !== null || $eoB !== null) {
                if ($eoA === null) return 1;   // undated after dated
                if ($eoB === null) return -1;
                return $eoA <=> $eoB;
            }
            /* fall through if neither open req has a date */
        }

        /* -------- 1) both DONE: compare earliest DONE dates -------- */
        if (!$openA && !$openB) {
            $edA = getEarliestDoneDate($a['requirements']);
            $edB = getEarliestDoneDate($b['requirements']);

            if ($edA !== null || $edB !== null) {
                if ($edA === null) return 1;
                if ($edB === null) return -1;
                return $edA <=> $edB;
            }
            /* fall through if no done reqs have dates */
        }

        /* -------- 2‑3) final fallback -------- */
        return ($a['idealTerm'] ?? 0) <=> ($b['idealTerm'] ?? 0)
            ?:  ($a['term']      ?? 0) <=> ($b['term']      ?? 0);
    });
}

/**
 * Sort requirements[] in‑place by
 *   0.  *open first*      (done = false/empty)
 *   1.  date (ascending)  – within each group
 *   2.  undated items come last inside their group
 */
function sortRequirements(array &$requirements): void
{
    usort($requirements, function ($a, $b) {

        /* 0) open before done */
        $doneA = !empty($a['done']);
        $doneB = !empty($b['done']);
        if ($doneA !== $doneB) {
            return $doneA ? 1 : -1;      // open (false) first
        }

        /* 1‑2) date ordering exactly as before */
        $tsA = !empty($a['date']) ? strtotime($a['date']) : null;
        $tsB = !empty($b['date']) ? strtotime($b['date']) : null;
        if ($tsA === null && $tsB === null) return 0;
        if ($tsA === null) return 1;
        if ($tsB === null) return -1;
        return $tsA <=> $tsB;
    });
}

/* ---------- helpers ------------------------------------------------- */

/** true if at least one requirement is still open */
function hasOpenReq(array $requirements): bool
{
    foreach ($requirements as $r) {
        if (empty($r['done'])) {
            return true;
        }
    }
    return false;
}

/** earliest date among OPEN requirements, or null */
function getEarliestOpenDate(array $reqs): ?int
{
    return getEarliestDateByDone($reqs, false);
}

/** earliest date among DONE requirements, or null */
function getEarliestDoneDate(array $reqs): ?int
{
    return getEarliestDateByDone($reqs, true);
}

/** shared worker: earliest YYYY‑MM‑DD where 'done' == $doneFlag */
function getEarliestDateByDone(array $reqs, bool $doneFlag): ?int
{
    $earliest = null;
    foreach ($reqs as $r) {
        if (!empty($r['date']) && (!empty($r['done']) === $doneFlag)) {
            $ts = strtotime($r['date']);
            if ($ts !== false && ($earliest === null || $ts < $earliest)) {
                $earliest = $ts;
            }
        }
    }
    return $earliest;
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

/**
 * Build grey-line datasets that show each shared user’s running grade average,
 * scaled so their first point lines up with your first exam and their last
 * point lines up with your last exam.
 *
 * @param bool  $showShared      state of the checkbox / GET param
 * @param array $userData        master user table
 * @param int   $originCount     how many exams *you* have (points on x-axis)
 * @return array                 Chart.js-ready datasets
 */
function buildSharedGradeDatasets(bool $showShared, ?array $userData, int $originCount): array
{
    $datasets = [];
    if (!$showShared || $originCount < 1 || !is_array($userData)) {
        return $datasets;
    }

    $origin   = $_SESSION['username'] ?? '';
    $usersDir = __DIR__ . '/users/';

    foreach ($userData as $uname => $uRec) {
        if ($uname === $origin) continue;
        $shares = $uRec['share_usernames'] ?? [];
        if (!in_array('*', $shares, true) && !in_array($origin, $shares, true)) continue;

        $file = $usersDir . $uRec['userid'] . '.json';
        if (!is_readable($file)) continue;
        $uData = json_decode(file_get_contents($file), true);
        if (!is_array($uData)) continue;

        /* gather that user’s finished exams -------------------------------- */
        $exams = [];
        $seq   = 0;
        foreach ($uData['modules'] ?? [] as $m) {
            foreach ($m['requirements'] as $r) {
                $g  = isset($r['grade']) ? (float) $r['grade'] : null;
                $cr = (float) ($r['credits'] ?? 0);
                if ($cr <= 0 || $g === null || empty($r['done'])) continue;
                $exams[] = [
                    'credits' => $cr,
                    'grade'   => $g,
                    'date'    => isset($r['date']) ? strtotime($r['date']) : null,
                    'seq'     => $seq++,
                ];
            }
        }
        if (!$exams) continue;

        usort($exams, function ($a, $b) {
            if ($a['date'] !== null && $b['date'] !== null) return $a['date'] <=> $b['date'];
            if ($a['date'] === null && $b['date'] === null) return $a['seq'] <=> $b['seq'];
            return ($a['date'] === null) ? 1 : -1;
        });

        /* cumulative averages + scaled x-coordinates ----------------------- */
        $runSum = $runCred = 0.0;
        $avgXY  = [];
        $theirCount = count($exams);

        foreach ($exams as $i => $e) {
            $runSum  += $e['grade'] * $e['credits'];
            $runCred += $e['credits'];

            // scale: first point 0, last point ($originCount-1)
            $x = ($theirCount > 1)
               ? ($originCount - 1) * $i / ($theirCount - 1)
               : 0;

            $avgXY[] = [ 'x' => $x, 'y' => round($runSum / $runCred, 3) ];
        }

        $datasets[] = [
            'label'            => $uname,
            'data'             => $avgXY,          // ← now [x,y] pairs
            'parsing'          => false,           // tell Chart.js to use x/y keys
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