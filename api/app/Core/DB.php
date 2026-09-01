<?php
namespace App\Core;

require_once __DIR__ . '/../Config/DBC.php';

class DB extends \DBC
{
    private static $_instance = [];
    private $mysqli;
    private $query_result;
    private $last_affected_rows = 0;

    // Query Builder Props
    private $qb_table = "";
    private $qb_where = [];
    private $qb_params = [];
    private $qb_types = ""; // s, i, d, b

    public function __construct($db = 0)
    {
        // Simple singleton logic for connections could be here or handled by getInstance
        // Select DB config based on environment
        $config = \DBC::getDbConfig((int) $db);
        
        $db_name = $config['db'];
        $db_user = $config['user'];
        $db_pass = $config['pass'];

        $this->mysqli = new \mysqli(parent::db_host, $db_user, $db_pass, $db_name);

        if ($this->mysqli->connect_error) {
            throw new \Exception('Database connection failed: (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset('utf8mb4');
        $this->mysqli->query("SET collation_connection = 'utf8mb4_unicode_ci'");
    }

    public static function getInstance($db = 0)
    {
        if (!isset(self::$_instance[$db])) {
            self::$_instance[$db] = new DB($db);
        }
        return self::$_instance[$db];
    }

    public function affected_rows()
    {
        return $this->last_affected_rows;
    }

    /**
     * Raw Query with Optional Params (Prepared Statement)
     * Usage: $this->db()->query("SELECT * FROM table WHERE id = ?", [1]);
     */
    public function query($sql, $params = [])
    {
        $this->reset_qb(); // Clear builder state on raw query

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->mysqli->error);
        }

        if (!empty($params)) {
            $types = "";
            foreach ($params as $param) {
                if (is_int($param)) $types .= "i";
                elseif (is_float($param)) $types .= "d";
                else $types .= "s";
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new \Exception("Execute failed: " . $stmt->error);
        }

        // Keep the result of the statement that just ran. Several atomic
        // guards (including WaDesk daily limits) rely on affected_rows() to
        // tell whether a conditional UPDATE actually reserved a slot.
        $this->last_affected_rows = (int) $stmt->affected_rows;
        $this->query_result = $stmt->get_result();
        // If query was INSERT/UPDATE/DELETE, get_result returns false, which is fine
        // We can capture affected_rows or insert_id here if needed but for now we focus on SELECT compatibility

        return $this; // Return self for chaining result methods
    }
    
    // --- Query Builder Methods ---

    /**
     * Builder: Where
     */
    public function where($key, $value = null)
    {
        // Handle array
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->where($k, $v);
            }
            return $this;
        }

        list($column, $operator) = $this->parseWhereKey($key);

        $this->qb_where[] = "$column $operator ?";
        $this->qb_params[] = $value;

        if (is_int($value)) $this->qb_types .= "i";
        elseif (is_float($value)) $this->qb_types .= "d";
        else $this->qb_types .= "s";

        return $this;
    }

    /**
     * Builder: Get
     */
    public function get($table = null, $limit = null, $offset = null)
    {
        if ($table) {
            $this->qb_table = $table;
        }

        $sql = "SELECT * FROM " . $this->qb_table;

        if (!empty($this->qb_where)) {
            $sql .= " WHERE " . implode(" AND ", $this->qb_where);
        }

        if ($limit) {
            $sql .= " LIMIT ?";
            $this->qb_params[] = $limit;
            $this->qb_types .= "i";
        }

        if ($offset) {
            $sql .= " OFFSET ?";
            $this->qb_params[] = $offset;
            $this->qb_types .= "i";
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) throw new \Exception("DB Error: " . $this->mysqli->error);

        if (!empty($this->qb_params)) {
             $stmt->bind_param($this->qb_types, ...$this->qb_params);
        }

        $stmt->execute();
        $this->query_result = $stmt->get_result();

        // Clear builder state but keep result
        $this->qb_table = "";
        $this->qb_where = [];
        $this->qb_params = [];
        $this->qb_types = "";

        return $this;
    }

    /**
     * Get Where
     * Usage: get_where('table', ['id' => 1])
     */
    public function get_where($table, $where = [], $limit = null)
    {
        $this->reset_qb();
        $this->qb_table = $table;

        $sql = "SELECT * FROM " . $table;
        $params = [];
        $types = "";

        if (!empty($where)) {
            $clauses = [];
            foreach ($where as $key => $val) {
                // Parse key to extract column and operator
                list($column, $operator) = $this->parseWhereKey($key);

                $clauses[] = "$column $operator ?";
                $params[] = $val;

                if (is_int($val)) $types .= "i";
                elseif (is_float($val)) $types .= "d";
                else $types .= "s";
            }
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= "i";
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) throw new \Exception("DB Error: " . $this->mysqli->error);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $this->query_result = $stmt->get_result();

        return $this;
    }

    /**
     * Insert — returns insert_id or false on failure (never throws)
     * Usage: insert('table', ['col' => 'val'])
     */
    public function insert($table, $data)
    {
        return $this->_doInsert('INSERT', $table, $data);
    }

    /**
     * INSERT IGNORE — silently skips duplicate-key violations
     */
    public function insertIgnore($table, $data)
    {
        return $this->_doInsert('INSERT IGNORE', $table, $data);
    }

    private function _doInsert($verb, $table, $data)
    {
        // $data must be associative array
        $cols = array_keys($data);
        $vals = array_values($data);

        $placeholders = array_fill(0, count($cols), "?");

        $sql = "$verb INTO $table (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $placeholders) . ")";

        $types = "";
        foreach ($vals as $val) {
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$vals);

        if ($stmt->execute()) {
            return $this->mysqli->insert_id;
        } else {
            return false;
        }
    }

    /**
     * Update
     * Usage: update('table', ['col' => 'newval'], ['id' => 1])
     */
    public function update($table, $data, $where)
    {
        $set_clauses = [];
        $params = [];
        $types = "";

        foreach ($data as $key => $val) {
            $set_clauses[] = "$key = ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        // Where clauses
        $where_clauses = [];
        foreach ($where as $key => $val) {
            // Parse key to extract column and operator
            list($column, $operator) = $this->parseWhereKey($key);
            
            $where_clauses[] = "$column $operator ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        $sql = "UPDATE $table SET " . implode(", ", $set_clauses) . " WHERE " . implode(" AND ", $where_clauses);

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);

        $status = $stmt->execute();
        $this->last_affected_rows = $stmt->affected_rows;
        return $status;
    }

    /**
     * Update with Limit
     * Usage: update_limit('table', ['col' => 'newval'], ['id' => 1], 5)
     */
    public function update_limit($table, $data, $where, $limit = 1)
    {
        $set_clauses = [];
        $params = [];
        $types = "";

        foreach ($data as $key => $val) {
            $set_clauses[] = "$key = ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        // Where clauses
        $where_clauses = [];
        foreach ($where as $key => $val) {
            // Parse key to extract column and operator
            list($column, $operator) = $this->parseWhereKey($key);
            
            $where_clauses[] = "$column $operator ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        $sql = "UPDATE $table SET " . implode(", ", $set_clauses) . " WHERE " . implode(" AND ", $where_clauses) . " LIMIT ?";

        // Add limit to params
        $params[] = $limit;
        $types .= "i";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);

        $status = $stmt->execute();
        $this->last_affected_rows = $stmt->affected_rows;
        return $status;
    }

    /**
     * Delete
     * Usage: delete('table', ['id' => 1])
     */
    public function delete($table, $where)
    {
        $where_clauses = [];
        $params = [];
        $types = "";

        foreach ($where as $key => $val) {
            // Parse key to extract column and operator
            list($column, $operator) = $this->parseWhereKey($key);
            
            $where_clauses[] = "$column $operator ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        $sql = "DELETE FROM $table WHERE " . implode(" AND ", $where_clauses);

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);

        $status = $stmt->execute();
        $this->last_affected_rows = $stmt->affected_rows;
        return $status;
    }

    /**
     * Delete with Limit
     * Usage: delete_limit('table', ['id' => 1], 5)
     */
    public function delete_limit($table, $where, $limit = 1)
    {
        $where_clauses = [];
        $params = [];
        $types = "";

        foreach ($where as $key => $val) {
            // Parse key to extract column and operator
            list($column, $operator) = $this->parseWhereKey($key);
            
            $where_clauses[] = "$column $operator ?";
            $params[] = $val;
            if (is_int($val)) $types .= "i";
            elseif (is_float($val)) $types .= "d";
            else $types .= "s";
        }

        $sql = "DELETE FROM $table WHERE " . implode(" AND ", $where_clauses) . " LIMIT ?";

        // Add limit to params
        $params[] = $limit;
        $types .= "i";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);

        $status = $stmt->execute();
        $this->last_affected_rows = $stmt->affected_rows;
        return $status;
    }

    // --- Result Methods (Fluent) ---

    public function row()
    {
        if ($this->query_result) {
            return $this->query_result->fetch_object();
        }
        return null;
    }

    public function row_array()
    {
        if ($this->query_result) {
            return $this->query_result->fetch_assoc();
        }
        return null;
    }

    public function result()
    {
        $rows = [];
        if ($this->query_result) {
            while ($row = $this->query_result->fetch_object()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function result_array()
    {
        $rows = [];
        if ($this->query_result) {
            while ($row = $this->query_result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function num_rows()
    {
        if ($this->query_result) {
            return $this->query_result->num_rows;
        }
        return 0;
    }

    // Internal Helper
    private function reset_qb()
    {
        $this->qb_table = "";
        $this->qb_where = [];
        $this->qb_params = [];
        $this->qb_types = "";
        $this->query_result = null;
    }

    /**
     * Parse WHERE key to extract column name and operator
     * Supports: "column", "column !=", "column >", "column <", "column >=", "column <=", "column LIKE"
     * Usage: parseWhereKey("state !=") returns ["state", "!="]
     *        parseWhereKey("id") returns ["id", "="]
     */
    private function parseWhereKey($key)
    {
        $key = trim($key);
        $supported_operators = ['!=', '>=', '<=', '>', '<', 'LIKE', 'like'];
        
        foreach ($supported_operators as $op) {
            // Check if key ends with operator
            if (substr($key, -strlen($op)) === $op) {
                $column = trim(substr($key, 0, -strlen($op)));
                return [$column, strtoupper($op)];
            }
        }
        
        // Default operator is =
        return [$key, '='];
    }

    // Helper to get raw connection if needed
    public function conn()
    {
        return $this->mysqli;
    }

    // ===== TRANSACTION METHODS =====
    
    /**
     * Begin database transaction
     * @return bool Success status
     */
    public function beginTransaction()
    {
        try {
            $result = $this->mysqli->begin_transaction();
            if (!$result) {
                error_log("DB Transaction Error: Failed to begin transaction - " . $this->mysqli->error);
            }
            return $result;
        } catch (\Throwable $th) {
            error_log("DB Transaction Exception: " . $th->getMessage());
            return false;
        }
    }

    /**
     * Commit database transaction
     * @return bool Success status
     */
    public function commit()
    {
        try {
            $result = $this->mysqli->commit();
            if (!$result) {
                error_log("DB Commit Error: Failed to commit transaction - " . $this->mysqli->error);
            }
            return $result;
        } catch (\Throwable $th) {
            error_log("DB Commit Exception: " . $th->getMessage());
            return false;
        }
    }

    /**
     * Rollback database transaction
     * @return bool Success status
     */
    public function rollback()
    {
        try {
            $result = $this->mysqli->rollback();
            if (!$result) {
                error_log("DB Rollback Error: Failed to rollback transaction - " . $this->mysqli->error);
            }
            return $result;
        } catch (\Throwable $th) {
            error_log("DB Rollback Exception: " . $th->getMessage());
            return false;
        }
    }

    // ===== END TRANSACTION METHODS =====
}
