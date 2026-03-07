<?php

function getDB() {

    static $db = null;

    if ($db === null) {

        $dbFile = __DIR__ . "/battleship.db";
        $db = new PDO("sqlite:" . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        initializeSchema($db);
    }

    return $db;
}

function initializeSchema($db) {

    $db->exec("
        CREATE TABLE IF NOT EXISTS players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            games_played INTEGER DEFAULT 0,
            wins INTEGER DEFAULT 0,
            losses INTEGER DEFAULT 0,
            total_shots INTEGER DEFAULT 0,
            total_hits INTEGER DEFAULT 0
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            creator_id INTEGER,
            grid_size INTEGER,
            max_players INTEGER,
            status TEXT DEFAULT 'waiting',
            current_turn_index INTEGER DEFAULT 0
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS game_players (
            game_id INTEGER,
            player_id INTEGER,
            turn_order INTEGER,
            PRIMARY KEY (game_id, player_id)
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS ships (
            game_id INTEGER,
            player_id INTEGER,
            row INTEGER,
            col INTEGER
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS moves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER,
            player_id INTEGER,
            row INTEGER,
            col INTEGER,
            result TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}
