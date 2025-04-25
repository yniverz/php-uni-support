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
        $data['totalNeededCredits'] = (int)($_POST['totalNeededCredits'] ?? 120);
        saveData($data, $jsonFile);
        header("Location: index.php?mode=edit");
        exit;
    }

    // Update semester targets
    if (isset($_POST['update_semester_targets'])) {
        $targetsStr = $_POST['semesterTargets'] ?? '';
        $targetsArr = array_map('trim', explode(',', $targetsStr));
        $filtered   = [];
        foreach ($targetsArr as $val) {
            $valInt = (int)$val;
            if ($valInt > 0) {
                $filtered[] = $valInt;
            }
        }
        $data['semesterTargets'] = $filtered;
        saveData($data, $jsonFile);
        header("Location: index.php?mode=edit");
        exit;
    }

    // Add a new module
    if (isset($_POST['add_module'])) {
        $moduleName   = trim($_POST['moduleName'] ?? '');
        $idealTerm    = (int)($_POST['idealTerm'] ?? 1);
        $assignedTerm = (int)($_POST['assignedTerm'] ?? 1);

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
                    'name'        => $moduleName,
                    'idealTerm'   => $idealTerm,
                    'term'        => $assignedTerm,
                    'requirements'=> [],
                    'allDone'     => false
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
    if (isset($_POST['reassign_module'])) {
        $moduleIndex = (int)($_POST['moduleIndexTerm'] ?? -1);
        $newTerm     = (int)($_POST['newTerm'] ?? 1);
        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            $data['modules'][$moduleIndex]['term'] = $newTerm;
            sortModules($data['modules']);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Delete module
    if (isset($_POST['delete_module'])) {
        $moduleIndex = (int)($_POST['moduleIndex'] ?? -1);
        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex])) {
            array_splice($data['modules'], $moduleIndex, 1);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Add a new sub-requirement
    if (isset($_POST['add_requirement'])) {
        $moduleIndex = (int)($_POST['moduleIndex'] ?? -1);
        $description = trim($_POST['requirement_desc'] ?? '');
        $credits     = (int)($_POST['requirement_credits'] ?? 0);
        $date        = trim($_POST['requirement_date'] ?? '');

        if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) && $description !== '') {
            $newRequirement = [
                'description' => $description,
                'credits'     => $credits,
                'done'        => false
            ];
            if ($date !== '') {
                $newRequirement['date'] = $date;
            }
            $data['modules'][$moduleIndex]['requirements'][] = $newRequirement;
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Update requirement (description, credits, date, done/not-done)
    if (isset($_POST['update_requirement'])) {
        $moduleIndex = (int)($_POST['moduleIndex'] ?? -1);
        $reqIndex    = (int)($_POST['reqIndex'] ?? -1);
        if (
            $moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
            $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])
        ) {
            // Grab updated info
            $reqDone        = !empty($_POST['req_done']);
            $reqDesc        = trim($_POST['requirement_desc'] ?? '');
            $reqCredits     = (int)($_POST['requirement_credits'] ?? 0);
            $reqGrade       = (float)($_POST['requirement_grade'] ?? 0);
            $reqDate        = trim($_POST['requirement_date'] ?? '');

            // Apply to the existing requirement
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['done']        = $reqDone;
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['description'] = $reqDesc;
            $data['modules'][$moduleIndex]['requirements'][$reqIndex]['credits']     = $reqCredits;
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
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }

    // Delete requirement
    if (isset($_POST['delete_requirement'])) {
        $moduleIndex = (int)($_POST['moduleIndex'] ?? -1);
        $reqIndex    = (int)($_POST['reqIndex'] ?? -1);

        if (
            $moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
            $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])
        ) {
            array_splice($data['modules'][$moduleIndex]['requirements'], $reqIndex, 1);
            updateModuleCompletionStatus($data['modules'][$moduleIndex]);
            saveData($data, $jsonFile);
        }
        header("Location: index.php?mode=edit");
        exit;
    }
}

// Requirement toggle (always allowed, even in view mode)
if (isset($_POST['toggle_req'])) {
    $moduleIndex = (int)($_POST['moduleIndex'] ?? -1);
    $reqIndex    = (int)($_POST['reqIndex'] ?? -1);

    if ($moduleIndex >= 0 && isset($data['modules'][$moduleIndex]) &&
        $reqIndex >= 0 && isset($data['modules'][$moduleIndex]['requirements'][$reqIndex])) {

        $isDone = !empty($_POST['req_done']);
        $data['modules'][$moduleIndex]['requirements'][$reqIndex]['done'] = $isDone;
        
        updateModuleCompletionStatus($data['modules'][$moduleIndex]);
        saveData($data, $jsonFile);
    }

    $redirectUrl = 'index.php';
    if ($isEditMode) {
        $redirectUrl .= '?mode=edit';
    }
    header("Location: " . $redirectUrl);
    exit;
}

// Finally, define $totalSoFar for the view (always needed)
$totalSoFar = getTotalCreditsSoFar($data['modules']);