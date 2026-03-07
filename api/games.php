<?php

$db = getDB();

# CREATE GAME
if ($method === "POST" && count($segments) === 1) {

    $data = getJsonInput();

    $creator = $data["creator_id"];
    $grid = $data["grid_size"];
    $max = $data["max_players"];

    $stmt = $db->prepare("
        INSERT INTO games(creator_id, grid_size, max_players)
        VALUES(?,?,?)
    ");
    $stmt->execute([$creator,$grid,$max]);

    $gameId = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO game_players(game_id,player_id,turn_order)
        VALUES(?,?,0)
    ");
    $stmt->execute([$gameId,$creator]);

    respond(["game_id"=>$gameId],201);
}

# JOIN GAME
if ($method === "POST" && ($segments[2] ?? null) === "join") {

    $gameId = $segments[1];
    $data = getJsonInput();
    $playerId = $data["player_id"];

    $count = $db->query("
        SELECT COUNT(*) FROM game_players WHERE game_id=$gameId
    ")->fetchColumn();

    $stmt = $db->prepare("
        INSERT INTO game_players(game_id,player_id,turn_order)
        VALUES(?,?,?)
    ");

    $stmt->execute([$gameId,$playerId,$count]);

    respond(["status"=>"joined"]);
}

# GET GAME
if ($method === "GET" && count($segments) === 2) {

    $gameId = $segments[1];

    $stmt = $db->prepare("SELECT * FROM games WHERE id=?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) respond(["error"=>"game not found"],404);

    $players = $db->query("
        SELECT COUNT(*) FROM game_players WHERE game_id=$gameId
    ")->fetchColumn();

    respond([
        "game_id"=>$game["id"],
        "grid_size"=>$game["grid_size"],
        "status"=>$game["status"],
        "current_turn_index"=>$game["current_turn_index"],
        "active_players"=>$players
    ]);
}

respond(["error"=>"Invalid games endpoint"],404);
