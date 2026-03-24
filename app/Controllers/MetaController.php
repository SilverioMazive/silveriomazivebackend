<?php

namespace Controllers;

use Models\Meta;

class MetaController extends BaseController
{
    public function index()
    {
        $data = Meta::query($this->table)
            ->notRemoved()
            ->get();

        return $this->json($data);
    }

    public function show($id)
    {
        $data = Meta::find($this->table, $id);
        $this->json($data);
    }

    public function create()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            return $this->json([
                "error" => "JSON inválido"
            ], 400);
        }

        try {
            $id = Meta::create($this->table, $input);

            $this->json([
                "success" => true,
                "id" => $id
            ]);
        } catch (\Exception $e) {
            $this->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function update($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            return $this->json([
                "error" => "JSON inválido"
            ], 400);
        }

        try {
            Meta::updateById($this->table, $id, $input);

            $this->json([
                "success" => true,
                "updated" => true
            ]);
        } catch (\Exception $e) {
            $this->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function search()
    {
        $query = Meta::query($this->table);

        // Lê JSON do body (POST)
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            return $this->json([
                "error" => "JSON inválido ou vazio"
            ], 400);
        }

        // parâmetros de controlo
        $controlParams = ['order', 'limit', 'offset', 'table'];

        foreach ($input as $key => $value) {

            // ignora params de controlo
            if (in_array($key, $controlParams)) {
                continue;
            }

            // meta_key (usa engine otimizada)
            if ($key === 'meta_key') {
                $query->metaKey($value);
                continue;
            }

            // 🔐 segurança: só permite meta_value.*
            if (strpos($key, 'meta_value.') !== 0) {
                return $this->json([
                    "error" => "Campo inválido: $key. Use meta_value.campo"
                ], 400);
            }

            // adiciona filtro
            $query->where($key, $value);
        }

        // ORDER
        if (!empty($input['order'])) {
            $parts = explode(":", $input['order']);

            $col = $parts[0] ?? 'id';
            $dir = strtoupper($parts[1] ?? 'ASC');

            if (!in_array($dir, ['ASC', 'DESC'])) {
                $dir = 'ASC';
            }

            $query->orderBy($col, $dir);
        }

        // LIMIT
        if (isset($input['limit'])) {
            $query->limit((int)$input['limit']);
        }

        // OFFSET
        if (isset($input['offset'])) {
            $query->offset((int)$input['offset']);
        }

        try {
            $data = $query->get();

            return $this->json([
                "success" => true,
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return $this->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $admin = $input['admin'] ?? 'unknown';

        $record = Meta::find($this->table, $id);

        if (!$record) {
            return $this->json(["error" => "Registo não encontrado"], 404);
        }

        // evita deletar duas vezes
        if (str_contains($record['meta_key'], '_removedby_')) {
            return $this->json(["error" => "Já está removido"], 400);
        }

        $newMetaKey = $record['meta_key']
            . "_removedby_" . $admin
            . "_" . date('YmdHis');

        Meta::updateById($this->table, $id, [
            "meta_key" => $newMetaKey
        ]);

        return $this->json([
            "success" => true,
            "message" => "Removido com sucesso",
            "meta_key" => $newMetaKey
        ]);
    }

    public function restore($id)
    {
        $record = Meta::find($this->table, $id);

        if (!$record) {
            return $this->json(["error" => "Registo não encontrado"], 404);
        }

        if (!str_contains($record['meta_key'], '_removedby_')) {
            return $this->json(["error" => "Registo não está removido"], 400);
        }

        // recupera nome original
        $original = explode('_removedby_', $record['meta_key'])[0];

        Meta::updateById($this->table, $id, [
            "meta_key" => $original
        ]);

        return $this->json([
            "success" => true,
            "message" => "Restaurado com sucesso",
            "meta_key" => $original
        ]);
    }

    public function forceDelete($id)
    {
        $pdo = \Core\DB::conn();

        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute(["id" => $id]);

        return $this->json([
            "success" => true,
            "message" => "Apagado permanentemente"
        ]);
    }

    public function trash()
    {
        $data = Meta::query($this->table)
            ->onlyRemoved()
            ->get();

        return $this->json($data);
    }
}
