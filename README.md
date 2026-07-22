# ChatApp — Backend

Laravel 13 REST API powering a real-time chat application with public group rooms, private one-on-one direct messages (end-to-end encrypted, with typing indicators), and 1:1 audio/video calling. Messages can be edited (within 15 minutes) and soft-deleted. Authentication is stateless via Laravel Sanctum bearer tokens, and real-time delivery uses Pusher over WebSockets (including WebRTC call signaling via Pusher client events).

The companion frontend (Vue 3 + TypeScript) lives in the `ChatApp-Frontend` repository.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (Bearer tokens) |
| Real-time | Pusher (cluster `ap2`) via Laravel broadcasting, incl. client events for WebRTC signaling |
| Calling | WebRTC signaling relay (no media server) + Cloudflare Realtime TURN for NAT traversal |
| Queue | Database driver (broadcasts are queued) — `sync` in production (see Deployment) |
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

# Calling — TURN relay (required for calls to connect across NATs/AP-isolated Wi-Fi;
# STUN alone isn't enough). Cloudflare Realtime is used in production:
CLOUDFLARE_TURN_KEY_ID=<turn-key-id>
CLOUDFLARE_TURN_API_TOKEN=<turn-key-api-token>
# Falls back to a static TURN server (or STUN-only) if unset — see TURN_URL/TURN_USERNAME/TURN_CREDENTIAL in config/services.php

# Calling — stale-call sweep. Required only on hosts without a scheduler process
# (e.g. Render's free tier): ping GET /api/cron/sweep-calls?token=<CRON_SECRET>
# from an external cron service (e.g. cron-job.org) every minute.
CRON_SECRET=<random-secret>
```

> **Getting Cloudflare TURN credentials:** create a TURN Key in the Cloudflare dashboard's Calls/Realtime section — it gives you a Key ID and an API token (shown once). `CallController::iceServers()` uses these to fetch short-lived (24h) TURN credentials per request from Cloudflare's API; nothing else to configure.

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

# Terminal 3 — scheduler (sweeps stale calls every minute; local/dev only —
# production uses the token-gated /api/cron/sweep-calls endpoint instead, see Deployment)
php artisan schedule:work
```

> To skip the queue worker during quick experiments, set `QUEUE_CONNECTION=sync` — broadcasts then fire inline at the cost of slower send requests.

## Deployment Notes

On hosts without a persistent worker/scheduler process (e.g. Render's free tier):
- Set `QUEUE_CONNECTION=sync` so broadcasts fire inline — no `queue:work` process needed.
- There's no native cron; instead `GET /api/cron/sweep-calls?token=<CRON_SECRET>` runs `calls:sweep` on demand. Point a free external cron service (e.g. cron-job.org) at it once a minute.
- Set `CLOUDFLARE_TURN_KEY_ID` / `CLOUDFLARE_TURN_API_TOKEN` so calls can traverse NATs and AP-isolated Wi-Fi — without a TURN server, calls will ring and accept but get stuck failing to connect on many real-world networks.

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
| POST | `/api/chat/room/{id}/typing` | Broadcast a typing signal to the room (throttled 40/min) |
| POST | `/api/conversations/{id}/typing` | Broadcast a typing signal to the DM peer (throttled 40/min) |
| GET | `/api/me/keys` | Fetch my E2E public key + wrapped private key material |
| POST | `/api/me/keys` | Enroll E2E keys (first-time setup) |
| PATCH | `/api/me/keys` | Update wrapped private key (e.g. passphrase change) |
| POST | `/api/me/keys/reset` | Reset E2E keys, invalidating old wraps for peers (throttled 5/min) |
| GET | `/api/me/conversation-keys` | My per-conversation wrapped encryption keys |
| POST | `/api/conversations/{id}/keys` | Store a wrapped conversation key for a peer |
| POST | `/api/calls` | Start a call (`conversation_id`, `type`: audio\|video; throttled 20/min) |
| POST | `/api/calls/{id}/accept` | Accept an incoming call |
| POST | `/api/calls/{id}/decline` | Decline an incoming call |
| POST | `/api/calls/{id}/end` | End an ongoing call (idempotent) |
| POST | `/api/calls/{id}/heartbeat` | Liveness ping while ongoing, updates `last_seen_at` (throttled 60/min) |
| POST | `/api/calls/{id}/seen` | Callee marks a missed call as seen |
| GET | `/api/calls/missed` | My unseen missed calls |
| GET | `/api/conversations/{id}/calls` | Call history for a conversation (cursor-paginated) |
| GET | `/api/ice-servers` | STUN/TURN servers for WebRTC (Cloudflare TURN if configured) |
| GET | `/api/cron/sweep-calls` | Token-gated (`?token=`), unauthenticated — marks stale ringing/ongoing calls missed/ended (throttled 5/min) |
| POST | `/broadcasting/auth` | Private channel authorization (Sanctum-guarded, used by Pusher) |

Editing and deletion are sender-only. Editing returns `403` once the 15-minute window has elapsed and `409` if the message was already deleted. Deletion is a soft delete: the row stays, `deleted_at` is set, and the message body is blanked to `''` (privacy) — clients render a "this message was deleted" tombstone. Deletes are idempotent (deleting again returns `204` without re-broadcasting).

Responses are shaped by Eloquent API Resources (`app/Http/Resources/`). Paginated endpoints return `{ data, links, meta }` with cursor information.

## Broadcasting

| | Group chat | Direct messages | Calls |
|---|---|---|---|
| Channel | `chat-room.{roomId}` (public) | `private-conversation.{conversationId}` | `private-call.{callId}` + `private-user.{id}` |
| Send | `MessageSent` | `DirectMessageSent` | `CallInitiated` (rings the callee via their `user.{id}` channel) |
| Edit | `MessageUpdated` | `DirectMessageUpdated` | — |
| Delete | `MessageDeleted` | `DirectMessageDeleted` | — |
| Typing | `UserTyping` | `DirectUserTyping` | — |
| Lifecycle | — | — | `CallAccepted`/`CallDeclined`/`CallEnded` (both `call.{id}` and both users' `user.{id}`), `CallMissed` (callee's `user.{id}` only) |
| Authorization | none | participants only — see `routes/channels.php` | participants only (`Call::isParticipant()`); `user.{id}` restricted to that user |

Private channel auth is registered in `bootstrap/app.php` via `withBroadcasting()` with the `auth:sanctum` middleware, so the SPA authorizes subscriptions with its bearer token (no sessions/cookies). All events broadcast with `->toOthers()` — the actor already has the result from the HTTP response, so the broadcast only reaches the other participants.

`DirectMessageSent` additionally broadcasts on the recipient's personal `user.{id}` channel so a brand-new conversation's first message arrives live, before the recipient has subscribed to it. Edit/delete events only target the conversation channel, since both participants are already subscribed by then.

**Calling** broadcasts lifecycle events (queued, like other broadcasts) but signals the actual WebRTC handshake — SDP offer/answer, ICE candidates — as Pusher **client events** (whispers) sent directly between peers on the `call.{callId}` channel. Client events never touch the server or the queue, but require "Enable Client Events" turned on for the app in the Pusher dashboard (off by default) — without it, calls ring and accept but never connect.

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Accounts (bcrypt-hashed passwords) |
| `personal_access_tokens` | Sanctum tokens |
| `chat_rooms` | Group rooms |
| `chat_messages` | Room messages (`chat_room_id`, `user_id`, `message`, `edited_at`, `deleted_at`) |
| `conversations` | One row per user pair — lower id stored first, `UNIQUE(user_one_id, user_two_id)` |
| `direct_messages` | DMs (`conversation_id`, `sender_id`, `message`, `read_at`, `edited_at`, `deleted_at`, E2E ciphertext fields) |
| `calls` | `conversation_id`, `caller_id`, `callee_id`, `type` (audio\|video), `status` (ringing\|ongoing\|ended\|declined\|missed\|failed), `started_at`, `answered_at`, `ended_at`, `seen_at`, `last_seen_at` |
| `conversation_keys` | Per-user wrapped E2E symmetric key for a conversation — `conversation_id`, `user_id`, `key_version`, `wrapped_key`, `UNIQUE(conversation_id, user_id, key_version)` |

`edited_at` / `deleted_at` are nullable timestamps managed by the app (no `SoftDeletes` trait — tombstones must stay visible in queries). `EDIT_WINDOW_MINUTES = 15` is a constant on both message models.

`users` additionally carries E2E key-escrow columns: `public_key`, `encrypted_private_key`, `key_salt`, `key_nonce` (all nullable — populated on first key enrollment; the private key stays encrypted with a passphrase-derived key the server never sees).

Stale calls are swept by `php artisan calls:sweep` (`app/Console/Commands/SweepStaleCalls.php`): `ringing` calls older than 60s become `missed`, `ongoing` calls with no heartbeat in 90s become `ended`.

## Authorization

`app/Policies/ConversationPolicy.php` defines a single `participate` ability enforced on every DM read, send, edit, delete, and mark-read — at the HTTP layer (controllers) and the WebSocket layer (`routes/channels.php`). Editing and deleting are further restricted to the message's original sender.

## Testing

```bash
php artisan test
```

Feature tests (123) cover registration/login, token auth, conversation idempotency, participant-only access (403s), unread counts, read receipts, message edit/delete (15-minute window, sender-only enforcement, tombstone blanking, idempotent deletes, cross-room/conversation 404s), E2E key enrollment/reset, typing broadcasts, and the full call lifecycle (ring/accept/decline/end, heartbeat, missed-call sweep, ICE server credentials incl. Cloudflare TURN with a `Http::fake()`d response, and the cron sweep endpoint's token gate). Tests run on in-memory SQLite (configured in `phpunit.xml`) and never touch your real database.

## Code Style

```bash
vendor/bin/pint app routes tests database bootstrap/app.php
```
