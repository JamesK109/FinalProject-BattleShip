<?php

$db = getDB();

if ($method === "POST" && count($segments) === 1) {

    $data = getJsonInput();
    $username = $data["username"] ?? null;

    if (!$username) {
        respond(["error"=>"username required"],400);
    }

    $stmt = $db->prepare("INSERT INTO players(username) VALUES(?)");

    try {
        $stmt->execute([$username]);
    } catch(Exception $e) {
        respond(["error"=>"username already exists"],400);
    }

    respond(["player_id"=>$db->lastInsertId()],201);
}

if ($method === "GET" && ($segments[2] ?? null) === "stats") {

    $id = $segments[1];

    $stmt = $db->prepare("SELECT * FROM players WHERE id=?");
    $stmt->execute([$id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        respond(["error"=>"player not found"],404);
    }

    $accuracy = 0;
    if ($player["total_shots"] > 0) {
        $accuracy = $player["total_hits"] / $player["total_shots"];
    }

    respond([
        "games_played"=>$player["games_played"],
        "wins"=>$player["wins"],
        "losses"=>$player["losses"],
        "total_shots"=>$player["total_shots"],
        "total_hits"=>$player["total_hits"],
        "accuracy"=>$accuracy
    ]);
}

respond(["error"=>"Invalid players endpoint"],404);
