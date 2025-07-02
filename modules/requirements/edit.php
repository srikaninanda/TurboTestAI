<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$requirementId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get requirement data and check access
$requirement = $db->fetch("
    SELECT r.*, p.name as project_name 
    FROM requirements r 
    LEFT JOIN projects p ON r.project_id = p.id 
    WHERE r.id = ?
", [$requirementId]);

if (!$requirement || !hasProjectAccess($requirement['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Get user projects
$projects = getUserProjects($user['id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = $_POST['project_id'] ?? '';
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'functional';
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'draft';
    $acceptanceCriteria = sanitizeInput($_POST['acceptance_criteria'] ?? '');
    
    if (empty($projectId) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } elseif (!hasProjectAccess($projectId)) {
        $error = 'You do not have access to the selected project.';
    } else {
        try {
            $updateData = [
                'project_id' => $projectId,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'priority' => $priority,
                'status' => $status,
                'acceptance_criteria' => $acceptanceCriteria
            ];
            
            $db->update('requirements', $updateData, 'id = ?', [$requirementId]);
            
            logActivity($user['id'], $projectId, 'requirement', $requirementId, 'update', 'Requirement updated: ' . $title);
            
            $success = 'Requirement updated successfully!';
            
            // Refresh requirement data
            $requirement = $db->fetch("
                SELECT r.*, p.name as project_name 
                FROM requirements r 
                LEFT JOIN projects p ON r.project_id = p.id 
                WHERE r.id = ?
            ", [$requirementId]);
        } catch (Exception $e) {
            error_log("Failed to update requirement: " . $e->getMessage());
            $error = 'Failed to update requirement. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Requirement - Test Management Framework</title>
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
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-edit"></i> Edit Requirement</h3>
                        <a href="ai_analyze.php?id=<?php echo $requirementId; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-robot"></i> AI Analysis
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($requirement['ai_analysis']): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-robot"></i> AI Analysis:</h6>
                                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($requirement['ai_analysis']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="project_id" class="form-label">Project *</label>
                                        <select class="form-select" id="project_id" name="project_id" required>
                                            <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>" 
                                                    <?php echo $requirement['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Type *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="functional" <?php echo $requirement['type'] === 'functional' ? 'selected' : ''; ?>>Functional</option>
                                            <option value="non-functional" <?php echo $requirement['type'] === 'non-functional' ? 'selected' : ''; ?>>Non-Functional</option>
                                            <option value="business" <?php echo $requirement['type'] === 'business' ? 'selected' : ''; ?>>Business</option>
                                            <option value="technical" <?php echo $requirement['type'] === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($requirement['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($requirement['description']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="acceptance_criteria" class="form-label">Acceptance Criteria</label>
                                <textarea class="form-control" id="acceptance_criteria" name="acceptance_criteria" rows="4"><?php echo htmlspecialchars($requirement['acceptance_criteria'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Priority *</label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low" <?php echo $requirement['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $requirement['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $requirement['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo $requirement['priority'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="draft" <?php echo $requirement['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="review" <?php echo $requirement['status'] === 'review' ? 'selected' : ''; ?>>Review</option>
                                            <option value="approved" <?php echo $requirement['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $requirement['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Requirement
                                </button>
                                <a href="index.php?project_id=<?php echo $requirement['project_id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Requirements
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
