<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$testExecutionId = $_GET['execution_id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get test execution data and check access
$testExecution = $db->fetch("
    SELECT te.*, tr.name as test_run_name, tr.project_id,
           tc.title as test_case_title, tc.description as test_case_description,
           tc.preconditions, p.name as project_name
    FROM test_executions te 
    LEFT JOIN test_runs tr ON te.test_run_id = tr.id
    LEFT JOIN test_cases tc ON te.test_case_id = tc.id
    LEFT JOIN projects p ON tr.project_id = p.id
    WHERE te.id = ?
", [$testExecutionId]);

if (!$testExecution || !hasProjectAccess($testExecution['project_id'])) {
    header('Location: ../testruns/index.php?error=access_denied');
    exit();
}

// Get test case steps
$testCaseSteps = $db->fetchAll("
    SELECT * FROM test_case_steps 
    WHERE test_case_id = ? 
    ORDER BY step_number
", [$testExecution['test_case_id']]);

// Get or create test step executions
$stepExecutions = [];
foreach ($testCaseSteps as $step) {
    $stepExecution = $db->fetch("
        SELECT tse.*, te.file_name, te.file_path, te.description as evidence_description
        FROM test_step_executions tse
        LEFT JOIN test_evidence te ON tse.id = te.test_step_execution_id
        WHERE tse.test_execution_id = ? AND tse.test_case_step_id = ?
    ", [$testExecutionId, $step['id']]);
    
    if (!$stepExecution) {
        // Create new step execution
        $stepData = [
            'test_execution_id' => $testExecutionId,
            'test_case_step_id' => $step['id'],
            'step_number' => $step['step_number'],
            'status' => 'not_run'
        ];
        $stepExecutionId = $db->insert('test_step_executions', $stepData);
        
        $stepExecution = [
            'id' => $stepExecutionId,
            'test_execution_id' => $testExecutionId,
            'test_case_step_id' => $step['id'],
            'step_number' => $step['step_number'],
            'status' => 'not_run',
            'actual_result' => '',
            'notes' => '',
            'executed_by' => null,
            'executed_at' => null
        ];
    }
    
    $stepExecutions[] = array_merge($step, $stepExecution);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_all_steps') {
        $steps = $_POST['steps'] ?? [];
        
        try {
            $db->getConnection()->beginTransaction();
            
            foreach ($steps as $stepNumber => $stepData) {
                $stepExecutionId = $stepData['step_execution_id'] ?? 0;
                $status = $stepData['status'] ?? 'not_run';
                $actualResult = sanitizeInput($stepData['actual_result'] ?? '');
                $notes = sanitizeInput($stepData['notes'] ?? '');
                
                $updateData = [
                    'status' => $status,
                    'actual_result' => $actualResult,
                    'notes' => $notes,
                    'executed_by' => $user['id'],
                    'executed_at' => date('Y-m-d H:i:s')
                ];
                
                $db->update('test_step_executions', $updateData, 'id = ?', [$stepExecutionId]);
                
                // Handle file upload if present for this step
                $fileKey = "steps_{$stepNumber}_evidence";
                if (isset($_FILES['steps']['tmp_name'][$stepNumber]['evidence']) && 
                    $_FILES['steps']['error'][$stepNumber]['evidence'] === UPLOAD_ERR_OK) {
                    
                    $uploadDir = '../../uploads/evidence/';
                    $originalName = $_FILES['steps']['name'][$stepNumber]['evidence'];
                    $fileName = time() . '_' . $stepNumber . '_' . basename($originalName);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['steps']['tmp_name'][$stepNumber]['evidence'], $filePath)) {
                        $evidenceData = [
                            'test_step_execution_id' => $stepExecutionId,
                            'file_name' => $originalName,
                            'file_path' => $filePath,
                            'file_type' => $_FILES['steps']['type'][$stepNumber]['evidence'],
                            'file_size' => $_FILES['steps']['size'][$stepNumber]['evidence'],
                            'description' => sanitizeInput($stepData['evidence_description'] ?? ''),
                            'uploaded_by' => $user['id']
                        ];
                        $db->insert('test_evidence', $evidenceData);
                    }
                }
            }
            
            $db->getConnection()->commit();
            
            logActivity($user['id'], $testExecution['project_id'], 'test_execution', $testExecutionId, 'update', 'Test steps bulk updated');
            
            $success = 'All test steps updated successfully!';
            
            // Refresh data
            header("Location: execute_steps.php?execution_id=" . $testExecutionId . "&success=bulk_updated");
            exit();
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            error_log("Failed to update test step executions: " . $e->getMessage());
            $error = 'Failed to update test step executions. Please try again.';
        }
    } elseif ($action === 'update_step') {
        $stepExecutionId = $_POST['step_execution_id'] ?? 0;
        $status = $_POST['status'] ?? 'not_run';
        $actualResult = sanitizeInput($_POST['actual_result'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        try {
            $updateData = [
                'status' => $status,
                'actual_result' => $actualResult,
                'notes' => $notes,
                'executed_by' => $user['id'],
                'executed_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('test_step_executions', $updateData, 'id = ?', [$stepExecutionId]);
            
            // Handle file upload if present
            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/evidence/';
                $fileName = time() . '_' . basename($_FILES['evidence']['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['evidence']['tmp_name'], $filePath)) {
                    $evidenceData = [
                        'test_step_execution_id' => $stepExecutionId,
                        'file_name' => $_FILES['evidence']['name'],
                        'file_path' => $filePath,
                        'file_type' => $_FILES['evidence']['type'],
                        'file_size' => $_FILES['evidence']['size'],
                        'description' => sanitizeInput($_POST['evidence_description'] ?? ''),
                        'uploaded_by' => $user['id']
                    ];
                    $db->insert('test_evidence', $evidenceData);
                }
            }
            
            logActivity($user['id'], $testExecution['project_id'], 'test_step_execution', $stepExecutionId, 'update', 'Test step execution updated');
            
            $success = 'Test step updated successfully!';
            
            // Refresh data
            header("Location: execute_steps.php?execution_id=" . $testExecutionId . "&success=step_updated");
            exit();
        } catch (Exception $e) {
            error_log("Failed to update test step execution: " . $e->getMessage());
            $error = 'Failed to update test step execution. Please try again.';
        }
    } elseif ($action === 'update_overall') {
        $overallStatus = $_POST['overall_status'] ?? 'not_run';
        $overallNotes = sanitizeInput($_POST['overall_notes'] ?? '');
        
        try {
            $updateData = [
                'status' => $overallStatus,
                'notes' => $overallNotes,
                'executed_by' => $user['id'],
                'executed_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('test_executions', $updateData, 'id = ?', [$testExecutionId]);
            
            logActivity($user['id'], $testExecution['project_id'], 'test_execution', $testExecutionId, 'update', 'Test execution completed');
            
            $success = 'Test execution updated successfully!';
            
            // Refresh data
            header("Location: execute_steps.php?execution_id=" . $testExecutionId . "&success=execution_updated");
            exit();
        } catch (Exception $e) {
            error_log("Failed to update test execution: " . $e->getMessage());
            $error = 'Failed to update test execution. Please try again.';
        }
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'step_updated') {
        $success = 'Test step updated successfully!';
    } elseif ($_GET['success'] === 'execution_updated') {
        $success = 'Test execution updated successfully!';
    } elseif ($_GET['success'] === 'bulk_updated') {
        $success = 'All test steps updated successfully!';
    }
}

// Calculate overall execution status
$totalSteps = count($stepExecutions);
$passedSteps = 0;
$failedSteps = 0;
$blockedSteps = 0;
$notRunSteps = 0;

foreach ($stepExecutions as $step) {
    switch ($step['status']) {
        case 'passed': $passedSteps++; break;
        case 'failed': $failedSteps++; break;
        case 'blocked': $blockedSteps++; break;
        default: $notRunSteps++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Execute Test Steps - Test Management Framework</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-bug"></i> Test Management Framework
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-play-circle"></i> Execute Test Steps</h1>
                    <a href="edit.php?id=<?php echo $testExecution['test_run_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Test Run
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Test Case Info -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Test Case Information</h5>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($testExecution['test_case_title']); ?></h6>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($testExecution['test_case_description']); ?></p>
                                
                                <?php if ($testExecution['preconditions']): ?>
                                <div class="mb-3">
                                    <strong>Preconditions:</strong>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($testExecution['preconditions'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Test Run:</strong> <?php echo htmlspecialchars($testExecution['test_run_name']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Project:</strong> <?php echo htmlspecialchars($testExecution['project_name']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Execution Summary -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6><i class="fas fa-chart-pie"></i> Execution Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-success text-white">
                                            <div class="h6 mb-0"><?php echo $passedSteps; ?></div>
                                            <small>Passed</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-danger text-white">
                                            <div class="h6 mb-0"><?php echo $failedSteps; ?></div>
                                            <small>Failed</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-warning text-white">
                                            <div class="h6 mb-0"><?php echo $blockedSteps; ?></div>
                                            <small>Blocked</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-secondary text-white">
                                            <div class="h6 mb-0"><?php echo $notRunSteps; ?></div>
                                            <small>Not Run</small>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <strong>Total: <?php echo $totalSteps; ?> steps</strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overall Status -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6><i class="fas fa-flag-checkered"></i> Overall Result</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_overall">
                                    
                                    <div class="mb-3">
                                        <label for="overall_status" class="form-label">Overall Status</label>
                                        <select class="form-select" id="overall_status" name="overall_status">
                                            <option value="not_run" <?php echo $testExecution['status'] === 'not_run' ? 'selected' : ''; ?>>Not Run</option>
                                            <option value="passed" <?php echo $testExecution['status'] === 'passed' ? 'selected' : ''; ?>>Passed</option>
                                            <option value="failed" <?php echo $testExecution['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                            <option value="blocked" <?php echo $testExecution['status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                            <option value="skipped" <?php echo $testExecution['status'] === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="overall_notes" class="form-label">Overall Notes</label>
                                        <textarea class="form-control" id="overall_notes" name="overall_notes" rows="3"><?php echo htmlspecialchars($testExecution['notes'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Overall Result
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Steps Execution -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-list-ol"></i> Test Steps Execution</h5>
                                <button type="submit" form="bulk-execution-form" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save All Steps
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($stepExecutions)): ?>
                                    <p class="text-muted">No test steps found for this test case.</p>
                                <?php else: ?>
                                    <form id="bulk-execution-form" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="update_all_steps">
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 60px;">Step #</th>
                                                        <th style="width: 25%;">Step Description</th>
                                                        <th style="width: 20%;">Expected Result</th>
                                                        <th style="width: 120px;">Status</th>
                                                        <th style="width: 25%;">Actual Result</th>
                                                        <th style="width: 120px;">Evidence</th>
                                                        <th style="width: 10%;">Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stepExecutions as $step): ?>
                                                    <tr class="
                                                        <?php 
                                                        switch($step['status']) {
                                                            case 'passed': echo 'table-success'; break;
                                                            case 'failed': echo 'table-danger'; break;
                                                            case 'blocked': echo 'table-warning'; break;
                                                            case 'skipped': echo 'table-info'; break;
                                                            default: echo ''; break;
                                                        }
                                                        ?>">
                                                        <td class="text-center">
                                                            <span class="badge bg-primary"><?php echo $step['step_number']; ?></span>
                                                            <input type="hidden" name="steps[<?php echo $step['step_number']; ?>][step_execution_id]" value="<?php echo $step['id']; ?>">
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($step['step_description'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($step['expected_result'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm" name="steps[<?php echo $step['step_number']; ?>][status]" onchange="updateRowStatus(this)">
                                                                <option value="not_run" <?php echo $step['status'] === 'not_run' ? 'selected' : ''; ?>>Not Run</option>
                                                                <option value="passed" <?php echo $step['status'] === 'passed' ? 'selected' : ''; ?>>Passed</option>
                                                                <option value="failed" <?php echo $step['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                                <option value="blocked" <?php echo $step['status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                                                <option value="skipped" <?php echo $step['status'] === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <textarea class="form-control form-control-sm" name="steps[<?php echo $step['step_number']; ?>][actual_result]" rows="3" placeholder="Actual result..."><?php echo htmlspecialchars($step['actual_result'] ?? ''); ?></textarea>
                                                        </td>
                                                        <td>
                                                            <input type="file" class="form-control form-control-sm mb-1" name="steps[<?php echo $step['step_number']; ?>][evidence]" accept="image/*,.pdf,.doc,.docx,.txt">
                                                            <input type="text" class="form-control form-control-sm" name="steps[<?php echo $step['step_number']; ?>][evidence_description]" placeholder="Evidence desc...">
                                                            <?php if ($step['file_name']): ?>
                                                            <div class="mt-1">
                                                                <a href="<?php echo htmlspecialchars($step['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                                    <i class="fas fa-file"></i> View
                                                                </a>
                                                            </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <textarea class="form-control form-control-sm" name="steps[<?php echo $step['step_number']; ?>][notes]" rows="3" placeholder="Notes..."><?php echo htmlspecialchars($step['notes'] ?? ''); ?></textarea>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-8">
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-success btn-sm" onclick="setAllStatus('passed')">
                                                        <i class="fas fa-check"></i> Mark All Passed
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="setAllStatus('failed')">
                                                        <i class="fas fa-times"></i> Mark All Failed
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" onclick="setAllStatus('blocked')">
                                                        <i class="fas fa-ban"></i> Mark All Blocked
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setAllStatus('not_run')">
                                                        <i class="fas fa-undo"></i> Reset All
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Save All Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateRowStatus(selectElement) {
            const row = selectElement.closest('tr');
            const status = selectElement.value;
            
            // Remove all status classes
            row.classList.remove('table-success', 'table-danger', 'table-warning', 'table-info');
            
            // Add appropriate status class
            switch(status) {
                case 'passed':
                    row.classList.add('table-success');
                    break;
                case 'failed':
                    row.classList.add('table-danger');
                    break;
                case 'blocked':
                    row.classList.add('table-warning');
                    break;
                case 'skipped':
                    row.classList.add('table-info');
                    break;
                default:
                    // No class for 'not_run'
                    break;
            }
        }
        
        function setAllStatus(status) {
            const statusSelects = document.querySelectorAll('select[name*="[status]"]');
            statusSelects.forEach(select => {
                select.value = status;
                updateRowStatus(select);
            });
        }
        
        // Auto-save functionality (optional - saves draft every 30 seconds)
        let autoSaveInterval;
        
        function startAutoSave() {
            autoSaveInterval = setInterval(() => {
                // Save form data to localStorage as backup
                const formData = new FormData(document.getElementById('bulk-execution-form'));
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (key.includes('[actual_result]') || key.includes('[notes]') || key.includes('[status]')) {
                        data[key] = value;
                    }
                }
                localStorage.setItem('test_execution_draft_' + <?php echo $testExecutionId; ?>, JSON.stringify(data));
            }, 30000); // Save every 30 seconds
        }
        
        function loadDraft() {
            const savedData = localStorage.getItem('test_execution_draft_' + <?php echo $testExecutionId; ?>);
            if (savedData) {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const element = document.querySelector(`[name="${key}"]`);
                    if (element) {
                        element.value = data[key];
                        if (element.tagName === 'SELECT') {
                            updateRowStatus(element);
                        }
                    }
                });
            }
        }
        
        function clearDraft() {
            localStorage.removeItem('test_execution_draft_' + <?php echo $testExecutionId; ?>);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Load any saved draft data
            loadDraft();
            
            // Start auto-save
            startAutoSave();
            
            // Clear draft on form submission
            document.getElementById('bulk-execution-form').addEventListener('submit', function() {
                clearDraft();
                clearInterval(autoSaveInterval);
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 's': // Ctrl+S to save
                            e.preventDefault();
                            document.getElementById('bulk-execution-form').submit();
                            break;
                        case '1': // Ctrl+1 to mark all passed
                            e.preventDefault();
                            setAllStatus('passed');
                            break;
                        case '2': // Ctrl+2 to mark all failed
                            e.preventDefault();
                            setAllStatus('failed');
                            break;
                        case '0': // Ctrl+0 to reset all
                            e.preventDefault();
                            setAllStatus('not_run');
                            break;
                    }
                }
            });
        });
    </script>
</body>
</html>