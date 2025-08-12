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

// Ensure details field exists
if (!isset($data['modules'][$modIndex]['requirements'][$reqIndex]['details'])) {
    $data['modules'][$modIndex]['requirements'][$reqIndex]['details'] = ['notes' => '', 'subs' => []];
}
$details =& $data['modules'][$modIndex]['requirements'][$reqIndex]['details'];

// --- Handle POST actions ---
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A) Update main inline fields (description, date, credits) - single Save button
    if (isset($_POST['update_main'])) {
        $newDesc    = trim($_POST['description'] ?? '');
        $newDateRaw = trim($_POST['date'] ?? '');
        $newCredits = $_POST['credits'] ?? '';

        // Validate description
        if ($newDesc === '') {
            $errors[] = 'Description is required.';
        }

        // Validate date (optional but must be YYYY-MM-DD if provided)
        $dateToStore = '';
        if ($newDateRaw !== '') {
            // The '!' makes parsing strict and zeroes unspecified fields.
            $dt = DateTime::createFromFormat('!Y-m-d', $newDateRaw);

            // Round-trip validation: only accept if formatting gives back exactly the input
            if ($dt && $dt->format('Y-m-d') === $newDateRaw) {
                $dateToStore = $newDateRaw; // store normalized YYYY-MM-DD
            } else {
                $errors[] = 'Date must be in YYYY-MM-DD format.';
            }
        }


        // Validate credits (integer >= 0)
        if ($newCredits === '' || !is_numeric($newCredits) || (int)$newCredits < 0) {
            $errors[] = 'Credits must be a non-negative integer.';
        } else {
            $newCredits = (int)$newCredits;
        }

        if (empty($errors)) {
            // Persist changes into $data
            $data['modules'][$modIndex]['requirements'][$reqIndex]['description'] = $newDesc;
            $data['modules'][$modIndex]['requirements'][$reqIndex]['credits']     = $newCredits;
            $data['modules'][$modIndex]['requirements'][$reqIndex]['date']        = $dateToStore; // empty string if not set

            // Save and redirect (PRG)
            saveData($data, $jsonFile);
            header('Location: requirement.php?id=' . urlencode($uuid));
            exit;
        }
    }

    // B) Update notes
    if (isset($_POST['notes']) && !isset($_POST['update_main'])) {
        $details['notes'] = trim($_POST['notes']);
        saveData($data, $jsonFile);
        header('Location: requirement.php?id=' . urlencode($uuid));
        exit;
    }

    // C) Add sub requirement
    if (isset($_POST['add_sub'])) {
        $desc = trim($_POST['sub_desc'] ?? '');
        if ($desc !== '') {
            $details['subs'][] = ['desc' => $desc, 'done' => false];
            saveData($data, $jsonFile);
        }
        header('Location: requirement.php?id=' . urlencode($uuid));
        exit;
    }

    // D) Toggle sub done
    if (isset($_POST['toggle_sub'])) {
        $si = (int) ($_POST['sub_index'] ?? -1);
        if (isset($details['subs'][$si])) {
            $details['subs'][$si]['done'] = !$details['subs'][$si]['done'];
            saveData($data, $jsonFile);
        }
        header('Location: requirement.php?id=' . urlencode($uuid));
        exit;
    }

    // E) Delete sub
    if (isset($_POST['delete_sub'])) {
        $si = (int) ($_POST['sub_index'] ?? -1);
        if (isset($details['subs'][$si])) {
            array_splice($details['subs'], $si, 1);
            saveData($data, $jsonFile);
        }
        header('Location: requirement.php?id=' . urlencode($uuid));
        exit;
    }
}

// Refresh current values for display (after possible validation errors)
$req        = $data['modules'][$modIndex]['requirements'][$reqIndex];
$moduleName = $data['modules'][$modIndex]['name'];
$subList    = $details['subs'];
$notes      = $details['notes'];

// Pre-fill inline form values (if validation failed, keep user input)
$prefillDescription = isset($_POST['update_main']) ? ($_POST['description'] ?? $req['description']) : $req['description'];
$prefillDate        = isset($_POST['update_main']) ? ($_POST['date'] ?? ($req['date'] ?? '')) : ($req['date'] ?? '');
$prefillCredits     = isset($_POST['update_main']) ? ($_POST['credits'] ?? $req['credits']) : $req['credits'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Requirement - <?php echo htmlspecialchars($req['description']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Inline "plain text" inputs */
        .inline-input {
            border: none;
            background: transparent;
            padding: 0;
            margin: 0;
            font: inherit;
            color: inherit;
            outline: none;
            box-shadow: none;
            appearance: none;
            -webkit-appearance: none;
        }
        .inline-input:focus {
            outline: none;
            background: rgba(0,0,0,0.02);
            border-bottom: 1px dashed #999;
        }
        .inline-h2 {
            display: inline-block;
            width: 100%;
            font-size: inherit; /* inherits from h2 */
            font-weight: inherit;
        }
        .inline-date { width: 14ch; }
        .inline-number { width: 8ch; }
        .meta-row { margin: 4px 0; }
        .errors {
            background:#ffecec; border:1px solid #f5a2a2; color:#a40000;
            padding:8px 12px; margin:10px 0; border-radius:4px;
        }
        .errors ul { margin:6px 0 0 18px; }
        .save-row { margin: 12px 0 18px; }
    </style>
    <?php include __DIR__ . '/app/elements/head.php'; ?>
</head>

<body>
    <div class="container">
        <header>
            <?php include __DIR__ . '/app/elements/header.php'; ?>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Inline edit form (description, date, credits) with one Save button -->
        <form method="post" style="margin-bottom:12px;">
            <input type="hidden" name="update_main" value="1">

            <h2>
                <input
                    type="text"
                    name="description"
                    class="inline-input inline-h2"
                    value="<?php echo htmlspecialchars($prefillDescription); ?>"
                    placeholder="Untitled requirement"
                    required
                >
            </h2>

            <p class="meta-row"><strong>Module:</strong> <?php echo htmlspecialchars($moduleName); ?></p>

            <p class="meta-row">
                <strong>Date:</strong>
                <input
                    type="date"
                    name="date"
                    class="inline-input inline-date"
                    value="<?php echo htmlspecialchars($prefillDate); ?>"
                >
                <?php if ($prefillDate === ''): ?>
                    <span style="color:#888;">—</span>
                <?php endif; ?>
            </p>

            <p class="meta-row">
                <strong>Credits:</strong>
                <input
                    type="number"
                    name="credits"
                    class="inline-input inline-number"
                    min="0"
                    step="1"
                    value="<?php echo htmlspecialchars((string) (int) $prefillCredits); ?>"
                >
            </p>

            <div class="save-row">
                <button type="submit">Save Changes</button>
            </div>
        </form>

        <hr>

        <!-- Notes (unchanged) -->
        <h3>Notes</h3>
        <form method="post">
            <textarea name="notes" rows="4" style="width:100%;max-width:100%;"><?php
                echo htmlspecialchars($notes);
            ?></textarea><br>
            <button type="submit">Save Notes</button>
        </form>

        <hr>

        <!-- Sub-requirements (unchanged) -->
        <h3>Sub-requirements</h3>
        <ul class="sub-list">
            <?php foreach ($subList as $i => $sub): ?>
                <li>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="sub_index" value="<?php echo $i; ?>">
                        <input type="hidden" name="toggle_sub" value="1">
                        <input type="checkbox" <?php if (!empty($sub['done'])) echo 'checked'; ?>
                               onchange="this.form.submit();">
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
