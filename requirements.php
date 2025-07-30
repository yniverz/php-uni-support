<?php
session_start();

// 1) Load config/data/helpers
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php'; // contains markRequirementDone/applyRequirementDoneOverrides

if (!$isLoggedIn) {
    header("Location: login.php");
    exit;
}

// ------------------------------------------------------------------
// Handle toggle of "done" via POST (from JS fetch or normal form submit fallback)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['_action'] ?? '') === 'toggle_done' || ($_POST['action'] ?? '') === 'toggle_done')) {
    $uuid = isset($_POST['uuid']) ? trim($_POST['uuid']) : '';
    $targetDone = (isset($_POST['target_done']) && $_POST['target_done'] == '1');

    if ($uuid !== '' && function_exists('markRequirementDone')) {
        markRequirementDone($uuid, $targetDone);
    }

    // If this is a background (fetch) call, return a tiny 204/empty response.
    // Detect an ajax/fetch call via header or explicit param.
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

    if ($isAjax) {
        http_response_code(204); // No Content
        exit;
    }

    // Normal POST fallback: redirect to preserve filter
    $currentReq = isset($_POST['current_req']) ? $_POST['current_req'] : '';
    $qs = $currentReq !== '' ? '?req=' . urlencode($currentReq) : '';
    header('Location: ' . $_SERVER['PHP_SELF'] . $qs);
    exit;
}

// ------------------------------------------------------------------
// FILTER: capture search term from GET param (free text or "exact")
// ------------------------------------------------------------------
$selectedDescription = isset($_GET['req']) ? trim($_GET['req']) : '';

// 2) Build a flat array of ALL requirements from all modules AND collect distinct descriptions
$allRequirements = [];
$distinctRequirementCounts = [];

foreach ($data['modules'] as $module) {
    $moduleName = $module['name'];
    $moduleTerm = isset($module['term']) ? (int)$module['term'] : 999; // fallback if missing
    foreach ($module['requirements'] as $req) {
        $desc = isset($req['description']) ? (string)$req['description'] : '';
        $distinctRequirementCounts[$desc] = ($distinctRequirementCounts[$desc] ?? 0) + 1;

        $allRequirements[] = [
            'moduleName'   => $moduleName,
            'term'         => $moduleTerm,  // used if no date
            'description'  => $desc,
            'credits'      => $req['credits'],
            'done'         => !empty($req['done']),
            'date'         => isset($req['date']) ? trim($req['date']) : '',
            'uuid'         => $req['uuid'],
        ];
    }
}

// ------------------------------------------------------------------
// Apply per-session "done" overrides (from helpers.php)
// ------------------------------------------------------------------
if (function_exists('applyRequirementDoneOverrides')) {
    $allRequirements = applyRequirementDoneOverrides($allRequirements);
}

// --- Helpers for searching ---
$contains = function ($haystack, $needle) {
    if ($needle === '') return true;
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
    return stripos($haystack, $needle) !== false;
};
$equalsIgnoreCase = function ($a, $b) {
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
    }
    return strcasecmp($a, $b) === 0;
};
/**
 * Extract quoted inner text if user wrapped the query in quotes.
 * Supports straight quotes " ", smart quotes “ ” and „ “, « », ‹ ›, and ' '.
 */
$extractQuoted = function ($s) {
    $sTrim = trim($s);
    if ($sTrim === '') return [false, $s];

    $pairs = [
        '"' => '"',
        '“' => '”',
        '„' => '“',
        '«' => '»',
        '‹' => '›',
        '‘' => '’',
        '‚' => '‘',
        "'" => "'",
    ];

    if (function_exists('mb_substr')) {
        $first = mb_substr($sTrim, 0, 1, 'UTF-8');
        $last  = mb_substr($sTrim, -1, 1, 'UTF-8');
        $len   = mb_strlen($sTrim, 'UTF-8');
        if (isset($pairs[$first]) && $pairs[$first] === $last && $len >= 2) {
            $inner = mb_substr($sTrim, 1, $len - 2, 'UTF-8');
            return [true, $inner];
        }
    } else {
        $first = substr($sTrim, 0, 1);
        $last  = substr($sTrim, -1);
        $len   = strlen($sTrim);
        if (isset($pairs[$first]) && $pairs[$first] === $last && $len >= 2) {
            $inner = substr($sTrim, 1, $len - 2);
            return [true, $inner];
        }
    }
    return [false, $s];
};

// 2b) Apply filter
$noResults = false;
if ($selectedDescription !== '') {
    list($isQuoted, $needle) = $extractQuoted($selectedDescription);

    $allRequirements = array_values(array_filter(
        $allRequirements,
        function ($r) use ($needle, $isQuoted, $contains, $equalsIgnoreCase) {
            $desc = isset($r['description']) ? $r['description'] : '';
            return $isQuoted
                ? $equalsIgnoreCase($desc, $needle)      // exact equality (case-insensitive)
                : $contains($desc, $needle);             // substring (case-insensitive)
        }
    ));

    if (count($allRequirements) === 0) {
        $noResults = true;
    }
}

// 3) Sort $allRequirements with the 4-level priority
usort($allRequirements, function ($a, $b) {
    $aDone = $a['done'] ? 1 : 0;
    $bDone = $b['done'] ? 1 : 0;
    if ($aDone !== $bDone) {
        return $aDone <=> $bDone; // not done (0) => first, done (1) => last
    }

    $aDate = $a['date'] ? $a['date'] : '9999-12-31';
    $bDate = $b['date'] ? $b['date'] : '9999-12-31';
    $dateCompare = strcmp($aDate, $bDate);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    return $a['term'] <=> $b['term'];
});

// We'll use today's date for row "grey-out" check
$today = date('Y-m-d');

/**
 * getTimeUntilDisplay($dateStr)
 */
function getTimeUntilDisplay($dateStr)
{
    if (!$dateStr) return ['', ''];
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) return ['', ''];

    $todayDt = new DateTime('today');
    $diff = $todayDt->diff($dt);
    $days = $diff->days;
    $isPast = ($diff->invert === 1);

    if ($isPast) $days += 1;

    $minusSign = $isPast ? '-' : '';
    if ($days > 395) {
        $years = floor($days / 365);
        $remDays = $days % 365;
        $months = floor($remDays / 30);
        $timeStr = $minusSign . $years . 'y ' . $months . 'M';
    } elseif ($days > 365) {
        $years = floor($days / 365);
        $remDays = $days % 365;
        $timeStr = $minusSign . $years . 'y ' . $remDays . 'd';
    } elseif ($days > 30) {
        $months = floor($days / 30);
        $remDays = $days % 30;
        $timeStr = $minusSign . $months . 'M ' . $remDays . 'd';
    } else {
        $timeStr = $minusSign . $days . 'd';
    }

    if ($isPast) {
        $style = 'color:inherit;';
    } else {
        if ($days > 7)      $style = 'color:green;';
        elseif ($days >= 3) $style = 'color:orange;';
        else                $style = 'color:red;';
    }

    return [$timeStr, $style];
}

/**
 * getTimeBetween($startDateStr, $endDateStr)
 */
function getTimeBetween($startDateStr, $endDateStr)
{
    if (!$startDateStr || !$endDateStr) return ['', ''];
    $startDt = DateTime::createFromFormat('Y-m-d', $startDateStr);
    $endDt   = DateTime::createFromFormat('Y-m-d', $endDateStr);
    if (!$startDt || !$endDt) return ['', ''];

    $diff = $startDt->diff($endDt);
    $days = $diff->days;

    if ($days > 395) {
        $years = floor($days / 365);
        $remDays = $days % 365;
        $months = floor($remDays / 30);
        $timeStr = $years . 'y ' . $months . 'M';
    } elseif ($days > 365) {
        $years = floor($days / 365);
        $remDays = $days % 365;
        $timeStr = $years . 'y ' . $remDays . 'd';
    } elseif ($days > 30) {
        $months = floor($days / 30);
        $remDays = $days % 30;
        $timeStr = $months . 'M ' . $remDays . 'd';
    } else {
        $timeStr = $days . 'd';
    }

    return [$timeStr, ''];
}

// Prepare list of requirement options sorted naturally (case-insensitive) for datalist suggestions
$reqOptions = $distinctRequirementCounts; // copy
if (!empty($reqOptions)) {
    uksort($reqOptions, function($a,$b){return strnatcasecmp($a,$b);} );
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>All Requirements by Date</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* keep the input compact within the table header */
        .req-filter-select {
            max-width: 250px;
        }
        .req-filter-form {
            margin: 4px 0 0 0;
        }
        .req-filter-form input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            padding: 4px 6px;
        }
        .no-results-msg {
            margin-top: 8px;
            color: #666;
            font-style: italic;
        }
        .req-row-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .req-done-form {
            display: inline;
            margin: 0;
        }
        /* Visual feedback while we save in background */
        tr.pending-save {
            opacity: 0.6;
        }
    </style>
</head>

<body>

    <div class="container">
        <header>
            <header>
                <?php include __DIR__ . '/app/elements/header.php'; ?>
            </header>
        </header>

        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #ccc;">
                    <th style="text-align:left; width:90px">Time<br>Until</th>
                    <th style="text-align:left; padding:8px;">Time<br>Delta</th>
                    <th style="text-align:left; width:100px">Date</th>
                    <th style="text-align:left; padding:8px;">
                        Requirement
                        <form method="get" action="" class="req-filter-form" style="display:flex; gap:6px; align-items:center;">
                            <input
                                type="text"
                                name="req"
                                class="req-filter-select"
                                list="req-options"
                                placeholder='Search requirements… (use "exact phrase" for exact match)'
                                value="<?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?>"
                                title='Type to filter. Use quotes for exact matches, e.g. "Anmeldung Prüfung".'
                            >
                            <datalist id="req-options">
                                <?php foreach ($reqOptions as $optDesc => $cnt): ?>
                                    <option value="<?php echo htmlspecialchars($optDesc, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                    <option value="<?php echo htmlspecialchars('"' . $optDesc . '"', ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if ($selectedDescription !== ''): ?>
                                <a href="?req=" style="font-size:0.9em; text-decoration:underline; white-space:nowrap;">Clear</a>
                            <?php endif; ?>
                            <noscript><button type="submit">Search</button></noscript>
                        </form>
                    </th>
                    <th style="text-align:left; padding:8px;">Term: Module</th>
                    <th style="text-align:left; padding:8px;">Credits</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $lastDate = '';
                foreach ($allRequirements as $req):
                    // Row styling for done or date < today => grey out
                    $rowStyle = '';
                    if ($req['done']) {
                        $rowStyle = 'background-color:#f0f0f0; color:#999;';
                    } else {
                        if (!empty($req['date']) && $req['date'] < $today) {
                            $rowStyle = 'background-color:#f0f0f0; color:#999;';
                        }
                    }

                    list($timeStrUntil, $timeStyleUntil) = getTimeUntilDisplay($req['date']);

                    $dateDisplay = $req['date'] ? $req['date'] : '—';
                    $desc = htmlspecialchars($req['description']);
                    $mod = htmlspecialchars($req['moduleName']);
                    $credits = (int) $req['credits'];
                    $term = (int) $req['term'];
                ?>
                    <tr style="border-bottom:1px solid #eee; <?php echo $rowStyle; ?>">
                        <!-- Time Until -->
                        <td style="<?php echo $timeStyleUntil; ?>">
                            <?php echo $timeStrUntil; ?>
                        </td>

                        <!-- Time Between -->
                        <td style="padding:8px; <?php echo $timeStyleUntil; ?>">
                            <?php
                            if ($req['date'] && $lastDate) {
                                list($timeStrBetween, $unusedStyle) = getTimeBetween($req['date'], $lastDate);
                                echo $timeStrBetween;
                            } else {
                                echo '—';
                            }
                            $lastDate = $req['date'];
                            ?>
                        </td>

                        <td><?php echo $dateDisplay; ?></td>

                        <!-- Requirement (checkbox + link) -->
                        <td style="padding:8px;">
                            <div class="req-row-wrap">
                                <form method="post" action="" class="req-done-form">
                                    <input type="hidden" name="_action" value="toggle_done">
                                    <input type="hidden" name="uuid" value="<?php echo htmlspecialchars($req['uuid'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="current_req" value="<?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="target_done" value="<?php echo $req['done'] ? '1' : '0'; ?>">
                                    <input type="hidden" name="ajax" value="1">
                                    <input
                                        type="checkbox"
                                        class="js-done-checkbox"
                                        <?php echo $req['done'] ? 'checked' : ''; ?>
                                        title="Mark as done / not done"
                                    >
                                    <noscript><button type="submit">Save</button></noscript>
                                </form>

                                <a href="requirement.php?id=<?php echo urlencode($req['uuid']); ?>" style="color:inherit; text-decoration:underline;">
                                    <?php echo $desc; ?>
                                </a>
                            </div>
                        </td>

                        <td style="padding:8px;"><?php echo $term; ?>: <?php echo $mod; ?></td>
                        <td style="padding:8px;"><?php echo $credits === 0 ? '' : $credits; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($allRequirements)): ?>
                    <!-- Keep an empty body so the table structure remains visible -->
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($noResults): ?>
            <div class="no-results-msg">
                No requirements found matching
                "<?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?>".
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <?php include __DIR__ . '/app/elements/footer.php'; ?>
    </footer>

    <!-- Auto-submit search after typing stops (debounced) -->
    <script>
    (function () {
        var form = document.querySelector('.req-filter-form');
        if (!form) return;
        var input = form.querySelector('input[name="req"]');
        if (!input) return;

        var t;
        input.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () { form.submit(); }, 300);
        });
    })();
    </script>

    <!-- Optimistic checkbox + background save + full reload -->
    <script>
    (function () {
        function qs(el, sel) { return el ? el.querySelector(sel) : null; }

        document.addEventListener('change', function (e) {
            var cb = e.target;
            if (!(cb && cb.classList && cb.classList.contains('js-done-checkbox'))) return;

            var form = cb.form;
            if (!form) return;

            // Update hidden field to reflect new state
            var hidden = qs(form, 'input[name="target_done"]');
            if (hidden) hidden.value = cb.checked ? '1' : '0';

            // Optimistic UI: keep it checked/unchecked, disable it, and mark row as pending
            cb.disabled = true;
            var tr = cb.closest('tr');
            if (tr) tr.classList.add('pending-save');

            // Build FormData and send as background POST
            var fd = new FormData(form);

            // Some servers rely on X-Requested-With to detect ajax
            var headers = new Headers({ 'X-Requested-With': 'XMLHttpRequest' });

            fetch(form.action || window.location.href, {
                method: 'POST',
                body: fd,
                headers: headers,
                credentials: 'same-origin'
            }).then(function () {
                // After the server stores the change, fully reload preserving ?req=
                var url = new URL(window.location.href);
                window.location.replace(url.pathname + url.search);
            }).catch(function () {
                // On error, still reload so state doesn't get stuck
                window.location.reload();
            });
        }, { passive: true });
    })();
    </script>
</body>

</html>
