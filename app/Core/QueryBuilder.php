<?php

namespace Core;

use Core\DB;

class QueryBuilder
{
    protected $pdo;
    protected $table;

    protected $columns = ["*"];
    protected $wheres = [];
    protected $params = [];

    protected $order = "";
    protected $limit = "";
    protected $offset = "";

    // -------------------------
    // META KEY (IMPORTANT)
    // -------------------------
    protected $metaKeyLocked = null;

    public function __construct($table)
    {
        $this->pdo = DB::conn();
        $this->table = $this->sanitizeTable($table);
    }

    // -------------------------
    // SECURITY
    // -------------------------
    private function sanitizeTable($table)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    }

    // -------------------------
    // META KEY ENGINE (NEW)
    // -------------------------
    public function metaKey($value)
    {
        $this->metaKeyLocked = $value;
        return $this;
    }

    // -------------------------
    // SELECT
    // -------------------------
    public function select(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    // -------------------------
    // JSON SUPPORT (dot notation)
    // meta_value.profile.name
    // -------------------------
    private function parseColumn($column)
    {
        if (str_contains($column, ".")) {
            [$col, $json] = explode(".", $column, 2);

            return "JSON_UNQUOTE(JSON_EXTRACT($col, '$.$json'))";
        }

        return $column;
    }

    // -------------------------
    // WHERE CORE
    // -------------------------
    private function addWhere($type, $column, $operator, $value)
    {
        $column = $this->parseColumn($column);

        $param = "p" . count($this->params);

        $this->wheres[] = [
            "type" => $type,
            "sql"  => "$column $operator :$param"
        ];

        $this->params[$param] = $value;

        return $this;
    }

    public function where($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = "=";
        }

        return $this->addWhere("AND", $column, $operator, $value);
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = "=";
        }

        return $this->addWhere("OR", $column, $operator, $value);
    }

    public function whereLike($column, $value)
    {
        return $this->addWhere("AND", $column, "LIKE", "%$value%");
    }

    public function whereIn($column, array $values)
    {
        $column = $this->parseColumn($column);

        $placeholders = [];

        foreach ($values as $v) {
            $param = "p" . count($this->params);
            $placeholders[] = ":$param";
            $this->params[$param] = $v;
        }

        $this->wheres[] = [
            "type" => "AND",
            "sql" => "$column IN (" . implode(",", $placeholders) . ")"
        ];

        return $this;
    }

    public function whereBetween($column, $a, $b)
    {
        $column = $this->parseColumn($column);

        $p1 = "p" . count($this->params);
        $this->params[$p1] = $a;

        $p2 = "p" . count($this->params);
        $this->params[$p2] = $b;

        $this->wheres[] = [
            "type" => "AND",
            "sql" => "$column BETWEEN :$p1 AND :$p2"
        ];

        return $this;
    }

    // -------------------------
    // ORDER / LIMIT / OFFSET
    // -------------------------
    public function orderBy($column, $dir = "ASC")
    {
        $this->order = "ORDER BY $column $dir";
        return $this;
    }

    public function limit($n)
    {
        $this->limit = "LIMIT " . (int)$n;
        return $this;
    }

    public function offset($n)
    {
        $this->offset = "OFFSET " . (int)$n;
        return $this;
    }

    // -------------------------
    // WHERE BUILDER (FIXED BUG)
    // -------------------------
    private function buildWhere()
    {
        if (empty($this->wheres)) return "";

        $sql = "WHERE ";

        foreach ($this->wheres as $i => $w) {

            if ($i === 0) {
                $sql .= $w["sql"];
            } else {
                $sql .= " {$w["type"]} {$w["sql"]}";
            }
        }

        return $sql;
    }

    // -------------------------
    // GET (OPTIMIZED meta_key FIRST)
    // -------------------------
    public function get()
    {
        // PERFORMANCE RULE: meta_key sempre primeiro no SQL
        if ($this->metaKeyLocked) {
            $this->wheres = array_values(array_filter($this->wheres, function ($w) {
                return !str_contains($w["sql"], "meta_key");
            }));

            array_unshift($this->wheres, [
                "type" => "AND",
                "sql" => "meta_key = :metaKeyForced"
            ]);

            $this->params["metaKeyForced"] = $this->metaKeyLocked;
        }

        $sql = "SELECT " . implode(",", $this->columns) . " FROM {$this->table} ";
        $sql .= $this->buildWhere() . " ";
        $sql .= $this->order . " ";
        $sql .= $this->limit . " ";
        $sql .= $this->offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            foreach ($row as $k => $v) {
                if ($this->isJson($v)) {
                    $row[$k] = json_decode($v, true);
                }
            }
        }

        $this->reset();

        return $data;
    }

    public function first()
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    // -------------------------
    // RESET
    // -------------------------
    private function reset()
    {
        $this->columns = ["*"];
        $this->wheres = [];
        $this->params = [];
        $this->order = "";
        $this->limit = "";
        $this->offset = "";
        $this->metaKeyLocked = null;
    }

    // -------------------------
    // JSON CHECK
    // -------------------------
    private function isJson($string)
    {
        if (!is_string($string)) return false;
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function notRemoved()
    {
        return $this->where("meta_key", "NOT LIKE", "%_removedby_%");
    }

    public function onlyRemoved()
    {
        return $this->where("meta_key", "LIKE", "%_removedby_%");
    }
}
