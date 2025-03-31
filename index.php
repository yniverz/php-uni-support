<?php
// public/index.php
session_start();

/**
 * The main "University Module Support System" page.
 * We assume user is logged in. If not, we redirect to login.php.
 */

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

// 1) Ensure user is logged in. Otherwise, send to login.
if (!$isLoggedIn) {
    header("Location: login.php");
    exit;
}

// 2) Include all app logic
require __DIR__ . '/app/logic.php';    // Main "edit" / "view" mode logic

// 3) At this point, $data is loaded, the POST actions are processed
//    We also have $isEditMode, $totalSoFar, etc. from logic.php

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>University Module Support System</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>

<div class="container">
    <header>
        <h1>University Module Support System (<?php echo htmlspecialchars($_SESSION['username']); ?>)</h1>
        <div class="top-links">
            <?php if ($_SESSION['userid'] === '0'): ?>
                <a href="admin.php">Admin Panel</a>
            <?php endif; ?>
            <a href="?action=logout">Logout</a>
            <?php if ($isEditMode): ?>
                <a href="index.php">Switch to View Mode</a>
            <?php else: ?>
                <a href="index.php?mode=edit">Switch to Edit Mode</a>
            <?php endif; ?>

            <a href="requirements.php">View All Requirements by Date</a>
        </div>
    </header>

    <?php if ($isEditMode): ?>
        <!-- EDIT MODE: Show forms for changing settings -->
        <h2>Overall Degree Settings</h2>
        <form method="post">
            <label>
                Total Needed Credits:
                <input type="number" name="totalNeededCredits"
                       value="<?php echo htmlspecialchars($data['totalNeededCredits']); ?>"
                       required />
            </label>
            <button type="submit" name="update_credits">Update</button>
        </form>
        <br />

        <form method="post">
            <label>
                Semester Targets (comma-separated, e.g. "6,9"):
                <input type="text" name="semesterTargets"
                       value="<?php echo htmlspecialchars(implode(', ', $data['semesterTargets'])); ?>"
                       required />
            </label>
            <button type="submit" name="update_semester_targets">Update</button>
        </form>
        <hr />
    <?php else: ?>
        <!-- VIEW MODE: Just display the settings -->
        <h2>Overall Degree Settings</h2>
        <p class="info-line">
            <strong>Total Needed Credits:</strong>
            <?php echo htmlspecialchars($data['totalNeededCredits']); ?>
        </p>
        <p class="info-line">
            <strong>Semester Targets:</strong>
            <?php echo htmlspecialchars(implode(', ', $data['semesterTargets'])); ?>
        </p>
        <hr />
    <?php endif; ?>

    <p class="info-line">
        <strong>Total Credits So Far:</strong>
        <?php echo $totalSoFar; ?> / <?php echo $data['totalNeededCredits']; ?>
    </p>
    <br />

    <?php if ($isEditMode): ?>
        <!-- EDIT MODE: Add module form -->
        <h2>Add a New Module</h2>
        <form method="post">
            <label>Module Name:
                <input type="text" name="moduleName" required />
            </label>
            <br />
            <label>Ideal Term:
                <input type="number" name="idealTerm" value="1" required />
            </label>
            <br />
            <label>Assign to Term:
                <input type="number" name="assignedTerm" value="1" required />
            </label>
            <br />
            <button type="submit" name="add_module">Add Module</button>
        </form>
        <hr />
    <?php endif; ?>

    <?php
    // Now display the modules by term
    if (!empty($data['modules'])) {
        sortModules($data['modules']);

        // group by term
        $modulesByTerm = [];
        foreach ($data['modules'] as $index => $mod) {
            $term = (int)$mod['term'];
            if (!isset($modulesByTerm[$term])) {
                $modulesByTerm[$term] = [];
            }
            $modulesByTerm[$term][] = ['index' => $index, 'module' => $mod];
        }

        ksort($modulesByTerm);

        $targets       = $data['semesterTargets'];
        $neededCredits = $data['totalNeededCredits'];

        foreach ($modulesByTerm as $termNumber => $list) {
            $termCredits = getTermCredits($data['modules'], $termNumber);
            echo "<div class='term-block'>";
            echo "<h2>Term $termNumber ($termCredits Credits)</h2>";

            // each module
            foreach ($list as $entry) {
                $mIndex = $entry['index'];
                $mod    = $entry['module'];
                $moduleClass = "module-card";
                if ($mod['allDone']) {
                    $moduleClass .= " completed";
                }

                echo "<div class='$moduleClass'>";
                echo "<strong>" . htmlspecialchars($mod['name']) . "</strong>";
                if ($mod['allDone']) {
                    echo " <em>(Completed)</em>";
                }
                echo "<br />";
                echo "Ideal Term: " . (int)$mod['idealTerm'];

                if ($isEditMode) {
                    echo "<br /><br />";
                    ?>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="moduleIndexTerm" value="<?php echo $mIndex; ?>">
                        <label>Reassign to Term:
                            <input type="number" name="newTerm" value="<?php echo (int)$mod['term']; ?>" style="width:60px;">
                        </label>
                        <button type="submit" name="reassign_module">Reassign</button>
                    </form>

                    <form method="post" class="form-inline">
                        <input type="hidden" name="delete_module" value="1">
                        <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this module?');">
                            Delete Module
                        </button>
                    </form>
                    <?php
                } else {
                    echo "<br />";
                }

                // show requirements
                echo "<ul class='req-list'>";
                foreach ($mod['requirements'] as $reqIndex => $req) {
                    $desc    = htmlspecialchars($req['description']);
                    $credits = $req['credits'];
                    $date    = !empty($req['date']) ? htmlspecialchars($req['date']) : '';
                    $done    = !empty($req['done']);
                    ?>
                    <li>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="toggle_req" value="1">
                            <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                            <input type="hidden" name="reqIndex" value="<?php echo $reqIndex; ?>">
                            <input type="checkbox" name="req_done" value="1"
                                   <?php if($done) echo 'checked'; ?>
                                   onchange="this.form.submit();">
                            <?php echo "$desc — Credits: $credits" . ($date ? " ($date)" : ""); ?>
                        </form>
                        <?php
                        if ($isEditMode) {
                            ?>
                            <form method="post" style="display:inline; margin-left:10px;">
                                <input type="hidden" name="delete_requirement" value="1">
                                <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                <input type="hidden" name="reqIndex" value="<?php echo $reqIndex; ?>">
                                <button type="submit" onclick="return confirm('Delete this requirement?');">
                                    x
                                </button>
                            </form>
                            <?php
                        }
                        ?>
                    </li>
                    <?php
                }
                echo "</ul>";

                // add new requirement in edit mode
                if ($isEditMode) {
                    ?>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                        <label>Description:
                            <input type="text" name="requirement_desc" required>
                        </label>
                        <label>Credits:
                            <input type="number" name="requirement_credits" value="0" required style="width:60px;">
                        </label>
                        <label>Date (optional):
                            <input type="date" name="requirement_date">
                        </label>
                        <button type="submit" name="add_requirement">Add Requirement</button>
                    </form>
                    <?php
                }

                echo "</div>"; // end module card
            }

            $actualCreditsSoFar = getCompletedCreditsUpToTerm($data['modules'], $termNumber);
            $wantCreditsSoFar = getAllCreditsUpToTerm($data['modules'], $termNumber);

            echo "<div class='credit-summary'>";
            echo "<p><strong>Credits after Term $termNumber:</strong></p>";
            echo "<table style='border-collapse: collapse;'>";
            echo "<tr><td style='padding: 4px 10px;'>Actual:</td><td style='padding: 4px 10px;'>$actualCreditsSoFar</td></tr>";
            echo "<tr><td style='padding: 4px 10px;'>Want:</td><td style='padding: 4px 10px;'>$wantCreditsSoFar</td></tr>";

            foreach ($targets as $target) {
                $ideal = (int) floor(($termNumber / $target) * $neededCredits);
                if ($ideal > $neededCredits) {
                    $ideal = $neededCredits;
                }
                echo "<tr><td style='padding: 4px 10px;'>Target $target:</td><td style='padding: 4px 10px;'>$ideal</td></tr>";
            }

            echo "</table>";
            echo "</div>";

            echo "</div>"; // end term-block
        }
    } else {
        echo "<p>No modules yet.</p>";
    }
    ?>
</div> <!-- end container -->

</body>
</html>