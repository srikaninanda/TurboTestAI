<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$projectId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get project and check access
$project = $db->fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
if (!$project || !hasProjectAccess($projectId)) {
    header('Location: index.php?error=access_denied');
    exit();
}

try {
    // Delete project (cascading deletes will handle related records)
    $db->delete('projects', 'id = ?', [$projectId]);
    
    logActivity($user['id'], null, 'project', $projectId, 'delete', 'Project deleted: ' . $project['name']);
    
    header('Location: index.php?success=project_deleted');
} catch (Exception $e) {
    error_log("Failed to delete project: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
