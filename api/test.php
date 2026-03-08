<?php

$db = getDB();
isTestRequestAuthorized();

if (($segments[1] ?? null) !== 'games') {
    respond(['error' => 'Unknown test endpoint'], 404);
}

$gameId = requirePositiveInt($segments[2] ?? null, 'id');
$game = ensureGameExists($db, $gameId);
$action = $segments[3] ?? null;

if ($method === 'POST' && $action === 'restart') {
    $db->prepare('DELETE FROM ships WHERE game_id = ?')->execute([$gameId]);
    $db->prepare('DELETE FROM moves WHERE game_id = ?')->execute([$gameId]);
    $db->prepare("UPDATE games SET status = 'waiting', current_turn_index = 0, winner_id = NULL WHERE id = ?")->execute([$gameId]);
    respond(['status' => 'restarted']);
}

if ($method === 'POST' && $action === 'ships') {
    $data = getJsonInput();
    requireFields($data, ['player_id', 'ships']);
    $playerId = requirePositiveInt($data['player_id'], 'player_id');
    $ships = $data['ships'];

    ensurePlayerExists($db, $playerId);
    ensurePlayerInGame($db, $gameId, $playerId);

    if (!is_array($ships) || count($ships) !== 3) {
        respond(['error' => 'must place exactly 3 ships'], 400);
    }

    $seen = [];
    foreach ($ships as $ship) {
        if (!is_array($ship) || !array_key_exists('row', $ship) || !array_key_exists('col', $ship)) {
            respond(['error' => 'each ship must include row and col'], 400);
        }
        $row = requireIntInRange($ship['row'], 'row', 0, ((int)$game['grid_size']) - 1);
        $col = requireIntInRange($ship['col'], 'col', 0, ((int)$game['grid_size']) - 1);
        $key = $row . ':' . $col;
        if (isset($seen[$key])) {
            respond(['error' => 'ship coordinates cannot overlap'], 400);
        }
        $seen[$key] = true;
    }

    $db->prepare('DELETE FROM ships WHERE game_id = ? AND player_id = ?')->execute([$gameId, $playerId]);
    $stmt = $db->prepare('INSERT INTO ships(game_id, player_id, row, col) VALUES(?, ?, ?, ?)');
    foreach ($ships as $ship) {
        $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
    }

    $newStatus = allPlayersPlaced($db, $gameId) ? 'active' : 'waiting';
    $db->prepare('UPDATE games SET status = ?, current_turn_index = 0, winner_id = NULL WHERE id = ?')->execute([$newStatus, $gameId]);
    respond(['status' => 'ships placed']);
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

respond(['error' => 'Unknown test endpoint'], 404);
