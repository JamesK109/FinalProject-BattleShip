<?php

function getJsonInput() {
    return json_decode(file_get_contents("php://input"), true);
}

function respond($data, $status=200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}