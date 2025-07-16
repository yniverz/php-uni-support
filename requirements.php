<?php
session_start();

// 1) Load config/data/helpers
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

if (!$isLoggedIn) {
    header("Location: login.php");
    exit;
}

// ------------------------------------------------------------------
// FILTER: capture selected requirement description from GET param
// ------------------------------------------------------------------
$selectedDescription = isset($_GET['req']) ? trim($_GET['req']) : '';

// 2) Build a flat array of ALL requirements from all modules AND collect distinct descriptions
$allRequirements = [];
$distinctRequirementCounts = [];

foreach ($data['modules'] as $module) {
    $moduleName = $module['name'];
    $moduleTerm = isset($module['term']) ? (int)$module['term'] : 999; // fallback if missing
    foreach ($module['requirements'] as $req) {
        $desc = $req['description'];
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

// 2b) If a filter is active, reduce $allRequirements to only matching description
if ($selectedDescription !== '') {
    $allRequirements = array_values(array_filter($allRequirements, function ($r) use ($selectedDescription) {
        return $r['description'] === $selectedDescription;
    }));
}

// 3) Sort $allRequirements with the 4-level priority
//    1) Not done + earliest date
//    2) Not done + no date (then by term)
//    3) Done + earliest date
//    4) Done + no date (then by term)
usort($allRequirements, function ($a, $b) {
    // (a) Compare done status first
    $aDone = $a['done'] ? 1 : 0;
    $bDone = $b['done'] ? 1 : 0;
    if ($aDone !== $bDone) {
        return $aDone <=> $bDone; // not done (0) => first, done (1) => last
    }

    // (b) If same done status, compare by date
    $aDate = $a['date'] ? $a['date'] : '9999-12-31';
    $bDate = $b['date'] ? $b['date'] : '9999-12-31';
    $dateCompare = strcmp($aDate, $bDate);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    // (c) If same date (including no-date), fallback to module term
    return $a['term'] <=> $b['term'];
});

// We'll use today's date for row "grey-out" check
$today = date('Y-m-d');

/**
 * getTimeUntilDisplay($dateStr)
 * Returns [ $timeString, $styleString ] 
 *   - $timeString includes a leading "-" if the date is in the past.
 *   - $styleString for color-coded text if in the future:
 *       >7 => green
 *       3..7 => orange
 *       0..2 => red
 *     If in the past => normal text color (inherit).
 */
function getTimeUntilDisplay($dateStr)
{
    if (!$dateStr) {
        // no date => blank
        return ['', ''];
    }
    // Attempt to parse the date
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) {
        // invalid or partial => no display
        return ['', ''];
    }

    // Compare with "today"
    $todayDt = new DateTime('today');
    $diff = $todayDt->diff($dt);
    $days = $diff->days;        // absolute difference in days
    $isPast = ($diff->invert === 1); // true if date < today

    if ($isPast) {
        // date is in the past => no special color
        $days += 1; // add 1 day to show "1d" instead of "0d"
    }

    // Build time string:
    $minusSign = $isPast ? '-' : '';
    if ($days > 365 + 30) {
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

    // Color logic:
    // negative => normal text
    // >7 => green, 3..7 => orange, 0..2 => red
    $style = '';
    if ($isPast) {
        // date is in the past => no special color
        $style = 'color:inherit;';
    } else {
        if ($days > 7) {
            $style = 'color:green;';
        } elseif ($days >= 3) {
            $style = 'color:orange;';
        } elseif ($days >= 0) {
            // 0..2 => red
            $style = 'color:red;';
        }
    }

    return [$timeStr, $style];
}

/**
 * getTimeBetween($startDateStr, $endDateStr)
 * Returns [ $timeString, '' ]
 * Used for the "Time Delta" column: difference between the current row's date
 * and the *previous visible row's* date (after filtering).
 */
function getTimeBetween($startDateStr, $endDateStr)
{
    if (!$startDateStr || !$endDateStr) {
        // no date => blank
        return ['', ''];
    }
    // Attempt to parse the date
    $startDt = DateTime::createFromFormat('Y-m-d', $startDateStr);
    $endDt = DateTime::createFromFormat('Y-m-d', $endDateStr);
    if (!$startDt || !$endDt) {
        // invalid or partial => no display
        return ['', ''];
    }

    $diff = $startDt->diff($endDt);
    $days = $diff->days;        // absolute difference in days

    // Build time string:
    if ($days > 365 + 30) {
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

// Prepare list of requirement options sorted naturally (case-insensitive)
$reqOptions = $distinctRequirementCounts; // copy
if (!empty($reqOptions)) {
    // Natural, case-insensitive sort by key (description)
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
        /* keep the select compact within the table header */
        .req-filter-select {
            max-width: 250px;
        }
        .req-filter-form {
            margin: 4px 0 0 0;
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

        <?php if (empty($allRequirements)): ?>
            <?php if ($selectedDescription !== ''): ?>
                <p>No requirements found for "<?php echo htmlspecialchars($selectedDescription); ?>".</p>
            <?php else: ?>
                <p>No requirements found.</p>
            <?php endif; ?>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #ccc;">
                        <th style="text-align:left; width:90px">Time<br>Until</th>
                        <th style="text-align:left; padding:8px;">Time<br>Delta</th>
                        <th style="text-align:left; width:100px">Date</th>
                        <th style="text-align:left; padding:8px;">
                            Requirement
                            <form method="get" action="" class="req-filter-form">
                                <select name="req" class="req-filter-select" onchange="this.form.submit()">
                                    <option value=""<?php echo ($selectedDescription === '' ? ' selected' : ''); ?>>All requirements</option>
                                    <?php foreach ($reqOptions as $optDesc => $cnt): ?>
                                        <option value="<?php echo htmlspecialchars($optDesc, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($selectedDescription === $optDesc ? ' selected' : ''); ?>>
                                            <?php echo htmlspecialchars($optDesc, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $cnt; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                            // if not done but date < today => also grey out
                            if (!empty($req['date']) && $req['date'] < $today) {
                                $rowStyle = 'background-color:#f0f0f0; color:#999;';
                            }
                        }

                        // "time until" column
                        list($timeStrUntil, $timeStyleUntil) = getTimeUntilDisplay($req['date']);

                        $dateDisplay = $req['date'] ? $req['date'] : '—';
                        $desc = htmlspecialchars($req['description']);
                        $mod = htmlspecialchars($req['moduleName']);
                        $credits = (int) $req['credits'];
                        $term = (int) $req['term'];
                        $done = $req['done'] ? 'Yes' : 'No';
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
                                // update $lastDate to current row date *after* computing delta
                                $lastDate = $req['date'];
                                ?>
                            </td>

                            <td><?php echo $dateDisplay; ?></td>
                            <td style="padding:8px;">
                                <a href="requirement.php?id=<?php echo urlencode($req['uuid']); ?>" style="color:inherit; text-decoration:underline;">
                                    <?php echo $desc; ?>
                                </a>
                            </td>
                            <td style="padding:8px;"><?php echo $term; ?>: <?php echo $mod; ?></td>
                            <td style="padding:8px;"><?php echo $credits === 0 ? '' : $credits; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <?php include __DIR__ . '/app/elements/footer.php'; ?>
    </footer>

</body>

</html>
