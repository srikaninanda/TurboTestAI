<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/ai.php';

requireAuth();

$user = getCurrentUser();
$db = getDB();

// Get user projects
$projects = getUserProjects($user['id']);
$selectedProjectId = $_GET['project_id'] ?? '';

// If project selected, validate access
if ($selectedProjectId && !hasProjectAccess($selectedProjectId)) {
    $selectedProjectId = '';
}

// Build project filter for queries
$projectFilter = '';
$projectParams = [];
if ($selectedProjectId) {
    $projectFilter = 'WHERE project_id = ?';
    $projectParams = [$selectedProjectId];
} else {
    // Filter by accessible projects
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $projectFilter = "WHERE project_id IN ($placeholders)";
        $projectParams = $projectIds;
    } else {
        $projectFilter = 'WHERE 1 = 0';
    }
}

// Get overall statistics
$stats = [
    'projects' => count($projects),
    'requirements' => 0,
    'test_cases' => 0,
    'test_runs' => 0,
    'bugs' => [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0
    ],
    'test_executions' => [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'blocked' => 0,
        'not_run' => 0,
        'skipped' => 0
    ]
];

// Get requirements count
if (!empty($projectParams)) {
    $result = $db->fetch("SELECT COUNT(*) as count FROM requirements $projectFilter", $projectParams);
    $stats['requirements'] = $result['count'];
    
    // Get test cases count
    $result = $db->fetch("SELECT COUNT(*) as count FROM test_cases $projectFilter", $projectParams);
    $stats['test_cases'] = $result['count'];
    
    // Get test runs count
    $result = $db->fetch("SELECT COUNT(*) as count FROM test_runs $projectFilter", $projectParams);
    $stats['test_runs'] = $result['count'];
    
    // Get bugs statistics
    $bugStats = $db->fetchAll("
        SELECT status, COUNT(*) as count 
        FROM bugs $projectFilter 
        GROUP BY status
    ", $projectParams);
    
    foreach ($bugStats as $stat) {
        $stats['bugs'][$stat['status']] = $stat['count'];
        $stats['bugs']['total'] += $stat['count'];
    }
    
    // Get test execution statistics
    $executionStats = $db->fetchAll("
        SELECT te.status, COUNT(*) as count
        FROM test_executions te
        JOIN test_runs tr ON te.test_run_id = tr.id
        " . str_replace('project_id', 'tr.project_id', $projectFilter) . "
        GROUP BY te.status
    ", $projectParams);
    
    foreach ($executionStats as $stat) {
        $stats['test_executions'][$stat['status']] = $stat['count'];
        $stats['test_executions']['total'] += $stat['count'];
    }
}

// Get recent activity
$recentActivity = [];
if (!empty($projectParams)) {
    $activityFilter = str_replace('project_id', 'al.project_id', $projectFilter);
    $recentActivity = $db->fetchAll("
        SELECT al.*, u.username, p.name as project_name
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN projects p ON al.project_id = p.id
        $activityFilter
        ORDER BY al.created_at DESC 
        LIMIT 10
    ", $projectParams);
}

// Get top bugs by severity
$topBugs = [];
if (!empty($projectParams)) {
    $topBugs = $db->fetchAll("
        SELECT b.*, p.name as project_name
        FROM bugs b
        LEFT JOIN projects p ON b.project_id = p.id
        $projectFilter
        AND b.status IN ('open', 'in_progress')
        ORDER BY 
            CASE b.severity 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            b.created_at DESC
        LIMIT 5
    ", $projectParams);
}

// Get project progress
$projectProgress = [];
foreach ($projects as $project) {
    if ($selectedProjectId && $project['id'] != $selectedProjectId) {
        continue;
    }
    
    $projectStats = getProjectStats($project['id']);
    $progress = [
        'id' => $project['id'],
        'name' => $project['name'],
        'status' => $project['status'],
        'requirements' => $projectStats['requirements'],
        'test_cases' => $projectStats['test_cases'],
        'test_runs' => $projectStats['test_runs'],
        'open_bugs' => $projectStats['open_bugs'],
        'completion' => 0
    ];
    
    // Calculate completion percentage based on test executions
    if ($projectStats['test_cases'] > 0) {
        $executionResult = $db->fetch("
            SELECT 
                COUNT(*) as total_executions,
                SUM(CASE WHEN te.status = 'passed' THEN 1 ELSE 0 END) as passed
            FROM test_executions te
            JOIN test_runs tr ON te.test_run_id = tr.id
            JOIN test_cases tc ON te.test_case_id = tc.id
            WHERE tc.project_id = ?
        ", [$project['id']]);
        
        if ($executionResult['total_executions'] > 0) {
            $progress['completion'] = round(($executionResult['passed'] / $executionResult['total_executions']) * 100);
        }
    }
    
    $projectProgress[] = $progress;
}

// Generate AI insights if requested
$aiInsights = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'generate_insights') {
    try {
        $ai = getAI();
        
        // Prepare dashboard data for AI analysis
        $dashboardData = [
            'projects' => $stats['projects'],
            'requirements' => $stats['requirements'],
            'test_cases' => $stats['test_cases'],
            'test_runs' => $stats['test_runs'],
            'bugs' => $stats['bugs'],
            'test_executions' => $stats['test_executions'],
            'project_progress' => $projectProgress
        ];
        
        $result = $ai->generateDashboardInsights($dashboardData);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $aiInsights = $result['insights'];
        }
    } catch (Exception $e) {
        error_log("AI insights generation failed: " . $e->getMessage());
        $error = 'AI insights generation failed. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Test Management Framework</title>
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
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    <div class="btn-group">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="generate_insights">
                            <button type="submit" class="btn btn-info" id="insights-btn">
                                <i class="fas fa-robot"></i> AI Insights
                            </button>
                        </form>
                        <a href="export.php<?php echo $selectedProjectId ? '?project_id=' . $selectedProjectId : ''; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-download"></i> Export
                        </a>
                    </div>
                </div>

                <!-- Project Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="project_id" class="form-label">Filter by Project</label>
                                <select class="form-select" id="project_id" name="project_id" onchange="this.form.submit()">
                                    <option value="">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo $selectedProjectId == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <?php if ($selectedProjectId): ?>
                                    <a href="?" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($aiInsights): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <h6><i class="fas fa-robot"></i> AI Dashboard Insights:</h6>
                        <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($aiInsights); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Projects</h6>
                                        <h3><?php echo $stats['projects']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-project-diagram fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Requirements</h6>
                                        <h3><?php echo $stats['requirements']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Test Cases</h6>
                                        <h3><?php echo $stats['test_cases']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-square fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Open Bugs</h6>
                                        <h3><?php echo $stats['bugs']['open'] + $stats['bugs']['in_progress']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bug fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Test Execution Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="testExecutionChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Bug Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="bugStatusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Progress and Top Issues -->
                <div class="row mb-4">
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <h5>Project Progress</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($projectProgress)): ?>
                                    <p class="text-muted">No projects to display.</p>
                                <?php else: ?>
                                    <?php foreach ($projectProgress as $project): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($project['name']); ?></h6>
                                            <small class="text-muted"><?php echo $project['completion']; ?>% Complete</small>
                                        </div>
                                        <div class="progress mb-2">
                                            <div class="progress-bar" style="width: <?php echo $project['completion']; ?>%"></div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <small class="text-muted">Req: <?php echo $project['requirements']; ?></small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Tests: <?php echo $project['test_cases']; ?></small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Runs: <?php echo $project['test_runs']; ?></small>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Bugs: <?php echo $project['open_bugs']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Priority Issues</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($topBugs)): ?>
                                    <p class="text-muted">No critical issues found.</p>
                                <?php else: ?>
                                    <?php foreach ($topBugs as $bug): ?>
                                    <div class="d-flex justify-content-between align-items-start mb-3 p-2 border rounded">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="../bugs/edit.php?id=<?php echo $bug['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($bug['title']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($bug['project_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <?php echo formatPriority($bug['severity']); ?>
                                            <?php echo formatStatus($bug['status']); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <p class="text-muted">No recent activity found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Action</th>
                                                    <th>Project</th>
                                                    <th>Description</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentActivity as $activity): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                                    <td><?php echo formatStatus($activity['action']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['project_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                                    <td><?php echo formatDate($activity['created_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Test Execution Chart
        const testExecutionCtx = document.getElementById('testExecutionChart').getContext('2d');
        new Chart(testExecutionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed', 'Blocked', 'Not Run', 'Skipped'],
                datasets: [{
                    data: [
                        <?php echo $stats['test_executions']['passed']; ?>,
                        <?php echo $stats['test_executions']['failed']; ?>,
                        <?php echo $stats['test_executions']['blocked']; ?>,
                        <?php echo $stats['test_executions']['not_run']; ?>,
                        <?php echo $stats['test_executions']['skipped']; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d', '#17a2b8'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Bug Status Chart
        const bugStatusCtx = document.getElementById('bugStatusChart').getContext('2d');
        new Chart(bugStatusCtx, {
            type: 'bar',
            data: {
                labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                datasets: [{
                    label: 'Number of Bugs',
                    data: [
                        <?php echo $stats['bugs']['open']; ?>,
                        <?php echo $stats['bugs']['in_progress']; ?>,
                        <?php echo $stats['bugs']['resolved']; ?>,
                        <?php echo $stats['bugs']['closed']; ?>
                    ],
                    backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#6c757d'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Add loading state to insights button
        document.getElementById('insights-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        });
    </script>
</body>
</html>
