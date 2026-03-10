# FinalProject-BattleShip

Team Members

James - Frontend developer assisting with backend

Bryce - Backend developer assisting with Frontend

## System Overview

This project implements a REST API for a distributed multiplayer Battleship game. The server allows players to create accounts, join games, place ships, take turns firing, and track lifetime statistics.

The system supports multiple players per game, persistent player identities, and turn-based gameplay. The API is designed so that both human clients and automated computer players can interact with the same server.

## Architecture

The system follows a layered architecture:

1. Client
2. REST API
3. Game Logic
4. Database

API Layer handles HTTP requests and JSON responses.

Game Logic Layer manages turns, ship placement, hit detection, and win conditions.

## Database Design Summary

The system uses a relational database with the following core tables:

### Players

Stores persistent player identities and lifetime statistics.

### Games

Stores game configuration, grid size, and game state.

### GamePlayers

Associates players with games and maintains turn order.

### Moves

Stores every shot fired along with timestamp and result.

### Database constraints enforce:

- Unique player identity

- Referential integrity between tables

- Valid game participation relationships

## Multiplayer Model

Each game supports multiple players who join before gameplay begins.

Game states:

waiting → active → finished

Players are assigned a turn order, and the server rotates turns after each move.
Players are eliminated when all of their ships are destroyed, and the last remaining player wins.

## Testing Strategy

Testing focuses on validating API correctness and game logic.

### Methods used:

- API endpoint testing using REST requests


## AI Tools Used

AI tools were used to assist development, including:

- ChatGPT

AI was primarily used for:

- debugging assistance

- generating test cases

- improving documentation

All AI output was reviewed and validated before implementation.