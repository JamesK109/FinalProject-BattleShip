<?php

if ($method !== "POST") {
    errorResponse('method_not_allowed', 'Method not allowed', 405);
}

$db = getDB();

$db->exec("DELETE FROM game_players");
$db->exec("DELETE FROM ships");
$db->exec("DELETE FROM moves");
$db->exec("DELETE FROM games");
$db->exec("DELETE FROM players");
$db->exec("DELETE FROM sqlite_sequence");

respond(["status" => "reset"]);