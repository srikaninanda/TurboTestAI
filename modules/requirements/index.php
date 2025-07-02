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

// If project_id is provided, check access
if ($projectId && !hasProjectAccess($projectId)) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Build query conditions
$whereConditions = [];
$params = [];

if ($projectId) {
    $whereConditions[] = "r.project_id = ?";
    $params[] = $projectId;
} else {
    // Show all accessible projects
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $whereConditions[] = "r.project_id IN ($placeholders)";
        $params = array_merge($params, $projectIds);
    } else {
        $whereConditions[] = "1 = 0"; // No accessible projects
    }
}

// Add search filter
$search = $_GET['search'] ?? '';
if ($search) {
    $whereConditions[] = "(r.title ILIKE ? OR r.description ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add status filter
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get requirements
$requirements = $db->fetchAll("
    SELECT r.*, p.name as project_name, u.username as created_by_name
    FROM requirements r
    LEFT JOIN projects p ON r.project_id = p.id
    LEFT JOIN users u ON r.created_by = u.id
    $whereClause
    ORDER BY r.created_at DESC
", $params);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements - Test Management Framework</title>
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
                    <h1><i class="fas fa-list"></i> Requirements</h1>
                    <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Requirement
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php 
                        switch ($success) {
                            case 'requirement_created': echo 'Requirement created successfully!'; break;
                            case 'requirement_updated': echo 'Requirement updated successfully!'; break;
                            case 'requirement_deleted': echo 'Requirement deleted successfully!'; break;
                            default: echo htmlspecialchars($success);
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <?php if ($projectId): ?>
                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search requirements...">
                            </div>
                            
                            <?php if (!$projectId): ?>
                            <div class="col-md-3">
                                <label for="project_filter" class="form-label">Project</label>
                                <select class="form-select" id="project_filter" name="project_id">
                                    <option value="">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo ($_GET['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="review" <?php echo $statusFilter === 'review' ? 'selected' : ''; ?>>Review</option>
                                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Requirements List -->
                <?php if (empty($requirements)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-list fa-4x text-muted mb-3"></i>
                        <h4>No Requirements Found</h4>
                        <p class="text-muted">Create your first requirement to get started.</p>
                        <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Requirement
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Project</th>
                                            <th>Type</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requirements as $requirement): ?>
                                        <tr>
                                            <td><?php echo $requirement['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($requirement['title']); ?></strong>
                                                <?php if ($requirement['ai_analysis']): ?>
                                                    <span class="ai-indicator">
                                                        <i class="fas fa-robot"></i> AI
                                                    </span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($requirement['description'], 0, 100)); ?>
                                                    <?php if (strlen($requirement['description']) > 100): ?>...<?php endif; ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($requirement['project_name']); ?></td>
                                            <td><?php echo formatStatus($requirement['type']); ?></td>
                                            <td><?php echo formatPriority($requirement['priority']); ?></td>
                                            <td><?php echo formatStatus($requirement['status']); ?></td>
                                            <td><?php echo htmlspecialchars($requirement['created_by_name']); ?></td>
                                            <td><?php echo formatDate($requirement['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $requirement['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="ai_analyze.php?id=<?php echo $requirement['id']; ?>" 
                                                       class="btn btn-outline-info" title="AI Analysis">
                                                        <i class="fas fa-robot"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $requirement['id']; ?>" 
                                                       class="btn btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this requirement?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
