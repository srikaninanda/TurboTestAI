<?php
require_once __DIR__ . '/../config/database.php';

function getUserProjects($userId) {
    $db = getDB();
    
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        // Admins can see all projects
        return $db->fetchAll("
            SELECT p.*, u.username as created_by_name,
                   COUNT(DISTINCT pm.user_id) as member_count
            FROM projects p 
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN project_members pm ON p.id = pm.project_id
            GROUP BY p.id, u.username
            ORDER BY p.created_at DESC
        ");
    } else {
        // Regular users see only their projects
        return $db->fetchAll("
            SELECT DISTINCT p.*, u.username as created_by_name,
                   COUNT(DISTINCT pm.user_id) as member_count
            FROM projects p 
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN project_members pm ON p.id = pm.project_id
            WHERE p.created_by = ? OR p.id IN (
                SELECT project_id FROM project_members WHERE user_id = ?
            )
            GROUP BY p.id, u.username
            ORDER BY p.created_at DESC
        ", [$userId, $userId]);
    }
}

function getProjectStats($projectId) {
    $db = getDB();
    
    $stats = [
        'requirements' => 0,
        'test_cases' => 0,
        'test_runs' => 0,
        'open_bugs' => 0,
        'total_bugs' => 0
    ];
    
    // Get requirements count
    $result = $db->fetch("SELECT COUNT(*) as count FROM requirements WHERE project_id = ?", [$projectId]);
    $stats['requirements'] = $result['count'];
    
    // Get test cases count
    $result = $db->fetch("SELECT COUNT(*) as count FROM test_cases WHERE project_id = ?", [$projectId]);
    $stats['test_cases'] = $result['count'];
    
    // Get test runs count
    $result = $db->fetch("SELECT COUNT(*) as count FROM test_runs WHERE project_id = ?", [$projectId]);
    $stats['test_runs'] = $result['count'];
    
    // Get bugs count
    $result = $db->fetch("SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as open
                         FROM bugs WHERE project_id = ?", [$projectId]);
    $stats['total_bugs'] = $result['total'];
    $stats['open_bugs'] = $result['open'];
    
    return $stats;
}

function getAllStats() {
    $db = getDB();
    $user = getCurrentUser();
    
    $stats = [
        'requirements' => 0,
        'test_cases' => 0,
        'test_runs' => 0,
        'open_bugs' => 0,
        'total_bugs' => 0
    ];
    
    if ($user['role'] === 'admin') {
        // Admin can see all stats
        $result = $db->fetch("SELECT COUNT(*) as count FROM requirements");
        $stats['requirements'] = $result['count'];
        
        $result = $db->fetch("SELECT COUNT(*) as count FROM test_cases");
        $stats['test_cases'] = $result['count'];
        
        $result = $db->fetch("SELECT COUNT(*) as count FROM test_runs");
        $stats['test_runs'] = $result['count'];
        
        $result = $db->fetch("SELECT COUNT(*) as total, 
                             SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as open
                             FROM bugs");
        $stats['total_bugs'] = $result['total'];
        $stats['open_bugs'] = $result['open'];
    } else {
        // Regular users see only their project stats
        $projectIds = array_column(getUserProjects($user['id']), 'id');
        
        if (!empty($projectIds)) {
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            
            $result = $db->fetch("SELECT COUNT(*) as count FROM requirements WHERE project_id IN ($placeholders)", $projectIds);
            $stats['requirements'] = $result['count'];
            
            $result = $db->fetch("SELECT COUNT(*) as count FROM test_cases WHERE project_id IN ($placeholders)", $projectIds);
            $stats['test_cases'] = $result['count'];
            
            $result = $db->fetch("SELECT COUNT(*) as count FROM test_runs WHERE project_id IN ($placeholders)", $projectIds);
            $stats['test_runs'] = $result['count'];
            
            $result = $db->fetch("SELECT COUNT(*) as total, 
                                 SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as open
                                 FROM bugs WHERE project_id IN ($placeholders)", $projectIds);
            $stats['total_bugs'] = $result['total'];
            $stats['open_bugs'] = $result['open'];
        }
    }
    
    return $stats;
}

function getRecentActivity($limit = 10) {
    $db = getDB();
    $user = getCurrentUser();
    
    if ($user['role'] === 'admin') {
        // Admin can see all activity
        return $db->fetchAll("
            SELECT al.*, u.username 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT ?
        ", [$limit]);
    } else {
        // Regular users see only their project activity
        $projectIds = array_column(getUserProjects($user['id']), 'id');
        
        if (empty($projectIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $params = array_merge($projectIds, [$limit]);
        
        return $db->fetchAll("
            SELECT al.*, u.username 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.project_id IN ($placeholders) OR al.user_id = ?
            ORDER BY al.created_at DESC 
            LIMIT ?
        ", array_merge($projectIds, [$user['id'], $limit]));
    }
}

function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M j, Y H:i', strtotime($date));
}

function formatStatus($status) {
    $statusMap = [
        'active' => 'success',
        'inactive' => 'secondary',
        'completed' => 'primary',
        'open' => 'danger',
        'in_progress' => 'warning',
        'resolved' => 'success',
        'closed' => 'secondary',
        'passed' => 'success',
        'failed' => 'danger',
        'blocked' => 'warning',
        'not_run' => 'secondary',
        'draft' => 'secondary',
        'review' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    
    $class = $statusMap[$status] ?? 'secondary';
    return "<span class='badge bg-$class'>" . ucwords(str_replace('_', ' ', $status)) . "</span>";
}

function formatPriority($priority) {
    $priorityMap = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    
    $class = $priorityMap[$priority] ?? 'secondary';
    return "<span class='badge bg-$class'>" . ucfirst($priority) . "</span>";
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write header
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function exportToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT);
}
?>
