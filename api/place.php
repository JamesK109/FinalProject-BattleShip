<?php

$db = getDB();

if ($method !== "POST") {
    respond(["error"=>"Method not allowed"],405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
$game = ensureGameExists($db, $gameId);
$data = getJsonInput();
requireFields($data, ['player_id', 'ships']);

$playerId = requirePositiveInt($data["player_id"], 'player_id');
ensurePlayerExists($db, $playerId);
ensurePlayerInGame($db, $gameId, $playerId);
$ships = $data["ships"];

if (!is_array($ships) || count($ships) !== 3) {
    respond(["error"=>"must place exactly 3 ships"],400);
}

$seen = [];
foreach ($ships as $ship) {
    if (!is_array($ship) || !array_key_exists('row', $ship) || !array_key_exists('col', $ship)) {
        respond(['error' => 'each ship must include row and col'], 400);
    }
    $row = requireIntInRange($ship["row"], 'row', 0, ((int)$game['grid_size']) - 1);
    $col = requireIntInRange($ship["col"], 'col', 0, ((int)$game['grid_size']) - 1);
    $key = $row . ':' . $col;
    if (isset($seen[$key])) {
        respond(['error' => 'ship coordinates cannot overlap'], 400);
    }
    $seen[$key] = true;
}

$db->prepare('DELETE FROM ships WHERE game_id = ? AND player_id = ?')->execute([$gameId, $playerId]);
$stmt = $db->prepare("
    INSERT INTO ships(game_id,player_id,row,col)
    VALUES(?,?,?,?)
");

foreach ($ships as $ship) {
    $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
}

$newStatus = allPlayersPlaced($db, $gameId) ? 'active' : 'waiting';
$db->prepare('UPDATE games SET status = ?, current_turn_index = 0, winner_id = NULL WHERE id = ?')->execute([$newStatus, $gameId]);

respond(["status"=>"ships placed"]);
