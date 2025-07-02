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
    $whereConditions[] = "tc.project_id = ?";
    $params[] = $projectId;
} else {
    // Show all accessible projects
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $whereConditions[] = "tc.project_id IN ($placeholders)";
        $params = array_merge($params, $projectIds);
    } else {
        $whereConditions[] = "1 = 0"; // No accessible projects
    }
}

// Add filters
$search = $_GET['search'] ?? '';
if ($search) {
    $whereConditions[] = "(tc.title ILIKE ? OR tc.description ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $whereConditions[] = "tc.status = ?";
    $params[] = $statusFilter;
}

$typeFilter = $_GET['type'] ?? '';
if ($typeFilter) {
    $whereConditions[] = "tc.type = ?";
    $params[] = $typeFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get test cases
$testCases = $db->fetchAll("
    SELECT tc.*, p.name as project_name, r.title as requirement_title, u.username as created_by_name
    FROM test_cases tc
    LEFT JOIN projects p ON tc.project_id = p.id
    LEFT JOIN requirements r ON tc.requirement_id = r.id
    LEFT JOIN users u ON tc.created_by = u.id
    $whereClause
    ORDER BY tc.created_at DESC
", $params);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Cases - Test Management Framework</title>
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
                    <h1><i class="fas fa-check-square"></i> Test Cases</h1>
                    <div class="btn-group">
                        <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Test Case
                        </a>
                        <a href="ai_generate.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-success">
                            <i class="fas fa-robot"></i> AI Generate
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php 
                        switch ($success) {
                            case 'testcase_created': echo 'Test case created successfully!'; break;
                            case 'testcase_updated': echo 'Test case updated successfully!'; break;
                            case 'testcase_deleted': echo 'Test case deleted successfully!'; break;
                            case 'testcases_generated': echo 'Test cases generated successfully!'; break;
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
                                       placeholder="Search test cases...">
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
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="functional" <?php echo $typeFilter === 'functional' ? 'selected' : ''; ?>>Functional</option>
                                    <option value="regression" <?php echo $typeFilter === 'regression' ? 'selected' : ''; ?>>Regression</option>
                                    <option value="integration" <?php echo $typeFilter === 'integration' ? 'selected' : ''; ?>>Integration</option>
                                    <option value="unit" <?php echo $typeFilter === 'unit' ? 'selected' : ''; ?>>Unit</option>
                                    <option value="performance" <?php echo $typeFilter === 'performance' ? 'selected' : ''; ?>>Performance</option>
                                    <option value="security" <?php echo $typeFilter === 'security' ? 'selected' : ''; ?>>Security</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="deprecated" <?php echo $statusFilter === 'deprecated' ? 'selected' : ''; ?>>Deprecated</option>
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

                <!-- Test Cases List -->
                <?php if (empty($testCases)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-square fa-4x text-muted mb-3"></i>
                        <h4>No Test Cases Found</h4>
                        <p class="text-muted">Create your first test case or use AI to generate them from requirements.</p>
                        <div class="btn-group">
                            <a href="create.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Test Case
                            </a>
                            <a href="ai_generate.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-success">
                                <i class="fas fa-robot"></i> AI Generate
                            </a>
                        </div>
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
                                            <th>Requirement</th>
                                            <th>Type</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testCases as $testCase): ?>
                                        <tr>
                                            <td><?php echo $testCase['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($testCase['title']); ?></strong>
                                                <?php if ($testCase['ai_generated']): ?>
                                                    <span class="ai-indicator">
                                                        <i class="fas fa-robot"></i> AI
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($testCase['description']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($testCase['description'], 0, 80)); ?>
                                                    <?php if (strlen($testCase['description']) > 80): ?>...<?php endif; ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($testCase['project_name']); ?></td>
                                            <td>
                                                <?php if ($testCase['requirement_title']): ?>
                                                    <small><?php echo htmlspecialchars($testCase['requirement_title']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatStatus($testCase['type']); ?></td>
                                            <td><?php echo formatPriority($testCase['priority']); ?></td>
                                            <td><?php echo formatStatus($testCase['status']); ?></td>
                                            <td><?php echo htmlspecialchars($testCase['created_by_name']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" title="View Details" 
                                                            data-bs-toggle="modal" data-bs-target="#testCaseModal"
                                                            onclick="loadTestCaseDetails(<?php echo $testCase['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="edit.php?id=<?php echo $testCase['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $testCase['id']; ?>" 
                                                       class="btn btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this test case?')">
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

    <!-- Test Case Details Modal -->
    <div class="modal fade" id="testCaseModal" tabindex="-1" aria-labelledby="testCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="testCaseModalLabel">
                        <i class="fas fa-check-square"></i> Test Case Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="testCaseDetails">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editTestCaseBtn">
                        <i class="fas fa-edit"></i> Edit Test Case
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTestCaseDetails(testCaseId) {
            const detailsContainer = document.getElementById('testCaseDetails');
            const editBtn = document.getElementById('editTestCaseBtn');
            
            // Show loading spinner
            detailsContainer.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch test case details via AJAX
            fetch(`view_details.php?id=${testCaseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        detailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Set edit button link
                    editBtn.onclick = () => window.location.href = `edit.php?id=${testCaseId}`;
                    
                    // Populate modal with test case details
                    detailsContainer.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-info-circle"></i> Basic Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Title:</strong></td><td>${data.title}</td></tr>
                                    <tr><td><strong>Project:</strong></td><td>${data.project_name}</td></tr>
                                    <tr><td><strong>Requirement:</strong></td><td>${data.requirement_title || 'None'}</td></tr>
                                    <tr><td><strong>Type:</strong></td><td>${data.type}</td></tr>
                                    <tr><td><strong>Priority:</strong></td><td>${data.priority}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td>${data.status}</td></tr>
                                    <tr><td><strong>Created By:</strong></td><td>${data.created_by_name}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                ${data.description ? `
                                <h6><i class="fas fa-file-text"></i> Description</h6>
                                <p>${data.description}</p>
                                ` : ''}
                                ${data.preconditions ? `
                                <h6><i class="fas fa-check-circle"></i> Preconditions</h6>
                                <p>${data.preconditions}</p>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6><i class="fas fa-list-ol"></i> Test Steps</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">Step #</th>
                                            <th style="width: 50%;">Test Step Description</th>
                                            <th style="width: 50%;">Expected Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.test_steps.map(step => `
                                            <tr>
                                                <td class="text-center"><strong>${step.step_number}</strong></td>
                                                <td>${step.step_description}</td>
                                                <td>${step.expected_result}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error loading test case details:', error);
                    detailsContainer.innerHTML = `<div class="alert alert-danger">Failed to load test case details. Please try again.</div>`;
                });
        }
    </script>
</body>
</html>
