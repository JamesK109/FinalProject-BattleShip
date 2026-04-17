const state = {
  playerId: Number(localStorage.getItem('battleship_player_id') || 0),
  username: localStorage.getItem('battleship_username') || '',
  selectedGameId: Number(localStorage.getItem('battleship_game_id') || 0),
  selectedShips: [],
  knownGameIds: JSON.parse(localStorage.getItem('battleship_known_game_ids') || '[]').filter((id) => Number.isInteger(id) && id > 0),
  shipCache: JSON.parse(localStorage.getItem('battleship_ship_cache') || '{}'),
  lobbyTimer: null,
};

const els = {
  message: document.getElementById('message'),
  registerSection: document.getElementById('registerSection'),
  usernameInput: document.getElementById('usernameInput'),
  registerBtn: document.getElementById('registerBtn'),
  gamesSection: document.getElementById('gamesSection'),
  createGameBtn: document.getElementById('createGameBtn'),
  refreshLobbyBtn: document.getElementById('refreshLobbyBtn'),
  gridSizeInput: document.getElementById('gridSizeInput'),
  maxPlayersInput: document.getElementById('maxPlayersInput'),
  gameIdInput: document.getElementById('gameIdInput'),
  openGameBtn: document.getElementById('openGameBtn'),
  joinGameBtn: document.getElementById('joinGameBtn'),
  lobbyList: document.getElementById('lobbyList'),
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
};

function api(path, options = {}) {
  return fetch(path, {
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
    els.identityName.textContent = `${state.username} (#${state.playerId})`;
    els.identityMeta.textContent = 'Identity is stored in localStorage for refresh-safe gameplay.';
  } else {
    els.identityName.textContent = 'Not registered';
    els.identityMeta.textContent = 'Register a player to create or join games.';
  }
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
  const canManageGames = hasIdentity;
  const hasActiveGame = Boolean(state.selectedGameId);

  setHidden(els.registerSection, hasIdentity);
  setHidden(els.gamesSection, !canManageGames);
  setHidden(els.activeGameSection, !hasActiveGame);
  setHidden(els.placementBar, !canPlaceShips);

  els.createGameBtn.disabled = !hasIdentity;
  els.openGameBtn.disabled = !hasIdentity;
  els.joinGameBtn.disabled = !hasIdentity;
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
    loadLeaderboard();
    loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
    updateUiState();
  } catch (err) {
    showMessage(err.message, 'error');
  }
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

async function loadLobby() {
  if (!state.knownGameIds.length) {
    renderLobby([]);
    updateUiState();
    return;
  }

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

  renderLobby(results.filter(Boolean));
  updateUiState();
}

function renderLobby(games) {
  if (!games.length) {
    els.lobbyList.innerHTML = '<div class="empty-card">No known games yet. Create one or enter a game ID to open or join it.</div>';
    return;
  }
  els.lobbyList.innerHTML = games.map((game) => {
    const joined = game.players.some((p) => p.player_id === state.playerId);
    const canJoin = game.status === 'waiting_setup' && !joined;
    return `
      <div class="game-card ${state.selectedGameId === game.game_id ? 'active' : ''}">
        <div class="section-head">
          <strong>Game #${game.game_id}</strong>
          <span class="badge">${game.status}</span>
        </div>
        <div class="muted">Grid: ${game.grid_size} × ${game.grid_size}</div>
        <div class="muted">Players joined: ${game.players.length}</div>
        <div class="muted">Moves: ${game.total_moves}</div>
        <div class="card-actions">
          <button onclick="selectGame(${game.game_id})" class="secondary">Open</button>
          ${canJoin ? `<button onclick="joinGame(${game.game_id})">Join</button>` : ''}
        </div>
      </div>`;
  }).join('');
}

window.joinGame = joinGame;
window.selectGame = async function (gameId) {
  state.selectedGameId = gameId;
  localStorage.setItem('battleship_game_id', String(gameId));
  rememberGame(gameId);
  els.gameIdInput.value = String(gameId);
  state.selectedShips = [];
  await loadSelectedGame();
};

async function openEnteredGame() {
  const gameId = getRequestedGameId();
  if (!gameId) return;
  await window.selectGame(gameId);
}

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
      showMessage(`Game over. Winner: player #${data.winner_id}.`, 'success');
    } else {
      showMessage(`Shot result: ${data.result}. Next player: #${data.next_player_id}.`, 'success');
    }
    await loadSelectedGame();
    await loadLobby();
    await loadLeaderboard();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}
window.fireAt = fireAt;

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
    renderGame(game, moves);
  } catch (err) {
    updateUiState();
    showMessage(`Could not load game: ${err.message}`, 'error');
  }
}

function createBoardCells(gridSize, stateByKey = {}) {
  const cells = [];
  for (let row = 0; row < gridSize; row += 1) {
    for (let col = 0; col < gridSize; col += 1) {
      cells.push({ row, col, state: stateByKey[`${row}:${col}`] || 'empty' });
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
    .filter((move) => move.player_id !== state.playerId)
    .forEach((move) => {
      const key = `${move.row}:${move.col}`;
      if (shipMap[key] === 'ship' && move.result === 'hit') {
        shipMap[key] = 'hit';
        return;
      }
      if (exactIncoming && move.result === 'miss' && !shipMap[key]) {
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
  moves.filter((move) => move.player_id === state.playerId).forEach((move) => {
    shotMap[`${move.row}:${move.col}`] = move.result;
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
  els.turnBadge.textContent = !joined
    ? 'Viewing only'
    : game.status === 'finished'
      ? `Winner: #${winnerId ?? 'done'}`
      : myTurn
        ? 'Your turn'
        : `Current turn: #${game.current_turn_player_id ?? 'waiting'}`;

  const boardMessage = !joined
    ? 'Join this game to place ships and fire.'
    : ownBoard && ownBoard.hasStoredShips
      ? ownBoard.exactIncoming
        ? 'Your board shows your ships plus incoming hits and misses.'
        : 'Your board shows your ships and confirmed incoming hits. Misses cannot be assigned exactly in multi-player games from this API contract.'
      : game.status === 'waiting_setup'
        ? 'Choose three ship cells on your board, then confirm placement.'
        : 'Your ship coordinates are only available on the device that placed them.';

  els.gameSummary.innerHTML = `
    <div><strong>Game #${game.game_id}</strong> · ${game.status}</div>
    <div class="muted">Grid size: ${game.grid_size} × ${game.grid_size}</div>
    <div class="muted">Players: ${game.players.map((p) => `#${p.player_id} (${p.ships_remaining} ships left)`).join(', ')}</div>
    <div class="muted">Total moves: ${game.total_moves}</div>
    <div class="muted">${boardMessage}</div>
  `;

  els.playerBoard.innerHTML = ownBoard
    ? renderBoardHtml(ownBoard.cells, game.grid_size, { own: true, alreadyPlaced: !ownBoard.canPlaceShips })
    : '<div class="empty-card">Join this game to place ships and play.</div>';

  els.opponentBoards.innerHTML = joined ? `
    <div class="opponent-card">
      <div class="section-head"><strong>Target Grid</strong><span class="muted">Your shots only</span></div>
      ${renderBoardHtml(targetBoard, game.grid_size, { own: false, fireEnabled: myTurn && game.status === 'playing' })}
    </div>
  ` : '<div class="empty-card">Join this game to fire shots.</div>';

  els.moveHistory.innerHTML = moves.length ? moves.map((move) => `
    <div class="history-item">
      <strong>Move ${move.move_number}</strong>
      <div>Player #${move.player_id} fired at (${move.row}, ${move.col})</div>
      <div class="muted">${move.result} · ${move.timestamp}</div>
    </div>
  `).join('') : '<div class="empty-card">No moves yet.</div>';
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
      if (cell.state === 'hit' || cell.state === 'miss') {
        action = `onclick="showMessage('You already targeted that cell.', 'error')"`;
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
    els.leaderboard.innerHTML = players.length ? players.map((p, i) => `
      <div class="history-item">
        <strong>#${i + 1} ${p.username}</strong>
        <div>Wins: ${p.wins} · Losses: ${p.losses} · Games: ${p.games_played}</div>
        <div class="muted">Accuracy: ${(p.accuracy * 100).toFixed(1)}%</div>
      </div>
    `).join('') : '<div class="empty-card">No player stats yet.</div>';
  } catch (err) {
    showMessage(`Could not load leaderboard: ${err.message}`, 'error');
  }
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
els.createGameBtn.addEventListener('click', createGame);
els.refreshLobbyBtn.addEventListener('click', () => {
  loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
});
els.openGameBtn.addEventListener('click', openEnteredGame);
els.joinGameBtn.addEventListener('click', joinEnteredGame);
els.submitShipsBtn.addEventListener('click', submitShips);
  els.refreshLeaderboardBtn.addEventListener('click', loadLeaderboard);

renderIdentity();
if (state.selectedGameId) {
  rememberGame(state.selectedGameId);
  els.gameIdInput.value = String(state.selectedGameId);
}
updateUiState();
loadLobby().catch((err) => showMessage(`Could not load games: ${err.message}`, 'error'));
loadLeaderboard();
if (state.selectedGameId) loadSelectedGame();
startAutoRefresh();
