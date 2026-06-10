# ChatApp — Backend

Laravel 13 REST API powering a real-time chat application with public group rooms and private one-on-one direct messages. Authentication is stateless via Laravel Sanctum bearer tokens, and real-time delivery uses Pusher over WebSockets.

The companion frontend (Vue 3 + TypeScript) lives in the `ChatApp-Frontend` repository.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (Bearer tokens) |
| Real-time | Pusher (cluster `ap2`) via Laravel broadcasting |
| Queue | Database driver (broadcasts are queued) |
| Tests | PHPUnit 12 (in-memory SQLite) |

## Getting Started

### 1. Install

```bash
composer install
cp .env.example .env        # if .env doesn't exist
php artisan key:generate
```

### 2. Configure `.env`

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chatapp_backend
DB_USERNAME=<your-user>
DB_PASSWORD=<your-password>

BROADCAST_CONNECTION=pusher
QUEUE_CONNECTION=database

PUSHER_APP_ID=<id>
PUSHER_APP_KEY=<key>
PUSHER_APP_SECRET=<secret>
PUSHER_APP_CLUSTER=ap2
PUSHER_HOST=api-ap2.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https
```

### 3. Migrate

```bash
php artisan migrate
```

### 4. Run (two terminals)

```bash
# Terminal 1 — HTTP server (0.0.0.0 so phones on the same Wi-Fi can connect)
php artisan serve --host=0.0.0.0

# Terminal 2 — queue worker (REQUIRED: broadcasts are queued;
# without it, messages save but never reach Pusher)
php artisan queue:work
```

> To skip the queue worker during quick experiments, set `QUEUE_CONNECTION=sync` — broadcasts then fire inline at the cost of slower send requests.

## API Routes

All API routes are prefixed with `/api`. Protected routes require `Authorization: Bearer <token>`.

| Method | Path | Description |
|---|---|---|
| POST | `/api/register` | Create account, returns token (throttled 10/min) |
| POST | `/api/login` | Authenticate, returns token (throttled 10/min) |
| POST | `/api/logout` | Revoke the current token |
| GET | `/api/me` | Authenticated user — used for session restore |
| GET | `/api/chat/rooms` | List group chat rooms |
| POST | `/api/chat/rooms` | Create a room |
| GET | `/api/chat/room/{id}/messages` | Room messages (cursor-paginated, 50/page) |
| POST | `/api/chat/room/{id}/messages` | Send a room message (throttled 60/min) |
| GET | `/api/users` | All users except the requester (for starting DMs) |
| GET | `/api/conversations` | My conversations with `other_user`, `last_message`, `unread_count` |
| POST | `/api/conversations` | Find-or-create a conversation with `{ user_id }` |
| GET | `/api/conversations/{id}/messages` | DM history (participants only, cursor-paginated) |
| POST | `/api/conversations/{id}/messages` | Send a DM, broadcasts to the other participant (throttled 60/min) |
| POST | `/api/conversations/{id}/read` | Mark all incoming DMs in the conversation as read |
| POST | `/broadcasting/auth` | Private channel authorization (Sanctum-guarded, used by Pusher) |

Responses are shaped by Eloquent API Resources (`app/Http/Resources/`). Paginated endpoints return `{ data, links, meta }` with cursor information.

## Broadcasting

| | Group chat | Direct messages |
|---|---|---|
| Channel | `chat-room.{roomId}` (public) | `private-conversation.{conversationId}` |
| Event | `MessageSent` | `DirectMessageSent` |
| Authorization | none | participants only — see `routes/channels.php` |

Private channel auth is registered in `bootstrap/app.php` via `withBroadcasting()` with the `auth:sanctum` middleware, so the SPA authorizes subscriptions with its bearer token (no sessions/cookies). Both events broadcast with `->toOthers()` — the sender already has the message from the HTTP response.

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Accounts (bcrypt-hashed passwords) |
| `personal_access_tokens` | Sanctum tokens |
| `chat_rooms` | Group rooms |
| `chat_messages` | Room messages (`chat_room_id`, `user_id`, `message`) |
| `conversations` | One row per user pair — lower id stored first, `UNIQUE(user_one_id, user_two_id)` |
| `direct_messages` | DMs (`conversation_id`, `sender_id`, `message`, `read_at` for read receipts) |

## Authorization

`app/Policies/ConversationPolicy.php` defines a single `participate` ability enforced on every DM read, send, and mark-read — at the HTTP layer (controllers) and the WebSocket layer (`routes/channels.php`).

## Testing

```bash
php artisan test
```

Feature tests cover registration/login, token auth, conversation idempotency, participant-only access (403s), unread counts, and read receipts. Tests run on in-memory SQLite (configured in `phpunit.xml`) and never touch your real database.

## Code Style

```bash
vendor/bin/pint app routes tests database bootstrap/app.php
```
