<?php

const TEST_MODE = true;
const TEST_PASSWORD = 'clemson-test-2026';

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        respond(['error' => 'Invalid JSON body'], 400);
    }

    return $data;
}

function respond($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requireFields(array $data, array $fields) {
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data)) {
            respond(['error' => "$field is required"], 400);
        }
    }
}

function requireIntInRange($value, string $field, int $min, int $max) {
    if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
        respond(['error' => "$field must be an integer"], 400);
    }
    $intValue = (int)$value;
    if ($intValue < $min || $intValue > $max) {
        respond(['error' => "$field must be between $min and $max"], 400);
    }
    return $intValue;
}

function requirePositiveInt($value, string $field) {
    if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value))) {
        respond(['error' => "$field must be a positive integer"], 400);
    }
    $intValue = (int)$value;
    if ($intValue <= 0) {
        respond(['error' => "$field must be a positive integer"], 400);
    }
    return $intValue;
}

function fetchOne(PDO $db, string $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchAllRows(PDO $db, string $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensurePlayerExists(PDO $db, int $playerId) {
    $player = fetchOne($db, 'SELECT * FROM players WHERE id = ?', [$playerId]);
    if (!$player) {
        respond(['error' => 'player not found'], 404);
    }
    return $player;
}

function ensureGameExists(PDO $db, int $gameId) {
    $game = fetchOne($db, 'SELECT * FROM games WHERE id = ?', [$gameId]);
    if (!$game) {
        respond(['error' => 'game not found'], 404);
    }
    return $game;
}

function ensurePlayerInGame(PDO $db, int $gameId, int $playerId) {
    $row = fetchOne($db, 'SELECT * FROM game_players WHERE game_id = ? AND player_id = ?', [$gameId, $playerId]);
    if (!$row) {
        respond(['error' => 'player is not in this game'], 400);
    }
    return $row;
}

function getActivePlayerIds(PDO $db, int $gameId) {
    $rows = fetchAllRows($db, 'SELECT player_id FROM game_players WHERE game_id = ? ORDER BY turn_order ASC', [$gameId]);
    return array_map(fn($r) => (int)$r['player_id'], $rows);
}

function allPlayersPlaced(PDO $db, int $gameId) {
    $rows = fetchAllRows(
        $db,
        'SELECT gp.player_id, COUNT(s.row) AS ship_count
         FROM game_players gp
         LEFT JOIN ships s ON s.game_id = gp.game_id AND s.player_id = gp.player_id
         WHERE gp.game_id = ?
         GROUP BY gp.player_id',
        [$gameId]
    );

    if (count($rows) === 0) {
        return false;
    }

    foreach ($rows as $row) {
        if ((int)$row['ship_count'] !== 3) {
            return false;
        }
    }
    return true;
}

function isTestRequestAuthorized() {
    if (!TEST_MODE) {
        respond(['error' => 'Test mode disabled'], 403);
    }

    $password = $_SERVER['HTTP_X_TEST_PASSWORD'] ?? null;
    if ($password !== TEST_PASSWORD) {
        respond(['error' => 'Forbidden'], 403);
    }
}
