<?php

function getDB() {

    static $db = null;

    if ($db === null) {

        $dbFile = __DIR__ . '/battleship.db';
        $db = new PDO('sqlite:' . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        initializeSchema($db);
    }

    return $db;
}

function initializeSchema($db) {

    $db->exec("PRAGMA foreign_keys = ON;");

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
            current_turn_index INTEGER DEFAULT 0,
            winner_id INTEGER DEFAULT NULL
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
            col INTEGER,
            PRIMARY KEY (game_id, player_id, row, col)
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS moves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER,
            player_id INTEGER,
            target_player_id INTEGER,
            row INTEGER,
            col INTEGER,
            result TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $columns = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_ASSOC);
    $gameColumns = array_column($columns, 'name');
    if (!in_array('winner_id', $gameColumns, true)) {
        $db->exec("ALTER TABLE games ADD COLUMN winner_id INTEGER DEFAULT NULL");
    }

    $moveColumns = $db->query("PRAGMA table_info(moves)")->fetchAll(PDO::FETCH_ASSOC);
    $moveColumnNames = array_column($moveColumns, 'name');
    if (!in_array('target_player_id', $moveColumnNames, true)) {
        $db->exec("ALTER TABLE moves ADD COLUMN target_player_id INTEGER DEFAULT NULL");
    }
}
