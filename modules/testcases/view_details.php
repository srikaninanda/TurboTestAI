<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

header('Content-Type: application/json');

$testCaseId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

try {
    // Get test case details
    $testCase = $db->fetch("
        SELECT tc.*, p.name as project_name, r.title as requirement_title, u.username as created_by_name
        FROM test_cases tc
        LEFT JOIN projects p ON tc.project_id = p.id
        LEFT JOIN requirements r ON tc.requirement_id = r.id
        LEFT JOIN users u ON tc.created_by = u.id
        WHERE tc.id = ?
    ", [$testCaseId]);

    if (!$testCase) {
        echo json_encode(['error' => 'Test case not found']);
        exit();
    }

    // Check project access
    if (!hasProjectAccess($testCase['project_id'])) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }

    // Get test steps
    $testSteps = $db->fetchAll("
        SELECT step_number, step_description, expected_result
        FROM test_case_steps
        WHERE test_case_id = ?
        ORDER BY step_number
    ", [$testCaseId]);

    // If no test steps found in new table, check if there are legacy steps
    if (empty($testSteps) && !empty($testCase['test_steps'])) {
        // Convert legacy format to new format for display
        $legacySteps = explode("\n", trim($testCase['test_steps']));
        $legacyResult = $testCase['expected_result'];
        
        foreach ($legacySteps as $index => $step) {
            $step = trim($step);
            if (!empty($step)) {
                $testSteps[] = [
                    'step_number' => $index + 1,
                    'step_description' => $step,
                    'expected_result' => $index === 0 ? $legacyResult : 'As per test step requirement'
                ];
            }
        }
    }

    // Prepare response
    $response = [
        'id' => $testCase['id'],
        'title' => htmlspecialchars($testCase['title']),
        'description' => htmlspecialchars($testCase['description'] ?? ''),
        'preconditions' => htmlspecialchars($testCase['preconditions'] ?? ''),
        'project_name' => htmlspecialchars($testCase['project_name']),
        'requirement_title' => $testCase['requirement_title'] ? htmlspecialchars($testCase['requirement_title']) : null,
        'type' => ucfirst($testCase['type']),
        'priority' => ucfirst($testCase['priority']),
        'status' => ucfirst($testCase['status']),
        'created_by_name' => htmlspecialchars($testCase['created_by_name']),
        'test_steps' => array_map(function($step) {
            return [
                'step_number' => $step['step_number'],
                'step_description' => htmlspecialchars($step['step_description']),
                'expected_result' => htmlspecialchars($step['expected_result'])
            ];
        }, $testSteps)
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Failed to fetch test case details: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load test case details']);
}
?>