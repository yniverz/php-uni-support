<?php
// public/app/logic.php
/**
 * This file processes the main "view/edit" logic for index.php.
 * Handling POST actions for editing modules, toggling requirements, etc.
 */

// Determine if we're in edit mode
$isEditMode = (isset($_GET['mode']) && $_GET['mode'] === 'edit');

// Check for "logout" action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['logged_in']);
    header("Location: index.php");
    exit;
}

// If in edit mode, handle updates
if ($isEditMode) {

    // Update total needed credits
    if (isset($_POST['update_credits'])) {
        $data['totalNeededCredits'] = (int) ($_POST['totalNeededCredits'] ?? 120);
        saveData($data, $jsonFile);
        header("Location: index.php?mode=edit");
        exit;
    }

    // Update semester targets
    else if (isset($_POST['update_semester_targets'])) {
        $targetsStr = $_POST['semesterTargets'] ?? '';
        $targetsArr = array_map('trim', explode(',', $targetsStr));
        $filtered = [];
        foreach ($targetsArr as $val) {
            $valInt = (int) $val;
            if ($valInt > 0) {
                $filtered[] = $valInt;
            }
        }
        
        // sort descending
        rsort($filtered);
        // remove duplicates
        $filtered = array_unique($filtered);

        $data['semesterTargets'] = $filtered;
        saveData($data, $jsonFile);
        header("Location: index.php?mode=edit");
        exit;
    }

    else if (isset($_POST['update_starting_term'])) {
        $startingTerm = (int) ($_POST['startingTerm'] ?? 1);
        if ($startingTerm > 0) {
            $data['startingTerm'] = $startingTerm;
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Add a new module
    else if (isset($_POST['add_module'])) {
        $moduleName = trim($_POST['moduleName'] ?? '');
        $idealTerm = (int) ($_POST['idealTerm'] ?? 1);
        $assignedTerm = (int) ($_POST['assignedTerm'] ?? 1);

        if ($moduleName !== '') {
            // Check uniqueness
            $unique = true;
            foreach ($data['modules'] as $m) {
                if (strcasecmp($m['name'], $moduleName) === 0) {
                    $unique = false;
                    break;
                }
            }
            if ($unique) {
                $newModule = [
                    'name' => $moduleName,
                    'idealTerm' => $idealTerm,
                    'term' => $assignedTerm,
                    'requirements' => [],
                    'allDone' => false
                ];
                $data['modules'][] = $newModule;
                sortModules($data['modules']);
                saveData($data, $jsonFile);
            }
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Reassign module to different term
    else if (isset($_POST['reassign_module'])) {
        $moduleIndex = (int) ($_POST['moduleIndexTerm'] ?? -1);
        $newTerm = (int) ($_POST['newTerm'] ?? 1);
        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            $data['modules'][$moduleIndex]['term'] = $newTerm;
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    else if (isset($_POST['new_module_name'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $newName = trim($_POST['new_module_name'] ?? '');
        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) && $newName !== '') {
            // Check uniqueness
            $unique = true;
            foreach ($data['modules'] as $m) {
                if (strcasecmp($m['name'], $newName) === 0) {
                    $unique = false;
                    echo "Module name already exists.";
                    break;
                }
            }
            if ($unique) {
                $data['modules'][$moduleIndex]['name'] = $newName;
                sortModules($data['modules']);
                saveData($data, $jsonFile);
            }
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Delete module
    else if (isset($_POST['delete_module'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            array_splice($data['modules'], $moduleIndex, 1);
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Add a new sub-requirement
    else if (isset($_POST['add_requirement'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $description = trim($_POST['requirement_desc'] ?? '');
        $credits = (float) ($_POST['requirement_credits'] ?? 0);
        $date = trim($_POST['requirement_date'] ?? '');

        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) && $description !== '') {
            $newRequirement = [
                'description' => $description,
                'credits' => $credits,
                'done' => false
            ];
            if ($date !== '') {
                $newRequirement['date'] = $date;
            }
            $data['modules'][$moduleIndex]['requirements'][] = $newRequirement;
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Update requirement (description, credits, date, done/not-done)
    else if (isset($_POST['update_requirement'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $reqIndex = (int) ($_POST['reqIndex'] ?? -1);
        if (
            $moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
            $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])
        ) {
            // Grab updated info
            $reqDone = !empty($_POST['req_done']);
            $reqDesc = trim($_POST['requirement_desc'] ?? '');
            $reqCredits = (float) ($_POST['requirement_credits'] ?? 0);
            $reqGrade = (float) ($_POST['requirement_grade'] ?? 0);
            $reqDate = trim($_POST['requirement_date'] ?? '');

            // Apply to the existing requirement
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['done'] = $reqDone;
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['description'] = $reqDesc;
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['credits'] = $reqCredits;
            if ($reqGrade > 0) {
                $data['modules'][$moduleIndex]['requirements'][$reqIndex]['grade'] = $reqGrade;
            } else {
                // If grade field is cleared, remove the grade key
                unset($data['modules'][$moduleIndex]['requirements'][$reqIndex]['grade']);
            }
            if ($reqDate !== '') {
                $data['modules'][$moduleIndex]['requirements'][$reqIndex]['date'] = $reqDate;
            } else {
                // If date field is cleared, remove the date key
                unset($data['modules'][$moduleIndex]['requirements'][$reqIndex]['date']);
            }

            updateModuleCompletionStatus($data['modules'][$moduleIndex]);
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Delete requirement
    else if (isset($_POST['delete_requirement'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $reqIndex = (int) ($_POST['reqIndex'] ?? -1);

        if (
            $moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
            $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])
        ) {
            array_splice($data['modules'][$moduleIndex]['requirements'], $reqIndex, 1);
            updateModuleCompletionStatus($data['modules'][$moduleIndex]);
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // save or add note to module
    elseif (isset($_POST['notes'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $notes = trim($_POST['notes'] ?? '');

        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            if ($notes !== '') {
                $data['modules'][$moduleIndex]['notes'] = $notes;
            } else {
                unset($data['modules'][$moduleIndex]['notes']);
            }
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }

        header("Location: index.php?mode=edit");
        exit;
    }

    else if (isset($_POST['share_usernames'])) {
        $usernames = trim($_POST['share_usernames'] ?? '');
        $usernamesArr = array_map('trim', explode(',', $usernames));
        $filtered = [];
        foreach ($usernamesArr as $val) {
            if ($val !== '') {
                $filtered[] = $val;
            }
        }
        $userData[$_SESSION['username']]['share_usernames'] = $filtered;
        saveData($userData, $userDataFile);

        header("Location: index.php?mode=edit");
        exit;
    }

    else if (isset($_POST['module_id'])) {
        $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
        $moduleName = trim($_POST['module_name'] ?? '');
        $moduleId = trim($_POST['module_id'] ?? '');

        // check if moduleId exists in globalModuleIDs
        if (!in_array($moduleId, $globalModuleIDs)) {

            // get copy of $globalModuleIDs
            $toBeRemoved = array_unique($globalModuleIDs);

            // iterate through all users and get a list of all used ids
            $allUsedIDs = [];
            foreach ($userData as $username => $user) {
                $userJsonFile = __DIR__ . '/users/' . $user['userid'] . '.json';
                if (file_exists($userJsonFile)) {
                    $temp_data = json_decode(file_get_contents($userJsonFile), true);
                    foreach ($temp_data['modules'] as $module) {
                        if (isset($module['id'])) {
                            $allUsedIDs[] = $module['id'];
                            echo "found id " . $module['id'] . "<br>";
                        }
                    }
                }
            }

            // get list of all entries in $globalModuleIDs that are not in $allUsedIDs
            $toBeRemoved = array_diff($toBeRemoved, $allUsedIDs);
            // remove duplicates
            $toBeRemoved = array_unique($toBeRemoved);

            // remove all ids from $globalModuleIDs that are in toBeRemoved
            foreach ($toBeRemoved as $id) {
                echo "removing $id<br>";
                unset($globalModuleIDs[array_search($id, $globalModuleIDs)]);
            }

            // moduleId does not exist, insert it
            $globalModuleIDs[] = $moduleId;
            
            // save globalModuleIDs
            file_put_contents($globalModuleIDFile, json_encode($globalModuleIDs, JSON_PRETTY_PRINT));
        }

        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            $data['modules'][$moduleIndex]['id'] = $moduleId;
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }

        header("Location: index.php?mode=edit");
        exit;
    }

}

if (isset($_POST['change_password'])) {
    $newPassword = trim($_POST['change_password'] ?? '');

    if ($newPassword !== '') {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $userData[$_SESSION['username']]['password'] = $hashedPassword;
        saveData($userData, $userDataFile);
        header("Location: index.php");
        exit;
    }
}

// toggle_highlight, toggles "highlighted" key in module, or sets to true if not set
elseif (isset($_POST['toggle_highlight'])) {
    $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
    if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
        $highlighted = $data['modules'][$moduleIndex]['highlighted'] ?? false;
        $data['modules'][$moduleIndex]['highlighted'] = !$highlighted;
        sortModules($data['modules']);
        saveData($data, $jsonFile);
    }

    $redirectUrl = 'index.php';
    if ($isEditMode) {
        $redirectUrl .= '?mode=edit';
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Requirement toggle (always allowed, even in view mode)
elseif (isset($_POST['toggle_req'])) {
    $moduleIndex = (int) ($_POST['moduleIndex'] ?? -1);
    $reqIndex = (int) ($_POST['reqIndex'] ?? -1);

    if (
        $moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
        $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])
    ) {

        $isDone = !empty($_POST['req_done']);
        $data['modules'][$moduleIndex]['requirements'][$reqIndex]['done'] = $isDone;

        updateModuleCompletionStatus($data['modules'][$moduleIndex]);
        sortModules($data['modules']);
        saveData($data, $jsonFile);
    }

    $redirectUrl = 'index.php';
    if ($isEditMode) {
        $redirectUrl .= '?mode=edit';
    }
    header("Location: " . $redirectUrl);
    exit;
}



// Planner save handler
elseif (isset($_POST['save_plan_json'])) {
    $changes = json_decode($_POST['save_plan_json'], true) ?? [];
    foreach ($changes as $idx => $newTerm) {
        $idx = (int) $idx;
        $newTerm = (int) $newTerm;
        if (isset($data['modules'][$idx]) && $newTerm > 0) {
            $data['modules'][$idx]['term'] = $newTerm;
        }
    }
    sortModules($data['modules']);
    saveData($data, $jsonFile);
    header('Location: index.php');
    exit;
}

// Finally, define $totalSoFar for the view (always needed)
$totalSoFar = getTotalCreditsSoFar($data['modules']);