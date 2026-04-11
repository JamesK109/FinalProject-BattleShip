<?php

$db = getDB();

if ($method !== "POST") {
    errorResponse('method_not_allowed', 'Method not allowed', 405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
$game = syncGameState($db, $gameId);
$data = getJsonInput();
ensureNoExtraFields($data, ['player_id', 'ships']);
requireFields($data, ['player_id', 'ships']);

if ($game['status'] !== 'waiting_setup') {
    errorResponse('forbidden', 'Ships can only be placed during setup', 403);
}

$playerId = requirePositiveInt($data["player_id"], 'player_id');
ensurePlayerExists($db, $playerId);
ensurePlayerInGame($db, $gameId, $playerId);
$ships = $data["ships"];

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
    if (!is_array($ship) || !array_key_exists('row', $ship) || !array_key_exists('col', $ship)) {
        errorResponse('bad_request', 'Each ship must include row and col', 400);
    }
    ensureNoExtraFields($ship, ['row', 'col']);
    $row = requireIntInRange($ship["row"], 'row', 0, ((int)$game['grid_size']) - 1);
    $col = requireIntInRange($ship["col"], 'col', 0, ((int)$game['grid_size']) - 1);
    $key = $row . ':' . $col;
    if (isset($seen[$key])) {
        errorResponse('bad_request', 'Ship coordinates cannot overlap', 400);
    }
    $seen[$key] = true;
    $normalizedShips[] = ['row' => $row, 'col' => $col];
}
$stmt = $db->prepare("
    INSERT INTO ships(game_id,player_id,row,col)
    VALUES(?,?,?,?)
");

foreach ($normalizedShips as $ship) {
    $stmt->execute([$gameId, $playerId, $ship['row'], $ship['col']]);
}

syncGameState($db, $gameId);

respond(["status" => "placed"]);
