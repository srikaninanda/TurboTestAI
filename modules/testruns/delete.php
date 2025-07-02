<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$testRunId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get test run and check access
$testRun = $db->fetch("SELECT * FROM test_runs WHERE id = ?", [$testRunId]);
if (!$testRun || !hasProjectAccess($testRun['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

try {
    // Delete test run (cascading deletes will handle test executions)
    $db->delete('test_runs', 'id = ?', [$testRunId]);
    
    logActivity($user['id'], $testRun['project_id'], 'test_run', $testRunId, 'delete', 'Test run deleted: ' . $testRun['name']);
    
    header('Location: index.php?success=testrun_deleted&project_id=' . $testRun['project_id']);
} catch (Exception $e) {
    error_log("Failed to delete test run: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
