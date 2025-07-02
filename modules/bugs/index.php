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
    $whereConditions[] = "b.project_id = ?";
    $params[] = $projectId;
} else {
    // Show all accessible projects
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $whereConditions[] = "b.project_id IN ($placeholders)";
        $params = array_merge($params, $projectIds);
    } else {
        $whereConditions[] = "1 = 0"; // No accessible projects
    }
}

// Add filters
$search = $_GET['search'] ?? '';
if ($search) {
    $whereConditions[] = "(b.title ILIKE ? OR b.description ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $whereConditions[] = "b.status = ?";
    $params[] = $statusFilter;
}

$severityFilter = $_GET['severity'] ?? '';
if ($severityFilter) {
    $whereConditions[] = "b.severity = ?";
    $params[] = $severityFilter;
}

$assignedFilter = $_GET['assigned'] ?? '';
if ($assignedFilter === 'me') {
    $whereConditions[] = "b.assigned_to = ?";
    $params[] = $user['id'];
} elseif ($assignedFilter === 'unassigned') {
    $whereConditions[] = "b.assigned_to IS NULL";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get bugs
$bugs = $db->fetchAll("
    SELECT b.*, p.name as project_name, 
           tc.title as test_case_title,
           reporter.username as reported_by_name,
           assignee.username as assigned_to_name
    FROM bugs b
    LEFT JOIN projects p ON b.project_id = p.id
    LEFT JOIN test_cases tc ON b.test_case_id = tc.id
    LEFT JOIN users reporter ON b.reported_by = reporter.id
    LEFT JOIN users assignee ON b.assigned_to = assignee.id
    $whereClause
    ORDER BY b.created_at DESC
", $params);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugs - Test Management Framework</title>
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
                    <h1><i class="fas fa-bug"></i> Bugs & Issues</h1>
                    <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Report Bug
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php 
                        switch ($success) {
                            case 'bug_created': echo 'Bug reported successfully!'; break;
                            case 'bug_updated': echo 'Bug updated successfully!'; break;
                            case 'bug_deleted': echo 'Bug deleted successfully!'; break;
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
                                       placeholder="Search bugs...">
                            </div>
                            
                            <?php if (!$projectId): ?>
                            <div class="col-md-2">
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
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="severity" class="form-label">Severity</label>
                                <select class="form-select" id="severity" name="severity">
                                    <option value="">All Severities</option>
                                    <option value="low" <?php echo $severityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $severityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $severityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $severityFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="assigned" class="form-label">Assignment</label>
                                <select class="form-select" id="assigned" name="assigned">
                                    <option value="">All</option>
                                    <option value="me" <?php echo $assignedFilter === 'me' ? 'selected' : ''; ?>>Assigned to Me</option>
                                    <option value="unassigned" <?php echo $assignedFilter === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                </select>
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bugs List -->
                <?php if (empty($bugs)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bug fa-4x text-muted mb-3"></i>
                        <h4>No Bugs Found</h4>
                        <p class="text-muted">No bugs match your current filters, or no bugs have been reported yet.</p>
                        <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Report First Bug
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
                                            <th>Severity</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Reported By</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bugs as $bug): ?>
                                        <tr>
                                            <td><?php echo $bug['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($bug['title']); ?></strong>
                                                <?php if ($bug['ai_categorization']): ?>
                                                    <span class="ai-indicator">
                                                        <i class="fas fa-robot"></i> AI
                                                    </span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($bug['description'], 0, 80)); ?>
                                                    <?php if (strlen($bug['description']) > 80): ?>...<?php endif; ?>
                                                </small>
                                                <?php if ($bug['test_case_title']): ?>
                                                <br><small class="text-info">
                                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars($bug['test_case_title']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($bug['project_name']); ?></td>
                                            <td><?php echo formatStatus($bug['type']); ?></td>
                                            <td><?php echo formatPriority($bug['severity']); ?></td>
                                            <td><?php echo formatPriority($bug['priority']); ?></td>
                                            <td><?php echo formatStatus($bug['status']); ?></td>
                                            <td>
                                                <?php if ($bug['assigned_to_name']): ?>
                                                    <?php echo htmlspecialchars($bug['assigned_to_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($bug['reported_by_name']); ?></td>
                                            <td><?php echo formatDate($bug['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $bug['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="ai_categorize.php?id=<?php echo $bug['id']; ?>" 
                                                       class="btn btn-outline-info" title="AI Analysis">
                                                        <i class="fas fa-robot"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $bug['id']; ?>" 
                                                       class="btn btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this bug?')">
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
