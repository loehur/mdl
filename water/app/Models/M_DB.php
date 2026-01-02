<?php

class M_DB
{
    private $db;

    public function __construct($db = 0)
    {
        $this->db[$db] = DB::getInstance($db);
        $this->db = $this->db[$db];
    }

    public function query($query)
    {
        return $this->db->query($query);
    }

    //GET
    public function get($table, $index = "", $group = 0)
    {
        return $this->db->get($table, $index, $group);
    }

    public function get_where($table, $where, $index = "", $group = 0)
    {
        return $this->db->get_where($table, $where, $index, $group);
    }

    public function get_cols($table, $cols, $row = 1, $index = "")
    {
        return $this->db->get_cols($table, $cols, $row, $index);
    }

    public function get_cols_where($table, $cols, $where, $row = 1, $index = "")
    {
        return $this->db->get_cols_where($table, $cols, $where, $row, $index);
    }

    public function get_cols_groubBy($table, $cols, $groupBy)
    {
        return $this->db->get_cols_groubBy($table, $cols, $groupBy);
    }

    public function get_where_order($table, $where, $order)
    {
        return $this->db->get_where_order($table, $where, $order);
    }

    public function get_order($table, $order)
    {
        return $this->db->get_order($table, $order);
    }

    public function get_where_row($table, $where)
    {
        return $this->db->get_where_row($table, $where);
    }

    //====================================================== COUNT//

    public function count($table)
    {
        return $this->db->count($table);
    }

    public function count_where($table, $where)
    {
        return $this->db->count_where($table, $where);
    }
    public function count_distinct_where($table, $distinct, $where)
    {
        return $this->db->count_distinct_where($table, $distinct, $where);
    }

    //===========================================================

    public function insert($table, $values, $update = "")
    {
        return $this->db->insert($table, $values, $update);
    }
    public function insertCols($table, $columns, $values = "", $update = "")
    {
        return $this->db->insertCols($table, $columns, $values, $update);
    }

    public function delete_where($table, $where)
    {
        return $this->db->delete_where($table, $where);
    }

    //======================================================
    public function update($table, $set, $where)
    {
        return $this->db->update($table, $set, $where);
    }

    //======================================================
    public function innerJoin1($table, $tb_join, $join_where)
    {
        return $this->db->innerJoin1($table, $tb_join, $join_where);
    }
    public function innerJoin1_where($table, $tb_join, $join_where, $where)
    {
        return $this->db->innerJoin1_where($table, $tb_join, $join_where, $where);
    }
    public function innerJoin1_orderBy($table, $tb_join, $join_where, $orderBy)
    {
        return $this->db->innerJoin1_orderBy($table, $tb_join, $join_where, $orderBy);
    }
    public function innerJoin2($table, $tb_join1, $join_where1, $tb_join2, $join_where2)
    {
        return $this->db->innerJoin2($table, $tb_join1, $join_where1, $tb_join2, $join_where2);
    }
    public function innerJoin2_where($table, $tb_join1, $join_where1, $tb_join2, $join_where2, $where)
    {
        return $this->db->innerJoin2_where($table, $tb_join1, $join_where1, $tb_join2, $join_where2, $where);
    }
    public function sum_col_where($table, $col, $where)
    {
        return $this->db->sum_col_where($table, $col, $where);
    }
}
