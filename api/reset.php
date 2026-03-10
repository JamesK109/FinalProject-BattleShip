<?php

if ($method !== "POST") {
    respond(["error"=>"Method not allowed"],405);
}

$db = getDB();

$db->exec("DELETE FROM games");
$db->exec("DELETE FROM game_players");
$db->exec("DELETE FROM ships");
$db->exec("DELETE FROM moves");
$db->exec("DELETE FROM players");

respond(["status"=>"reset"]);