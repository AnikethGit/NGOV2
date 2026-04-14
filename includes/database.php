<?php
/**
 * Database singleton — PDO wrapper
 * Methods: fetch(), fetchAll(), query(), insert(), update()
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $cfg = Config::db();

        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']} COLLATE utf8mb4_unicode_ci",
        ];

        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Fetch a single row
    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Fetch all rows
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Execute (INSERT / UPDATE / DELETE)
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Convenience: INSERT with associative array → returns last insert ID
    public function insert(string $table, array $data): int {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $stmt   = $this->pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$places})");
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    // Convenience: UPDATE — $where is a SQL fragment e.g. 'id = ?', $whereParams are bound separately
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$set} WHERE {$where}");
        $stmt->execute(array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    // Return raw PDO for transactions etc.
    public function getPdo(): PDO {
        return $this->pdo;
    }
}
