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
        <script>
            (function () {
                const STORAGE_KEY = 'scrollPositionData';
                let scrollTimeout;

                var currentUrl = window.location.href.split('?')[0];

                // On page load, restore scroll position if URL matches
                window.addEventListener('load', () => {
                    const saved = localStorage.getItem(STORAGE_KEY);
                    if (saved) {
                        try {
                            const { url, scrollPercentage } = JSON.parse(saved);
                            if (url === currentUrl) {
                                console.log('Restoring scroll position:', scrollPercentage);
                                setTimeout(() => {
                                    // Use setTimeout to ensure the scroll position is set after the page has fully loaded
                                    window.scrollTo({ 
                                        top: Math.round(scrollPercentage * document.body.scrollHeight), 
                                        behavior: 'instant'
                                    });
                                }, 100);
                            }
                        } catch (e) {
                            console.error('Failed to parse scroll position data:', e);
                        }
                    }
                });

                // Save scroll position with debounce (after scrolling ends)
                // save scroll position in percentage of the page
                window.addEventListener('scroll', () => {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        const scrollData = {
                            url: currentUrl,
                            scrollPercentage: window.scrollY / document.body.scrollHeight
                        };
                        console.log('Saving scroll position:', scrollData);
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(scrollData));
                    }, 200); // 200ms after scroll stops
                });

                // Handle "e" key for edit mode
                window.addEventListener('keydown', function (event) {
                    if (event.key === 'e' && event.target.tagName !== 'TEXTAREA' && event.target.tagName !== 'INPUT') {
                        // window.location.href = '?mode=edit';
                        // toggle edit mode
                        const currentUrl = window.location.href;
                        const newUrl = currentUrl.includes('?mode=edit') ? currentUrl.replace('?mode=edit', '') : currentUrl + '?mode=edit';
                        window.history.pushState({ path: newUrl }, '', newUrl);
                        window.location.reload();
                    }
                });
            })();
        </script>
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
                    <a href="stats.php">Stats</a>
                </div>
            </header>

            <?php if ($isEditMode): ?>
                <!-- EDIT MODE: Show forms for changing settings -->
                <h2>Account Settings</h2>
                <!-- comma seperated list of usernames -->
                <form method="post">
                    <label>
                        Sharing Usernames (comma-separated):
                        <input type="text" name="share_usernames"
                            value="<?php echo htmlspecialchars(implode(', ', $userData[$_SESSION['username']]['share_usernames'] ?? [])); ?>" required />
                    </label>
                    <button type="submit" name="update_share_usernames">Update</button>
                </form>

                <h2>Overall Degree Settings</h2>
                <form method="post">
                    <label>
                        Total Needed Credits:
                        <input type="number" name="totalNeededCredits"
                            value="<?php echo htmlspecialchars($data['totalNeededCredits']); ?>" required />
                    </label>
                    <button type="submit" name="update_credits">Update</button>
                </form>
                <br />

                <form method="post">
                    <label>
                        Target Terms (comma-separated, e.g. "6,9"):
                        <input type="text" name="semesterTargets"
                            value="<?php echo htmlspecialchars(implode(', ', $data['semesterTargets'])); ?>" required />
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
                    <strong>Target Terms:</strong>
                    <?php echo htmlspecialchars(implode(', ', $data['semesterTargets'])); ?>
                </p>
                <hr />
            <?php endif; ?>

            <p class="info-line">
                <strong>Total Credits So Far:</strong>
                <?php echo $totalSoFar; ?> / <?php echo $data['totalNeededCredits']; ?>
            </p>
            <p class="info-line">
                <strong>Average Grade:</strong>
                <?php echo htmlspecialchars(getAverageGrade($data['modules'])); ?>
            </p>
            <br />

            <?php if ($isEditMode): ?>
                <!-- EDIT MODE: Add module form -->
                <h2>Add a New Module</h2>
                <form method="post">
                    <table style='border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 4px 10px;'>
                                <label for="add_module_name">Module Name:</label>
                            </td>
                            <td style='padding: 4px 10px;'>
                                <input id="add_module_name" type="text" name="moduleName" required />
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 4px 10px;'>
                                <label for="add_module_ideal_term">Ideal Term:</label>
                            </td>
                            <td style='padding: 4px 10px;'>
                                <input id="add_module_ideal_term" type="number" name="idealTerm" value="1" required />
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 4px 10px;'>
                                <label for="add_module_assigned_term">Assign to Term:</label>
                            </td>
                            <td style='padding: 4px 10px;'>
                                <input id="add_module_assigned_term" type="number" name="assignedTerm" value="1" required />
                            </td>
                        </tr>
                    </table>
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
                    $term = (int) $mod['term'];
                    if (!isset($modulesByTerm[$term])) {
                        $modulesByTerm[$term] = [];
                    }
                    $modulesByTerm[$term][] = ['index' => $index, 'module' => $mod];
                }

                ksort($modulesByTerm);

                $targets = $data['semesterTargets'];
                $neededCredits = $data['totalNeededCredits'];

                foreach ($modulesByTerm as $termNumber => $list) {
                    $termCredits = getTermCredits($data['modules'], $termNumber);
                    echo "<div class='term-block'>";
                    echo "<h2>Term $termNumber ($termCredits Credits)</h2>";

                    // each module
                    foreach ($list as $entry) {
                        $mIndex = $entry['index'];
                        $mod = $entry['module'];
                        $moduleClass = "module-card";
                        $highlighted = isset($mod['highlighted']) ? $mod['highlighted'] : false;
                        if ($mod['allDone']) {
                            $moduleClass .= " completed";
                        }
                        if ($highlighted) {
                            $moduleClass .= " highlighted";
                        }

                        echo "<div class='$moduleClass'>";
                        // echo "<strong>" . htmlspecialchars($mod['name']) . "</strong>";
                        if ($isEditMode) {
                            ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                <strong>
                                    <input type="text" name="new_module_name" value="<?php echo htmlspecialchars($mod['name']); ?>" required />
                                </strong>
                                <button type="submit" name="save_module_name">Save</button>
                            </form>
                            <?php
                        } else {
                            echo "<strong>" . htmlspecialchars($mod['name']) . "</strong>";
                        }
                        if ($mod['allDone']) {
                            echo " <em>(Completed)</em>";
                        }

                        // add checkbox for highlight (checked if highlighted))
                        ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="toggle_highlight" value="1">
                            <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                            <input type="checkbox" name="highlighted" value="1" <?php if ($highlighted)
                                echo 'checked'; ?> onchange="this.form.submit();">
                        </form>
                        <?php

                        if (isset($mod['id']) && $mod['id'] !== '') {

                            $similarUsers = getUsersWithModuleTerms($_SESSION['username'], $mod['id'], $userData);
                            // returns dict of username => list of integers which are terms
                            if (!empty($similarUsers)) {
                                // echo all who have this term in their list
                                $thisTermPrefix = $mod['allDone'] ? "You joined: " : "You will join: ";
                                $thisTerms = "";
                                foreach ($similarUsers as $user => $terms) {
                                    if (in_array($mod['term'], $terms)) {
                                        $thisTerms .= htmlspecialchars($user) . ", ";
                                    }
                                }

                                $thisTerms = rtrim($thisTerms, ", ");
                                if ($thisTerms) {
                                    echo $thisTermPrefix . $thisTerms;
                                }

                                $otherTermsPrefix = "Students in Other Terms: ";
                                $otherTerms = "";
                                foreach ($similarUsers as $user => $terms) {
                                    if (in_array($mod['term'], $terms)) {
                                        continue; // skip this user
                                    }
                                    $otherTerms .= "<i>" . htmlspecialchars($user) . "</i> (Term: " . implode(", ", $terms) . "), ";
                                }

                                $otherTerms = rtrim($otherTerms, ", ");
                                if ($otherTerms) {
                                    echo "<br />" . $otherTermsPrefix . $otherTerms;
                                }

                            }
                        }

                        echo "<br />";
                        echo "Ideal Term: " . (int) $mod['idealTerm'];

                        if ($isEditMode) {
                            echo "<br /><br />";
                            ?>
                            <!-- give optional id to module -->
                            <form method="post" class="form-inline">
                                <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                <input type="hidden" name="module_name" value="<?php echo htmlspecialchars($mod['name']); ?>">
                                <label>Module ID:
                                    <input type="text" name="module_id" value="<?php echo htmlspecialchars($mod['id'] ?? ''); ?>" style="width:120px;" list="suggestions">
                                    <datalist id="suggestions">
                                        <?php
                                        foreach ($globalModuleIDs as $id) {
                                            echo "<option value='" . htmlspecialchars($id) . "'>";
                                        }
                                        ?>
                                    </datalist>
                                </label>
                                <button type="submit" name="save_module_id">Save</button>
                            </form>
                            <br />

                            <!-- Reassign or Delete Module Forms -->
                            <form method="post" class="form-inline" style="margin-bottom:5px;">
                                <input type="hidden" name="moduleIndexTerm" value="<?php echo $mIndex; ?>">
                                <label>Reassign to Term:
                                    <input type="number" name="newTerm" value="<?php echo (int) $mod['term']; ?>" style="width:60px;">
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
                            $desc = htmlspecialchars($req['description']);
                            $credits = $req['credits'];
                            $date = !empty($req['date']) ? htmlspecialchars($req['date']) : '';
                            $grade = !empty($req['grade']) ? htmlspecialchars($req['grade']) : '';
                            $done = !empty($req['done']);

                            if ($isEditMode) {
                                // In edit mode, show as input fields + update button
                                ?>
                                <li>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="update_requirement" value="1">
                                        <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                        <input type="hidden" name="reqIndex" value="<?php echo $reqIndex; ?>">

                                        <!-- Done Checkbox -->
                                        <input type="checkbox" name="req_done" value="1" <?php if ($done)
                                            echo 'checked'; ?> />

                                        <!-- Editable fields -->
                                        <input type="text" name="requirement_desc" value="<?php echo $desc; ?>" required title="Description" />
                                        <input type="number" name="requirement_credits" step="0.5" value="<?php echo $credits; ?>"
                                            style="width:60px;" required title="Credits" />
                                        <?php if ($credits > 0 && $done): ?>
                                            <input type="number" name="requirement_grade" step="0.1" value="<?php echo $grade; ?>"
                                                style="width:60px;" title="Grade (optional)" />
                                        <?php endif; ?>
                                        <input type="date" name="requirement_date" value="<?php echo $date; ?>" />

                                        <!-- Update button -->
                                        <button type="submit">
                                            Update
                                        </button>
                                    </form>

                                    <!-- Delete button (separate form) -->
                                    <form method="post" style="display:inline; margin-left:10px;">
                                        <input type="hidden" name="delete_requirement" value="1">
                                        <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                        <input type="hidden" name="reqIndex" value="<?php echo $reqIndex; ?>">
                                        <button type="submit" onclick="return confirm('Delete this requirement?');">
                                            x
                                        </button>
                                    </form>
                                </li>
                                <?php
                            } else {
                                // View mode only
                                ?>
                                <li>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="toggle_req" value="1">
                                        <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                        <input type="hidden" name="reqIndex" value="<?php echo $reqIndex; ?>">
                                        <input type="checkbox" name="req_done" value="1" <?php if ($done)
                                            echo 'checked'; ?>
                                            onchange="this.form.submit();">
                                        <?php echo ($grade ? "$grade — " : "") . "$desc" . ($credits === 0 ? "" : " — Credits: $credits") . ($date ? " — ($date)" : ""); ?>
                                    </form>
                                </li>
                                <?php
                            }
                        }
                        echo "</ul>";

                        // add new requirement in edit mode
                        if ($isEditMode) {
                            ?>
                            <p style="margin-bottom: 0.5em">Add Requirement:</p>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                <label>Description:
                                    <input type="text" name="requirement_desc" required>
                                </label>
                                <label>Credits:
                                    <input type="number" name="requirement_credits" step="0.5" value="0" required style="width:60px;">
                                </label>
                                <label>Date (optional):
                                    <input type="date" name="requirement_date">
                                </label>
                                <button type="submit" name="add_requirement">Add Requirement</button>
                            </form>

                            <form method="post" class="form-inline">
                                <input type="hidden" name="moduleIndex" value="<?php echo $mIndex; ?>">
                                <label>Notes:
                                    <input type="text" name="notes" value="<?php echo htmlspecialchars($mod['notes'] ?? ''); ?>" style="width:200px;">
                                </label>
                                <button type="submit" name="save_notes">Save</button>
                            </form>
                            <?php
                        } else if (isset($mod['notes'])) {
                            echo "<p><strong>Notes:</strong> " . htmlspecialchars($mod['notes'] ?? '') . "</p>";
                        }
                        echo "</div>"; // end module-card
                    }

                    $haveCreditsSoFar = getCompletedCreditsUpToTerm($data['modules'], $termNumber);
                    $wantCreditsSoFar = getAllCreditsUpToTerm($data['modules'], $termNumber);

                    echo "<div class='credit-summary'>";
                    echo "<p><strong>Credits after Term $termNumber:</strong></p>";
                    echo "<table style='border-collapse: collapse;'>";
                    echo "<tr><td style='padding: 4px 10px;'>Have:</td><td style='padding: 4px 10px;'>$haveCreditsSoFar</td></tr>";
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

        <footer>
            <a id="change_password">Change Password</a>
            <p>See this Project on <a href="https://github.com/yniverz/php-uni-support">GitHub</a></p>
            <p>Created by <a href="https://github.com/yniverz">yniverz</a></p>
            <p><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/VERSION')); ?></p>


            <script>
                // when change_password is clicked, show prompt for new password and then send to server
                document.getElementById('change_password').addEventListener('click', function () {
                    const newPassword = prompt("Enter new password:");
                    if (newPassword) {
                        const form = document.createElement('form');
                        form.method = 'post';
                        form.action = '';
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'change_password';
                        input.value = newPassword;
                        form.appendChild(input);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });

            </script>
        </footer>

    </body>

</html>