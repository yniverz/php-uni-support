<?php

session_start();

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/app/logic.php';

//---------------------------------------------
// Build term‑wise statistics
//---------------------------------------------
$modules = $data['modules'] ?? [];

$termPlanned = [];// credits planned in each term (done or not)
$termEarned  = [];// credits actually earned (requirements marked done)

foreach ($modules as $mod) {
    $term = (int)$mod['term'];
    foreach ($mod['requirements'] as $req) {
        $credits = (float)$req['credits'];
        if ($credits <= 0) continue;

        // planned credits (every requirement counts)
        if (!isset($termPlanned[$term])) $termPlanned[$term] = 0;
        $termPlanned[$term] += $credits;

        // earned credits (only if done)
        if (!empty($req['done'])) {
            if (!isset($termEarned[$term])) $termEarned[$term] = 0;
            $termEarned[$term] += $credits;
        }
    }
}

if (empty($termPlanned) && empty($termEarned)) {
    echo 'No data yet.';
    exit;
}

$maxTerm = !empty($termPlanned) ? max(array_keys($termPlanned)) : 0;
$maxTerm = max($maxTerm, !empty($termEarned) ? max(array_keys($termEarned)) : 0);

// Term labels 1 ... $maxTerm
$labels = range(1, $maxTerm);

// Build per‑term arrays aligned to labels
$plannedPerTerm = [];
$earnedPerTerm  = [];
$missingPerTerm = [];

$cumPlanned = [];
$cumEarned  = [];

$runningPlanned = 0;
$runningEarned  = 0;

foreach ($labels as $t) {
    $planned = $termPlanned[$t] ?? 0;
    $earned  = $termEarned[$t]  ?? 0;
    $missing = max($planned - $earned, 0);

    $plannedPerTerm[] = $planned;
    $earnedPerTerm[]  = $earned;
    $missingPerTerm[] = $missing;

    $runningPlanned += $planned;
    $runningEarned  += $earned;

    $cumPlanned[] = $runningPlanned;
    // For the earned cumulative line we want null when earned==0 to break line
    $cumEarned[]  = ($earned > 0) ? $runningEarned : null;
}

//---------------------------------------------
// Build target lines (cumulative ideal)
//---------------------------------------------
$targets         = $data['semesterTargets'];
$totalNeeded     = (int)$data['totalNeededCredits'];
$targetLinesData = [];// array of [ 'name' => 'Target 6', 'data' => [...] ]

foreach ($targets as $target) {
    $line = [];
    foreach ($labels as $t) {
        $value = floor(($t / $target) * $totalNeeded);
        if ($value > $totalNeeded) $value = $totalNeeded;
        $line[] = $value;
    }
    $targetLinesData[] = [ 'name' => "Target $target", 'data' => $line ];
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Stats - University Module Support System</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="container">
    <header>
        <h1>Statistics Dashboard (<?php echo htmlspecialchars($_SESSION['username']); ?>)</h1>
        <div class="top-links">
            <a href="index.php">Back to Overview</a>
        </div>
    </header>

    <div class="chart-wrapper">
        <canvas id="cumChart"></canvas>
    </div>

    <div class="chart-wrapper">
        <canvas id="termBarChart"></canvas>
    </div>
</div>

<script>
const labels         = <?php echo json_encode($labels); ?>;
const cumEarned      = <?php echo json_encode($cumEarned); ?>;
const cumPlanned     = <?php echo json_encode($cumPlanned); ?>;
const plannedPerTerm = <?php echo json_encode($plannedPerTerm); ?>;
const earnedPerTerm  = <?php echo json_encode($earnedPerTerm); ?>;
const missingPerTerm = <?php echo json_encode($missingPerTerm); ?>;
const targetLines    = <?php echo json_encode($targetLinesData); ?>;

// ----------------- Chart 1: cumulative -----------------
const datasetsCum = [
    {
        label: 'Earned ∑',
        data: cumEarned,
        borderWidth: 2,
        borderColor: 'rgba(44, 130, 201, 1)',
        backgroundColor: 'rgba(44, 130, 201, 0.3)',
        tension: 0,
        spanGaps: false,
        pointRadius: 4,
        pointHoverRadius: 6,
    },
    {
        label: 'Planned ∑',
        data: cumPlanned,
        borderWidth: 2,
        borderDash: [6, 6],
        borderColor: 'rgba(100, 100, 100, 0.8)',
        backgroundColor: 'rgba(100, 100, 100, 0.2)',
        tension: 0,
        fill: false,
        pointRadius: 0,
        pointHoverRadius: 0,
    },
];
// Append each target line
for (const t of targetLines) {
    // random color
    const randomColor = () => {
        // do with hsv
        const hue = Math.floor(Math.random() * 360);
        return `hsl(${hue}, 100%, 50%)`;
    };

    datasetsCum.push({
        label: t.name,
        data: t.data,
        borderWidth: 1,
        borderDash: [4, 4],
        borderColor: randomColor(),
        pointRadius: 0,
        pointHoverRadius: 0,
        tension: 0,
    });
}

new Chart(
    document.getElementById('cumChart').getContext('2d'),
    {
        type: 'line',
        data: { labels, datasets: datasetsCum },
        options: {
            plugins: {
                title: { display: true, text: 'Cumulative Credits vs. Targets' },
                legend: { position: 'bottom' },
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Credits' } }
            }
        }
    }
);

// ----------------- Chart 2: term stacked bars -----------------
new Chart(
    document.getElementById('termBarChart').getContext('2d'),
    {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Earned',
                    data: earnedPerTerm,
                    backgroundColor: 'rgba(44, 130, 201, 0.8)',
                    stack: 'credits',
                },
                {
                    label: 'Missing',
                    data: missingPerTerm,
                    backgroundColor: 'rgba(200, 200, 200, 0.5)',
                    stack: 'credits',
                },
            ]
        },
        options: {
            plugins: {
                title: { display: true, text: 'Per-Term Credits (earned vs. missing)' },
                legend: { position: 'bottom' },
            },
            scales: {
                x: { stacked: true, title: { display: true, text: 'Term' } },
                y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Credits' } }
            }
        }
    }
);
</script>
</body>
</html>
