<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$requirementId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get requirement and check access
$requirement = $db->fetch("SELECT * FROM requirements WHERE id = ?", [$requirementId]);
if (!$requirement || !hasProjectAccess($requirement['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

try {
    // Delete requirement
    $db->delete('requirements', 'id = ?', [$requirementId]);
    
    logActivity($user['id'], $requirement['project_id'], 'requirement', $requirementId, 'delete', 'Requirement deleted: ' . $requirement['title']);
    
    header('Location: index.php?success=requirement_deleted&project_id=' . $requirement['project_id']);
} catch (Exception $e) {
    error_log("Failed to delete requirement: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
