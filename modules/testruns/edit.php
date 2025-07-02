<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$testRunId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get test run data and check access
$testRun = $db->fetch("
    SELECT tr.*, p.name as project_name 
    FROM test_runs tr 
    LEFT JOIN projects p ON tr.project_id = p.id 
    WHERE tr.id = ?
", [$testRunId]);

if (!$testRun || !hasProjectAccess($testRun['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Get test executions for this test run
$testExecutions = $db->fetchAll("
    SELECT te.*, tc.title as test_case_title, tc.type, tc.priority,
           u.username as executed_by_name
    FROM test_executions te
    LEFT JOIN test_cases tc ON te.test_case_id = tc.id
    LEFT JOIN users u ON te.executed_by = u.id
    WHERE te.test_run_id = ?
    ORDER BY tc.title
", [$testRunId]);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_basic') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $environment = sanitizeInput($_POST['environment'] ?? '');
        $status = $_POST['status'] ?? 'planned';
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        
        if (empty($name)) {
            $error = 'Test run name is required.';
        } else {
            try {
                $updateData = [
                    'name' => $name,
                    'description' => $description,
                    'environment' => $environment,
                    'status' => $status,
                    'start_date' => $startDate ?: null,
                    'end_date' => $endDate ?: null
                ];
                
                $db->update('test_runs', $updateData, 'id = ?', [$testRunId]);
                
                logActivity($user['id'], $testRun['project_id'], 'test_run', $testRunId, 'update', 'Test run updated: ' . $name);
                
                $success = 'Test run updated successfully!';
                
                // Refresh test run data
                $testRun = $db->fetch("
                    SELECT tr.*, p.name as project_name 
                    FROM test_runs tr 
                    LEFT JOIN projects p ON tr.project_id = p.id 
                    WHERE tr.id = ?
                ", [$testRunId]);
            } catch (Exception $e) {
                error_log("Failed to update test run: " . $e->getMessage());
                $error = 'Failed to update test run. Please try again.';
            }
        }
    } elseif ($action === 'update_execution') {
        $executionId = $_POST['execution_id'] ?? 0;
        $executionStatus = $_POST['execution_status'] ?? 'not_run';
        $actualResult = sanitizeInput($_POST['actual_result'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        try {
            $updateData = [
                'status' => $executionStatus,
                'actual_result' => $actualResult,
                'notes' => $notes,
                'executed_by' => $user['id'],
                'executed_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('test_executions', $updateData, 'id = ?', [$executionId]);
            
            logActivity($user['id'], $testRun['project_id'], 'test_execution', $executionId, 'update', 'Test execution updated');
            
            $success = 'Test execution updated successfully!';
            
            // Refresh test executions
            $testExecutions = $db->fetchAll("
                SELECT te.*, tc.title as test_case_title, tc.type, tc.priority,
                       u.username as executed_by_name
                FROM test_executions te
                LEFT JOIN test_cases tc ON te.test_case_id = tc.id
                LEFT JOIN users u ON te.executed_by = u.id
                WHERE te.test_run_id = ?
                ORDER BY tc.title
            ", [$testRunId]);
        } catch (Exception $e) {
            error_log("Failed to update test execution: " . $e->getMessage());
            $error = 'Failed to update test execution. Please try again.';
        }
    }
}

// Calculate execution stats
$totalExecutions = count($testExecutions);
$executionStats = [
    'passed' => 0,
    'failed' => 0,
    'blocked' => 0,
    'skipped' => 0,
    'not_run' => 0
];

foreach ($testExecutions as $execution) {
    $executionStats[$execution['status']]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Run Details - Test Management Framework</title>
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
                    <h1><i class="fas fa-play"></i> Test Run Details</h1>
                    <div class="btn-group">
                        <a href="ai_insights.php?id=<?php echo $testRunId; ?>" class="btn btn-info">
                            <i class="fas fa-robot"></i> AI Insights
                        </a>
                        <a href="index.php?project_id=<?php echo $testRun['project_id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
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
                    <!-- Test Run Details -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Test Run Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_basic">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($testRun['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($testRun['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="environment" class="form-label">Environment</label>
                                        <input type="text" class="form-control" id="environment" name="environment" 
                                               value="<?php echo htmlspecialchars($testRun['environment'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="planned" <?php echo $testRun['status'] === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                            <option value="in_progress" <?php echo $testRun['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $testRun['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="aborted" <?php echo $testRun['status'] === 'aborted' ? 'selected' : ''; ?>>Aborted</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo $testRun['start_date'] ? date('Y-m-d\TH:i', strtotime($testRun['start_date'])) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $testRun['end_date'] ? date('Y-m-d\TH:i', strtotime($testRun['end_date'])) : ''; ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Execution Statistics -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6><i class="fas fa-chart-bar"></i> Execution Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-success text-white">
                                            <div class="h6 mb-0"><?php echo $executionStats['passed']; ?></div>
                                            <small>Passed</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-danger text-white">
                                            <div class="h6 mb-0"><?php echo $executionStats['failed']; ?></div>
                                            <small>Failed</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-warning text-white">
                                            <div class="h6 mb-0"><?php echo $executionStats['blocked']; ?></div>
                                            <small>Blocked</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="border rounded p-2 bg-secondary text-white">
                                            <div class="h6 mb-0"><?php echo $executionStats['not_run']; ?></div>
                                            <small>Not Run</small>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <strong>Total: <?php echo $totalExecutions; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Executions -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-check-square"></i> Test Executions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($testExecutions)): ?>
                                    <p class="text-muted">No test cases in this test run.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Test Case</th>
                                                    <th>Status</th>
                                                    <th>Executed By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($testExecutions as $execution): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($execution['test_case_title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo formatStatus($execution['type']); ?>
                                                            <?php echo formatPriority($execution['priority']); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo formatStatus($execution['status']); ?></td>
                                                    <td>
                                                        <?php if ($execution['executed_by_name']): ?>
                                                            <?php echo htmlspecialchars($execution['executed_by_name']); ?>
                                                            <br><small class="text-muted"><?php echo formatDate($execution['executed_at']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not executed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="execute_steps.php?execution_id=<?php echo $execution['id']; ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-play-circle"></i> Execute Steps
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#executionModal"
                                                                    onclick="loadExecution(<?php echo $execution['id']; ?>, '<?php echo htmlspecialchars($execution['test_case_title'], ENT_QUOTES); ?>', '<?php echo $execution['status']; ?>', '<?php echo htmlspecialchars($execution['actual_result'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($execution['notes'] ?? '', ENT_QUOTES); ?>')">
                                                                <i class="fas fa-edit"></i> Quick Update
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Execution Modal -->
    <div class="modal fade" id="executionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_execution">
                    <input type="hidden" name="execution_id" id="modal_execution_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Update Test Execution</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Test Case</label>
                            <p id="modal_test_case_title" class="form-control-plaintext"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_execution_status" class="form-label">Status *</label>
                            <select class="form-select" id="modal_execution_status" name="execution_status" required>
                                <option value="not_run">Not Run</option>
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="blocked">Blocked</option>
                                <option value="skipped">Skipped</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_actual_result" class="form-label">Actual Result</label>
                            <textarea class="form-control" id="modal_actual_result" name="actual_result" rows="4"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Execution</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadExecution(id, title, status, actualResult, notes) {
            document.getElementById('modal_execution_id').value = id;
            document.getElementById('modal_test_case_title').textContent = title;
            document.getElementById('modal_execution_status').value = status;
            document.getElementById('modal_actual_result').value = actualResult;
            document.getElementById('modal_notes').value = notes;
        }
    </script>
</body>
</html>
