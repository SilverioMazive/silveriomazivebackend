<?php

namespace Controllers;

use Config\Config;

class BaseController
{
    protected string $table;
    protected string $appcode;

    public function __construct()
    {
        $this->appcode = $this->resolveAppCode();
        $this->table = $this->resolveTable();
    }

    protected function resolveAppCode(): string
    {
        $headers = getallheaders();
        $appcode = $headers['X-AppCode'] ?? null;

        if (!$appcode) {
            $this->json(["error" => "X-AppCode header is required"], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $appcode)) {
            $this->json(["error" => "invalid appcode"], 400);
        }

        // ✅ compara com o AppCode da classe Config
        if ($appcode !== Config::APP_CODE) {
            $this->json(["error" => "unauthorized appcode"], 403);
        }

        return $appcode;
    }

    protected function resolveTable(): string
    {
        $table = $_GET['table'] ?? null;

        if (!$table) {
            $this->json(["error" => "table parameter is required"], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->json(["error" => "invalid table name"], 400);
        }

        return $table;
    }

    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header("Content-Type: application/json");
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}