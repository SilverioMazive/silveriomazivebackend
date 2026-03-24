<?php

namespace Controllers;

class BaseController
{
    protected string $table;

    public function __construct()
    {
        $this->table = $this->resolveTable();
    }

    // Pega a tabela do endpoint
    protected function resolveTable(): string
    {
        $table = $_GET['table'] ?? null;

        if (!$table) {
            $this->json(["error" => "table parameter is required"]);
            exit;
        }

        // segurança (evita SQL injection via nome de tabela)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->json(["error" => "invalid table name"]);
            exit;
        }

        return $table;
    }

    // resposta JSON padrão
    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header("Content-Type: application/json");
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
