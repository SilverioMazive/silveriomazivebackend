<?php
session_start();
date_default_timezone_set('Africa/Maputo');

/**
 * =========================
 * AUTOLOADER PSR-4
 * =========================
 */
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * =========================
 * HELPERS
 * =========================
 */
require_once __DIR__ . '/helpers/helpers.php';

/**
 * =========================
 * JSON RESPONSE
 * =========================
 */
function jsonResponse($data, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * =========================
 * REQUEST INFO
 * =========================
 */
$httpMethod = $_SERVER['REQUEST_METHOD'];

$uri = $_GET['url'] ?? '';
$uri = trim($uri, '/');
$parts = $uri ? explode('/', $uri) : [];

/**
 * =========================
 * CONTROLLER
 * =========================
 */
$controllerName = !empty($parts[0])
    ? ucfirst($parts[0]) . 'Controller'
    : 'MetaController';

$controllerClass = "Controllers\\$controllerName";

if (!class_exists($controllerClass)) {
    jsonResponse(["error" => "Controller not found: $controllerClass"], 404);
}

$controller = new $controllerClass();

/**
 * =========================
 * AUTO METHOD RESOLUTION
 * =========================
 */
$method = null;
$params = [];

// REST STYLE
switch ($httpMethod) {

    case 'GET':
        // /meta/ → index
        // /meta/1 → show(1)
        if (isset($parts[1])) {
            $method = 'show';
            $params[] = $parts[1];
        } else {
            $method = 'index';
        }
        break;

    case 'POST':
        // /meta/search → search()
        // /meta/ → create()
        if (isset($parts[1]) && $parts[1] === 'search') {
            $method = 'search';
        } else {
            $method = 'create';
        }
        break;

    case 'PUT':
        // /meta/1 → update(1)
        parse_str(file_get_contents("php://input"), $_PUT);

        if (isset($parts[1])) {
            $method = 'update';
            $params[] = $parts[1];
        } else {
            jsonResponse(["error" => "ID required for update"], 400);
        }
        break;

    case 'DELETE':
        // /meta/1 → delete(1)
        if (isset($parts[1])) {
            $method = 'delete';
            $params[] = $parts[1];
        } else {
            jsonResponse(["error" => "ID required for delete"], 400);
        }
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}

/**
 * =========================
 * EXECUTE
 * =========================
 */
if (!method_exists($controller, $method)) {
    jsonResponse(["error" => "Method not found: $method"], 404);
}

try {
    call_user_func_array([$controller, $method], $params);
} catch (\Exception $e) {
    jsonResponse([
        "error" => $e->getMessage()
    ], 500);
}
