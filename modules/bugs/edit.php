<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$bugId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get bug data and check access
$bug = $db->fetch("
    SELECT b.*, p.name as project_name 
    FROM bugs b 
    LEFT JOIN projects p ON b.project_id = p.id 
    WHERE b.id = ?
", [$bugId]);

if (!$bug || !hasProjectAccess($bug['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Get user projects
$projects = getUserProjects($user['id']);

// Get test cases for the project
$testCases = $db->fetchAll("
    SELECT id, title 
    FROM test_cases 
    WHERE project_id = ?
    ORDER BY title
", [$bug['project_id']]);

// Get all users for assignment
$users = $db->fetchAll("SELECT id, username FROM users ORDER BY username");

// Get bug comments
$comments = $db->fetchAll("
    SELECT bc.*, u.username 
    FROM bug_comments bc 
    LEFT JOIN users u ON bc.user_id = u.id 
    WHERE bc.bug_id = ? 
    ORDER BY bc.created_at ASC
", [$bugId]);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_bug') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $stepsToReproduce = sanitizeInput($_POST['steps_to_reproduce'] ?? '');
        $expectedBehavior = sanitizeInput($_POST['expected_behavior'] ?? '');
        $actualBehavior = sanitizeInput($_POST['actual_behavior'] ?? '');
        $testCaseId = $_POST['test_case_id'] ?? null;
        $type = $_POST['type'] ?? 'bug';
        $severity = $_POST['severity'] ?? 'medium';
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'open';
        $environment = sanitizeInput($_POST['environment'] ?? '');
        $browser = sanitizeInput($_POST['browser'] ?? '');
        $os = sanitizeInput($_POST['os'] ?? '');
        $assignedTo = $_POST['assigned_to'] ?? null;
        
        if (empty($title) || empty($description)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $updateData = [
                    'title' => $title,
                    'description' => $description,
                    'steps_to_reproduce' => $stepsToReproduce,
                    'expected_behavior' => $expectedBehavior,
                    'actual_behavior' => $actualBehavior,
                    'test_case_id' => $testCaseId ?: null,
                    'type' => $type,
                    'severity' => $severity,
                    'priority' => $priority,
                    'status' => $status,
                    'environment' => $environment,
                    'browser' => $browser,
                    'os' => $os,
                    'assigned_to' => $assignedTo ?: null
                ];
                
                $db->update('bugs', $updateData, 'id = ?', [$bugId]);
                
                logActivity($user['id'], $bug['project_id'], 'bug', $bugId, 'update', 'Bug updated: ' . $title);
                
                $success = 'Bug updated successfully!';
                
                // Refresh bug data
                $bug = $db->fetch("
                    SELECT b.*, p.name as project_name 
                    FROM bugs b 
                    LEFT JOIN projects p ON b.project_id = p.id 
                    WHERE b.id = ?
                ", [$bugId]);
            } catch (Exception $e) {
                error_log("Failed to update bug: " . $e->getMessage());
                $error = 'Failed to update bug. Please try again.';
            }
        }
    } elseif ($action === 'add_comment') {
        $comment = sanitizeInput($_POST['comment'] ?? '');
        
        if (empty($comment)) {
            $error = 'Comment cannot be empty.';
        } else {
            try {
                $commentData = [
                    'bug_id' => $bugId,
                    'user_id' => $user['id'],
                    'comment' => $comment
                ];
                
                $db->insert('bug_comments', $commentData);
                
                logActivity($user['id'], $bug['project_id'], 'bug_comment', $bugId, 'create', 'Comment added to bug');
                
                $success = 'Comment added successfully!';
                
                // Refresh comments
                $comments = $db->fetchAll("
                    SELECT bc.*, u.username 
                    FROM bug_comments bc 
                    LEFT JOIN users u ON bc.user_id = u.id 
                    WHERE bc.bug_id = ? 
                    ORDER BY bc.created_at ASC
                ", [$bugId]);
            } catch (Exception $e) {
                error_log("Failed to add comment: " . $e->getMessage());
                $error = 'Failed to add comment. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bug - Test Management Framework</title>
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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-edit"></i> Edit Bug #<?php echo $bugId; ?></h3>
                        <a href="ai_categorize.php?id=<?php echo $bugId; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-robot"></i> AI Categorize
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($bug['ai_categorization']): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-robot"></i> AI Categorization:</h6>
                                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($bug['ai_categorization']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_bug">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Bug Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($bug['title']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="test_case_id" class="form-label">Related Test Case</label>
                                        <select class="form-select" id="test_case_id" name="test_case_id">
                                            <option value="">Select Test Case (Optional)</option>
                                            <?php foreach ($testCases as $testCase): ?>
                                            <option value="<?php echo $testCase['id']; ?>" 
                                                    <?php echo $bug['test_case_id'] == $testCase['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($testCase['title']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($bug['description']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="steps_to_reproduce" class="form-label">Steps to Reproduce</label>
                                        <textarea class="form-control" id="steps_to_reproduce" name="steps_to_reproduce" rows="4"><?php echo htmlspecialchars($bug['steps_to_reproduce'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expected_behavior" class="form-label">Expected Behavior</label>
                                        <textarea class="form-control" id="expected_behavior" name="expected_behavior" rows="2"><?php echo htmlspecialchars($bug['expected_behavior'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="actual_behavior" class="form-label">Actual Behavior</label>
                                        <textarea class="form-control" id="actual_behavior" name="actual_behavior" rows="2"><?php echo htmlspecialchars($bug['actual_behavior'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Type *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="bug" <?php echo $bug['type'] === 'bug' ? 'selected' : ''; ?>>Bug</option>
                                            <option value="enhancement" <?php echo $bug['type'] === 'enhancement' ? 'selected' : ''; ?>>Enhancement</option>
                                            <option value="task" <?php echo $bug['type'] === 'task' ? 'selected' : ''; ?>>Task</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="severity" class="form-label">Severity *</label>
                                        <select class="form-select" id="severity" name="severity" required>
                                            <option value="low" <?php echo $bug['severity'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $bug['severity'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $bug['severity'] === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo $bug['severity'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Priority *</label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low" <?php echo $bug['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $bug['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $bug['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo $bug['priority'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="open" <?php echo $bug['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                            <option value="in_progress" <?php echo $bug['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $bug['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $bug['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            <option value="rejected" <?php echo $bug['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($users as $assignee): ?>
                                            <option value="<?php echo $assignee['id']; ?>" 
                                                    <?php echo $bug['assigned_to'] == $assignee['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($assignee['username']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="environment" class="form-label">Environment</label>
                                        <input type="text" class="form-control" id="environment" name="environment" 
                                               value="<?php echo htmlspecialchars($bug['environment'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="browser" class="form-label">Browser</label>
                                        <input type="text" class="form-control" id="browser" name="browser" 
                                               value="<?php echo htmlspecialchars($bug['browser'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="os" class="form-label">Operating System</label>
                                        <input type="text" class="form-control" id="os" name="os" 
                                               value="<?php echo htmlspecialchars($bug['os'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Bug
                                </button>
                                <a href="index.php?project_id=<?php echo $bug['project_id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Bugs
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Comments Section -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-comments"></i> Comments</h5>
                    </div>
                    <div class="card-body">
                        <!-- Add Comment Form -->
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-2">
                                <textarea class="form-control" name="comment" rows="3" placeholder="Add a comment..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Add Comment
                            </button>
                        </form>
                        
                        <!-- Comments List -->
                        <?php if (empty($comments)): ?>
                            <p class="text-muted">No comments yet.</p>
                        <?php else: ?>
                            <div class="comments-list" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($comments as $comment): ?>
                                <div class="comment mb-3 p-2 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <small class="fw-bold"><?php echo htmlspecialchars($comment['username']); ?></small>
                                        <small class="text-muted"><?php echo formatDate($comment['created_at']); ?></small>
                                    </div>
                                    <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($comment['comment']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
