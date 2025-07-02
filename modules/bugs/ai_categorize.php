<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/ai.php';

requireAuth();

$bugId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get bug data and check access
$bug = $db->fetch("
    SELECT b.*, p.name as project_name, tc.title as test_case_title
    FROM bugs b 
    LEFT JOIN projects p ON b.project_id = p.id 
    LEFT JOIN test_cases tc ON b.test_case_id = tc.id
    WHERE b.id = ?
", [$bugId]);

if (!$bug || !hasProjectAccess($bug['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

$categorization = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ai = getAI();
        
        // Prepare bug description for analysis
        $bugDescription = "Title: " . $bug['title'] . "\n\n";
        $bugDescription .= "Description: " . $bug['description'] . "\n\n";
        
        if ($bug['steps_to_reproduce']) {
            $bugDescription .= "Steps to Reproduce: " . $bug['steps_to_reproduce'] . "\n\n";
        }
        
        if ($bug['expected_behavior']) {
            $bugDescription .= "Expected Behavior: " . $bug['expected_behavior'] . "\n\n";
        }
        
        if ($bug['actual_behavior']) {
            $bugDescription .= "Actual Behavior: " . $bug['actual_behavior'] . "\n\n";
        }
        
        if ($bug['environment']) {
            $bugDescription .= "Environment: " . $bug['environment'] . "\n";
        }
        
        if ($bug['browser']) {
            $bugDescription .= "Browser: " . $bug['browser'] . "\n";
        }
        
        if ($bug['os']) {
            $bugDescription .= "OS: " . $bug['os'] . "\n";
        }
        
        $result = $ai->categorizeBug($bugDescription);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $categorization = $result['categorization'];
            
            // Save AI categorization to database
            $db->update('bugs', ['ai_categorization' => $categorization], 'id = ?', [$bugId]);
            
            logActivity($user['id'], $bug['project_id'], 'bug', $bugId, 'ai_categorize', 'AI categorization performed');
        }
    } catch (Exception $e) {
        error_log("AI categorization failed: " . $e->getMessage());
        $error = 'AI categorization failed. Please try again later.';
    }
}

// If categorization already exists, show it
if (!$categorization && $bug['ai_categorization']) {
    $categorization = $bug['ai_categorization'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Bug Categorization - Test Management Framework</title>
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
                        <h3><i class="fas fa-robot"></i> AI Bug Categorization</h3>
                        <p class="mb-0 text-muted">Analyzing: <strong><?php echo htmlspecialchars($bug['title']); ?></strong></p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Bug Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6><i class="fas fa-bug"></i> Bug Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Project:</strong> <?php echo htmlspecialchars($bug['project_name']); ?></p>
                                        <p><strong>Type:</strong> <?php echo formatStatus($bug['type']); ?></p>
                                        <p><strong>Current Severity:</strong> <?php echo formatPriority($bug['severity']); ?></p>
                                        <p><strong>Current Priority:</strong> <?php echo formatPriority($bug['priority']); ?></p>
                                        <p><strong>Status:</strong> <?php echo formatStatus($bug['status']); ?></p>
                                        
                                        <?php if ($bug['test_case_title']): ?>
                                        <p><strong>Related Test Case:</strong> <?php echo htmlspecialchars($bug['test_case_title']); ?></p>
                                        <?php endif; ?>
                                        
                                        <h6>Description:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($bug['description'])); ?></p>
                                        
                                        <?php if ($bug['steps_to_reproduce']): ?>
                                        <h6>Steps to Reproduce:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($bug['steps_to_reproduce'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($bug['expected_behavior']): ?>
                                        <h6>Expected Behavior:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($bug['expected_behavior'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($bug['actual_behavior']): ?>
                                        <h6>Actual Behavior:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($bug['actual_behavior'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($bug['environment'] || $bug['browser'] || $bug['os']): ?>
                                        <h6>Environment Details:</h6>
                                        <?php if ($bug['environment']): ?>
                                        <p><strong>Environment:</strong> <?php echo htmlspecialchars($bug['environment']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($bug['browser']): ?>
                                        <p><strong>Browser:</strong> <?php echo htmlspecialchars($bug['browser']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($bug['os']): ?>
                                        <p><strong>OS:</strong> <?php echo htmlspecialchars($bug['os']); ?></p>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-robot"></i> AI Categorization</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($categorization): ?>
                                            <div class="categorization-result">
                                                <div style="white-space: pre-wrap; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; border-left: 4px solid #dc3545;">
                                                    <?php echo htmlspecialchars($categorization); ?>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="fas fa-info-circle"></i> Categorization generated by AI
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No categorization available yet. Click the button below to get AI insights about this bug.</p>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="mt-3">
                                            <button type="submit" class="btn btn-primary" <?php echo $error ? '' : 'id="categorize-btn"'; ?>>
                                                <i class="fas fa-robot"></i> 
                                                <?php echo $categorization ? 'Re-analyze' : 'Categorize'; ?> Bug
                                            </button>
                                        </form>
                                        
                                        <?php if ($categorization): ?>
                                        <div class="mt-3">
                                            <h6>Quick Actions:</h6>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="updateSeverity('high')">
                                                    <i class="fas fa-exclamation-triangle"></i> Mark as High Severity
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="updateSeverity('critical')">
                                                    <i class="fas fa-fire"></i> Mark as Critical
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm" onclick="updateStatus('in_progress')">
                                                    <i class="fas fa-play"></i> Start Working
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?php echo $bugId; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Bug
                            </a>
                            <a href="../testcases/create.php?project_id=<?php echo $bug['project_id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-plus"></i> Create Test Case
                            </a>
                            <a href="index.php?project_id=<?php echo $bug['project_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Bugs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading state to categorize button
        document.getElementById('categorize-btn')?.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
        });
        
        // Quick action functions
        function updateSeverity(severity) {
            if (confirm(`Are you sure you want to update the severity to ${severity}?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'edit.php?id=<?php echo $bugId; ?>';
                
                // Add current bug data as hidden inputs
                const fields = {
                    'action': 'update_bug',
                    'title': '<?php echo addslashes($bug['title']); ?>',
                    'description': '<?php echo addslashes($bug['description']); ?>',
                    'steps_to_reproduce': '<?php echo addslashes($bug['steps_to_reproduce'] ?? ''); ?>',
                    'expected_behavior': '<?php echo addslashes($bug['expected_behavior'] ?? ''); ?>',
                    'actual_behavior': '<?php echo addslashes($bug['actual_behavior'] ?? ''); ?>',
                    'test_case_id': '<?php echo $bug['test_case_id'] ?? ''; ?>',
                    'type': '<?php echo $bug['type']; ?>',
                    'severity': severity,
                    'priority': '<?php echo $bug['priority']; ?>',
                    'status': '<?php echo $bug['status']; ?>',
                    'environment': '<?php echo addslashes($bug['environment'] ?? ''); ?>',
                    'browser': '<?php echo addslashes($bug['browser'] ?? ''); ?>',
                    'os': '<?php echo addslashes($bug['os'] ?? ''); ?>',
                    'assigned_to': '<?php echo $bug['assigned_to'] ?? ''; ?>'
                };
                
                Object.keys(fields).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function updateStatus(status) {
            if (confirm(`Are you sure you want to update the status to ${status.replace('_', ' ')}?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'edit.php?id=<?php echo $bugId; ?>';
                
                // Add current bug data as hidden inputs
                const fields = {
                    'action': 'update_bug',
                    'title': '<?php echo addslashes($bug['title']); ?>',
                    'description': '<?php echo addslashes($bug['description']); ?>',
                    'steps_to_reproduce': '<?php echo addslashes($bug['steps_to_reproduce'] ?? ''); ?>',
                    'expected_behavior': '<?php echo addslashes($bug['expected_behavior'] ?? ''); ?>',
                    'actual_behavior': '<?php echo addslashes($bug['actual_behavior'] ?? ''); ?>',
                    'test_case_id': '<?php echo $bug['test_case_id'] ?? ''; ?>',
                    'type': '<?php echo $bug['type']; ?>',
                    'severity': '<?php echo $bug['severity']; ?>',
                    'priority': '<?php echo $bug['priority']; ?>',
                    'status': status,
                    'environment': '<?php echo addslashes($bug['environment'] ?? ''); ?>',
                    'browser': '<?php echo addslashes($bug['browser'] ?? ''); ?>',
                    'os': '<?php echo addslashes($bug['os'] ?? ''); ?>',
                    'assigned_to': '<?php echo $bug['assigned_to'] ?? ''; ?>'
                };
                
                Object.keys(fields).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
