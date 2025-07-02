<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/ai.php';

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

$analysis = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ai = getAI();
        
        // Prepare requirement text for analysis
        $requirementText = "Title: " . $requirement['title'] . "\n\n";
        $requirementText .= "Description: " . $requirement['description'] . "\n\n";
        if ($requirement['acceptance_criteria']) {
            $requirementText .= "Acceptance Criteria: " . $requirement['acceptance_criteria'];
        }
        
        $result = $ai->analyzeRequirement($requirementText);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $analysis = $result['analysis'];
            
            // Save AI analysis to database
            $db->update('requirements', ['ai_analysis' => $analysis], 'id = ?', [$requirementId]);
            
            logActivity($user['id'], $requirement['project_id'], 'requirement', $requirementId, 'ai_analyze', 'AI analysis performed');
        }
    } catch (Exception $e) {
        error_log("AI analysis failed: " . $e->getMessage());
        $error = 'AI analysis failed. Please try again later.';
    }
}

// If analysis already exists, show it
if (!$analysis && $requirement['ai_analysis']) {
    $analysis = $requirement['ai_analysis'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analysis - Test Management Framework</title>
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
                        <h3><i class="fas fa-robot"></i> AI Requirement Analysis</h3>
                        <p class="mb-0 text-muted">Analyzing: <strong><?php echo htmlspecialchars($requirement['title']); ?></strong></p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Requirement Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6><i class="fas fa-list"></i> Requirement Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Project:</strong> <?php echo htmlspecialchars($requirement['project_name']); ?></p>
                                        <p><strong>Type:</strong> <?php echo formatStatus($requirement['type']); ?></p>
                                        <p><strong>Priority:</strong> <?php echo formatPriority($requirement['priority']); ?></p>
                                        <p><strong>Status:</strong> <?php echo formatStatus($requirement['status']); ?></p>
                                        
                                        <h6>Description:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($requirement['description'])); ?></p>
                                        
                                        <?php if ($requirement['acceptance_criteria']): ?>
                                        <h6>Acceptance Criteria:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($requirement['acceptance_criteria'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-robot"></i> AI Analysis</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($analysis): ?>
                                            <div class="analysis-result">
                                                <div style="white-space: pre-wrap; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; border-left: 4px solid #0d6efd;">
                                                    <?php echo htmlspecialchars($analysis); ?>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="fas fa-info-circle"></i> Analysis generated by AI
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No analysis available yet. Click the button below to generate AI insights.</p>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="mt-3">
                                            <button type="submit" class="btn btn-primary" <?php echo $error ? '' : 'id="analyze-btn"'; ?>>
                                                <i class="fas fa-robot"></i> 
                                                <?php echo $analysis ? 'Re-analyze' : 'Analyze'; ?> Requirement
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?php echo $requirementId; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Requirement
                            </a>
                            <a href="../testcases/ai_generate.php?requirement_id=<?php echo $requirementId; ?>" class="btn btn-outline-success">
                                <i class="fas fa-magic"></i> Generate Test Cases
                            </a>
                            <a href="index.php?project_id=<?php echo $requirement['project_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Requirements
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading state to analyze button
        document.getElementById('analyze-btn')?.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
        });
    </script>
</body>
</html>
