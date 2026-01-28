<?php
require_once 'app/Config/DBC.php';

class DB extends DBC
{
    private static $_instance = [0 => null];
    private $mysqli;
    private $db_name, $db_user, $db_pass;
    private $db_id; // Store DB Index for reconnection

    public function __construct($db = 0)
    {
        $this->db_id = $db; // Save index
        $this->db_name = DBC::dbm[$db]['db'];
        $this->db_user = DBC::dbm[$db]['user'];
        $this->db_pass = DBC::dbm[$db]['pass'];
        $this->mysqli = new mysqli(DBC::db_host, $this->db_user, $this->db_pass, $this->db_name) or die('DB Error');
    }

    /**
     * @param int|string $db
     * @return DB
     */
    public static function getInstance($db = 0)
    {
        if (!isset(self::$_instance[$db])) {
            self::$_instance[$db] = new DB($db);
        }

        return self::$_instance[$db];
    }
    
    // Helper to ensure connection is alive
    private function checkConnection() {
        if (!($this->mysqli instanceof mysqli) || $this->mysqli->connect_errno) {
             $this->__construct($this->db_id);
             return;
        }
        try {
            if (!$this->mysqli->ping()) {
                $this->__construct($this->db_id);
            }
        } catch (\Throwable $th) {
            $this->__construct($this->db_id);
        }
    }

    /**
     * Escape string for use in SQL
     * @param string|null $str
     * @return string
     */
    public function escape($str)
    {
        $this->checkConnection();
        if ($str === null) {
            return '';
        }
        return $this->mysqli->real_escape_string((string) $str);
    }

    public function get($table, $index = "", $group = 0)
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT * FROM $table";
        $result = $this->mysqli->query($query);

        if ($result) {
            $no = 0;
            while ($row = $result->fetch_assoc())
                if ($index == "") {
                    $reply[] = $row;
                } else {
                    if ($group == 0) {
                        $reply[$row[$index]] = $row;
                    } else {
                        $no += 1;
                        $reply[$row[$index]][$no] = $row;
                    }
                }
        }

        return $reply;
    }

    public function get_where($table, $where, $index = "", $group = 0)
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT * FROM $table WHERE $where";
        $result = $this->mysqli->query($query);

        if ($result) {
            $no = 0;
            while ($row = $result->fetch_assoc())
                if ($index == "") {
                    $reply[] = $row;
                } else {
                    if ($group == 0) {
                        $reply[$row[$index]] = $row;
                    } else {
                        $no += 1;
                        $reply[$row[$index]][$no] = $row;
                    }
                }
        }

        return $reply;
    }

    public function get_cols($table, $cols, $row = 1, $index = "")
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT $cols FROM $table";
        $result = $this->mysqli->query($query);
        if ($result) {
            switch ($row) {
                case "0":
                    $reply = $result->fetch_assoc();
                case "1";
                    while ($row = $result->fetch_assoc())
                        if ($index == "")
                            $reply[] = $row;
                        else
                            $reply[$row[$index]] = $row;
                    break;
            }

            if (is_array($reply)) {
                return $reply;
            } else {
                return [];
            }
        } else {
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        }
    }

    public function get_cols_where($table, $cols, $where, $row = 1, $index = "")
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT $cols FROM $table WHERE $where";
        $result = $this->mysqli->query($query);
        if ($result) {
            switch ($row) {
                case "0":
                    $reply = $result->fetch_assoc();
                case "1";
                    while ($row = $result->fetch_assoc())
                        if ($index == "")
                            $reply[] = $row;
                        else
                            $reply[$row[$index]] = $row;
                    break;
            }

            if (is_array($reply)) {
                return $reply;
            } else {
                return [];
            }
        } else {
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        }
    }

    public function get_cols_groubBy($table, $cols, $groupBy)
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT $cols FROM $table GROUP BY $groupBy";
        $result = $this->mysqli->query($query);

        while ($row = $result->fetch_assoc())
            $reply[] = $row;

        return $reply;
    }

    public function get_order($table, $order)
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT * FROM $table ORDER BY $order";
        $result = $this->mysqli->query($query);

        while ($row = $result->fetch_assoc())
            $reply[] = $row;

        return $reply;
    }


    public function get_where_order($table, $where, $order)
    {
        $this->checkConnection();
        $reply = [];
        $query = "SELECT * FROM $table WHERE $where ORDER BY $order";
        $result = $this->mysqli->query($query);

        while ($row = $result->fetch_assoc())
            $reply[] = $row;

        return $reply;
    }

    public function get_where_row($table, $where)
    {
        $this->checkConnection();
        $query = "SELECT * FROM $table WHERE $where";
        $result = $this->mysqli->query($query);
        
        // Check if query succeeded BEFORE calling fetch_assoc
        if ($result === false) {
            // Log error untuk debugging
            error_log("DB Error in get_where_row: " . $this->mysqli->error . " for query: " . $query);
            return [];
        }
        
        $reply = $result->fetch_assoc();
        if (is_array($reply)) {
            return $reply;
        } else {
            return [];
        }
    }

    public function insert($table, $data)
    {
        $this->checkConnection();
        $columns = implode(', ', array_keys($data));
        $escapedValues = array_map(function ($value) {
            if (is_string($value)) {
                return "'" . $this->mysqli->real_escape_string($value) . "'";
            } elseif (is_null($value)) {
                return 'NULL';
            }
            return $value;
        }, array_values($data));
        $valuesString = implode(', ', $escapedValues);

        $query = "INSERT INTO $table ($columns) VALUES ($valuesString)";
        try {
            $this->mysqli->query($query);
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        } catch (\Throwable $th) {
            return array('query' => $query, 'error' => $th->getMessage(), 'errno' => $this->mysqli->errno);
        }
    }

    public function insertIgnore($table, $data)
    {
        $this->checkConnection();
        $columns = implode(', ', array_keys($data));
        $escapedValues = array_map(function ($value) {
            if (is_string($value)) {
                return "'" . $this->mysqli->real_escape_string($value) . "'";
            } elseif (is_null($value)) {
                return 'NULL';
            }
            return $value;
        }, array_values($data));
        $valuesString = implode(', ', $escapedValues);

        $query = "INSERT IGNORE INTO $table ($columns) VALUES ($valuesString)";
        try {
            $this->mysqli->query($query);
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        } catch (\Throwable $th) {
            return array('query' => $query, 'error' => $th->getMessage(), 'errno' => $this->mysqli->errno);
        }
    }

    public function insertReplace($table, $data)
    {
        $this->checkConnection();
        $columns = implode(', ', array_keys($data));
        $escapedValues = array_map(function ($value) {
            if (is_string($value)) {
                return "'" . $this->mysqli->real_escape_string($value) . "'";
            } elseif (is_null($value)) {
                return 'NULL';
            }
            return $value;
        }, array_values($data));
        $valuesString = implode(', ', $escapedValues);

        $query = "REPLACE INTO $table ($columns) VALUES ($valuesString)";
        try {
            $this->mysqli->query($query);
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        } catch (\Throwable $th) {
            return array('query' => $query, 'error' => $th->getMessage(), 'errno' => $this->mysqli->errno);
        }
    }

    public function delete($table, $where)
    {
        $this->checkConnection();
        $query = "DELETE FROM $table WHERE $where";
        
        try {
            $this->mysqli->query($query);
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno);
        } catch (\Throwable $th) {
            return array('query' => $query, 'error' => $th->getMessage(), 'errno' => 9999);
        }
    }

    public function update($table, $set, $where)
    {
        $this->checkConnection();
        if (is_array($set)) {
            $setParts = [];
            foreach ($set as $key => $value) {
                if (is_null($value)) {
                    $setParts[] = "$key = NULL";
                } else {
                    $escapedValue = $this->mysqli->real_escape_string($value);
                    $setParts[] = "$key = '$escapedValue'";
                }
            }
            $set = implode(', ', $setParts);
        }

        $query = "UPDATE $table SET $set WHERE $where";
        try {
            $this->mysqli->query($query);
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno, 'db' => $this->db_name);
        } catch (\Throwable $th) {
            return array('query' => $query, 'error' => $th->getMessage(), 'errno' => $this->mysqli->errno, 'db' => $this->db_name);
        }
    }

    public function count($table)
    {
        $query = "SELECT COUNT(*) FROM $table";
        $result = $this->mysqli->query($query);

        $reply = $result->fetch_array();
        if ($reply) {
            return $reply[0];
        } else {
            return array('query' => $query, 'info' => $this->mysqli->error);
        }
    }

    public function count_where($table, $where)
    {
        $query = "SELECT COUNT(*) FROM $table WHERE $where";
        $result = $this->mysqli->query($query);

        $reply = $result->fetch_array();
        if ($reply) {
            return $reply[0];
        } else {
            return array('query' => $query, 'info' => $this->mysqli->error);
        }
    }

    public function count_distinct_where($table, $distinct, $where)
    {
        $query =  "SELECT COUNT(DISTINCT $distinct) as count FROM $table WHERE $where";
        $result = $this->mysqli->query($query);

        $reply = $result->fetch_array();
        if ($reply) {
            return $reply['count'];
        } else {
            return array('query' => $query, 'info' => $this->mysqli->error);
        }
    }

    public function query($query)
    {
        $this->checkConnection();
        try {
            $runQuery = $this->mysqli->query($query);
            if ($runQuery) {
                return TRUE;
            } else {
                // Log error for debugging
                error_log("DB Query Error: " . $this->mysqli->error . " | Query: " . $query);
                return FALSE;
            }
        } catch (\Throwable $th) {
            error_log("DB Query Exception: " . $th->getMessage() . " | Query: " . $query);
            return FALSE;
        }
    }

    // ===== TRANSACTION METHODS =====
    
    /**
     * Begin database transaction
     * @return bool Success status
     */
    public function beginTransaction()
    {
        $this->checkConnection();
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

    public function innerJoin1($table, $tb_join, $join_where)
    {
        $query = "SELECT * FROM $table INNER JOIN $tb_join ON $join_where";
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc())
                $reply[] = $row;
            return $reply;
        } else {
            return FALSE;
        }
    }

    public function innerJoin2($table, $tb_join1, $join_where1, $tb_join2, $join_where2)
    {
        $query = "SELECT * FROM $table INNER JOIN $tb_join1 ON $join_where1 INNER JOIN $tb_join2 ON $join_where2";
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc())
                $reply[] = $row;
            return $reply;
        } else {
            return FALSE;
        }
    }

    public function innerJoin2_where($table, $tb_join1, $join_where1, $tb_join2, $join_where2, $where)
    {
        $query = "SELECT * FROM $table INNER JOIN $tb_join1 ON $join_where1 INNER JOIN $tb_join2 ON $join_where2 WHERE $where";
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc())
                $reply[] = $row;
            return $reply;
        } else {
            return FALSE;
        }
    }

    public function innerJoin1_where($table, $tb_join, $join_where, $where)
    {
        $query = "SELECT * FROM $table INNER JOIN $tb_join ON $join_where WHERE $where";
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc())
                $reply[] = $row;
            return $reply;
        } else {
            return FALSE;
        }
    }
    public function innerJoin1_orderBy($table, $tb_join, $join_where, $orderBy)
    {
        $query = "SELECT * FROM $table INNER JOIN $tb_join ON $join_where ORDER BY $orderBy";
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc())
                $reply[] = $row;
            return $reply;
        } else {
            return FALSE;
        }
    }

    /**
     * Execute custom SQL query and return results as array
     * @param string $query SQL query string
     * @return array|false Array of results or FALSE on failure
     */
    public function query_array($query)
    {
        $this->checkConnection();
        $result = $this->mysqli->query($query);
        if ($result) {
            $reply = [];
            while ($row = $result->fetch_assoc()) {
                $reply[] = $row;
            }
            return $reply;
        } else {
            error_log("DB Query Error: " . $this->mysqli->error . " | Query: " . $query);
            return FALSE;
        }
    }

    //============================================

    public function sum_col_where($table, $col, $where)
    {
        $query = "SELECT SUM($col) as Total FROM $table WHERE $where";
        $result = $this->mysqli->query($query);

        // Check $result BEFORE calling fetch_assoc to prevent fatal error
        if ($result === false) {
            return 0;
        }
        
        $reply = $result->fetch_assoc();
        return $reply["Total"] ?? 0;
    }

    public function max($table, $col)
    {
        $query = "SELECT MAX($col) as max FROM $table";
        $result = $this->mysqli->query($query);

        $reply = $result->fetch_assoc();
        if ($result) {
            if ($reply["max"] == "") {
                return 0;
            } else {
                return $reply["max"];
            }
        } else {
            return array('query' => $query, 'error' => $this->mysqli->error, 'errno' => $this->mysqli->errno, 'db' => $this->db_name);
        }
    }
}
