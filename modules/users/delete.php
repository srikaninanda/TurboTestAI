<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('admin');

$userId = $_GET['id'] ?? 0;
$currentUser = getCurrentUser();

// Prevent deleting self
if ($userId == $currentUser['id']) {
    header('Location: index.php?error=cannot_delete_self');
    exit();
}

$db = getDB();

// Check if user exists
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    header('Location: index.php?error=user_not_found');
    exit();
}

try {
    // Delete user
    $db->delete('users', 'id = ?', [$userId]);
    
    logActivity($currentUser['id'], null, 'user', $userId, 'delete', 'User deleted: ' . $user['username']);
    
    header('Location: index.php?success=user_deleted');
} catch (Exception $e) {
    error_log("Failed to delete user: " . $e->getMessage());
    header('Location: index.php?error=delete_failed');
}

exit();
?>
