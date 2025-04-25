<?php
/**
 * stats.php – Visual statistics dashboard for the University Module Support System
 * -----------------------------------------------------------------------------
 * 1️⃣  Cumulative credits earned  vs.  cumulative planned credits  + target lines
 * 2️⃣  Per‑term stacked bars: earned vs. missing credits
 * 3️⃣  Grade‑average progression after every graded requirement (exam)
 *      • Red line = running, credit‑weighted average (points only where exams exist).
 *      • Blue dots = the individual exam grades themselves (not connected).
 *      • Hover shows *Module – Requirement* for each point; axis titles hidden.
 *      • Y‑axis reversed so grade 1.0 is at the top.
 */

session_start();

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/app/logic.php'; // populates $data

//---------------------------------------------------------
// 1) Per‑term statistics (planned / earned / missing)
//---------------------------------------------------------
$modules = $data['modules'] ?? [];

$termPlanned = $termEarned = [];
foreach ($modules as $mod) {
    $term = (int)$mod['term'];
    foreach ($mod['requirements'] as $req) {
        $credits = (float)($req['credits'] ?? 0);
        if ($credits <= 0) continue;
        $termPlanned[$term] = ($termPlanned[$term] ?? 0) + $credits;
        if (!empty($req['done'])) {
            $termEarned[$term] = ($termEarned[$term] ?? 0) + $credits;
        }
    }
}
if (!$termPlanned && !$termEarned) { echo 'No credit data yet.'; exit; }

$labels   = range(1, (int)max(array_keys($termPlanned + $termEarned)));
$plannedPerTerm = $earnedPerTerm = $missingPerTerm = [];
$cumPlanned = $cumEarned = [];
$runningPlanned = $runningEarned = 0;
foreach ($labels as $t) {
    $pl = $termPlanned[$t] ?? 0;
    $er = $termEarned[$t]  ?? 0;
    $ms = max($pl - $er, 0);
    $plannedPerTerm[] = $pl;
    $earnedPerTerm[]  = $er;
    $missingPerTerm[] = $ms;
    $runningPlanned += $pl;
    $runningEarned  += $er;
    $cumPlanned[] = $runningPlanned;
    $cumEarned[]  = ($er > 0) ? $runningEarned : null; // gap for idle terms
}

//---------------------------------------------------------
// 2) Target lines (ideal cumulative)
//---------------------------------------------------------
$targets         = $data['semesterTargets'];
$totalNeeded     = (int)$data['totalNeededCredits'];
$targetLinesData = [];
foreach ($targets as $tg) {
    $line = [];
    foreach ($labels as $t) {
        $val = (int)floor(($t / $tg) * $totalNeeded);
        $line[] = ($val > $totalNeeded) ? $totalNeeded : $val;
    }
    $targetLinesData[] = ['name' => "Target $tg", 'data' => $line];
}

//---------------------------------------------------------
// 3) Grade progression + individual exam grades
//---------------------------------------------------------
$examEntries = [];
$seq=0;
foreach ($modules as $mod) {
    foreach ($mod['requirements'] as $req) {
        $grade   = isset($req['grade']) ? (float)$req['grade'] : null;
        $credits = (float)($req['credits'] ?? 0);
        if ($credits<=0 || $grade===null || empty($req['done'])) continue;
        $examEntries[] = [
            'module' => $mod['name'],
            'desc'   => $req['description'],
            'credits'=> $credits,
            'grade'  => $grade,
            'date'   => $req['date'] ? strtotime($req['date']) : null,
            'seq'    => $seq++,
        ];
    }
}
// sort by date, fallback to input order
usort($examEntries, function($a,$b){
    if($a['date']!==null && $b['date']!==null) return $a['date']<=>$b['date'];
    if($a['date']===null && $b['date']===null) return $a['seq']<=>$b['seq'];
    return ($a['date']===null)?1:-1;
});

$gradeLabels=$gradeProgress=$actualGrades=[];
$runSum=$runCred=0.0;
foreach($examEntries as $e){
    $runSum  += $e['grade']*$e['credits'];
    $runCred += $e['credits'];
    $gradeLabels[]   = $e['module'].' – '.$e['desc'];
    $gradeProgress[] = round($runSum/$runCred,3);
    $actualGrades[]  = $e['grade'];
}

//---------------------------------------------------------
// HTML output
//---------------------------------------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Stats – University Module Support System</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-trendline/dist/chartjs-plugin-trendline.min.js"></script>
<style>.chart-wrapper{margin-bottom:40px}canvas{max-width:100%;height:420px}</style>
</head>
<body>
<div class="container">
 <header>
  <h1>Statistics Dashboard (<?php echo htmlspecialchars($_SESSION['username']); ?>)</h1>
  <div class="top-links"><a href="index.php">Back to Overview</a></div>
 </header>

 <div class="chart-wrapper"><canvas id="cumChart"></canvas></div>
 <div class="chart-wrapper"><canvas id="termBarChart"></canvas></div>
 <?php if($gradeProgress): ?><div class="chart-wrapper"><canvas id="gradeChart"></canvas></div><?php endif; ?>
</div>

<script>
const labels         = <?php echo json_encode($labels); ?>;
const cumEarned      = <?php echo json_encode($cumEarned); ?>;
const cumPlanned     = <?php echo json_encode($cumPlanned); ?>;
const earnedPerTerm  = <?php echo json_encode($earnedPerTerm); ?>;
const missingPerTerm = <?php echo json_encode($missingPerTerm); ?>;
const targetLines    = <?php echo json_encode($targetLinesData); ?>;
const gradeLabels    = <?php echo json_encode($gradeLabels); ?>;
const gradeProgress  = <?php echo json_encode($gradeProgress); ?>;
const actualGrades   = <?php echo json_encode($actualGrades); ?>;

// -------- Chart 1: cumulative credits --------
const datasetsCum=[{label:'Earned ∑',data:cumEarned,borderWidth:2,borderColor:'rgba(44,130,201,1)',backgroundColor:'rgba(44,130,201,0.3)',tension:0,spanGaps:false,pointRadius:4,pointHoverRadius:6},{label:'Planned ∑',data:cumPlanned,borderWidth:2,borderDash:[6,6],borderColor:'rgba(100,100,100,0.8)',backgroundColor:'rgba(100,100,100,0.2)',tension:0,fill:false,pointRadius:0,pointHoverRadius:0}];
function randColour(){return`hsl(${Math.floor(Math.random()*360)},100%,50%)`}for(const t of targetLines){datasetsCum.push({label:t.name,data:t.data,borderWidth:1,borderDash:[4,4],borderColor:randColour(),pointRadius:0,pointHoverRadius:0,tension:0})}
new Chart(document.getElementById('cumChart'),{type:'line',data:{labels,datasets:datasetsCum},options:{plugins:{title:{display:true,text:'Cumulative Credits vs. Targets'},legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});

// -------- Chart 2: per‑term stacked bars --------
new Chart(document.getElementById('termBarChart'),{type:'bar',data:{labels,datasets:[{label:'Earned',data:earnedPerTerm,backgroundColor:'rgba(44,130,201,0.85)',stack:'credits'},{label:'Missing',data:missingPerTerm,backgroundColor:'rgba(200,200,200,0.6)',stack:'credits'}]},options:{plugins:{title:{display:true,text:'Per‑Term Credits (earned vs. missing)'},legend:{position:'bottom'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}}}});

// -------- Chart 3: grade average + individual grades --------
if(gradeProgress.length){
  new Chart(document.getElementById('gradeChart'),{
    type:'line',
    data:{labels:gradeLabels,datasets:[
      {
        label:'Average (cumulative)',
        data:gradeProgress,
        borderColor:'rgba(231,76,60,1)',
        backgroundColor:'rgba(231,76,60,0.25)',
        tension:0,
        pointRadius:4,
        pointHoverRadius:6,
        borderWidth:2
      },
      {
        label:'Exam grade',
        data:actualGrades,
        showLine:false,
        borderColor:'rgba(52,152,219,0.9)',
        backgroundColor:'rgba(52,152,219,0.9)',
        pointRadius:5,
        pointHoverRadius:7,
        type:'line', // keeps categorical x‑axis mapping without extra {x,y}
        trendlineLinear: {
            colorMin: 'rgba(52,152,219,0.25)',  // start colour
            colorMax: 'rgba(52,152,219,0.25)',  // end colour (same = solid line)
            lineStyle: 'dashed',                // solid | dotted | dashed | dashdot
            width: 2
        }
      }
    ]},
    options:{
      plugins:{
        title:{display:true,text:'Grade Average Progression'},
        legend:{position:'bottom'},
        tooltip:{callbacks:{label:ctx=>{
          const val=ctx.parsed.y.toFixed(2);
          return ctx.dataset.label==='Average (cumulative)'?'Ø '+val:val;
        }}}
      },
      scales:{
        y:{reverse:true,suggestedMin:1},
        x:{display:false}
      }
    }
  })
}
</script>
</body>
</html>
