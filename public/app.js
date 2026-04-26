const state = {
  playerId: Number(localStorage.getItem('battleship_player_id') || 0),
  username: localStorage.getItem('battleship_username') || '',
  selectedGameId: Number(localStorage.getItem('battleship_game_id') || 0),
  apiBaseUrl: localStorage.getItem('battleship_api_base_url') || '',
  defaultApiBaseUrl: '',
  theme: localStorage.getItem('battleship_theme') || 'dark',
  selectedShips: [],
  knownGameIds: JSON.parse(localStorage.getItem('battleship_known_game_ids') || '[]').filter((id) => Number.isInteger(id) && id > 0),
  shipCache: JSON.parse(localStorage.getItem('battleship_ship_cache') || '{}'),
  playerNames: {},
  shownGameOver: JSON.parse(sessionStorage.getItem('battleship_shown_game_over') || '[]'),
  lobbyTimer: null,
};

const els = {
  message: document.getElementById('message'),
  serverDisplay: document.getElementById('serverDisplay'),
  serverUrlInput: document.getElementById('serverUrlInput'),
  saveServerBtn: document.getElementById('saveServerBtn'),
  resetServerBtn: document.getElementById('resetServerBtn'),
  tabButtons: document.querySelectorAll('.tab-btn'),
  pages: document.querySelectorAll('.page'),
  registerSection: document.getElementById('registerSection'),
  usernameInput: document.getElementById('usernameInput'),
  registerBtn: document.getElementById('registerBtn'),
  themeToggleBtn: document.getElementById('themeToggleBtn'),
  logoutBtn: document.getElementById('logoutBtn'),
  gamesSection: document.getElementById('gamesSection'),
  createGameBtn: document.getElementById('createGameBtn'),
  refreshLobbyBtn: document.getElementById('refreshLobbyBtn'),
  gridSizeInput: document.getElementById('gridSizeInput'),
  maxPlayersInput: document.getElementById('maxPlayersInput'),
  gameIdInput: document.getElementById('gameIdInput'),
  joinGameBtn: document.getElementById('joinGameBtn'),
  availableGamesWrap: document.getElementById('availableGamesWrap'),
  availableGamesSelect: document.getElementById('availableGamesSelect'),
  joinAvailableGameBtn: document.getElementById('joinAvailableGameBtn'),
  myGamesList: document.getElementById('myGamesList'),
  joinableGamesList: document.getElementById('joinableGamesList'),
  identityName: document.getElementById('identityName'),
  identityMeta: document.getElementById('identityMeta'),
  activeGameSection: document.getElementById('activeGameSection'),
  gameSummary: document.getElementById('gameSummary'),
  turnBadge: document.getElementById('turnBadge'),
  placementBar: document.getElementById('placementBar'),
  placementTitle: document.getElementById('placementTitle'),
  placementHint: document.getElementById('placementHint'),
  playerBoard: document.getElementById('playerBoard'),
  opponentBoards: document.getElementById('opponentBoards'),
  submitShipsBtn: document.getElementById('submitShipsBtn'),
  moveHistory: document.getElementById('moveHistory'),
  leaderboard: document.getElementById('leaderboard'),
  refreshLeaderboardBtn: document.getElementById('refreshLeaderboardBtn'),
  gameOverModal: document.getElementById('gameOverModal'),
  gameOverText: document.getElementById('gameOverText'),
  closeGameOverBtn: document.getElementById('closeGameOverBtn'),
};

function normalizeApiBaseUrl(value) {
  return String(value || '').trim().replace(/\/+$/, '');
}

function buildApiUrl(path) {
  const cleanPath = String(path).replace(/^\/+/, '').replace(/^api\/?/, '');
  const base = normalizeApiBaseUrl(state.apiBaseUrl || state.defaultApiBaseUrl || 'api');
  return `${base}/${cleanPath}`;
}

function api(path, options = {}) {
  return fetch(buildApiUrl(path), {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  }).then(async (res) => {
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { message: text || 'Unexpected response' }; }
    if (!res.ok) {
      throw new Error(data.message || 'Request failed');
    }
    return data;
  });
}

async function loadDefaultServerUrl() {
  if (window.DEFAULT_API_BASE_URL) {
    state.defaultApiBaseUrl = normalizeApiBaseUrl(window.DEFAULT_API_BASE_URL);
  }
  const candidates = ['../base_url.txt', 'base_url.txt', '/base_url.txt'];
  for (const url of candidates) {
    if (state.defaultApiBaseUrl) break;
    try {
      const res = await fetch(url, { cache: 'no-store' });
      if (res.ok) {
        const text = (await res.text()).trim();
        if (text) {
          state.defaultApiBaseUrl = normalizeApiBaseUrl(`${text}/api`);
          break;
        }
      }
    } catch {
      // Try the next likely path.
    }
  }
  if (!state.defaultApiBaseUrl) {
    state.defaultApiBaseUrl = normalizeApiBaseUrl(`${window.location.origin}${window.location.pathname.replace(/\/[^/]*$/, '')}/api`);
  }
  if (!state.apiBaseUrl) {
    state.apiBaseUrl = state.defaultApiBaseUrl;
  }
  renderServerBanner();
}

function renderServerBanner() {
  els.serverDisplay.textContent = state.apiBaseUrl || state.defaultApiBaseUrl || 'No server selected';
  els.serverUrlInput.value = state.apiBaseUrl || '';
}

function applyTheme() {
  const light = state.theme === 'light';
  document.body.classList.toggle('light', light);
  els.themeToggleBtn.textContent = light ? 'Dark mode' : 'Light mode';
}

function toggleTheme() {
  state.theme = state.theme === 'light' ? 'dark' : 'light';
  localStorage.setItem('battleship_theme', state.theme);
  applyTheme();
}

function saveServerUrl() {
  const nextUrl = normalizeApiBaseUrl(els.serverUrlInput.value);
  if (!nextUrl) {
    showMessage('Enter a server URL ending in /api.', 'error');
    return;
  }
  state.apiBaseUrl = nextUrl;
  localStorage.setItem('battleship_api_base_url', nextUrl);
  renderServerBanner();
  showMessage(`Connected server set to ${nextUrl}.`, 'success');
  reloadData();
}

function resetServerUrl() {
  state.apiBaseUrl = state.defaultApiBaseUrl;
  localStorage.removeItem('battleship_api_base_url');
  renderServerBanner();
  showMessage(`Connected server reset to ${state.apiBaseUrl}.`, 'success');
  reloadData();
}

function showPage(pageId) {
  els.pages.forEach((page) => page.classList.toggle('active', page.id === pageId));
  els.tabButtons.forEach((button) => button.classList.toggle('active', button.dataset.page === pageId));
}

function showMessage(text, type = 'info') {
  els.message.textContent = text;
  els.message.className = `message ${type}`;
}

function clearMessage() {
  els.message.className = 'message hidden';
  els.message.textContent = '';
}

function persistKnownGames() {
  localStorage.setItem('battleship_known_game_ids', JSON.stringify(state.knownGameIds));
}

function persistShipCache() {
  localStorage.setItem('battleship_ship_cache', JSON.stringify(state.shipCache));
}

function persistShownGameOver() {
  sessionStorage.setItem('battleship_shown_game_over', JSON.stringify(state.shownGameOver));
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));
}

function playerName(playerId) {
  if (!playerId) return 'waiting';
  return state.playerNames[playerId] || `Player #${playerId}`;
}

function describeStatus(game, joined, myTurn, winnerId) {
  if (!game) return 'No game selected';
  if (game.status === 'waiting_setup') return 'Waiting for players to place ships';
  if (game.status === 'finished') return winnerId ? `Finished. Winner: ${playerName(winnerId)}` : 'Finished. No winner';
  if (!joined) return `In progress. Current turn: ${playerName(game.current_turn_player_id)}`;
  return myTurn ? `Your turn, ${state.username}` : `${playerName(game.current_turn_player_id)}'s turn`;
}

function shipCacheKey(gameId, playerId) {
  return `${gameId}:${playerId}`;
}

function getStoredShips(gameId = state.selectedGameId, playerId = state.playerId) {
  if (!gameId || !playerId) return [];
  const ships = state.shipCache[shipCacheKey(gameId, playerId)];
  return Array.isArray(ships) ? ships : [];
}

function storeShips(gameId, playerId, ships) {
  if (!gameId || !playerId) return;
  state.shipCache[shipCacheKey(gameId, playerId)] = ships.map((ship) => ({ row: ship.row, col: ship.col }));
  persistShipCache();
}

function rememberGame(gameId) {
  const id = Number(gameId);
  if (!Number.isInteger(id) || id <= 0) return;
  state.knownGameIds = [id, ...state.knownGameIds.filter((existingId) => existingId !== id)].slice(0, 12);
  persistKnownGames();
}

function forgetGame(gameId) {
  state.knownGameIds = state.knownGameIds.filter((id) => id !== gameId);
  persistKnownGames();
}

function getRequestedGameId() {
  const gameId = Number(els.gameIdInput.value);
  if (!Number.isInteger(gameId) || gameId <= 0) {
    showMessage('Enter a valid game ID.', 'error');
    return 0;
  }
  return gameId;
}

function setIdentity(playerId, username) {
  state.playerId = playerId;
  state.username = username;
  localStorage.setItem('battleship_player_id', String(playerId));
  localStorage.setItem('battleship_username', username);
  renderIdentity();
}

function renderIdentity() {
  if (state.playerId) {
    els.identityName.textContent = state.username;
    els.identityMeta.textContent = 'Saved locally.';
    els.logoutBtn.classList.remove('hidden');
  } else {
    els.identityName.textContent = 'Not registered';
    els.identityMeta.textContent = 'Register or login.';
    els.logoutBtn.classList.add('hidden');
  }
}

function logoutPlayer() {
  state.playerId = 0;
  state.username = '';
  state.selectedShips = [];
  localStorage.removeItem('battleship_player_id');
  localStorage.removeItem('battleship_username');
  renderIdentity();
  updateUiState();
  showPage('setupPage');
  showMessage('Logged out. Choose or create a player to continue.', 'success');
  if (state.selectedGameId) loadSelectedGame();
}

function setHidden(element, hidden) {
  element.classList.toggle('hidden', hidden);
}

function updateUiState(game = null) {
  const hasIdentity = Boolean(state.playerId);
  const joined = game ? game.players.some((player) => player.player_id === state.playerId) : false;
  const storedShips = getStoredShips(game ? game.game_id : 0, state.playerId);
  const hasPlacedShips = storedShips.length === 3;
  const canPlaceShips = Boolean(game && joined && game.status === 'waiting_setup' && !hasPlacedShips);

  setHidden(els.registerSection, false);
  setHidden(els.gamesSection, false);
  setHidden(els.activeGameSection, false);
  setHidden(els.placementBar, !canPlaceShips);

  els.createGameBtn.disabled = !hasIdentity;
  els.joinGameBtn.disabled = !hasIdentity;
  els.joinAvailableGameBtn.disabled = !hasIdentity || !els.availableGamesSelect.value;
  els.refreshLobbyBtn.disabled = !hasIdentity;

  if (!game || !joined) {
    els.submitShipsBtn.disabled = true;
    return;
  }

  els.submitShipsBtn.disabled = !canPlaceShips || state.selectedShips.length !== 3;
  if (canPlaceShips) {
    els.placementTitle.textContent = 'Place exactly 3 ships.';
    els.placementHint.textContent = state.selectedShips.length
      ? `${state.selectedShips.length}/3 selected. Click your board to finish placement.`
      : 'Click your board to choose three ship cells, then confirm.';
  }
}

async function registerPlayer() {
  const username = els.usernameInput.value.trim();
  if (!username) {
    showMessage('Enter a username before registering.', 'error');
    return;
  }
  try {
    const data = await api('api/players', {
      method: 'POST',
      body: JSON.stringify({ username }),
    });
    setIdentity(data.player_id, username);
    els.usernameInput.value = '';
    showMessage(`Registered ${username} successfully.`, 'success');
    showPage('gamesPage');
    await loadLeaderboard();
    loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
    updateUiState();
  } catch (err) {
    if (/username already/i.test(err.message)) {
      try {
        const existingPlayer = await findPlayerByUsername(username);
        setIdentity(existingPlayer.player_id, existingPlayer.username);
        els.usernameInput.value = '';
        showMessage(`Logged in as ${existingPlayer.username}.`, 'success');
        showPage('gamesPage');
        await loadLobby();
        updateUiState();
        return;
      } catch (lookupErr) {
        showMessage(`Username exists, but the client could not find its player id: ${lookupErr.message}`, 'error');
        return;
      }
    }
    showMessage(err.message, 'error');
  }
}

async function findPlayerByUsername(username) {
  const players = await loadLeaderboard();
  const match = players.find((player) => player.username === username);
  if (!match) throw new Error('player was not present in the leaderboard response');
  return match;
}

async function createGame() {
  if (!state.playerId) {
    showMessage('Register a player first.', 'error');
    return;
  }
  try {
    const payload = {
      creator_id: state.playerId,
      grid_size: Number(els.gridSizeInput.value),
      max_players: Number(els.maxPlayersInput.value),
    };
    const data = await api('api/games', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    state.selectedGameId = data.game_id;
    localStorage.setItem('battleship_game_id', String(data.game_id));
    rememberGame(data.game_id);
    els.gameIdInput.value = String(data.game_id);
    state.selectedShips = [];
    showMessage(`Game #${data.game_id} created. Waiting for players.`, 'success');
    showPage('playPage');
    await loadLobby();
    await loadSelectedGame();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}

async function joinGame(gameId) {
  if (!state.playerId) {
    showMessage('Register a player first.', 'error');
    return;
  }
  try {
    await api(`api/games/${gameId}/join`, {
      method: 'POST',
      body: JSON.stringify({ player_id: state.playerId }),
    });
    state.selectedGameId = gameId;
    localStorage.setItem('battleship_game_id', String(gameId));
    rememberGame(gameId);
    els.gameIdInput.value = String(gameId);
    state.selectedShips = [];
    showMessage(`Joined game #${gameId}.`, 'success');
    showPage('playPage');
    await loadLobby();
    await loadSelectedGame();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}

async function joinEnteredGame() {
  const gameId = getRequestedGameId();
  if (!gameId) return;
  await joinGame(gameId);
}

async function joinSelectedAvailableGame() {
  const gameId = Number(els.availableGamesSelect.value);
  if (!Number.isInteger(gameId) || gameId <= 0) {
    showMessage('Choose a game or enter an ID.', 'error');
    return;
  }
  await joinGame(gameId);
}

async function loadLobby() {
  const [myGames, joinableGames] = await Promise.all([
    loadMyGames(),
    loadAvailableGames(),
  ]);
  renderMyGames(myGames);
  renderJoinableGames(joinableGames);
  renderAvailableGames(joinableGames);
  updateUiState();
}

async function loadMyGames() {
  if (!state.playerId) return [];
  try {
    const games = await api(`api/games?player_id=${state.playerId}`);
    if (!Array.isArray(games)) return [];
    games.forEach((game) => rememberGame(game.game_id));
    return games;
  } catch {
    return loadKnownGames();
  }
}

async function loadKnownGames() {
  if (!state.knownGameIds.length) return [];
  const results = await Promise.all(state.knownGameIds.map(async (gameId) => {
    try {
      return await api(`api/games/${gameId}`);
    } catch (err) {
      if (err.message === 'Game does not exist') {
        forgetGame(gameId);
        if (state.selectedGameId === gameId) {
          state.selectedGameId = 0;
          localStorage.removeItem('battleship_game_id');
        }
        return null;
      }
      throw new Error(`Game #${gameId}: ${err.message}`);
    }
  }));

  return results
    .filter(Boolean)
    .filter((game) => game.players.some((player) => player.player_id === state.playerId));
}

async function loadAvailableGames() {
  try {
    const games = await api('api/games');
    if (!Array.isArray(games)) return [];
    games.forEach((game) => rememberGame(game.game_id));
    return games;
  } catch {
    return [];
  }
}

function renderAvailableGames(games) {
  const joinableGames = games.filter((game) => {
    const joined = game.players.some((player) => player.player_id === state.playerId);
    return game.status === 'waiting_setup' && !joined;
  });

  setHidden(els.availableGamesWrap, joinableGames.length === 0);
  els.availableGamesSelect.innerHTML = joinableGames.map((game) => {
    const players = game.players.length;
    return `<option value="${game.game_id}">Game #${game.game_id} · ${players}/${game.max_players || '?'} players</option>`;
  }).join('');
  els.joinAvailableGameBtn.disabled = !state.playerId || joinableGames.length === 0;
}

function renderMyGames(games) {
  if (!games.length) {
    els.myGamesList.innerHTML = '<div class="empty-card">No games yet.</div>';
    return;
  }
  els.myGamesList.innerHTML = games.map((game) => renderGameCard(game, 'mine')).join('');
}

function renderJoinableGames(games) {
  const joinableGames = games.filter((game) => {
    const joined = game.players.some((player) => player.player_id === state.playerId);
    return game.status === 'waiting_setup' && !joined;
  });

  if (!joinableGames.length) {
    els.joinableGamesList.innerHTML = '<div class="empty-card">No joinable games.</div>';
    return;
  }
  els.joinableGamesList.innerHTML = joinableGames.map((game) => renderGameCard(game, 'joinable')).join('');
}

function renderGameCard(game, mode) {
  const currentName = playerName(game.current_turn_player_id);
  const players = game.max_players ? `${game.players.length}/${game.max_players}` : String(game.players.length);
  return `
    <div class="game-card ${state.selectedGameId === game.game_id ? 'active' : ''}">
      <div class="section-head">
        <strong>Game #${game.game_id}</strong>
        <span class="badge">${game.status}</span>
      </div>
      <div class="muted">Grid: ${game.grid_size} × ${game.grid_size}</div>
      <div class="muted">Players: ${players}</div>
      <div class="muted">Turn: ${escapeHtml(game.status === 'playing' ? currentName : 'not started')}</div>
      <div class="card-actions">
        ${mode === 'mine' ? `<button onclick="selectGame(${game.game_id})" class="secondary">Open</button>` : ''}
        ${mode === 'joinable' ? `<button onclick="joinGame(${game.game_id})">Join</button>` : ''}
      </div>
    </div>`;
}

window.joinGame = joinGame;
window.selectGame = async function (gameId) {
  state.selectedGameId = gameId;
  localStorage.setItem('battleship_game_id', String(gameId));
  rememberGame(gameId);
  els.gameIdInput.value = String(gameId);
  state.selectedShips = [];
  showPage('playPage');
  await loadSelectedGame();
};

function toggleShipSelection(row, col, alreadyPlaced) {
  if (alreadyPlaced) {
    showMessage('Ships are already locked in for this game.', 'error');
    return;
  }
  const key = `${row}:${col}`;
  const index = state.selectedShips.findIndex((s) => `${s.row}:${s.col}` === key);
  if (index >= 0) {
    state.selectedShips.splice(index, 1);
  } else {
    if (state.selectedShips.length >= 3) {
      showMessage('You can only choose 3 ship cells.', 'error');
      return;
    }
    state.selectedShips.push({ row, col });
  }
  loadSelectedGame();
}
window.toggleShipSelection = toggleShipSelection;

async function submitShips() {
  if (!state.selectedGameId || !state.playerId) {
    showMessage('Select a game first.', 'error');
    return;
  }
  if (state.selectedShips.length !== 3) {
    showMessage('Choose exactly 3 ships before confirming.', 'error');
    return;
  }
  try {
    await api(`api/games/${state.selectedGameId}/place`, {
      method: 'POST',
      body: JSON.stringify({ player_id: state.playerId, ships: state.selectedShips }),
    });
    storeShips(state.selectedGameId, state.playerId, state.selectedShips);
    state.selectedShips = [];
    showMessage('Ships placed successfully.', 'success');
    await loadSelectedGame();
    await loadLobby();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}

async function fireAt(row, col, allowed) {
  if (!allowed) {
    showMessage('You can only fire on your turn.', 'error');
    return;
  }
  try {
    const data = await api(`api/games/${state.selectedGameId}/fire`, {
      method: 'POST',
      body: JSON.stringify({ player_id: state.playerId, row, col }),
    });
    if (data.game_status === 'finished') {
      showMessage(data.winner_id ? `Game over. Winner: ${playerName(data.winner_id)}.` : 'Game over. No winner.', 'success');
      showGameOver(data.winner_id);
    } else {
      showMessage(`Shot result: ${data.result}. Next player: ${playerName(data.next_player_id)}.`, 'success');
    }
    await loadSelectedGame();
    await loadLobby();
    await loadLeaderboard();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}
window.fireAt = fireAt;

function showGameOver(winnerId) {
  els.gameOverText.textContent = winnerId ? `Winner: ${playerName(winnerId)}.` : 'No winner.';
  els.gameOverModal.classList.remove('hidden');
}

async function loadSelectedGame() {
  if (!state.selectedGameId) {
    els.gameSummary.textContent = 'Join or create a game to begin.';
    els.turnBadge.textContent = 'No game selected';
    els.playerBoard.innerHTML = '';
    els.opponentBoards.innerHTML = '';
    els.moveHistory.innerHTML = '';
    updateUiState();
    return;
  }
  try {
    const [game, moves] = await Promise.all([
      api(`api/games/${state.selectedGameId}`),
      api(`api/games/${state.selectedGameId}/moves`),
    ]);
    await syncOwnShips(game);
    renderGame(game, moves);
  } catch (err) {
    updateUiState();
    showMessage(`Could not load game: ${err.message}`, 'error');
  }
}

async function syncOwnShips(game) {
  if (!state.playerId || !game.players.some((player) => player.player_id === state.playerId)) return;
  try {
    const ships = await api(`api/games/${game.game_id}/ships?player_id=${state.playerId}`);
    if (Array.isArray(ships)) {
      storeShips(game.game_id, state.playerId, ships);
    }
  } catch {
    // Other teams' servers may not expose this helper; keep using local cache.
  }
}

function createBoardCells(gridSize, stateByKey = {}) {
  const cells = [];
  for (let row = 0; row < gridSize; row += 1) {
    for (let col = 0; col < gridSize; col += 1) {
      const cellData = stateByKey[`${row}:${col}`];
      if (cellData && typeof cellData === 'object') {
        cells.push({ row, col, state: cellData.state || 'empty', locked: Boolean(cellData.locked) });
      } else {
        cells.push({ row, col, state: cellData || 'empty', locked: false });
      }
    }
  }
  return cells;
}

function inferWinnerId(game) {
  if (game.status !== 'finished') return null;
  const survivors = game.players.filter((player) => player.ships_remaining > 0);
  return survivors.length === 1 ? survivors[0].player_id : null;
}

function buildOwnBoard(game, joined, moves) {
  if (!joined || !state.playerId) return null;

  const shipMap = {};
  const storedShips = getStoredShips(game.game_id, state.playerId);
  storedShips.forEach((ship) => {
    shipMap[`${ship.row}:${ship.col}`] = 'ship';
  });
  if (game.status === 'waiting_setup' && !storedShips.length) {
    state.selectedShips.forEach((ship) => {
      shipMap[`${ship.row}:${ship.col}`] = 'ship';
    });
  }

  const otherPlayers = game.players.filter((player) => player.player_id !== state.playerId);
  const exactIncoming = otherPlayers.length === 1;
  moves
    .forEach((move) => {
      const key = `${move.row}:${move.col}`;
      if (shipMap[key] === 'ship' && move.result === 'hit') {
        shipMap[key] = 'hit';
        return;
      }
      if (move.player_id !== state.playerId && exactIncoming && move.result === 'miss' && !shipMap[key]) {
        shipMap[key] = 'miss';
      }
    });

  return {
    cells: createBoardCells(game.grid_size, shipMap),
    canPlaceShips: game.status === 'waiting_setup' && storedShips.length === 0,
    hasStoredShips: storedShips.length === 3,
    exactIncoming,
  };
}

function buildTargetBoard(game, moves) {
  const shotMap = {};
  moves.forEach((move) => {
    shotMap[`${move.row}:${move.col}`] = {
      state: move.result,
      locked: true,
    };
  });
  return createBoardCells(game.grid_size, shotMap);
}

function renderGame(game, moves) {
  const myTurn = game.current_turn_player_id === state.playerId;
  const joined = game.players.some((p) => p.player_id === state.playerId);
  const winnerId = inferWinnerId(game);
  const ownBoard = buildOwnBoard(game, joined, moves);
  const targetBoard = buildTargetBoard(game, moves);
  updateUiState(game);
  els.turnBadge.textContent = describeStatus(game, joined, myTurn, winnerId);

  const boardMessage = !joined
    ? 'Join to play.'
    : ownBoard && ownBoard.hasStoredShips
      ? ownBoard.exactIncoming
        ? 'Ships, hits, and misses.'
        : 'Ships and confirmed hits.'
      : game.status === 'waiting_setup'
        ? 'Place three ships.'
        : 'Ships are stored on this device.';

  const currentTurn = game.status === 'playing' ? playerName(game.current_turn_player_id) : 'Not active';
  els.gameSummary.innerHTML = `
    <div class="state-grid">
      <div class="state-card"><strong>Game</strong><span>#${game.game_id}</span></div>
      <div class="state-card"><strong>Status</strong><span>${escapeHtml(describeStatus(game, joined, myTurn, winnerId))}</span></div>
      <div class="state-card"><strong>Current turn</strong><span>${escapeHtml(currentTurn)}</span></div>
      <div class="state-card"><strong>Moves</strong><span>${game.total_moves}</span></div>
    </div>
    <div class="muted">Grid size: ${game.grid_size} × ${game.grid_size}</div>
    <div class="muted">Players: ${game.players.map((p) => `${escapeHtml(playerName(p.player_id))} (${p.ships_remaining} ships left)`).join(', ')}</div>
    <div class="muted">${escapeHtml(boardMessage)}</div>
  `;

  els.playerBoard.innerHTML = ownBoard
    ? renderBoardHtml(ownBoard.cells, game.grid_size, { own: true, alreadyPlaced: !ownBoard.canPlaceShips })
    : '<div class="empty-card">Join to play.</div>';

  els.opponentBoards.innerHTML = joined ? `
    <div class="opponent-card">
      <div class="section-head"><strong>Target Grid</strong><span class="muted">All shots</span></div>
      ${renderBoardHtml(targetBoard, game.grid_size, { own: false, fireEnabled: myTurn && game.status === 'playing' })}
    </div>
  ` : '<div class="empty-card">Join to fire.</div>';

  els.moveHistory.innerHTML = moves.length ? moves.map((move) => `
    <div class="history-item">
      <strong>Move ${move.move_number}</strong>
      <div>${escapeHtml(playerName(move.player_id))} fired at (${move.row}, ${move.col})</div>
      <div class="muted">${move.result} · ${move.timestamp}</div>
    </div>
  `).join('') : '<div class="empty-card">No moves yet.</div>';

  const gameOverKey = String(game.game_id);
  if (game.status === 'finished' && !state.shownGameOver.includes(gameOverKey)) {
    state.shownGameOver.push(gameOverKey);
    persistShownGameOver();
    showGameOver(winnerId);
  }
}

function renderBoardHtml(cells, gridSize, options = {}) {
  const selectedMap = new Set(state.selectedShips.map((s) => `${s.row}:${s.col}`));
  const style = `grid-template-columns: repeat(${gridSize}, minmax(0, 1fr));`;
  const html = cells.map((cell) => {
    let cls = `cell ${cell.state}`;
    let action = '';
    if (options.own) {
      const key = `${cell.row}:${cell.col}`;
      if (selectedMap.has(key)) cls += ' selected';
      action = `onclick="toggleShipSelection(${cell.row}, ${cell.col}, ${options.alreadyPlaced ? 'true' : 'false'})"`;
    } else if (options.fireEnabled) {
      action = `onclick="fireAt(${cell.row}, ${cell.col}, true)"`;
      if (cell.locked) {
        action = `onclick="showMessage('That cell has already been fired on.', 'error')"`;
      }
    }
    return `<button class="${cls}" ${action} title="${cell.row},${cell.col}"></button>`;
  }).join('');
  return cells.length
    ? `<div class="board-grid" style="${style}">${html}</div>`
    : '<div class="empty-card">Board unavailable.</div>';
}

async function loadLeaderboard() {
  try {
    const players = await api('api/leaderboard');
    state.playerNames = players.reduce((names, player) => {
      names[player.player_id] = player.username;
      return names;
    }, {});
    els.leaderboard.innerHTML = players.length ? players.map((p, i) => `
      <div class="history-item">
        <strong>#${i + 1} ${escapeHtml(p.username)}</strong>
        <div>Wins: ${p.wins} · Losses: ${p.losses} · Games: ${p.games_played}</div>
        <div class="muted">Accuracy: ${(p.accuracy * 100).toFixed(1)}%</div>
      </div>
    `).join('') : '<div class="empty-card">No player stats yet.</div>';
    return players;
  } catch (err) {
    showMessage(`Could not load leaderboard: ${err.message}`, 'error');
    return [];
  }
}

function reloadData() {
  loadLeaderboard().then(() => {
    loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
    if (state.selectedGameId) loadSelectedGame();
  });
}

function startAutoRefresh() {
  if (state.lobbyTimer) clearInterval(state.lobbyTimer);
  state.lobbyTimer = setInterval(() => {
    loadLobby().catch((err) => showMessage(`Could not refresh games: ${err.message}`, 'error'));
    loadLeaderboard();
    if (state.selectedGameId) loadSelectedGame();
  }, 3000);
}

els.registerBtn.addEventListener('click', registerPlayer);
els.themeToggleBtn.addEventListener('click', toggleTheme);
els.logoutBtn.addEventListener('click', logoutPlayer);
els.saveServerBtn.addEventListener('click', saveServerUrl);
els.resetServerBtn.addEventListener('click', resetServerUrl);
els.tabButtons.forEach((button) => button.addEventListener('click', () => showPage(button.dataset.page)));
els.createGameBtn.addEventListener('click', createGame);
els.refreshLobbyBtn.addEventListener('click', () => {
  loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
});
els.joinGameBtn.addEventListener('click', joinEnteredGame);
els.joinAvailableGameBtn.addEventListener('click', joinSelectedAvailableGame);
els.submitShipsBtn.addEventListener('click', submitShips);
els.refreshLeaderboardBtn.addEventListener('click', loadLeaderboard);
els.closeGameOverBtn.addEventListener('click', () => els.gameOverModal.classList.add('hidden'));

async function init() {
  applyTheme();
  renderIdentity();
  await loadDefaultServerUrl();
  if (state.selectedGameId) {
    rememberGame(state.selectedGameId);
    els.gameIdInput.value = String(state.selectedGameId);
    showPage('playPage');
  }
  updateUiState();
  await loadLeaderboard();
  loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
  if (state.selectedGameId) loadSelectedGame();
  startAutoRefresh();
}

init();
