<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$projectId = $_GET['project_id'] ?? '';
$db = getDB();

// Get user projects
$projects = getUserProjects($user['id']);

// Get test cases for selected project
$testCases = [];
if ($projectId && hasProjectAccess($projectId)) {
    $testCases = $db->fetchAll("
        SELECT id, title, type, priority 
        FROM test_cases 
        WHERE project_id = ? AND status = 'active'
        ORDER BY title
    ", [$projectId]);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProjectId = $_POST['project_id'] ?? '';
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $environment = sanitizeInput($_POST['environment'] ?? '');
    $status = $_POST['status'] ?? 'planned';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $selectedTestCases = $_POST['test_cases'] ?? [];
    
    if (empty($selectedProjectId) || empty($name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!hasProjectAccess($selectedProjectId)) {
        $error = 'You do not have access to the selected project.';
    } elseif (empty($selectedTestCases)) {
        $error = 'Please select at least one test case.';
    } else {
        try {
            $db->getConnection()->beginTransaction();
            
            $testRunData = [
                'project_id' => $selectedProjectId,
                'name' => $name,
                'description' => $description,
                'environment' => $environment,
                'status' => $status,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'created_by' => $user['id']
            ];
            
            $testRunId = $db->insert('test_runs', $testRunData);
            
            // Add test executions
            foreach ($selectedTestCases as $testCaseId) {
                $executionData = [
                    'test_run_id' => $testRunId,
                    'test_case_id' => $testCaseId,
                    'status' => 'not_run'
                ];
                $db->insert('test_executions', $executionData);
            }
            
            $db->getConnection()->commit();
            
            logActivity($user['id'], $selectedProjectId, 'test_run', $testRunId, 'create', 'Test run created: ' . $name);
            
            header('Location: index.php?success=testrun_created&project_id=' . $selectedProjectId);
            exit();
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            error_log("Failed to create test run: " . $e->getMessage());
            $error = 'Failed to create test run. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test Run - Test Management Framework</title>
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
                        <h3><i class="fas fa-plus"></i> Create New Test Run</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="project_id" class="form-label">Project *</label>
                                        <select class="form-select" id="project_id" name="project_id" required onchange="loadTestCases(this.value)">
                                            <option value="">Select Project</option>
                                            <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>" 
                                                    <?php echo ($projectId == $project['id'] || ($_POST['project_id'] ?? '') == $project['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="environment" class="form-label">Environment</label>
                                        <input type="text" class="form-control" id="environment" name="environment" 
                                               value="<?php echo htmlspecialchars($_POST['environment'] ?? ''); ?>"
                                               placeholder="e.g., Production, Staging, Development">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Test Run Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="planned" <?php echo ($_POST['status'] ?? 'planned') === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                            <option value="in_progress" <?php echo ($_POST['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo $_POST['start_date'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $_POST['end_date'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Test Cases Selection -->
                            <div class="mb-3">
                                <label class="form-label">Select Test Cases *</label>
                                <?php if (empty($testCases)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        Please select a project first to see available test cases.
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAllTestCases()">
                                                <label class="form-check-label fw-bold" for="select_all">
                                                    Select All
                                                </label>
                                            </div>
                                        </div>
                                        <hr>
                                        <?php foreach ($testCases as $testCase): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input test-case-checkbox" type="checkbox" 
                                                   id="testcase_<?php echo $testCase['id']; ?>" 
                                                   name="test_cases[]" value="<?php echo $testCase['id']; ?>"
                                                   <?php echo in_array($testCase['id'], $_POST['test_cases'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="testcase_<?php echo $testCase['id']; ?>">
                                                <strong><?php echo htmlspecialchars($testCase['title']); ?></strong>
                                                <span class="ms-2"><?php echo formatStatus($testCase['type']); ?></span>
                                                <span class="ms-1"><?php echo formatPriority($testCase['priority']); ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Test Run
                                </button>
                                <a href="index.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Test Runs
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTestCases(projectId) {
            if (!projectId) return;
            
            // In a real implementation, this would be an AJAX call
            const url = new URL(window.location);
            url.searchParams.set('project_id', projectId);
            window.location.href = url.toString();
        }
        
        function toggleAllTestCases() {
            const selectAll = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.test-case-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // Update select all checkbox when individual checkboxes change
        document.querySelectorAll('.test-case-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.test-case-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.test-case-checkbox:checked');
                const selectAll = document.getElementById('select_all');
                
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });
    </script>
</body>
</html>
