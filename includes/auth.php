<?php
require_once __DIR__ . '/../config/database.php';

function logActivity($userId, $projectId, $entityType, $entityId, $action, $description) {
    try {
        $db = getDB();
        $activityData = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description
        ];
        
        $db->insert('activity_log', $activityData);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    return $db->fetch("SELECT id, username, email, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function login($username, $password) {
    $db = getDB();
    
    $user = $db->fetch("SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?", 
                       [$username, $username]);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Log activity
        logActivity($user['id'], null, 'user', $user['id'], 'login', 'User logged in');
        
        return true;
    }
    
    return false;
}

function logout() {
    $userId = $_SESSION['user_id'] ?? null;
    
    if ($userId) {
        // Log activity
        logActivity($userId, null, 'user', $userId, 'logout', 'User logged out');
    }
    
    session_destroy();
}

function register($username, $email, $password, $role = 'user') {
    $db = getDB();
    
    // Check if user already exists
    $existing = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existing) {
        return false;
    }
    
    $userData = [
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role
    ];
    
    try {
        $userId = $db->insert('users', $userData);
        
        // Log activity
        logActivity($userId, null, 'user', $userId, 'register', 'New user registered');
        
        return $userId;
    } catch (Exception $e) {
        error_log("Registration failed: " . $e->getMessage());
        return false;
    }
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

function requireRole($requiredRole) {
    requireAuth();
    
    $user = getCurrentUser();
    $roles = ['user' => 1, 'manager' => 2, 'admin' => 3];
    
    if ($roles[$user['role']] < $roles[$requiredRole]) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

function hasProjectAccess($projectId, $userId = null) {
    if ($userId === null) {
        $user = getCurrentUser();
        $userId = $user['id'];
        
        // Admins have access to all projects
        if ($user['role'] === 'admin') {
            return true;
        }
    }
    
    $db = getDB();
    
    // Check if user is project creator or member
    $access = $db->fetch("
        SELECT 1 FROM projects p 
        LEFT JOIN project_members pm ON p.id = pm.project_id 
        WHERE p.id = ? AND (p.created_by = ? OR pm.user_id = ?)
    ", [$projectId, $userId, $userId]);
    
    return (bool)$access;
}
?>
