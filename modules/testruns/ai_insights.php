<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/ai.php';

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

// Get test execution results
$testExecutions = $db->fetchAll("
    SELECT te.*, tc.title as test_case_title, tc.type, tc.priority
    FROM test_executions te
    LEFT JOIN test_cases tc ON te.test_case_id = tc.id
    WHERE te.test_run_id = ?
    ORDER BY te.status, tc.title
", [$testRunId]);

$insights = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ai = getAI();
        
        // Prepare test results data for analysis
        $testResults = "Test Run: " . $testRun['name'] . "\n";
        $testResults .= "Environment: " . ($testRun['environment'] ?? 'Not specified') . "\n";
        $testResults .= "Status: " . $testRun['status'] . "\n\n";
        
        $stats = [
            'total' => count($testExecutions),
            'passed' => 0,
            'failed' => 0,
            'blocked' => 0,
            'not_run' => 0,
            'skipped' => 0
        ];
        
        $testResults .= "Test Results Summary:\n";
        
        foreach ($testExecutions as $execution) {
            $stats[$execution['status']]++;
        }
        
        foreach ($stats as $status => $count) {
            if ($status !== 'total') {
                $testResults .= "- " . ucfirst(str_replace('_', ' ', $status)) . ": $count\n";
            }
        }
        
        $testResults .= "\nDetailed Results:\n";
        
        foreach ($testExecutions as $execution) {
            $testResults .= "\n" . $execution['test_case_title'] . ":\n";
            $testResults .= "- Status: " . $execution['status'] . "\n";
            $testResults .= "- Type: " . $execution['type'] . "\n";
            $testResults .= "- Priority: " . $execution['priority'] . "\n";
            
            if ($execution['actual_result']) {
                $testResults .= "- Result: " . $execution['actual_result'] . "\n";
            }
            
            if ($execution['notes']) {
                $testResults .= "- Notes: " . $execution['notes'] . "\n";
            }
        }
        
        $result = $ai->analyzeTestRun($testResults);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $insights = $result['insights'];
            
            // Save AI insights to database
            $db->update('test_runs', ['ai_insights' => $insights], 'id = ?', [$testRunId]);
            
            logActivity($user['id'], $testRun['project_id'], 'test_run', $testRunId, 'ai_analyze', 'AI insights generated');
        }
    } catch (Exception $e) {
        error_log("AI insights generation failed: " . $e->getMessage());
        $error = 'AI insights generation failed. Please try again later.';
    }
}

// If insights already exist, show them
if (!$insights && $testRun['ai_insights']) {
    $insights = $testRun['ai_insights'];
}

// Calculate execution statistics
$executionStats = [
    'total' => count($testExecutions),
    'passed' => 0,
    'failed' => 0,
    'blocked' => 0,
    'not_run' => 0,
    'skipped' => 0
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
    <title>AI Test Run Insights - Test Management Framework</title>
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

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-robot"></i> AI Test Run Insights</h3>
                        <p class="mb-0 text-muted">Analyzing: <strong><?php echo htmlspecialchars($testRun['name']); ?></strong></p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Test Run Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6><i class="fas fa-info-circle"></i> Test Run Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Project:</strong> <?php echo htmlspecialchars($testRun['project_name']); ?></p>
                                        <p><strong>Status:</strong> <?php echo formatStatus($testRun['status']); ?></p>
                                        <p><strong>Environment:</strong> <?php echo htmlspecialchars($testRun['environment'] ?? 'Not specified'); ?></p>
                                        
                                        <?php if ($testRun['description']): ?>
                                        <p><strong>Description:</strong></p>
                                        <p><?php echo nl2br(htmlspecialchars($testRun['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <h6>Execution Statistics:</h6>
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <div class="border rounded p-2 bg-success text-white">
                                                    <div class="h6 mb-0"><?php echo $executionStats['passed']; ?></div>
                                                    <small>Passed</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2 bg-danger text-white">
                                                    <div class="h6 mb-0"><?php echo $executionStats['failed']; ?></div>
                                                    <small>Failed</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2 bg-warning text-white">
                                                    <div class="h6 mb-0"><?php echo $executionStats['blocked']; ?></div>
                                                    <small>Blocked</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2 bg-secondary text-white">
                                                    <div class="h6 mb-0"><?php echo $executionStats['not_run']; ?></div>
                                                    <small>Not Run</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-robot"></i> AI Insights</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($insights): ?>
                                            <div class="insights-result">
                                                <div style="white-space: pre-wrap; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; border-left: 4px solid #0d6efd;">
                                                    <?php echo htmlspecialchars($insights); ?>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="fas fa-info-circle"></i> Insights generated by AI
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No insights available yet. Click the button below to generate AI analysis of test results.</p>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="mt-3">
                                            <button type="submit" class="btn btn-primary" id="analyze-btn">
                                                <i class="fas fa-robot"></i> 
                                                <?php echo $insights ? 'Re-analyze' : 'Generate'; ?> Insights
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test Execution Results -->
                        <?php if (!empty($testExecutions)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-list"></i> Test Execution Results</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Test Case</th>
                                                <th>Status</th>
                                                <th>Type</th>
                                                <th>Priority</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($testExecutions as $execution): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($execution['test_case_title']); ?></td>
                                                <td><?php echo formatStatus($execution['status']); ?></td>
                                                <td><?php echo formatStatus($execution['type']); ?></td>
                                                <td><?php echo formatPriority($execution['priority']); ?></td>
                                                <td>
                                                    <?php if ($execution['notes']): ?>
                                                        <small><?php echo htmlspecialchars(substr($execution['notes'], 0, 50)); ?>
                                                        <?php if (strlen($execution['notes']) > 50): ?>...<?php endif; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions -->
                        <div class="d-flex gap-2 mt-3">
                            <a href="edit.php?id=<?php echo $testRunId; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Test Run
                            </a>
                            <a href="../bugs/create.php?test_run_id=<?php echo $testRunId; ?>" class="btn btn-outline-danger">
                                <i class="fas fa-bug"></i> Report Bug
                            </a>
                            <a href="index.php?project_id=<?php echo $testRun['project_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Test Runs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading state to analyze button
        document.getElementById('analyze-btn')?.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Insights...';
        });
    </script>
</body>
</html>
