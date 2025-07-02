<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$db = getDB();
$format = $_GET['format'] ?? 'csv';
$projectId = $_GET['project_id'] ?? '';

// Validate project access if specified
if ($projectId && !hasProjectAccess($projectId)) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Build project filter
$projectFilter = '';
$projectParams = [];
if ($projectId) {
    $projectFilter = 'WHERE project_id = ?';
    $projectParams = [$projectId];
} else {
    // Filter by accessible projects
    $projects = getUserProjects($user['id']);
    $projectIds = array_column($projects, 'id');
    if (!empty($projectIds)) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $projectFilter = "WHERE project_id IN ($placeholders)";
        $projectParams = $projectIds;
    } else {
        $projectFilter = 'WHERE 1 = 0';
    }
}

// Prepare data based on type
$exportType = $_GET['type'] ?? 'summary';
$filename = 'dashboard_export_' . date('Y-m-d_H-i-s');

switch ($exportType) {
    case 'requirements':
        $data = exportRequirements($db, $projectFilter, $projectParams);
        $filename = 'requirements_' . date('Y-m-d_H-i-s');
        break;
        
    case 'testcases':
        $data = exportTestCases($db, $projectFilter, $projectParams);
        $filename = 'testcases_' . date('Y-m-d_H-i-s');
        break;
        
    case 'testruns':
        $data = exportTestRuns($db, $projectFilter, $projectParams);
        $filename = 'testruns_' . date('Y-m-d_H-i-s');
        break;
        
    case 'bugs':
        $data = exportBugs($db, $projectFilter, $projectParams);
        $filename = 'bugs_' . date('Y-m-d_H-i-s');
        break;
        
    case 'activity':
        $data = exportActivity($db, $projectFilter, $projectParams);
        $filename = 'activity_' . date('Y-m-d_H-i-s');
        break;
        
    default:
        $data = exportSummary($db, $projectFilter, $projectParams);
        $filename = 'dashboard_summary_' . date('Y-m-d_H-i-s');
        break;
}

// Export based on format
if ($format === 'json') {
    exportToJSON($data, $filename . '.json');
} else {
    generateCSV($data, $filename . '.csv');
}

// Export functions
function exportSummary($db, $projectFilter, $projectParams) {
    $summary = [];
    
    // Get project statistics
    $projects = $db->fetchAll("
        SELECT p.id, p.name, p.status, p.created_at,
               COUNT(DISTINCT r.id) as requirements_count,
               COUNT(DISTINCT tc.id) as testcases_count,
               COUNT(DISTINCT tr.id) as testruns_count,
               COUNT(DISTINCT b.id) as bugs_count
        FROM projects p
        LEFT JOIN requirements r ON p.id = r.project_id
        LEFT JOIN test_cases tc ON p.id = tc.project_id
        LEFT JOIN test_runs tr ON p.id = tr.project_id
        LEFT JOIN bugs b ON p.id = b.project_id
        " . str_replace('project_id', 'p.id', $projectFilter) . "
        GROUP BY p.id, p.name, p.status, p.created_at
        ORDER BY p.name
    ", $projectParams);
    
    foreach ($projects as $project) {
        $summary[] = [
            'Project ID' => $project['id'],
            'Project Name' => $project['name'],
            'Status' => $project['status'],
            'Created Date' => $project['created_at'],
            'Requirements' => $project['requirements_count'],
            'Test Cases' => $project['testcases_count'],
            'Test Runs' => $project['testruns_count'],
            'Bugs' => $project['bugs_count']
        ];
    }
    
    return $summary;
}

function exportRequirements($db, $projectFilter, $projectParams) {
    return $db->fetchAll("
        SELECT r.id, r.title, r.description, r.type, r.priority, r.status,
               r.acceptance_criteria, r.created_at, r.updated_at,
               p.name as project_name, u.username as created_by
        FROM requirements r
        LEFT JOIN projects p ON r.project_id = p.id
        LEFT JOIN users u ON r.created_by = u.id
        $projectFilter
        ORDER BY r.created_at DESC
    ", $projectParams);
}

function exportTestCases($db, $projectFilter, $projectParams) {
    return $db->fetchAll("
        SELECT tc.id, tc.title, tc.description, tc.preconditions, tc.test_steps,
               tc.expected_result, tc.type, tc.priority, tc.status, tc.ai_generated,
               tc.created_at, tc.updated_at,
               p.name as project_name, r.title as requirement_title, u.username as created_by
        FROM test_cases tc
        LEFT JOIN projects p ON tc.project_id = p.id
        LEFT JOIN requirements r ON tc.requirement_id = r.id
        LEFT JOIN users u ON tc.created_by = u.id
        $projectFilter
        ORDER BY tc.created_at DESC
    ", $projectParams);
}

function exportTestRuns($db, $projectFilter, $projectParams) {
    return $db->fetchAll("
        SELECT tr.id, tr.name, tr.description, tr.status, tr.environment,
               tr.start_date, tr.end_date, tr.created_at, tr.updated_at,
               p.name as project_name, u.username as created_by,
               COUNT(te.id) as total_executions,
               SUM(CASE WHEN te.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
               SUM(CASE WHEN te.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
               SUM(CASE WHEN te.status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
               SUM(CASE WHEN te.status = 'not_run' THEN 1 ELSE 0 END) as not_run_count
        FROM test_runs tr
        LEFT JOIN projects p ON tr.project_id = p.id
        LEFT JOIN users u ON tr.created_by = u.id
        LEFT JOIN test_executions te ON tr.id = te.test_run_id
        $projectFilter
        GROUP BY tr.id, p.name, u.username
        ORDER BY tr.created_at DESC
    ", $projectParams);
}

function exportBugs($db, $projectFilter, $projectParams) {
    return $db->fetchAll("
        SELECT b.id, b.title, b.description, b.steps_to_reproduce, b.expected_behavior,
               b.actual_behavior, b.type, b.severity, b.priority, b.status,
               b.environment, b.browser, b.os, b.created_at, b.updated_at,
               p.name as project_name, tc.title as test_case_title,
               reporter.username as reported_by, assignee.username as assigned_to
        FROM bugs b
        LEFT JOIN projects p ON b.project_id = p.id
        LEFT JOIN test_cases tc ON b.test_case_id = tc.id
        LEFT JOIN users reporter ON b.reported_by = reporter.id
        LEFT JOIN users assignee ON b.assigned_to = assignee.id
        $projectFilter
        ORDER BY b.created_at DESC
    ", $projectParams);
}

function exportActivity($db, $projectFilter, $projectParams) {
    $activityFilter = str_replace('project_id', 'al.project_id', $projectFilter);
    return $db->fetchAll("
        SELECT al.id, al.entity_type, al.entity_id, al.action, al.description,
               al.created_at, u.username, p.name as project_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN projects p ON al.project_id = p.id
        $activityFilter
        ORDER BY al.created_at DESC
        LIMIT 1000
    ", $projectParams);
}

// If this is a direct page access (not export), show export options
if (!isset($_GET['format']) && !isset($_GET['type'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Dashboard - Test Management Framework</title>
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
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-download"></i> Export Dashboard Data</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Export Type</h5>
                                <div class="list-group mb-3">
                                    <a href="?type=summary&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-bar"></i> Dashboard Summary (CSV)
                                    </a>
                                    <a href="?type=summary&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-bar"></i> Dashboard Summary (JSON)
                                    </a>
                                    <a href="?type=requirements&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-list"></i> Requirements (CSV)
                                    </a>
                                    <a href="?type=testcases&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-check-square"></i> Test Cases (CSV)
                                    </a>
                                    <a href="?type=testruns&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-play"></i> Test Runs (CSV)
                                    </a>
                                    <a href="?type=bugs&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-bug"></i> Bugs (CSV)
                                    </a>
                                    <a href="?type=activity&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-history"></i> Activity Log (CSV)
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>JSON Exports</h5>
                                <div class="list-group mb-3">
                                    <a href="?type=requirements&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-list"></i> Requirements (JSON)
                                    </a>
                                    <a href="?type=testcases&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-check-square"></i> Test Cases (JSON)
                                    </a>
                                    <a href="?type=testruns&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-play"></i> Test Runs (JSON)
                                    </a>
                                    <a href="?type=bugs&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-bug"></i> Bugs (JSON)
                                    </a>
                                    <a href="?type=activity&format=json<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" class="list-group-item list-group-item-action">
                                        <i class="fas fa-history"></i> Activity Log (JSON)
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($projectId): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Exports will be filtered for the selected project only.
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2">
                            <a href="index.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
?>
