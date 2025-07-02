<?php
require_once '../config/database.php';

class DatabaseMigrations {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function runMigrations() {
        try {
            $this->createTables();
            $this->createDefaultAdmin();
            echo "Database migrations completed successfully!\n";
        } catch (Exception $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function createTables() {
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if ($schema === false) {
            throw new Exception("Could not read schema.sql file");
        }
        
        // Execute the schema
        $this->db->getConnection()->exec($schema);
        echo "Database schema created successfully!\n";
    }
    
    private function createDefaultAdmin() {
        // Check if admin user already exists
        $existingAdmin = $this->db->fetch("SELECT id FROM users WHERE username = 'admin'");
        
        if (!$existingAdmin) {
            $adminData = [
                'username' => 'admin',
                'email' => 'admin@testframework.com',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin'
            ];
            
            $this->db->insert('users', $adminData);
            echo "Default admin user created (username: admin, password: admin123)\n";
        } else {
            echo "Admin user already exists\n";
        }
    }
    
    public function checkConnection() {
        try {
            $result = $this->db->fetch("SELECT version()");
            echo "Database connection successful. PostgreSQL version: " . $result['version'] . "\n";
            return true;
        } catch (Exception $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migrations if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $migrations = new DatabaseMigrations();
    
    if ($migrations->checkConnection()) {
        $migrations->runMigrations();
    }
}
?>
