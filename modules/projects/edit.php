<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$projectId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get project data and check access
$project = $db->fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
if (!$project || !hasProjectAccess($projectId)) {
    header('Location: index.php?error=access_denied');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Project name is required.';
    } else {
        try {
            $updateData = [
                'name' => $name,
                'description' => $description,
                'status' => $status
            ];
            
            $db->update('projects', $updateData, 'id = ?', [$projectId]);
            
            logActivity($user['id'], $projectId, 'project', $projectId, 'update', 'Project updated: ' . $name);
            
            $success = 'Project updated successfully!';
            
            // Refresh project data
            $project = $db->fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
        } catch (Exception $e) {
            error_log("Failed to update project: " . $e->getMessage());
            $error = 'Failed to update project. Please try again.';
        }
    }
}

// Get project members
$members = $db->fetchAll("
    SELECT pm.*, u.username, u.email 
    FROM project_members pm 
    JOIN users u ON pm.user_id = u.id 
    WHERE pm.project_id = ? 
    ORDER BY pm.role DESC, u.username
", [$projectId]);

// Get all users for member addition
$allUsers = $db->fetchAll("
    SELECT id, username, email 
    FROM users 
    WHERE id NOT IN (SELECT user_id FROM project_members WHERE project_id = ?)
    ORDER BY username
", [$projectId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - Test Management Framework</title>
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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-edit"></i> Edit Project</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Project Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($project['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $project['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $project['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Project
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Projects
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Project Members</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <p class="text-muted">No members found.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($members as $member): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($member['username']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                    </div>
                                    <div>
                                        <?php echo formatStatus($member['role']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($allUsers)): ?>
                        <hr>
                        <h6>Add Member</h6>
                        <form method="POST" action="add_member.php">
                            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                            <div class="mb-2">
                                <select name="user_id" class="form-select form-select-sm" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($allUsers as $availableUser): ?>
                                    <option value="<?php echo $availableUser['id']; ?>">
                                        <?php echo htmlspecialchars($availableUser['username']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <select name="role" class="form-select form-select-sm" required>
                                    <option value="member">Member</option>
                                    <option value="lead">Lead</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
