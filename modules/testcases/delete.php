<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$testCaseId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get test case and check access
$testCase = $db->fetch("SELECT * FROM test_cases WHERE id = ?", [$testCaseId]);
if (!$testCase || !hasProjectAccess($testCase['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

try {
    // Delete test case
    $db->delete('test_cases', 'id = ?', [$testCaseId]);
    
    logActivity($user['id'], $testCase['project_id'], 'test_case', $testCaseId, 'delete', 'Test case deleted: ' . $testCase['title']);
    
    header('Location: index.php?success=testcase_deleted&project_id=' . $testCase['project_id']);
} catch (Exception $e) {
    error_log("Failed to delete test case: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
