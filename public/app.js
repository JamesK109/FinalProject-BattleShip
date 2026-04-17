const state = {
  playerId: Number(localStorage.getItem('battleship_player_id') || 0),
  username: localStorage.getItem('battleship_username') || '',
  selectedGameId: Number(localStorage.getItem('battleship_game_id') || 0),
  selectedShips: [],
  lobbyTimer: null,
};

const els = {
  message: document.getElementById('message'),
  usernameInput: document.getElementById('usernameInput'),
  registerBtn: document.getElementById('registerBtn'),
  createGameBtn: document.getElementById('createGameBtn'),
  refreshLobbyBtn: document.getElementById('refreshLobbyBtn'),
  gridSizeInput: document.getElementById('gridSizeInput'),
  maxPlayersInput: document.getElementById('maxPlayersInput'),
  lobbyList: document.getElementById('lobbyList'),
  identityName: document.getElementById('identityName'),
  identityMeta: document.getElementById('identityMeta'),
  gameSummary: document.getElementById('gameSummary'),
  turnBadge: document.getElementById('turnBadge'),
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
    loadLobby();
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
    state.selectedShips = [];
    showMessage(`Joined game #${gameId}.`, 'success');
    await loadLobby();
    await loadSelectedGame();
  } catch (err) {
    showMessage(err.message, 'error');
  }
}

async function loadLobby() {
  try {
    const games = await api('api/games');
    renderLobby(games);
  } catch (err) {
    showMessage(`Could not load lobby: ${err.message}`, 'error');
  }
}

function renderLobby(games) {
  if (!games.length) {
    els.lobbyList.innerHTML = '<div class="empty-card">No games yet. Create one to start.</div>';
    return;
  }
  els.lobbyList.innerHTML = games.map((game) => {
    const joined = game.players.some((p) => p.player_id === state.playerId);
    const canJoin = game.status === 'waiting_setup' && game.joined_players < game.max_players && !joined;
    return `
      <div class="game-card ${state.selectedGameId === game.game_id ? 'active' : ''}">
        <div class="section-head">
          <strong>Game #${game.game_id}</strong>
          <span class="badge">${game.status}</span>
        </div>
        <div class="muted">Grid: ${game.grid_size} × ${game.grid_size}</div>
        <div class="muted">Players: ${game.joined_players}/${game.max_players}</div>
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
  state.selectedShips = [];
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
    return;
  }
  try {
    const [game, boards, moves] = await Promise.all([
      api(`api/games/${state.selectedGameId}`),
      state.playerId ? api(`api/games/${state.selectedGameId}/boards?player_id=${state.playerId}`) : Promise.resolve({ boards: [], grid_size: 0 }),
      api(`api/games/${state.selectedGameId}/moves`),
    ]);
    renderGame(game, boards, moves);
  } catch (err) {
    showMessage(`Could not load game: ${err.message}`, 'error');
  }
}

function renderGame(game, boardsData, moves) {
  const myTurn = game.current_turn_player_id === state.playerId;
  const joined = game.players.some((p) => p.player_id === state.playerId);
  const winnerId = boardsData.winner_id || null;
  els.turnBadge.textContent = !joined
    ? 'Viewing only'
    : game.status === 'finished'
      ? `Winner: #${winnerId ?? 'done'}`
      : myTurn
        ? 'Your turn'
        : `Current turn: #${game.current_turn_player_id ?? 'waiting'}`;

  els.gameSummary.innerHTML = `
    <div><strong>Game #${game.game_id}</strong> · ${game.status}</div>
    <div class="muted">Grid size: ${game.grid_size} × ${game.grid_size}</div>
    <div class="muted">Players: ${game.players.map((p) => `#${p.player_id} (${p.ships_remaining} ships left)`).join(', ')}</div>
    <div class="muted">Total moves: ${game.total_moves}</div>
  `;

  const myBoard = boardsData.boards.find((b) => b.is_viewer);
  const alreadyPlaced = myBoard && myBoard.cells.some((c) => c.state === 'ship' || c.state === 'hit' || c.state === 'miss');
  els.playerBoard.innerHTML = myBoard ? renderBoardHtml(myBoard.cells, boardsData.grid_size, { own: true, alreadyPlaced }) : '<div class="empty-card">Join this game to place ships and play.</div>';

  const others = boardsData.boards.filter((b) => !b.is_viewer);
  els.opponentBoards.innerHTML = others.length ? others.map((board) => `
    <div class="opponent-card">
      <div class="section-head"><strong>Player #${board.player_id}</strong><span class="muted">${board.ships_remaining} ships left</span></div>
      ${renderBoardHtml(board.cells, boardsData.grid_size, { own: false, fireEnabled: myTurn && game.status === 'playing' })}
    </div>
  `).join('') : '<div class="empty-card">No opponents yet.</div>';

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
  return `<div class="board-grid" style="${style}">${html}</div>`;
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
    loadLobby();
    loadLeaderboard();
    if (state.selectedGameId) loadSelectedGame();
  }, 3000);
}

els.registerBtn.addEventListener('click', registerPlayer);
els.createGameBtn.addEventListener('click', createGame);
els.refreshLobbyBtn.addEventListener('click', loadLobby);
els.submitShipsBtn.addEventListener('click', submitShips);
els.refreshLeaderboardBtn.addEventListener('click', loadLeaderboard);

renderIdentity();
loadLobby();
loadLeaderboard();
if (state.selectedGameId) loadSelectedGame();
startAutoRefresh();
