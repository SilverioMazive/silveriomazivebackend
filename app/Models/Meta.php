<?php

namespace Models;

use Core\QueryBuilder;
use Core\DB;

class Meta
{
    public static function query(string $table)
    {
        return new QueryBuilder($table);
    }

    public static function find(string $table, $id)
    {
        return self::query($table)
            ->where("id", $id)
            ->first();
    }

    public static function create(string $table, array $data)
    {
        $pdo = DB::conn();

        if (!isset($data["meta_key"])) {
            throw new \Exception("meta_key é obrigatório");
        }

        if (isset($data["meta_value"]) && is_array($data["meta_value"])) {
            $data["meta_value"] = json_encode($data["meta_value"]);
        }

        $cols = array_keys($data);
        $params = array_map(fn($c) => ":$c", $cols);

        $sql = "INSERT INTO $table (" . implode(",", $cols) . ")
            VALUES (" . implode(",", $params) . ")";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $pdo->lastInsertId();
    }

    // public static function updateById(string $table, $id, array $data)
    // {
    //     $pdo = DB::conn();

    //     $fields = [];
    //     $params = ["id" => $id];

    //     foreach ($data as $key => $value) {

    //         if ($key === "meta_value" && is_array($value)) {

    //             $stmt = $pdo->prepare("SELECT meta_value FROM $table WHERE id = :id");
    //             $stmt->execute(["id" => $id]);
    //             $current = $stmt->fetchColumn();

    //             $current = $current ? json_decode($current, true) : [];

    //             $merged = array_merge($current, $value);

    //             $fields[] = "meta_value = :meta_value";
    //             $params["meta_value"] = json_encode($merged);
    //         } else {
    //             $fields[] = "$key = :$key";
    //             $params[$key] = $value;
    //         }
    //     }

    //     $sql = "UPDATE $table SET " . implode(",", $fields) . " WHERE id = :id";

    //     return $pdo->prepare($sql)->execute($params);
    // }

    public static function updateById(string $table, $id, array $data)
    {
        $pdo = DB::conn();

        // Pega o registro atual
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->execute(["id" => $id]);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$current) {
            throw new \Exception("Registro não encontrado para backup");
        }

        // Inserir na tabela de backup
        $backupTable = "backup";
        $cols = array_keys($current);
        $params = array_map(fn($c) => ":$c", $cols);

        $sql = "INSERT INTO $backupTable (" . implode(",", $cols) . ") 
            VALUES (" . implode(",", $params) . ")";
        $stmtBackup = $pdo->prepare($sql);
        $stmtBackup->execute($current);

        // Prepara update normal
        $fields = [];
        $params = ["id" => $id];

        foreach ($data as $key => $value) {
            if ($key === "meta_value" && is_array($value)) {
                $currentMeta = isset($current["meta_value"]) ? json_decode($current["meta_value"], true) : [];
                $merged = array_merge($currentMeta, $value);
                $fields[] = "meta_value = :meta_value";
                $params["meta_value"] = json_encode($merged);
            } else {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        $sqlUpdate = "UPDATE $table SET " . implode(",", $fields) . " WHERE id = :id";
        return $pdo->prepare($sqlUpdate)->execute($params);
    }
}
