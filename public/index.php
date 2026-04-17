<?php

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === false || $path === '') {
    $path = '/';
}

$segments = explode('/', trim($path, '/'));

if (($segments[0] ?? null) === 'api') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/../utils.php';

    array_shift($segments);
    $resource = $segments[0] ?? null;

    if ($method === 'GET' && $resource === null) {
        respond([
            'name' => API_NAME,
            'version' => API_VERSION,
            'spec_version' => SPEC_VERSION,
            'environment' => TEST_MODE ? 'test' : 'production',
            'test_mode' => TEST_MODE,
        ]);
    }

    switch ($resource) {
        case 'version':
            if ($method === 'GET' && count($segments) === 1) {
                respond([
                    'api_version' => API_VERSION,
                    'spec_version' => SPEC_VERSION,
                ]);
            }
            break;

        case 'health':
            if ($method === 'GET' && count($segments) === 1) {
                respond([
                    'status' => 'ok',
                    'uptime_seconds' => (int)($_SERVER['REQUEST_TIME'] ?? time()),
                ]);
            }
            break;

        case 'players':
            require __DIR__ . '/../api/players.php';
            break;

        case 'games':
            if (($segments[2] ?? null) === 'place') {
                require __DIR__ . '/../api/place.php';
                break;
            }
            if (($segments[2] ?? null) === 'fire') {
                require __DIR__ . '/../api/fire.php';
                break;
            }
            if (($segments[2] ?? null) === 'moves') {
                require __DIR__ . '/../api/moves.php';
                break;
            }
            require __DIR__ . '/../api/games.php';
            break;

        case 'leaderboard':
            require __DIR__ . '/../api/leaderboard.php';
            break;

        case 'test':
            require __DIR__ . '/../api/test.php';
            break;

        default:
            errorResponse('not_found', 'Unknown endpoint', 404);
    }

    errorResponse('not_found', 'Unknown endpoint', 404);
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Battleship Client</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="app-shell">
    <header class="hero">
      <div>
        <p class="eyebrow">CPSC 3750 · Phase 2</p>
        <h1>Battleship Client</h1>
        <p class="sub">Register, join open games, place 3 ships, fire on your turn, track move history, and watch all boards update live.</p>
      </div>
      <div class="hero-card">
        <div class="small-label">Current player</div>
        <div id="identityName" class="identity-name">Not registered</div>
        <div id="identityMeta" class="muted">Stored in localStorage so refreshes keep your player.</div>
      </div>
    </header>

    <div id="message" class="message hidden"></div>

    <main class="layout">
      <section class="panel stack">
        <h2>1. Register player</h2>
        <div class="inline-form">
          <input id="usernameInput" type="text" maxlength="30" placeholder="Enter username (letters, numbers, underscore)">
          <button id="registerBtn">Register</button>
        </div>
        <p class="muted">The client stores your player id locally so you stay signed in after refresh.</p>
      </section>

      <section class="panel stack">
        <div class="section-head">
          <h2>2. Lobby</h2>
          <button id="refreshLobbyBtn" class="secondary">Refresh now</button>
        </div>
        <div class="inline-form compact">
          <label>Grid size <input id="gridSizeInput" type="number" min="5" max="15" value="5"></label>
          <label>Max players <input id="maxPlayersInput" type="number" min="2" max="10" value="2"></label>
          <button id="createGameBtn">Create game</button>
        </div>
        <div id="lobbyList" class="list-grid"></div>
      </section>

      <section class="panel stack game-panel">
        <div class="section-head">
          <h2>3. Active game</h2>
          <div id="turnBadge" class="badge">No game selected</div>
        </div>
        <div id="gameSummary" class="summary-box muted">Join or create a game to begin.</div>

        <div class="placement-bar">
          <div>
            <strong>Place exactly 3 ships.</strong>
            <span class="muted">Click your board to choose cells, then confirm.</span>
          </div>
          <button id="submitShipsBtn" class="secondary">Confirm ships</button>
        </div>

        <div class="boards-wrap">
          <div>
            <h3>Your board</h3>
            <div id="playerBoard" class="board"></div>
          </div>
          <div>
            <h3>Opponent boards</h3>
            <div id="opponentBoards" class="opponents-grid"></div>
          </div>
        </div>
      </section>

      <section class="panel stack two-col">
        <div>
          <div class="section-head">
            <h2>4. Move history</h2>
            <span class="muted">With timestamps</span>
          </div>
          <div id="moveHistory" class="history-list"></div>
        </div>
        <div>
          <div class="section-head">
            <h2>5. Leaderboard</h2>
            <button id="refreshLeaderboardBtn" class="secondary">Refresh</button>
          </div>
          <div id="leaderboard" class="history-list"></div>
        </div>
      </section>
    </main>
  </div>
  <script src="app.js"></script>
</body>
</html>
