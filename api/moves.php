<?php

$db = getDB();

if ($method !== 'GET') {
    errorResponse('method_not_allowed', 'Method not allowed', 405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
ensureGameExists($db, $gameId);

$moves = fetchAllRows(
    $db,
    "SELECT id, player_id, row, col, result,
            strftime('%Y-%m-%dT%H:%M:%SZ', created_at) AS timestamp
     FROM moves
     WHERE game_id = ?
     ORDER BY id ASC",
    [$gameId]
);

foreach ($moves as &$move) {
    $move = [
        'move_number' => (int)$move['id'],
        'player_id' => (int)$move['player_id'],
        'row' => (int)$move['row'],
        'col' => (int)$move['col'],
        'result' => $move['result'],
        'timestamp' => $move['timestamp'],
    ];
}
unset($move);

respond($moves);
