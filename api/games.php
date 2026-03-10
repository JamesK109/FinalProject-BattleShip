<?php

$db = getDB();

# CREATE GAME
if ($method === "POST" && count($segments) === 1) {
    $data = getJsonInput();
    requireFields($data, ['creator_id', 'grid_size', 'max_players']);
    $creator = requirePositiveInt($data['creator_id'], 'creator_id');
    $grid = requireIntInRange($data['grid_size'], 'grid_size', 5, 15);
    $max = requirePositiveInt($data['max_players'], 'max_players');
    ensurePlayerExists($db, $creator);

    $stmt = $db->prepare("
        INSERT INTO games(creator_id, grid_size, max_players)
        VALUES(?,?,?)
    ");
    $stmt->execute([$creator, $grid, $max]);

    $gameId = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO game_players(game_id,player_id,turn_order)
        VALUES(?,?,0)
    ");
    $stmt->execute([$gameId, $creator]);

    respond(["game_id" => (int)$gameId], 201);
}

# JOIN GAME
if ($method === "POST" && ($segments[2] ?? null) === "join") {
    $gameId = requirePositiveInt($segments[1] ?? null, 'id');
    $game = ensureGameExists($db, $gameId);
    $data = getJsonInput();
    requireFields($data, ['player_id']);
    $playerId = requirePositiveInt($data['player_id'], 'player_id');
    ensurePlayerExists($db, $playerId);

    $alreadyJoined = fetchOne(
        $db,
        'SELECT player_id FROM game_players WHERE game_id = ? AND player_id = ?',
        [$gameId, $playerId]
    );
    if ($alreadyJoined) {
        respond(['error' => 'player already joined'], 400);
    }

    $count = (int)fetchOne(
        $db,
        'SELECT COUNT(*) AS c FROM game_players WHERE game_id = ?',
        [$gameId]
    )['c'];
    if ($count >= (int)$game['max_players']) {
        respond(['error' => 'game is full'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO game_players(game_id,player_id,turn_order)
        VALUES(?,?,?)
    ");
    $stmt->execute([$gameId, $playerId, $count]);

    respond(["status" => "joined"]);
}

# GET GAME
if ($method === "GET" && count($segments) === 2) {
    $gameId = requirePositiveInt($segments[1] ?? null, 'id');
    $game = ensureGameExists($db, $gameId);
    $players = (int)fetchOne(
        $db,
        'SELECT COUNT(*) AS c FROM game_players WHERE game_id = ?',
        [$gameId]
    )['c'];

    respond([
        "game_id" => (int)$game["id"],
        "grid_size" => (int)$game["grid_size"],
        "status" => $game["status"],
        "current_turn_index" => (int)$game["current_turn_index"],
        "active_players" => $players
    ]);
}

respond(["error"=>"Invalid games endpoint"],404);
