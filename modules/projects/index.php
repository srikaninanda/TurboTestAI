<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$projects = getUserProjects($user['id']);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Test Management Framework</title>
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
                    <h1><i class="fas fa-project-diagram"></i> Projects</h1>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Project
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php 
                        switch ($success) {
                            case 'project_created': echo 'Project created successfully!'; break;
                            case 'project_updated': echo 'Project updated successfully!'; break;
                            case 'project_deleted': echo 'Project deleted successfully!'; break;
                            default: echo htmlspecialchars($success);
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php 
                        switch ($error) {
                            case 'project_not_found': echo 'Project not found.'; break;
                            case 'access_denied': echo 'Access denied.'; break;
                            case 'delete_failed': echo 'Failed to delete project.'; break;
                            default: echo htmlspecialchars($error);
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
                                <h4>No Projects Found</h4>
                                <p class="text-muted">Create your first project to get started with test management.</p>
                                <a href="create.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Project
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                                        <?php echo formatStatus($project['status']); ?>
                                    </div>
                                    
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr($project['description'] ?? 'No description', 0, 100)); ?>
                                        <?php if (strlen($project['description'] ?? '') > 100): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($project['created_by_name']); ?><br>
                                            <i class="fas fa-users"></i> Members: <?php echo $project['member_count']; ?><br>
                                            <i class="fas fa-calendar"></i> <?php echo formatDate($project['created_at']); ?>
                                        </small>
                                    </div>
                                    
                                    <?php $stats = getProjectStats($project['id']); ?>
                                    <div class="row text-center mb-3">
                                        <div class="col-3">
                                            <div class="border rounded p-2">
                                                <div class="h6 mb-0"><?php echo $stats['requirements']; ?></div>
                                                <small class="text-muted">Req</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-2">
                                                <div class="h6 mb-0"><?php echo $stats['test_cases']; ?></div>
                                                <small class="text-muted">Tests</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-2">
                                                <div class="h6 mb-0"><?php echo $stats['test_runs']; ?></div>
                                                <small class="text-muted">Runs</small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="border rounded p-2">
                                                <div class="h6 mb-0"><?php echo $stats['open_bugs']; ?></div>
                                                <small class="text-muted">Bugs</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        <a href="../requirements/index.php?project_id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this project?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
