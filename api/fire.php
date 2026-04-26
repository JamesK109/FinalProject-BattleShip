<?php

$db = getDB();

if ($method !== 'POST') {
    errorResponse('method_not_allowed', 'Method not allowed', 405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
$game = syncGameState($db, $gameId);
$data = getJsonInput();
ensureNoExtraFields($data, ['player_id', 'row', 'col']);
requireFields($data, ['player_id', 'row', 'col']);

$playerId = requirePositiveInt($data['player_id'], 'player_id');
ensurePlayerExists($db, $playerId);
ensurePlayerInGame($db, $gameId, $playerId);

$row = requireIntInRange($data['row'], 'row', 0, ((int)$game['grid_size']) - 1);
$col = requireIntInRange($data['col'], 'col', 0, ((int)$game['grid_size']) - 1);

if ($game['status'] === 'finished') {
    errorResponse('bad_request', 'Game is already finished', 400);
}

if ($game['status'] !== 'playing') {
    errorResponse('bad_request', 'Game is not in playing state', 400);
}

$gameDetail = buildGameDetail($db, $gameId);
$currentTurnPlayerId = $gameDetail['current_turn_player_id'];
if ($currentTurnPlayerId !== $playerId) {
    errorResponse('forbidden', 'Not your turn', 403);
}

$alreadyFired = fetchOne(
    $db,
    'SELECT id FROM moves WHERE game_id = ? AND row = ? AND col = ?',
    [$gameId, $row, $col]
);
if ($alreadyFired) {
    errorResponse('conflict', 'Cell already fired upon', 409);
}

$hitShip = fetchOne(
    $db,
    'SELECT player_id FROM ships
     WHERE game_id = ? AND row = ? AND col = ?
     ORDER BY player_id ASC
     LIMIT 1',
    [$gameId, $row, $col]
);

$targetPlayerId = $hitShip ? (int)$hitShip['player_id'] : null;
$result = $targetPlayerId !== null ? 'hit' : 'miss';

$db->prepare('INSERT INTO moves(game_id, player_id, target_player_id, row, col, result) VALUES(?, ?, ?, ?, ?, ?)')
    ->execute([$gameId, $playerId, $targetPlayerId, $row, $col, $result]);

$db->prepare('UPDATE players SET total_shots = total_shots + 1 WHERE id = ?')->execute([$playerId]);
if ($result === 'hit') {
    $db->prepare('UPDATE players SET total_hits = total_hits + 1 WHERE id = ?')->execute([$playerId]);
}

$alivePlayers = getAlivePlayerIds($db, $gameId);
if (count($alivePlayers) === 0) {
    finalizeFinishedGame($db, $gameId, $playerId);
    respond([
        'result' => $result,
        'next_player_id' => null,
        'game_status' => 'finished',
        'winner_id' => $playerId,
    ]);
}

if (count($alivePlayers) === 1) {
    $winnerId = $alivePlayers[0];
    finalizeFinishedGame($db, $gameId, $winnerId);
    respond([
        'result' => $result,
        'next_player_id' => null,
        'game_status' => 'finished',
        'winner_id' => $winnerId,
    ]);
}

$nextPlayerId = getNextAlivePlayerId($db, $gameId, $playerId);
if ($nextPlayerId === null) {
    errorResponse('bad_request', 'Unable to determine next player', 400);
}

$nextTurnIndex = getTurnOrderIndex($db, $gameId, $nextPlayerId);
$db->prepare('UPDATE games SET current_turn_index = ? WHERE id = ?')->execute([$nextTurnIndex, $gameId]);

respond([
    'result' => $result,
    'next_player_id' => $nextPlayerId,
    'game_status' => 'playing',
    'winner_id' => null,
]);
