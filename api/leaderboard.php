<?php

$db = getDB();

if ($method !== 'GET' || count($segments) !== 1) {
    errorResponse('not_found', 'Invalid leaderboard endpoint', 404);
}

$rows = fetchAllRows(
    $db,
    'SELECT id, username, games_played, wins, losses, total_shots, total_hits
     FROM players
     ORDER BY wins DESC, total_hits DESC, total_shots ASC, username ASC'
);

$leaderboard = [];
foreach ($rows as $row) {
    $shots = (int)$row['total_shots'];
    $hits = (int)$row['total_hits'];
    $leaderboard[] = [
        'player_id' => (int)$row['id'],
        'username' => $row['username'],
        'games_played' => (int)$row['games_played'],
        'wins' => (int)$row['wins'],
        'losses' => (int)$row['losses'],
        'accuracy' => $shots > 0 ? round($hits / $shots, 3) : 0.0,
    ];
}

respond($leaderboard);
