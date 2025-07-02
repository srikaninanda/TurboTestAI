<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$projectId = $_GET['project_id'] ?? '';
$testCaseId = $_GET['test_case_id'] ?? '';
$testRunId = $_GET['test_run_id'] ?? '';
$db = getDB();

// Get user projects
$projects = getUserProjects($user['id']);

// Get test cases for selected project
$testCases = [];
if ($projectId && hasProjectAccess($projectId)) {
    $testCases = $db->fetchAll("
        SELECT id, title 
        FROM test_cases 
        WHERE project_id = ?
        ORDER BY title
    ", [$projectId]);
}

// Get all users for assignment
$users = $db->fetchAll("SELECT id, username FROM users ORDER BY username");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProjectId = $_POST['project_id'] ?? '';
    $selectedTestCaseId = $_POST['test_case_id'] ?? null;
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $stepsToReproduce = sanitizeInput($_POST['steps_to_reproduce'] ?? '');
    $expectedBehavior = sanitizeInput($_POST['expected_behavior'] ?? '');
    $actualBehavior = sanitizeInput($_POST['actual_behavior'] ?? '');
    $type = $_POST['type'] ?? 'bug';
    $severity = $_POST['severity'] ?? 'medium';
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'open';
    $environment = sanitizeInput($_POST['environment'] ?? '');
    $browser = sanitizeInput($_POST['browser'] ?? '');
    $os = sanitizeInput($_POST['os'] ?? '');
    $assignedTo = $_POST['assigned_to'] ?? null;
    
    if (empty($selectedProjectId) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } elseif (!hasProjectAccess($selectedProjectId)) {
        $error = 'You do not have access to the selected project.';
    } else {
        try {
            $bugData = [
                'project_id' => $selectedProjectId,
                'test_case_id' => $selectedTestCaseId ?: null,
                'title' => $title,
                'description' => $description,
                'steps_to_reproduce' => $stepsToReproduce,
                'expected_behavior' => $expectedBehavior,
                'actual_behavior' => $actualBehavior,
                'type' => $type,
                'severity' => $severity,
                'priority' => $priority,
                'status' => $status,
                'environment' => $environment,
                'browser' => $browser,
                'os' => $os,
                'assigned_to' => $assignedTo ?: null,
                'reported_by' => $user['id']
            ];
            
            $bugId = $db->insert('bugs', $bugData);
            
            logActivity($user['id'], $selectedProjectId, 'bug', $bugId, 'create', 'Bug reported: ' . $title);
            
            header('Location: index.php?success=bug_created&project_id=' . $selectedProjectId);
            exit();
        } catch (Exception $e) {
            error_log("Failed to create bug: " . $e->getMessage());
            $error = 'Failed to report bug. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Bug - Test Management Framework</title>
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
                        <h3><i class="fas fa-plus"></i> Report New Bug</h3>
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
                                        <label for="test_case_id" class="form-label">Related Test Case</label>
                                        <select class="form-select" id="test_case_id" name="test_case_id">
                                            <option value="">Select Test Case (Optional)</option>
                                            <?php foreach ($testCases as $testCase): ?>
                                            <option value="<?php echo $testCase['id']; ?>" 
                                                    <?php echo ($testCaseId == $testCase['id'] || ($_POST['test_case_id'] ?? '') == $testCase['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($testCase['title']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Bug Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="steps_to_reproduce" class="form-label">Steps to Reproduce</label>
                                        <textarea class="form-control" id="steps_to_reproduce" name="steps_to_reproduce" rows="4"><?php echo htmlspecialchars($_POST['steps_to_reproduce'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expected_behavior" class="form-label">Expected Behavior</label>
                                        <textarea class="form-control" id="expected_behavior" name="expected_behavior" rows="2"><?php echo htmlspecialchars($_POST['expected_behavior'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="actual_behavior" class="form-label">Actual Behavior</label>
                                        <textarea class="form-control" id="actual_behavior" name="actual_behavior" rows="2"><?php echo htmlspecialchars($_POST['actual_behavior'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Type *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="bug" <?php echo ($_POST['type'] ?? 'bug') === 'bug' ? 'selected' : ''; ?>>Bug</option>
                                            <option value="enhancement" <?php echo ($_POST['type'] ?? '') === 'enhancement' ? 'selected' : ''; ?>>Enhancement</option>
                                            <option value="task" <?php echo ($_POST['type'] ?? '') === 'task' ? 'selected' : ''; ?>>Task</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="severity" class="form-label">Severity *</label>
                                        <select class="form-select" id="severity" name="severity" required>
                                            <option value="low" <?php echo ($_POST['severity'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo ($_POST['severity'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo ($_POST['severity'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo ($_POST['severity'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
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
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($users as $assignee): ?>
                                            <option value="<?php echo $assignee['id']; ?>" 
                                                    <?php echo ($_POST['assigned_to'] ?? '') == $assignee['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($assignee['username']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="environment" class="form-label">Environment</label>
                                        <input type="text" class="form-control" id="environment" name="environment" 
                                               value="<?php echo htmlspecialchars($_POST['environment'] ?? ''); ?>"
                                               placeholder="e.g., Production, Staging">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="browser" class="form-label">Browser</label>
                                        <input type="text" class="form-control" id="browser" name="browser" 
                                               value="<?php echo htmlspecialchars($_POST['browser'] ?? ''); ?>"
                                               placeholder="e.g., Chrome 91.0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="os" class="form-label">Operating System</label>
                                        <input type="text" class="form-control" id="os" name="os" 
                                               value="<?php echo htmlspecialchars($_POST['os'] ?? ''); ?>"
                                               placeholder="e.g., Windows 10, macOS 11">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Report Bug
                                </button>
                                <a href="ai_categorize.php" class="btn btn-info" id="ai-categorize-btn" style="display: none;">
                                    <i class="fas fa-robot"></i> AI Categorize
                                </a>
                                <a href="index.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Bugs
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
        
        // Show AI categorize button when description is filled
        document.getElementById('description').addEventListener('input', function() {
            const aiBtn = document.getElementById('ai-categorize-btn');
            if (this.value.trim().length > 50) {
                aiBtn.style.display = 'inline-block';
            } else {
                aiBtn.style.display = 'none';
            }
        });
    </script>
</body>
</html>
