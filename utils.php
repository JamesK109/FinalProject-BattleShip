<?php

const TEST_MODE = true;
const TEST_PASSWORD = 'clemson-test-2026';
const API_VERSION = '2.3.0';
const SPEC_VERSION = '2.3';
const API_NAME = 'Battleship API';

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        errorResponse('bad_request', 'Invalid JSON body', 400);
    }

    return $data;
}

function respond($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function requireFields(array $data, array $fields) {
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data)) {
            errorResponse('bad_request', "$field is required", 400);
        }
    }
}

function requireIntInRange($value, string $field, int $min, int $max) {
    if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
        errorResponse('bad_request', "$field must be an integer", 400);
    }
    $intValue = (int)$value;
    if ($intValue < $min || $intValue > $max) {
        errorResponse('bad_request', "$field must be between $min and $max", 400);
    }
    return $intValue;
}

function requirePositiveInt($value, string $field) {
    if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value))) {
        errorResponse('bad_request', "$field must be a positive integer", 400);
    }
    $intValue = (int)$value;
    if ($intValue <= 0) {
        errorResponse('bad_request', "$field must be a positive integer", 400);
    }
    return $intValue;
}

function errorResponse(string $error, string $message, int $status) {
    respond(['error' => $error, 'message' => $message], $status);
}

function ensureNoExtraFields(array $data, array $allowedFields) {
    $extraFields = array_diff(array_keys($data), $allowedFields);
    if ($extraFields !== []) {
        errorResponse('bad_request', 'Unexpected fields: ' . implode(', ', $extraFields), 400);
    }
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
        errorResponse('not_found', 'Player does not exist', 404);
    }
    return $player;
}

function ensureGameExists(PDO $db, int $gameId) {
    $game = fetchOne($db, 'SELECT * FROM games WHERE id = ?', [$gameId]);
    if (!$game) {
        errorResponse('not_found', 'Game does not exist', 404);
    }
    return $game;
}

function ensurePlayerInGame(PDO $db, int $gameId, int $playerId) {
    $row = fetchOne($db, 'SELECT * FROM game_players WHERE game_id = ? AND player_id = ?', [$gameId, $playerId]);
    if (!$row) {
        errorResponse('bad_request', 'Player is not in this game', 400);
    }
    return $row;
}

function getActivePlayerIds(PDO $db, int $gameId) {
    $rows = fetchAllRows($db, 'SELECT player_id FROM game_players WHERE game_id = ? ORDER BY turn_order ASC', [$gameId]);
    return array_map(fn($r) => (int)$r['player_id'], $rows);
}

function countRemainingShips(PDO $db, int $gameId, int $playerId) {
    $row = fetchOne(
        $db,
        'SELECT COUNT(*) AS c
         FROM ships s
         WHERE s.game_id = ? AND s.player_id = ?
           AND NOT EXISTS (
               SELECT 1 FROM moves m
               WHERE m.game_id = s.game_id
                 AND m.row = s.row
                 AND m.col = s.col
                 AND m.result = "hit"
           )',
        [$gameId, $playerId]
    );

    return (int)($row['c'] ?? 0);
}

function getAlivePlayerIds(PDO $db, int $gameId) {
    $playerIds = getActivePlayerIds($db, $gameId);
    $alive = [];

    foreach ($playerIds as $playerId) {
        if (countRemainingShips($db, $gameId, $playerId) > 0) {
            $alive[] = $playerId;
        }
    }

    return $alive;
}

function getNextAlivePlayerId(PDO $db, int $gameId, int $playerId) {
    $playerIds = getActivePlayerIds($db, $gameId);
    $count = count($playerIds);

    if ($count === 0) {
        return null;
    }

    $currentIndex = array_search($playerId, $playerIds, true);
    if ($currentIndex === false) {
        return null;
    }

    for ($offset = 1; $offset < $count; $offset++) {
        $candidateId = $playerIds[($currentIndex + $offset) % $count];
        if (countRemainingShips($db, $gameId, $candidateId) > 0) {
            return $candidateId;
        }
    }

    return null;
}

function getTurnOrderIndex(PDO $db, int $gameId, int $playerId) {
    $row = fetchOne(
        $db,
        'SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?',
        [$gameId, $playerId]
    );

    return $row ? (int)$row['turn_order'] : null;
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

function joinedPlayerCount(PDO $db, int $gameId) {
    $row = fetchOne($db, 'SELECT COUNT(*) AS c FROM game_players WHERE game_id = ?', [$gameId]);
    return (int)($row['c'] ?? 0);
}

function isReadyToPlay(PDO $db, array $game) {
    return joinedPlayerCount($db, (int)$game['id']) === (int)$game['max_players']
        && allPlayersPlaced($db, (int)$game['id']);
}

function syncGameState(PDO $db, int $gameId) {
    $game = ensureGameExists($db, $gameId);
    $status = $game['status'];
    $winnerId = $game['winner_id'] !== null ? (int)$game['winner_id'] : null;
    $alivePlayers = getAlivePlayerIds($db, $gameId);

    if ($status === 'finished') {
        if (count($alivePlayers) === 1 && $winnerId === null) {
            $winnerId = $alivePlayers[0];
            $db->prepare('UPDATE games SET winner_id = ?, current_turn_index = NULL WHERE id = ?')->execute([$winnerId, $gameId]);
        } elseif (count($alivePlayers) !== 1) {
            $winnerId = null;
            $status = isReadyToPlay($db, $game) ? 'playing' : 'waiting_setup';
            $db->prepare('UPDATE games SET status = ?, winner_id = NULL, current_turn_index = 0 WHERE id = ?')->execute([$status, $gameId]);
        }
        return ensureGameExists($db, $gameId);
    }

    if ($status === 'playing' && count($alivePlayers) === 1) {
        $winnerId = $alivePlayers[0];
        $db->prepare('UPDATE games SET status = ?, winner_id = ?, current_turn_index = NULL WHERE id = ?')->execute(['finished', $winnerId, $gameId]);
        return ensureGameExists($db, $gameId);
    }

    if ($status !== 'playing' && isReadyToPlay($db, $game)) {
        $db->prepare('UPDATE games SET status = ?, current_turn_index = 0, winner_id = NULL WHERE id = ?')->execute(['playing', $gameId]);
        return ensureGameExists($db, $gameId);
    }

    if ($status !== 'waiting_setup' && !isReadyToPlay($db, $game)) {
        $db->prepare('UPDATE games SET status = ?, current_turn_index = 0, winner_id = NULL WHERE id = ?')->execute(['waiting_setup', $gameId]);
        return ensureGameExists($db, $gameId);
    }

    return $game;
}

function buildGameDetail(PDO $db, int $gameId) {
    $game = syncGameState($db, $gameId);
    $players = fetchAllRows(
        $db,
        'SELECT player_id FROM game_players WHERE game_id = ? ORDER BY turn_order ASC',
        [$gameId]
    );

    $playerDetails = [];
    foreach ($players as $player) {
        $playerId = (int)$player['player_id'];
        $playerDetails[] = [
            'player_id' => $playerId,
            'ships_remaining' => countRemainingShips($db, $gameId, $playerId),
        ];
    }

    $currentTurnPlayerId = null;
    if ($game['status'] === 'playing') {
        $currentTurnIndex = $game['current_turn_index'] !== null ? (int)$game['current_turn_index'] : 0;
        $currentTurnPlayerId = $playerDetails[$currentTurnIndex]['player_id'] ?? null;
        if ($currentTurnPlayerId !== null && countRemainingShips($db, $gameId, $currentTurnPlayerId) === 0) {
            $currentTurnPlayerId = getNextAlivePlayerId($db, $gameId, $currentTurnPlayerId);
            if ($currentTurnPlayerId !== null) {
                $turnOrder = getTurnOrderIndex($db, $gameId, $currentTurnPlayerId);
                $db->prepare('UPDATE games SET current_turn_index = ? WHERE id = ?')->execute([$turnOrder, $gameId]);
            }
        }
    }

    $moveCount = fetchOne($db, 'SELECT COUNT(*) AS c FROM moves WHERE game_id = ?', [$gameId]);

    return [
        'game_id' => (int)$game['id'],
        'grid_size' => (int)$game['grid_size'],
        'status' => $game['status'],
        'players' => $playerDetails,
        'current_turn_player_id' => $game['status'] === 'playing' ? $currentTurnPlayerId : null,
        'total_moves' => (int)($moveCount['c'] ?? 0),
    ];
}

function finalizeFinishedGame(PDO $db, int $gameId, int $winnerId) {
    $game = ensureGameExists($db, $gameId);
    if ($game['status'] === 'finished' && (int)$game['winner_id'] === $winnerId) {
        return;
    }

    $db->prepare('UPDATE games SET status = ?, winner_id = ?, current_turn_index = NULL WHERE id = ?')->execute(['finished', $winnerId, $gameId]);

    $playerIds = getActivePlayerIds($db, $gameId);
    foreach ($playerIds as $playerId) {
        $db->prepare('UPDATE players SET games_played = games_played + 1 WHERE id = ?')->execute([$playerId]);
        if ($playerId === $winnerId) {
            $db->prepare('UPDATE players SET wins = wins + 1 WHERE id = ?')->execute([$playerId]);
        } else {
            $db->prepare('UPDATE players SET losses = losses + 1 WHERE id = ?')->execute([$playerId]);
        }
    }
}

function restartGameState(PDO $db, int $gameId, bool $clearPlayers = true) {
    ensureGameExists($db, $gameId);

    $db->prepare('DELETE FROM ships WHERE game_id = ?')->execute([$gameId]);
    $db->prepare('DELETE FROM moves WHERE game_id = ?')->execute([$gameId]);

    if ($clearPlayers) {
        $db->prepare('DELETE FROM game_players WHERE game_id = ?')->execute([$gameId]);
    }

    $db->prepare(
        "UPDATE games
         SET status = 'waiting_setup',
             current_turn_index = 0,
             winner_id = NULL
         WHERE id = ?"
    )->execute([$gameId]);
}

function isTestRequestAuthorized() {
    if (!TEST_MODE) {
        errorResponse('forbidden', 'Test mode disabled', 403);
    }

    $password = $_SERVER['HTTP_X_TEST_PASSWORD'] ?? null;
    if ($password !== TEST_PASSWORD) {
        errorResponse('forbidden', 'Invalid test password', 403);
    }
}

function resetDatabase(PDO $db) {
    $db->exec('DELETE FROM moves');
    $db->exec('DELETE FROM ships');
    $db->exec('DELETE FROM game_players');
    $db->exec('DELETE FROM games');
    $db->exec('DELETE FROM players');
    $db->exec('DELETE FROM sqlite_sequence');
}
