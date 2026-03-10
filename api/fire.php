<?php

$db = getDB();

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$gameId = requirePositiveInt($segments[1] ?? null, 'id');
$game = ensureGameExists($db, $gameId);
$data = getJsonInput();
requireFields($data, ['player_id', 'row', 'col']);

$playerId = requirePositiveInt($data['player_id'], 'player_id');
$row = requireIntInRange($data['row'], 'row', 0, ((int)$game['grid_size']) - 1);
$col = requireIntInRange($data['col'], 'col', 0, ((int)$game['grid_size']) - 1);

$player = fetchOne($db, 'SELECT id FROM players WHERE id = ?', [$playerId]);
if (!$player) {
    respond(['error' => 'invalid player'], 403);
}
$currentMembership = fetchOne($db, 'SELECT * FROM game_players WHERE game_id = ? AND player_id = ?', [$gameId, $playerId]);
if (!$currentMembership) {
    respond(['error' => 'wrong game for player'], 403);
}

if ($game['status'] === 'finished') {
    respond(['error' => 'game is already finished'], 400);
}
if ($game['status'] !== 'active') {
    respond(['error' => 'all players must place ships before firing'], 400);
}
if (!allPlayersPlaced($db, $gameId)) {
    respond(['error' => 'all players must place ships before firing'], 400);
}

$activePlayers = getActivePlayerIds($db, $gameId);
if (count($activePlayers) < 2) {
    respond(['error' => 'at least two players are required to fire'], 400);
}

$currentTurnIndex = (int)$game['current_turn_index'];
$expectedPlayerId = $activePlayers[$currentTurnIndex] ?? null;
if ($expectedPlayerId !== $playerId) {
    respond(['error' => 'not this player\'s turn'], 403);
}

$alreadyFired = fetchOne($db, 'SELECT id FROM moves WHERE game_id = ? AND player_id = ? AND row = ? AND col = ?', [$gameId, $playerId, $row, $col]);
if ($alreadyFired) {
    respond(['error' => 'player has already fired at that coordinate'], 403);
}

$targetIndex = ($currentTurnIndex + 1) % count($activePlayers);
$targetPlayerId = $activePlayers[$targetIndex];

$hitShip = fetchOne($db, 'SELECT row, col FROM ships WHERE game_id = ? AND player_id = ? AND row = ? AND col = ?', [$gameId, $targetPlayerId, $row, $col]);
$result = $hitShip ? 'hit' : 'miss';

$stmt = $db->prepare('INSERT INTO moves(game_id, player_id, target_player_id, row, col, result) VALUES(?, ?, ?, ?, ?, ?)');
$stmt->execute([$gameId, $playerId, $targetPlayerId, $row, $col, $result]);

$db->prepare('UPDATE players SET total_shots = total_shots + 1 WHERE id = ?')->execute([$playerId]);
if ($result === 'hit') {
    $db->prepare('UPDATE players SET total_hits = total_hits + 1 WHERE id = ?')->execute([$playerId]);
}

$remainingTargetShips = (int)fetchOne(
    $db,
    'SELECT COUNT(*) AS c
     FROM ships s
     WHERE s.game_id = ? AND s.player_id = ?
       AND NOT EXISTS (
           SELECT 1 FROM moves m
           WHERE m.game_id = s.game_id
             AND m.target_player_id = s.player_id
             AND m.row = s.row
             AND m.col = s.col
             AND m.result = "hit"
       )',
    [$gameId, $targetPlayerId]
)['c'];

if ($remainingTargetShips === 0) {
    $db->prepare("UPDATE games SET status = 'finished', winner_id = ? WHERE id = ?")->execute([$playerId, $gameId]);

    foreach ($activePlayers as $pid) {
        $db->prepare('UPDATE players SET games_played = games_played + 1 WHERE id = ?')->execute([$pid]);
        if ($pid === $playerId) {
            $db->prepare('UPDATE players SET wins = wins + 1 WHERE id = ?')->execute([$pid]);
        } else {
            $db->prepare('UPDATE players SET losses = losses + 1 WHERE id = ?')->execute([$pid]);
        }
    }

    respond([
        'result' => $result,
        'next_player_id' => null,
        'game_status' => 'finished',
        'winner_id' => $playerId,
    ]);
}

$nextTurnIndex = ($currentTurnIndex + 1) % count($activePlayers);
$nextPlayerId = $activePlayers[$nextTurnIndex];
$db->prepare('UPDATE games SET current_turn_index = ? WHERE id = ?')->execute([$nextTurnIndex, $gameId]);

respond([
    'result' => $result,
    'next_player_id' => $nextPlayerId,
    'game_status' => 'active',
]);
