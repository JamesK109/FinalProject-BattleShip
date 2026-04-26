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
        <p class="sub">Connect. Join. Play.</p>
      </div>
      <div class="hero-card">
        <div class="small-label">Current player</div>
        <div id="identityName" class="identity-name">Not registered</div>
        <div id="identityMeta" class="muted">Saved locally.</div>
        <div class="identity-actions">
          <button id="themeToggleBtn" class="ghost" type="button">Light mode</button>
          <button id="logoutBtn" class="ghost hidden" type="button">Logout</button>
        </div>
      </div>
    </header>

    <div class="sticky-bar">
      <section class="server-banner">
        <div>
          <div class="small-label">Connected server</div>
          <div id="serverDisplay" class="server-url">Loading default server...</div>
        </div>
        <div class="server-controls">
          <input id="serverUrlInput" type="url" placeholder="https://example.com/api">
          <button id="saveServerBtn" class="secondary">Use server</button>
          <button id="resetServerBtn" class="ghost">Reset</button>
        </div>
      </section>

      <nav class="page-tabs" aria-label="Application sections">
        <button class="tab-btn active" data-page="setupPage">Player</button>
        <button class="tab-btn" data-page="gamesPage">Games</button>
        <button class="tab-btn" data-page="playPage">Play</button>
        <button class="tab-btn" data-page="statsPage">Stats</button>
      </nav>
    </div>

    <div id="message" class="message hidden"></div>

    <main class="layout">
      <section id="setupPage" class="page active">
      <section id="registerSection" class="panel stack">
        <h2>Player</h2>
        <div class="inline-form">
          <input id="usernameInput" type="text" maxlength="30" placeholder="Enter username (letters, numbers, underscore)">
          <button id="registerBtn">Register or Login</button>
        </div>
      </section>
      </section>

      <section id="gamesPage" class="page">
      <section id="gamesSection" class="panel stack">
        <div class="section-head">
          <h2>Games</h2>
          <button id="refreshLobbyBtn" class="secondary">Refresh</button>
        </div>
        <div class="inline-form compact">
          <label>Grid size <input id="gridSizeInput" type="number" min="5" max="15" value="5"></label>
          <label>Max players <input id="maxPlayersInput" type="number" min="2" max="10" value="2"></label>
          <button id="createGameBtn">Create game</button>
        </div>
        <div class="inline-form compact">
          <label>Game ID <input id="gameIdInput" type="number" min="1" step="1" placeholder="Enter game id"></label>
          <button id="joinGameBtn">Join game</button>
        </div>
        <div id="availableGamesWrap" class="inline-form compact hidden">
          <label>Available <select id="availableGamesSelect"></select></label>
          <button id="joinAvailableGameBtn" class="secondary">Join selected</button>
        </div>
        <div id="lobbyList" class="list-grid"></div>
      </section>
      </section>

      <section id="playPage" class="page">
      <section id="activeGameSection" class="panel stack game-panel">
        <div class="section-head">
          <h2>Active game</h2>
          <div id="turnBadge" class="badge">No game selected</div>
        </div>
        <div id="gameSummary" class="summary-box muted">Join or create a game to begin.</div>

        <div id="placementBar" class="placement-bar">
          <div>
            <strong id="placementTitle">Place exactly 3 ships.</strong>
            <span id="placementHint" class="muted">Click your board to choose cells, then confirm.</span>
          </div>
          <button id="submitShipsBtn" class="secondary">Confirm ships</button>
        </div>

        <div class="boards-wrap">
          <div class="board-panel">
            <h3>Your board</h3>
            <div id="playerBoard" class="board"></div>
          </div>
          <div class="board-panel">
            <h3>Target board</h3>
            <div id="opponentBoards" class="opponents-grid"></div>
          </div>
        </div>
      </section>
      </section>

      <section id="statsPage" class="page">
      <section class="panel stack two-col">
        <div>
          <div class="section-head">
            <h2>Move history</h2>
            <span class="muted">With timestamps</span>
          </div>
          <div id="moveHistory" class="history-list"></div>
        </div>
        <div>
          <div class="section-head">
            <h2>Leaderboard</h2>
            <button id="refreshLeaderboardBtn" class="secondary">Refresh</button>
          </div>
          <div id="leaderboard" class="history-list"></div>
        </div>
      </section>
      </section>
    </main>
  </div>
  <div id="gameOverModal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="gameOverTitle">
    <div class="modal">
      <h2 id="gameOverTitle">Game finished</h2>
      <p id="gameOverText" class="sub"></p>
      <button id="closeGameOverBtn">Close</button>
    </div>
  </div>
  <script>
    window.DEFAULT_API_BASE_URL = <?= json_encode(rtrim(trim((string)@file_get_contents(__DIR__ . '/../base_url.txt')), '/') . '/api') ?>;
  </script>
  <script src="app.js"></script>
</body>
</html>
