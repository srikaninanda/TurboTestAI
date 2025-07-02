<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAuth();

$bugId = $_GET['id'] ?? 0;
$db = getDB();
$user = getCurrentUser();

// Get bug and check access
$bug = $db->fetch("SELECT * FROM bugs WHERE id = ?", [$bugId]);
if (!$bug || !hasProjectAccess($bug['project_id'])) {
    header('Location: index.php?error=access_denied');
    exit();
}

try {
    // Delete bug (cascading deletes will handle comments)
    $db->delete('bugs', 'id = ?', [$bugId]);
    
    logActivity($user['id'], $bug['project_id'], 'bug', $bugId, 'delete', 'Bug deleted: ' . $bug['title']);
    
    header('Location: index.php?success=bug_deleted&project_id=' . $bug['project_id']);
} catch (Exception $e) {
    error_log("Failed to delete bug: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
