<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Get database configuration from environment variables
            $host = $_ENV['PGHOST'] ?? getenv('PGHOST') ?? 'localhost';
            $port = $_ENV['PGPORT'] ?? getenv('PGPORT') ?? '5432';
            $dbname = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE') ?? 'test_management';
            $username = $_ENV['PGUSER'] ?? getenv('PGUSER') ?? 'postgres';
            $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD') ?? '';
            
            // Alternative: Use DATABASE_URL if available
            $database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
            
            if ($database_url) {
                $url = parse_url($database_url);
                $host = $url['host'];
                $port = $url['port'] ?? 5432;
                $dbname = ltrim($url['path'], '/');
                $username = $url['user'];
                $password = $url['pass'] ?? '';
            }
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders) RETURNING id";
        
        $stmt = $this->query($sql, $data);
        $result = $stmt->fetch();
        return $result['id'];
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
}

// Global database instance
function getDB() {
    return Database::getInstance();
}
?>
