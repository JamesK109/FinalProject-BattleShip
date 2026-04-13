<?php

$db = getDB();

if ($method === 'POST' && count($segments) === 1) {
    $data = getJsonInput();
    ensureNoExtraFields($data, ['username']);
    requireFields($data, ['username']);

    $username = trim((string)$data['username']);
    if ($username === '' || strlen($username) > 30 || !preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        errorResponse('bad_request', 'Username must be alphanumeric with underscores only', 400);
    }

    $existing = fetchOne($db, 'SELECT id FROM players WHERE username = ?', [$username]);
    if ($existing) {
        errorResponse('conflict', 'Username already taken', 409);
    }

    try {
        $stmt = $db->prepare('INSERT INTO players(username) VALUES(?)');
        $stmt->execute([$username]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            errorResponse('conflict', 'Username already exists', 409);
        }
        throw $e;
    }

    respond(['player_id' => (int)$db->lastInsertId()], 201);
}

if ($method === 'GET' && ($segments[2] ?? null) === 'stats') {
    $playerId = requirePositiveInt($segments[1] ?? null, 'player_id');
    $player = ensurePlayerExists($db, $playerId);

    $shots = (int)$player['total_shots'];
    $hits = (int)$player['total_hits'];
    $accuracy = $shots > 0 ? round($hits / $shots, 3) : 0.0;

    respond([
        'games_played' => (int)$player['games_played'],
        'wins' => (int)$player['wins'],
        'losses' => (int)$player['losses'],
        'total_shots' => $shots,
        'total_hits' => $hits,
        'accuracy' => $accuracy,
    ]);
}

errorResponse('not_found', 'Invalid players endpoint', 404);
