<?php

$db = getDB();
isTestRequestAuthorized();

if (($segments[1] ?? null) !== 'games') {
    errorResponse('not_found', 'Unknown test endpoint', 404);
}

$gameId = requirePositiveInt($segments[2] ?? null, 'id');
$game = ensureGameExists($db, $gameId);
$action = $segments[3] ?? null;

if ($method === 'POST' && $action === 'restart') {
    $db->prepare('DELETE FROM ships WHERE game_id = ?')->execute([$gameId]);
    $db->prepare('DELETE FROM moves WHERE game_id = ?')->execute([$gameId]);
    $db->prepare("UPDATE games SET status = 'waiting_setup', current_turn_index = 0, winner_id = NULL WHERE id = ?")->execute([$gameId]);
    respond(['status' => 'reset']);
}

if ($method === 'POST' && $action === 'ships') {
    $data = getJsonInput();
    $rawPlayerId = $data['playerId'] ?? ($data['player_id'] ?? null);
    if ($rawPlayerId === null) {
        errorResponse('bad_request', 'playerId is required', 400);
    }
    $playerId = requirePositiveInt($rawPlayerId, 'playerId');
    if (!array_key_exists('ships', $data)) {
        errorResponse('bad_request', 'ships is required', 400);
    }
    $ships = $data['ships'];

    if ($game['status'] !== 'waiting_setup') {
        errorResponse('forbidden', 'Ships can only be placed during setup', 403);
    }

    ensurePlayerExists($db, $playerId);
    ensurePlayerInGame($db, $gameId, $playerId);

    $existingShips = (int)fetchOne(
        $db,
        'SELECT COUNT(*) AS c FROM ships WHERE game_id = ? AND player_id = ?',
        [$gameId, $playerId]
    )['c'];
    if ($existingShips > 0) {
        errorResponse('conflict', 'Ships already placed', 409);
    }

    if (!is_array($ships) || count($ships) !== 3) {
        errorResponse('bad_request', 'Must place exactly 3 ships', 400);
    }

    $normalizedShips = [];
    $seen = [];
    foreach ($ships as $ship) {
        if (!is_array($ship)) {
            errorResponse('bad_request', 'Each ship must be an object', 400);
        }

        if (array_key_exists('coordinates', $ship)) {
            if (!is_array($ship['coordinates']) || count($ship['coordinates']) !== 1) {
                errorResponse('bad_request', 'Each ship must occupy exactly one coordinate', 400);
            }
            $coord = $ship['coordinates'][0];
            if (!is_array($coord) || count($coord) !== 2) {
                errorResponse('bad_request', 'Coordinates must contain [row, col]', 400);
            }
            $row = requireIntInRange($coord[0], 'row', 0, ((int)$game['grid_size']) - 1);
            $col = requireIntInRange($coord[1], 'col', 0, ((int)$game['grid_size']) - 1);
        } elseif (array_key_exists('row', $ship) && array_key_exists('col', $ship)) {
            $row = requireIntInRange($ship['row'], 'row', 0, ((int)$game['grid_size']) - 1);
            $col = requireIntInRange($ship['col'], 'col', 0, ((int)$game['grid_size']) - 1);
        } else {
            errorResponse('bad_request', 'Each ship must include coordinates or row/col', 400);
        }

        $key = $row . ':' . $col;
        if (isset($seen[$key])) {
            errorResponse('bad_request', 'Ship coordinates cannot overlap', 400);
        }
        $seen[$key] = true;
        $normalizedShips[] = ['row' => $row, 'col' => $col];
    }

    $stmt = $db->prepare('INSERT INTO ships(game_id, player_id, row, col) VALUES(?, ?, ?, ?)');
    foreach ($normalizedShips as $ship) {
        $stmt->execute([$gameId, $playerId, $ship['row'], $ship['col']]);
    }

    syncGameState($db, $gameId);
    respond(['status' => 'placed']);
}

if ($method === 'GET' && $action === 'board' && isset($segments[4])) {
    $playerId = requirePositiveInt($segments[4], 'player_id');
    ensurePlayerExists($db, $playerId);
    ensurePlayerInGame($db, $gameId, $playerId);

    $ships = fetchAllRows($db, 'SELECT row, col FROM ships WHERE game_id = ? AND player_id = ? ORDER BY row, col', [$gameId, $playerId]);
    $hitsReceived = fetchAllRows($db, 'SELECT row, col FROM moves WHERE game_id = ? AND target_player_id = ? AND result = "hit" ORDER BY id', [$gameId, $playerId]);
    $missesByOthers = fetchAllRows($db, 'SELECT row, col FROM moves WHERE game_id = ? AND target_player_id = ? AND result = "miss" ORDER BY id', [$gameId, $playerId]);

    foreach ($ships as &$ship) {
        $ship['row'] = (int)$ship['row'];
        $ship['col'] = (int)$ship['col'];
    }
    unset($ship);
    foreach ($hitsReceived as &$hit) {
        $hit['row'] = (int)$hit['row'];
        $hit['col'] = (int)$hit['col'];
    }
    unset($hit);
    foreach ($missesByOthers as &$miss) {
        $miss['row'] = (int)$miss['row'];
        $miss['col'] = (int)$miss['col'];
    }
    unset($miss);

    respond([
        'game_id' => $gameId,
        'player_id' => $playerId,
        'status' => $game['status'],
        'ships' => $ships,
        'hits_received' => $hitsReceived,
        'misses_received' => $missesByOthers,
    ]);
}

errorResponse('not_found', 'Unknown test endpoint', 404);
