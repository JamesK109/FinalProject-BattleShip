<?php

$db = getDB();

if ($method === 'POST' && count($segments) === 1) {
    $data = getJsonInput();
    ensureNoExtraFields($data, ['creator_id', 'grid_size', 'max_players']);
    $requiredFields = ['creator_id', 'grid_size', 'max_players'];
    $missingFields = array_values(array_filter(
        $requiredFields,
        fn($field) => !array_key_exists($field, $data)
    ));
    if ($missingFields !== []) {
        errorResponse(
            'missing required fields',
            'Missing required fields: ' . implode(', ', $missingFields),
            400
        );
    }

    $creatorId = requirePositiveInt($data['creator_id'], 'creator_id');
    $gridSize = requireIntInRange($data['grid_size'], 'grid_size', 5, 15);
    $maxPlayers = requireIntInRange($data['max_players'], 'max_players', 2, 10);
    ensurePlayerExists($db, $creatorId);

    $stmt = $db->prepare('INSERT INTO games(creator_id, grid_size, max_players, status, current_turn_index, winner_id) VALUES(?, ?, ?, ?, 0, NULL)');
    $stmt->execute([$creatorId, $gridSize, $maxPlayers, 'waiting_setup']);

    $gameId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO game_players(game_id, player_id, turn_order) VALUES(?, ?, 0)')->execute([$gameId, $creatorId]);

    respond([
        'game_id' => $gameId,
        'status' => 'waiting_setup',
    ], 201);
}

if ($method === 'POST' && ($segments[2] ?? null) === 'join') {
    $gameId = requirePositiveInt($segments[1] ?? null, 'id');
    $game = syncGameState($db, $gameId);
    if ($game['status'] !== 'waiting_setup') {
        errorResponse('bad_request', 'Game has already started', 400);
    }

    $data = getJsonInput();
    ensureNoExtraFields($data, ['player_id']);
    requireFields($data, ['player_id']);

    $playerId = requirePositiveInt($data['player_id'], 'player_id');
    if (!fetchOne($db, 'SELECT id FROM players WHERE id = ?', [$playerId])) {
        errorResponse('player does not exist', 'Player does not exist', 404);
    }

    $alreadyJoined = fetchOne($db, 'SELECT 1 FROM game_players WHERE game_id = ? AND player_id = ?', [$gameId, $playerId]);
    if ($alreadyJoined) {
        errorResponse('bad_request', 'Player already joined this game', 400);
    }

    $joinedCount = joinedPlayerCount($db, $gameId);
    if ($joinedCount >= (int)$game['max_players']) {
        errorResponse('bad_request', 'Game is full', 400);
    }

    $db->prepare('INSERT INTO game_players(game_id, player_id, turn_order) VALUES(?, ?, ?)')->execute([$gameId, $playerId, $joinedCount]);
    syncGameState($db, $gameId);

    respond(['status' => 'joined']);
}

if ($method === 'GET' && count($segments) === 2) {
    $gameId = requirePositiveInt($segments[1] ?? null, 'id');
    respond(buildGameDetail($db, $gameId));
}

errorResponse('not_found', 'Invalid games endpoint', 404);
