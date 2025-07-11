<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$projectId = $_GET['project_id'] ?? '';
$requirementId = $_GET['requirement_id'] ?? '';
$db = getDB();

// Get user projects
$projects = getUserProjects($user['id']);

// Get requirements for selected project
$requirements = [];
if ($projectId && hasProjectAccess($projectId)) {
    $requirements = $db->fetchAll("
        SELECT id, title 
        FROM requirements 
        WHERE project_id = ? 
        ORDER BY title
    ", [$projectId]);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProjectId = $_POST['project_id'] ?? '';
    $selectedRequirementId = $_POST['requirement_id'] ?? null;
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $preconditions = sanitizeInput($_POST['preconditions'] ?? '');
    $testStepsData = $_POST['test_steps'] ?? [];
    $type = $_POST['type'] ?? 'functional';
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'active';
    
    // Validate test steps
    $validTestSteps = [];
    if (!empty($testStepsData) && is_array($testStepsData)) {
        foreach ($testStepsData as $stepNum => $stepData) {
            $stepDescription = trim($stepData['description'] ?? '');
            $stepExpectedResult = trim($stepData['expected_result'] ?? '');
            
            if (!empty($stepDescription) && !empty($stepExpectedResult)) {
                $validTestSteps[] = [
                    'step_number' => count($validTestSteps) + 1,
                    'description' => sanitizeInput($stepDescription),
                    'expected_result' => sanitizeInput($stepExpectedResult)
                ];
            }
        }
    }
    
    if (empty($selectedProjectId) || empty($title) || empty($validTestSteps)) {
        $error = 'Please fill in all required fields including at least one test step.';
    } elseif (!hasProjectAccess($selectedProjectId)) {
        $error = 'You do not have access to the selected project.';
    } else {
        try {
            // Start transaction
            $db->getConnection()->beginTransaction();
            
            $testCaseData = [
                'project_id' => $selectedProjectId,
                'requirement_id' => $selectedRequirementId ?: null,
                'title' => $title,
                'description' => $description,
                'preconditions' => $preconditions,
                'test_steps' => '', // Keep for backward compatibility
                'expected_result' => '', // Keep for backward compatibility
                'type' => $type,
                'priority' => $priority,
                'status' => $status,
                'created_by' => $user['id']
            ];
            
            $testCaseId = $db->insert('test_cases', $testCaseData);
            
            // Insert test steps
            foreach ($validTestSteps as $step) {
                $stepData = [
                    'test_case_id' => $testCaseId,
                    'step_number' => $step['step_number'],
                    'step_description' => $step['description'],
                    'expected_result' => $step['expected_result']
                ];
                $db->insert('test_case_steps', $stepData);
            }
            
            // Commit transaction
            $db->getConnection()->commit();
            
            logActivity($user['id'], $selectedProjectId, 'test_case', $testCaseId, 'create', 'Test case created: ' . $title);
            
            header('Location: index.php?success=testcase_created&project_id=' . $selectedProjectId);
            exit();
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            error_log("Failed to create test case: " . $e->getMessage());
            $error = 'Failed to create test case. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test Case - Test Management Framework</title>
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
                        <h3><i class="fas fa-plus"></i> Create New Test Case</h3>
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
                                        <select class="form-select" id="project_id" name="project_id" required onchange="loadRequirements(this.value)">
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
                                        <label for="requirement_id" class="form-label">Requirement</label>
                                        <select class="form-select" id="requirement_id" name="requirement_id">
                                            <option value="">Select Requirement (Optional)</option>
                                            <?php foreach ($requirements as $requirement): ?>
                                            <option value="<?php echo $requirement['id']; ?>" 
                                                    <?php echo ($requirementId == $requirement['id'] || ($_POST['requirement_id'] ?? '') == $requirement['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($requirement['title']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Test Case Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="preconditions" class="form-label">Preconditions</label>
                                <textarea class="form-control" id="preconditions" name="preconditions" rows="2"><?php echo htmlspecialchars($_POST['preconditions'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Test Steps *</label>
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Test Steps</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTestStep()">
                                            <i class="fas fa-plus"></i> Add Step
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="test-steps-container">
                                            <div class="table-responsive">
                                                <table class="table table-bordered" id="test-steps-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 80px;">Step #</th>
                                                            <th style="width: 40%;">Test Step Description</th>
                                                            <th style="width: 40%;">Expected Result</th>
                                                            <th style="width: 80px;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="test-steps-tbody">
                                                        <tr class="test-step-row">
                                                            <td class="step-number">1</td>
                                                            <td>
                                                                <textarea class="form-control" name="test_steps[1][description]" rows="2" required placeholder="Enter test step description..."></textarea>
                                                            </td>
                                                            <td>
                                                                <textarea class="form-control" name="test_steps[1][expected_result]" rows="2" required placeholder="Enter expected result..."></textarea>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTestStep(this)" disabled>
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <small class="text-muted">Add multiple test steps with their corresponding expected results. At least one step is required.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Type *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="functional" <?php echo ($_POST['type'] ?? 'functional') === 'functional' ? 'selected' : ''; ?>>Functional</option>
                                            <option value="regression" <?php echo ($_POST['type'] ?? '') === 'regression' ? 'selected' : ''; ?>>Regression</option>
                                            <option value="integration" <?php echo ($_POST['type'] ?? '') === 'integration' ? 'selected' : ''; ?>>Integration</option>
                                            <option value="unit" <?php echo ($_POST['type'] ?? '') === 'unit' ? 'selected' : ''; ?>>Unit</option>
                                            <option value="performance" <?php echo ($_POST['type'] ?? '') === 'performance' ? 'selected' : ''; ?>>Performance</option>
                                            <option value="security" <?php echo ($_POST['type'] ?? '') === 'security' ? 'selected' : ''; ?>>Security</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Priority *</label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo ($_POST['priority'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Test Case
                                </button>
                                <a href="index.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Test Cases
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
        let stepCounter = 1;
        
        function loadRequirements(projectId) {
            const requirementSelect = document.getElementById('requirement_id');
            
            // Clear current options
            requirementSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!projectId) {
                requirementSelect.innerHTML = '<option value="">Select Requirement (Optional)</option>';
                return;
            }
            
            // In a real implementation, this would be an AJAX call
            // For now, we'll reload the page with the project_id parameter
            const url = new URL(window.location);
            url.searchParams.set('project_id', projectId);
            window.location.href = url.toString();
        }
        
        function addTestStep() {
            stepCounter++;
            const tbody = document.getElementById('test-steps-tbody');
            const newRow = document.createElement('tr');
            newRow.className = 'test-step-row';
            
            newRow.innerHTML = `
                <td class="step-number">${stepCounter}</td>
                <td>
                    <textarea class="form-control" name="test_steps[${stepCounter}][description]" rows="2" required placeholder="Enter test step description..."></textarea>
                </td>
                <td>
                    <textarea class="form-control" name="test_steps[${stepCounter}][expected_result]" rows="2" required placeholder="Enter expected result..."></textarea>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTestStep(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            updateStepNumbers();
            updateRemoveButtons();
        }
        
        function removeTestStep(button) {
            const row = button.closest('tr');
            row.remove();
            updateStepNumbers();
            updateRemoveButtons();
        }
        
        function updateStepNumbers() {
            const rows = document.querySelectorAll('.test-step-row');
            rows.forEach((row, index) => {
                const stepNumber = index + 1;
                row.querySelector('.step-number').textContent = stepNumber;
                
                // Update textarea names to maintain sequential numbering
                const descTextarea = row.querySelector('textarea[name*="[description]"]');
                const resultTextarea = row.querySelector('textarea[name*="[expected_result]"]');
                
                descTextarea.name = `test_steps[${stepNumber}][description]`;
                resultTextarea.name = `test_steps[${stepNumber}][expected_result]`;
            });
        }
        
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.test-step-row button[onclick*="removeTestStep"]');
            removeButtons.forEach(button => {
                button.disabled = removeButtons.length === 1;
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>
