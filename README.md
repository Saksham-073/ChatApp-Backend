# ChatApp — Backend

Laravel 13 REST API powering a real-time chat application with public group rooms and private one-on-one direct messages. Messages can be edited (within 15 minutes) and soft-deleted. Authentication is stateless via Laravel Sanctum bearer tokens, and real-time delivery uses Pusher over WebSockets.

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
| PATCH | `/api/chat/room/{id}/messages/{messageId}` | Edit own room message within 15 min (throttled 60/min) |
| DELETE | `/api/chat/room/{id}/messages/{messageId}` | Soft-delete own room message (anytime) |
| GET | `/api/users` | All users except the requester (for starting DMs) |
| GET | `/api/conversations` | My conversations with `other_user`, `last_message`, `unread_count` |
| POST | `/api/conversations` | Find-or-create a conversation with `{ user_id }` |
| GET | `/api/conversations/{id}/messages` | DM history (participants only, cursor-paginated) |
| POST | `/api/conversations/{id}/messages` | Send a DM, broadcasts to the other participant (throttled 60/min) |
| PATCH | `/api/conversations/{id}/messages/{messageId}` | Edit own DM within 15 min (throttled 60/min) |
| DELETE | `/api/conversations/{id}/messages/{messageId}` | Soft-delete own DM (anytime) |
| POST | `/api/conversations/{id}/read` | Mark all incoming DMs in the conversation as read |
| POST | `/broadcasting/auth` | Private channel authorization (Sanctum-guarded, used by Pusher) |

Editing and deletion are sender-only. Editing returns `403` once the 15-minute window has elapsed and `409` if the message was already deleted. Deletion is a soft delete: the row stays, `deleted_at` is set, and the message body is blanked to `''` (privacy) — clients render a "this message was deleted" tombstone. Deletes are idempotent (deleting again returns `204` without re-broadcasting).

Responses are shaped by Eloquent API Resources (`app/Http/Resources/`). Paginated endpoints return `{ data, links, meta }` with cursor information.

## Broadcasting

| | Group chat | Direct messages |
|---|---|---|
| Channel | `chat-room.{roomId}` (public) | `private-conversation.{conversationId}` |
| Send | `MessageSent` | `DirectMessageSent` |
| Edit | `MessageUpdated` | `DirectMessageUpdated` |
| Delete | `MessageDeleted` | `DirectMessageDeleted` |
| Authorization | none | participants only — see `routes/channels.php` |

Private channel auth is registered in `bootstrap/app.php` via `withBroadcasting()` with the `auth:sanctum` middleware, so the SPA authorizes subscriptions with its bearer token (no sessions/cookies). All events broadcast with `->toOthers()` — the actor already has the result from the HTTP response, so the broadcast only reaches the other participants.

`DirectMessageSent` additionally broadcasts on the recipient's personal `user.{id}` channel so a brand-new conversation's first message arrives live, before the recipient has subscribed to it. Edit/delete events only target the conversation channel, since both participants are already subscribed by then.

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Accounts (bcrypt-hashed passwords) |
| `personal_access_tokens` | Sanctum tokens |
| `chat_rooms` | Group rooms |
| `chat_messages` | Room messages (`chat_room_id`, `user_id`, `message`, `edited_at`, `deleted_at`) |
| `conversations` | One row per user pair — lower id stored first, `UNIQUE(user_one_id, user_two_id)` |
| `direct_messages` | DMs (`conversation_id`, `sender_id`, `message`, `read_at`, `edited_at`, `deleted_at`) |

`edited_at` / `deleted_at` are nullable timestamps managed by the app (no `SoftDeletes` trait — tombstones must stay visible in queries). `EDIT_WINDOW_MINUTES = 15` is a constant on both message models.

## Authorization

`app/Policies/ConversationPolicy.php` defines a single `participate` ability enforced on every DM read, send, edit, delete, and mark-read — at the HTTP layer (controllers) and the WebSocket layer (`routes/channels.php`). Editing and deleting are further restricted to the message's original sender.

## Testing

```bash
php artisan test
```

Feature tests (31) cover registration/login, token auth, conversation idempotency, participant-only access (403s), unread counts, read receipts, and message edit/delete (15-minute window, sender-only enforcement, tombstone blanking, idempotent deletes, cross-room/conversation 404s). Tests run on in-memory SQLite (configured in `phpunit.xml`) and never touch your real database.

## Code Style

```bash
vendor/bin/pint app routes tests database bootstrap/app.php
```
