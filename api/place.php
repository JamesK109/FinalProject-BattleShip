<?php

$db = getDB();

if ($method !== "POST") {
    respond(["error"=>"Method not allowed"],405);
}

$gameId = $segments[1];
$data = getJsonInput();

$playerId = $data["player_id"];
$ships = $data["ships"];

if (count($ships) !== 3) {
    respond(["error"=>"must place exactly 3 ships"],400);
}

foreach ($ships as $ship) {

    $row = $ship["row"];
    $col = $ship["col"];

    $stmt = $db->prepare("
        INSERT INTO ships(game_id,player_id,row,col)
        VALUES(?,?,?,?)
    ");

    $stmt->execute([$gameId,$playerId,$row,$col]);
}

respond(["status"=>"ships placed"]);