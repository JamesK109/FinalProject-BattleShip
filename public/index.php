<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../utils.php";

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize to an API-relative path regardless of whether requests come through
// `/public/index.php`, `/public`, or a project-root index proxy.
$scriptDir = rtrim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? "")), "/");
if ($scriptDir !== "" && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === false || $path === "") {
    $path = "/";
}

$segments = explode("/", trim($path, "/"));

if (($segments[0] ?? null) !== "api") {
    http_response_code(404);
    echo json_encode(["error" => "Invalid API path"]);
    exit;
}

array_shift($segments);
$resource = $segments[0] ?? null;

switch ($resource) {

    case "reset":
        require __DIR__ . "/../api/reset.php";
        break;

    case "players":
        require __DIR__ . "/../api/players.php";
        break;

    case "games":
        if (($segments[2] ?? null) === "place") {
            require __DIR__ . "/../api/place.php";
            break;
        }
        require __DIR__ . "/../api/games.php";
        break;

    default:
        http_response_code(404);
        echo json_encode(["error"=>"Unknown endpoint"]);
}
