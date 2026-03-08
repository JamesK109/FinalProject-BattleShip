<?php

$db = getDB();

if ($method !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
ensureGameExists($db, $gameId);

$moves = fetchAllRows(
    $db,
    'SELECT id, game_id, player_id, target_player_id, row, col, result, created_at
     FROM moves
     WHERE game_id = ?
     ORDER BY id ASC',
    [$gameId]
);

foreach ($moves as &$move) {
    $move['id'] = (int)$move['id'];
    $move['game_id'] = (int)$move['game_id'];
    $move['player_id'] = (int)$move['player_id'];
    $move['target_player_id'] = $move['target_player_id'] !== null ? (int)$move['target_player_id'] : null;
    $move['row'] = (int)$move['row'];
    $move['col'] = (int)$move['col'];
}
unset($move);

respond($moves);
