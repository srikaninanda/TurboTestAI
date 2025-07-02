<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/ai.php';

requireAuth();

$user = getCurrentUser();
$projectId = $_GET['project_id'] ?? '';
$requirementId = $_GET['requirement_id'] ?? '';
$db = getDB();

// Get user projects
$projects = getUserProjects($user['id']);

// Get requirements
$requirements = [];
if ($projectId && hasProjectAccess($projectId)) {
    $requirements = $db->fetchAll("
        SELECT id, title, description 
        FROM requirements 
        WHERE project_id = ? AND status = 'approved'
        ORDER BY title
    ", [$projectId]);
}

$generatedTestCases = [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProjectId = $_POST['project_id'] ?? '';
    $selectedRequirementId = $_POST['requirement_id'] ?? '';
    $generateAll = isset($_POST['generate_all']);
    
    if (empty($selectedProjectId)) {
        $error = 'Please select a project.';
    } elseif (!$generateAll && empty($selectedRequirementId)) {
        $error = 'Please select a requirement or choose to generate for all requirements.';
    } elseif (!hasProjectAccess($selectedProjectId)) {
        $error = 'You do not have access to the selected project.';
    } else {
        try {
            $ai = getAI();
            $requirementsToProcess = [];
            
            if ($generateAll) {
                $requirementsToProcess = $db->fetchAll("
                    SELECT id, title, description, acceptance_criteria
                    FROM requirements 
                    WHERE project_id = ? AND status = 'approved'
                ", [$selectedProjectId]);
            } else {
                $requirement = $db->fetch("
                    SELECT id, title, description, acceptance_criteria
                    FROM requirements 
                    WHERE id = ? AND project_id = ?
                ", [$selectedRequirementId, $selectedProjectId]);
                
                if ($requirement) {
                    $requirementsToProcess[] = $requirement;
                }
            }
            
            if (empty($requirementsToProcess)) {
                $error = 'No approved requirements found to generate test cases.';
            } else {
                foreach ($requirementsToProcess as $req) {
                    // Prepare requirement text
                    $requirementText = "Title: " . $req['title'] . "\n\n";
                    $requirementText .= "Description: " . $req['description'] . "\n\n";
                    if ($req['acceptance_criteria']) {
                        $requirementText .= "Acceptance Criteria: " . $req['acceptance_criteria'];
                    }
                    
                    $result = $ai->generateTestCases($requirementText);
                    
                    if (isset($result['error'])) {
                        $error = $result['error'];
                        break;
                    } else {
                        // Parse AI generated test cases and save them
                        $testCasesText = $result['test_cases'];
                        $parsedTestCases = $this->parseAITestCases($testCasesText, $req['title']);
                        
                        foreach ($parsedTestCases as $parsedCase) {
                            $testCaseData = [
                                'project_id' => $selectedProjectId,
                                'requirement_id' => $req['id'],
                                'title' => $parsedCase['title'],
                                'description' => $parsedCase['description'],
                                'preconditions' => $parsedCase['preconditions'],
                                'test_steps' => $parsedCase['test_steps'],
                                'expected_result' => $parsedCase['expected_result'],
                                'type' => 'functional',
                                'priority' => 'medium',
                                'status' => 'active',
                                'ai_generated' => true,
                                'created_by' => $user['id']
                            ];
                            
                            $testCaseId = $db->insert('test_cases', $testCaseData);
                            $generatedTestCases[] = $testCaseData;
                            
                            logActivity($user['id'], $selectedProjectId, 'test_case', $testCaseId, 'ai_generate', 'AI generated test case: ' . $parsedCase['title']);
                        }
                    }
                }
                
                if (!$error) {
                    $success = count($generatedTestCases) . ' test cases generated successfully!';
                }
            }
        } catch (Exception $e) {
            error_log("AI test case generation failed: " . $e->getMessage());
            $error = 'Failed to generate test cases. Please try again later.';
        }
    }
}

// Simple parser for AI generated test cases
function parseAITestCases($aiText, $requirementTitle) {
    $testCases = [];
    
    // Split by common patterns that indicate new test cases
    $sections = preg_split('/(?:Test Case \d+:|^\d+\.|\n\n(?=Test|Scenario))/mi', $aiText);
    
    foreach ($sections as $section) {
        $section = trim($section);
        if (empty($section)) continue;
        
        $testCase = [
            'title' => '',
            'description' => '',
            'preconditions' => '',
            'test_steps' => '',
            'expected_result' => ''
        ];
        
        // Extract title (first line or up to first colon)
        $lines = explode("\n", $section);
        $firstLine = trim($lines[0]);
        if (!empty($firstLine)) {
            $testCase['title'] = $firstLine;
        } else {
            $testCase['title'] = "Test case for: " . $requirementTitle;
        }
        
        // Simple extraction - in a real implementation, this would be more sophisticated
        if (preg_match('/Steps?:\s*(.*?)(?:Expected|Result)/si', $section, $matches)) {
            $testCase['test_steps'] = trim($matches[1]);
        } else {
            // Fallback - use middle part as steps
            $middleLines = array_slice($lines, 1, -1);
            $testCase['test_steps'] = implode("\n", $middleLines);
        }
        
        if (preg_match('/(?:Expected|Result):\s*(.*)$/si', $section, $matches)) {
            $testCase['expected_result'] = trim($matches[1]);
        } else {
            // Fallback - use last line as expected result
            $testCase['expected_result'] = end($lines);
        }
        
        // Set description as the full AI text for reference
        $testCase['description'] = "AI Generated from requirement: " . $requirementTitle;
        
        // Only add if we have meaningful content
        if (!empty($testCase['test_steps']) && !empty($testCase['expected_result'])) {
            $testCases[] = $testCase;
        }
    }
    
    // If parsing failed, create one generic test case
    if (empty($testCases)) {
        $testCases[] = [
            'title' => "Test case for: " . $requirementTitle,
            'description' => "AI Generated test case",
            'preconditions' => '',
            'test_steps' => $aiText,
            'expected_result' => "System should behave as described in the requirement"
        ];
    }
    
    return $testCases;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Generate Test Cases - Test Management Framework</title>
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
                        <h3><i class="fas fa-robot"></i> AI Generate Test Cases</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="index.php?project_id=<?php echo $selectedProjectId ?? $projectId; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-eye"></i> View Generated Test Cases
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="generateForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="project_id" class="form-label">Project *</label>
                                        <select class="form-select" id="project_id" name="project_id" required onchange="loadRequirements(this.value)">
                                            <option value="">Select Project</option>
                                            <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>" 
                                                    <?php echo ($projectId == $project['id'] || ($_POST['project_id'] ?? '') == $project['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="requirement_id" class="form-label">Requirement</label>
                                        <select class="form-select" id="requirement_id" name="requirement_id">
                                            <option value="">Select Requirement</option>
                                            <?php foreach ($requirements as $requirement): ?>
                                            <option value="<?php echo $requirement['id']; ?>" 
                                                    <?php echo ($requirementId == $requirement['id'] || ($_POST['requirement_id'] ?? '') == $requirement['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($requirement['title']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="generate_all" name="generate_all" 
                                           <?php echo isset($_POST['generate_all']) ? 'checked' : ''; ?>
                                           onchange="toggleRequirementSelect()">
                                    <label class="form-check-label" for="generate_all">
                                        Generate test cases for all approved requirements in the project
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> How it works:</h6>
                                <ul class="mb-0">
                                    <li>AI will analyze the selected requirement(s)</li>
                                    <li>Generate comprehensive test cases including positive, negative, and edge cases</li>
                                    <li>Test cases will be marked as AI-generated for easy identification</li>
                                    <li>You can edit the generated test cases after creation</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success" id="generateBtn">
                                    <i class="fas fa-robot"></i> Generate Test Cases
                                </button>
                                <a href="index.php<?php echo $projectId ? '?project_id=' . $projectId : ''; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Test Cases
                                </a>
                            </div>
                        </form>
                        
                        <?php if (!empty($generatedTestCases)): ?>
                        <hr class="my-4">
                        <h5><i class="fas fa-list"></i> Generated Test Cases Preview</h5>
                        <div class="row">
                            <?php foreach (array_slice($generatedTestCases, 0, 3) as $index => $testCase): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <small>Test Case <?php echo $index + 1; ?></small>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($testCase['title']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($testCase['test_steps'], 0, 100)); ?>
                                                <?php if (strlen($testCase['test_steps']) > 100): ?>...<?php endif; ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($generatedTestCases) > 3): ?>
                        <p class="text-muted">...and <?php echo count($generatedTestCases) - 3; ?> more test cases</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadRequirements(projectId) {
            if (!projectId) return;
            
            // In a real implementation, this would be an AJAX call
            const url = new URL(window.location);
            url.searchParams.set('project_id', projectId);
            window.location.href = url.toString();
        }
        
        function toggleRequirementSelect() {
            const generateAll = document.getElementById('generate_all').checked;
            const requirementSelect = document.getElementById('requirement_id');
            
            if (generateAll) {
                requirementSelect.disabled = true;
                requirementSelect.value = '';
            } else {
                requirementSelect.disabled = false;
            }
        }
        
        // Initialize on page load
        toggleRequirementSelect();
        
        // Add loading state to generate button
        document.getElementById('generateForm').addEventListener('submit', function() {
            const btn = document.getElementById('generateBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Test Cases...';
        });
    </script>
</body>
</html>
