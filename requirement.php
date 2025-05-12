<?php
// public/requirement.php - detailed view & editor for a single requirement
// Requires a GET parameter ?id=<uuid>
// Data is stored in requirement['details'] = [ 'notes' => string, 'subs' => [ [desc,done], ... ] ]

session_start();
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/logic.php';

$uuid = trim($_GET['id'] ?? '');
if ($uuid === '') {
    exit('<p>Invalid request - missing requirement id.</p>');
}

$found = null;
$modIndex = $reqIndex = null;
foreach ($data['modules'] as $mi => $m) {
    foreach ($m['requirements'] as $ri => $r) {
        if (($r['uuid'] ?? '') === $uuid) {
            $found = $r;
            $modIndex = $mi;
            $reqIndex = $ri;
            break 2;
        }
    }
}
if (!$found) {
    exit('<p>Requirement not found.</p>');
}

// ensure details field exists
if (!isset($data['modules'][$modIndex]['requirements'][$reqIndex]['details'])) {
    $data['modules'][$modIndex]['requirements'][$reqIndex]['details'] = ['notes' => '', 'subs' => []];
}
$details =& $data['modules'][$modIndex]['requirements'][$reqIndex]['details'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // update notes
    if (isset($_POST['notes'])) {
        $details['notes'] = trim($_POST['notes']);
    }
    // add sub requirement
    if (isset($_POST['add_sub'])) {
        $desc = trim($_POST['sub_desc'] ?? '');
        if ($desc !== '') {
            $details['subs'][] = ['desc' => $desc, 'done' => false];
        }
    }
    // toggle sub done
    if (isset($_POST['toggle_sub'])) {
        $si = (int) $_POST['sub_index'];
        if (isset($details['subs'][$si])) {
            $details['subs'][$si]['done'] = !$details['subs'][$si]['done'];
        }
    }
    // delete sub
    if (isset($_POST['delete_sub'])) {
        $si = (int) $_POST['sub_index'];
        if (isset($details['subs'][$si])) {
            array_splice($details['subs'], $si, 1);
        }
    }

    // persist changes
    saveData($data, $jsonFile);
    header('Location: requirement.php?id=' . urlencode($uuid));
    exit;
}

$req = $data['modules'][$modIndex]['requirements'][$reqIndex];
$moduleName = $data['modules'][$modIndex]['name'];
$subList = $details['subs'];
$notes = $details['notes'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Requirement - <?php echo htmlspecialchars($req['description']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="container">
        <header>
            <?php include __DIR__ . '/app/elements/header.php'; ?>
        </header>
        <h2><?php echo htmlspecialchars($req['description']); ?></h2>
        <p><strong>Module:</strong> <?php echo htmlspecialchars($moduleName); ?></p>
        <p><strong>Credits:</strong> <?php echo $req['credits']; ?></p>
        <p><strong>Date:</strong> <?php echo htmlspecialchars($req['date'] ?? '—'); ?></p>
        <hr>
        <h3>Notes</h3>
        <form method="post">
            <textarea name="notes" rows="4"
                style="width:100%;max-width:100%;"><?php echo htmlspecialchars($notes); ?></textarea><br>
            <button type="submit">Save Notes</button>
        </form>
        <hr>
        <h3>Sub-requirements</h3>
        <ul class="sub-list">
            <?php foreach ($subList as $i => $sub): ?>
                <li>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sub_index" value="<?php echo $i; ?>">
                        <input type="hidden" name="toggle_sub" value="1">
                        <input type="checkbox" <?php if ($sub['done'])
                            echo 'checked'; ?> onchange="this.form.submit();">
                        <?php echo htmlspecialchars($sub['desc']); ?>
                    </form>
                    <form method="post" style="display:inline; margin-left:6px;">
                        <input type="hidden" name="sub_index" value="<?php echo $i; ?>">
                        <input type="hidden" name="delete_sub" value="1">
                        <button type="submit" onclick="return confirm('Delete sub-requirement?');">x</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="margin-top:8px;">
            <input type="text" name="sub_desc" placeholder="New sub-requirement" required>
            <button type="submit" name="add_sub" value="1">Add</button>
        </form>
    </div>
    <footer><?php include __DIR__ . '/app/elements/footer.php'; ?></footer>
</body>

</html>