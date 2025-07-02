<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Get database configuration from environment variables
            $host = '127.0.0.1'; //$_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST') ?? 'localhost';
            $port = '3306'; //$_ENV['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?? '3306';
            $dbname = 'turbotest'; //$_ENV['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?? 'turbotest';
            $username = 'root'; //$_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER') ?? 'root';
            $password = 'root'; //$_ENV['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD') ?? 'root';

            // Alternative: Use DATABASE_URL if available
            $database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

            if ($database_url) {
                        $url = parse_url($database_url);
                        $host = 'localhost';//$url['host'] ?? 'localhost';
                        $port = '3306';$url['port'] ?? '3306';
                        $dbname = 'turbotest';//isset($url['path']) ? ltrim($url['path'], '/') : 'turbotest';
                        $username = 'root'; //$url['user'] ?? 'root';
                        $password = 'root'; //$url['pass'] ?? 'root';
                        }

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            //$this->connection  = mysqli_connect($host, $username, $password,$dbname,$port);



        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            error_log("DSN used: " . $dsn);
            error_log("Username: " . $username);
            throw new Exception("Database connection failed: " . $e->getMessage());
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
    // Use mysqli for queries
    $stmt = $this->connection->prepare($sql);
    if ($stmt === false) {
        error_log("Database query preparation failed: " . implode(" ", $this->connection->errorInfo()));
        throw new Exception("Database query preparation failed: " . implode(" ", $this->connection->errorInfo()));
    }

    if (!empty($params)) {
        if (!$stmt->execute($params)) {
            error_log("Database query execution failed: " . implode(" ", $stmt->errorInfo()));
            throw new Exception("Database query execution failed: " . implode(" ", $stmt->errorInfo()));
        }
    } else {
        if (!$stmt->execute()) {
            error_log("Database query execution failed: " . implode(" ", $stmt->errorInfo()));
            throw new Exception("Database query execution failed: " . implode(" ", $stmt->errorInfo()));
        }
    }

    return $stmt;
    if ($stmt === false) {
        error_log("Database query preparation failed: " . mysqli_error($this->connection));
        throw new Exception("Database query preparation failed: " . mysqli_error($this->connection));
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assuming all parameters are strings for simplicity
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Database query execution failed: " . mysqli_stmt_error($stmt));
        throw new Exception("Database query execution failed: " . mysqli_stmt_error($stmt));
    }

    return $stmt;
    }
    
    public function fetch($sql, $params = []) {
    $stmt = $this->query($sql, $params);
    $result = $stmt->fetch();
    if ($result === false) {
        error_log("Failed to get result set: " . mysqli_stmt_error($stmt));
        return false;
    }
    $row = $result;
    return $row;
    }
    
    public function fetchAll($sql, $params = []) {
    $stmt = $this->query($sql, $params);
    $rows = $stmt->fetchAll();
    return $rows;
    }
    
    public function insert($table, $data) {
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $this->connection->prepare($sql);
    if ($stmt === false) {
        error_log("Insert statement preparation failed: " . implode(" ", $this->connection->errorInfo()));
        throw new Exception("Insert statement preparation failed: " . implode(" ", $this->connection->errorInfo()));
    }
    if (!$stmt->execute($data)) {
        error_log("Insert statement execution failed: " . implode(" ", $stmt->errorInfo()));
        throw new Exception("Insert statement execution failed: " . implode(" ", $stmt->errorInfo()));
    }
    return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
    // Construct parameters for mysqli_stmt_bind_param
    $params = array_values($data);
    $whereValues = array_values($whereParams);
    $params = array_merge($params, $whereValues);

    $types = str_repeat('s', count($data)) . str_repeat('s', count($whereValues));

    $stmt = mysqli_prepare($this->connection, $sql);
    if ($stmt === false) {
        error_log("Update statement preparation failed: " . mysqli_error($this->connection));
        throw new Exception("Update statement preparation failed: " . mysqli_error($this->connection));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Update statement execution failed: " . mysqli_stmt_error($stmt));
        throw new Exception("Update statement execution failed: " . mysqli_stmt_error($stmt));
    }
    return mysqli_stmt_affected_rows($stmt);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
    $stmt = mysqli_prepare($this->connection, $sql);
    if ($stmt === false) {
        error_log("Delete statement preparation failed: " . mysqli_error($this->connection));
        throw new Exception("Delete statement preparation failed: " . mysqli_error($this->connection));
    }
    return $this->query($sql, $params); // Re-using query method, which now handles mysqli
    }
}

// Global database instance
function getDB() {
    return Database::getInstance();
}
?>
