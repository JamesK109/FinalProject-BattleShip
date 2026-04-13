<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../utils.php";

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize to an API-relative path regardless of whether requests come through
// `/public/index.php`, `/public`, or a project-root index proxy.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === false || $path === '') {
    $path = '/';
}

$segments = explode('/', trim($path, '/'));

if (($segments[0] ?? null) !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'message' => 'Invalid API path']);
    exit;
}

array_shift($segments);
$resource = $segments[0] ?? null;

if ($method === 'GET' && $resource === null) {
    respond([
        'name' => API_NAME,
        'version' => API_VERSION,
        'spec_version' => SPEC_VERSION,
        'environment' => TEST_MODE ? 'test' : 'production',
        'test_mode' => TEST_MODE,
    ]);
}

switch ($resource) {
    case 'version':
        if ($method === 'GET' && count($segments) === 1) {
            respond([
                'api_version' => API_VERSION,
                'spec_version' => SPEC_VERSION,
            ]);
        }
        break;

    case 'health':
        if ($method === 'GET' && count($segments) === 1) {
            respond([
                'status' => 'ok',
                'uptime_seconds' => (int)($_SERVER['REQUEST_TIME'] ?? time()),
            ]);
        }
        break;

    case 'players':
        require __DIR__ . '/../api/players.php';
        break;

    case 'games':
        if (($segments[2] ?? null) === 'place') {
            require __DIR__ . '/../api/place.php';
            break;
        }
        if (($segments[2] ?? null) === 'fire') {
            require __DIR__ . '/../api/fire.php';
            break;
        }
        if (($segments[2] ?? null) === 'moves') {
            require __DIR__ . '/../api/moves.php';
            break;
        }
        require __DIR__ . '/../api/games.php';
        break;

    case 'test':
        require __DIR__ . '/../api/test.php';
        break;

    default:
        errorResponse('not_found', 'Unknown endpoint', 404);
}

errorResponse('not_found', 'Unknown endpoint', 404);
