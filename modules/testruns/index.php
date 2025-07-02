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
    $whereConditions[] = "tr.project_id = ?";
    $params[] = $projectId;
} else {
    // Show all accessible projects
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $whereConditions[] = "tr.project_id IN ($placeholders)";
        $params = array_merge($params, $projectIds);
    } else {
        $whereConditions[] = "1 = 0"; // No accessible projects
    }
}

// Add filters
$search = $_GET['search'] ?? '';
if ($search) {
    $whereConditions[] = "(tr.name ILIKE ? OR tr.description ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $whereConditions[] = "tr.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get test runs with execution stats
$testRuns = $db->fetchAll("
    SELECT tr.*, p.name as project_name, u.username as created_by_name,
           COUNT(te.id) as total_executions,
           SUM(CASE WHEN te.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
           SUM(CASE WHEN te.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
           SUM(CASE WHEN te.status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
           SUM(CASE WHEN te.status = 'not_run' THEN 1 ELSE 0 END) as not_run_count
    FROM test_runs tr
    LEFT JOIN projects p ON tr.project_id = p.id
    LEFT JOIN users u ON tr.created_by = u.id
    LEFT JOIN test_executions te ON tr.id = te.test_run_id
    $whereClause
    GROUP BY tr.id, p.name, u.username
    ORDER BY tr.created_at DESC
", $params);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Runs - Test Management Framework</title>
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
                    <h1><i class="fas fa-play"></i> Test Runs</h1>
                    <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Test Run
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php 
                        switch ($success) {
                            case 'testrun_created': echo 'Test run created successfully!'; break;
                            case 'testrun_updated': echo 'Test run updated successfully!'; break;
                            case 'testrun_deleted': echo 'Test run deleted successfully!'; break;
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
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search test runs...">
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
                                    <option value="planned" <?php echo $statusFilter === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="aborted" <?php echo $statusFilter === 'aborted' ? 'selected' : ''; ?>>Aborted</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
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

                <!-- Test Runs List -->
                <?php if (empty($testRuns)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-play fa-4x text-muted mb-3"></i>
                        <h4>No Test Runs Found</h4>
                        <p class="text-muted">Create your first test run to execute test cases.</p>
                        <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Test Run
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($testRuns as $testRun): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title"><?php echo htmlspecialchars($testRun['name']); ?></h5>
                                        <?php echo formatStatus($testRun['status']); ?>
                                    </div>
                                    
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr($testRun['description'] ?? 'No description', 0, 100)); ?>
                                        <?php if (strlen($testRun['description'] ?? '') > 100): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <?php if ($testRun['ai_insights']): ?>
                                        <div class="mb-2">
                                            <span class="ai-indicator">
                                                <i class="fas fa-robot"></i> AI Insights Available
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($testRun['project_name']); ?><br>
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($testRun['created_by_name']); ?><br>
                                            <i class="fas fa-calendar"></i> <?php echo formatDate($testRun['created_at']); ?>
                                            <?php if ($testRun['environment']): ?>
                                            <br><i class="fas fa-server"></i> <?php echo htmlspecialchars($testRun['environment']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Execution Stats -->
                                    <?php if ($testRun['total_executions'] > 0): ?>
                                    <div class="row text-center mb-3">
                                        <div class="col-3">
                                            <div class="border rounded p-1 bg-success text-white">
                                                <div class="small fw-bold"><?php echo $testRun['passed_count']; ?></div>
                                                <small>Pass</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-1 bg-danger text-white">
                                                <div class="small fw-bold"><?php echo $testRun['failed_count']; ?></div>
                                                <small>Fail</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-1 bg-warning text-white">
                                                <div class="small fw-bold"><?php echo $testRun['blocked_count']; ?></div>
                                                <small>Block</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-1 bg-secondary text-white">
                                                <div class="small fw-bold"><?php echo $testRun['not_run_count']; ?></div>
                                                <small>N/A</small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted text-center mb-3">
                                        <i class="fas fa-info-circle"></i> No test executions yet
                                    </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="d-flex gap-1">
                                        <a href="edit.php?id=<?php echo $testRun['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="ai_insights.php?id=<?php echo $testRun['id']; ?>" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-robot"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $testRun['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this test run?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
