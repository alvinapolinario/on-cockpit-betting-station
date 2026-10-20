# Sabonglara (On-Cockpit Betting Station) — Technical Reference

This document is a from-scratch specification of the Laravel application in this repository
("sabonglara" / "on-cockpit-betting-station"). It is written so that a Rust reimplementation
could be built from this document alone, without reading the PHP source. Every data type,
validation rule, state transition, money-arithmetic rule, and side effect (DB write, broadcast,
bridge call) that the current code performs is described explicitly. Where the implementation is
inconsistent, redundant, or relies on an accidental behavior, that is called out so the rewrite
does not silently "fix" something the real system depends on.

Current HEAD at time of writing: `df30e44fd069c2a68e80d90c73e484a2c06d6ef6` (2026-10-03).

---

## 1. Overview & role in the two-app system

Sabonglara is the **betting half** of a two-application cockfighting ("sabong") derby system that
runs on one physical server at the arena, fully offline (no required internet access during an
event):

- **sabong-matching-system** (Node/Express, sibling repo) owns entries, owners, gamecocks, and the
  *matching* algorithm that pairs cocks into fights ("matches"). It decides **which fight is next**.
- **sabonglara** (this repo, Laravel 10 / PHP 8.1) owns everything about **money**: teller betting
  (Meron/Wala/draw wagering), cash handed to and collected from tellers, payouts of winnings,
  teller and admin cash reconciliation ("remittances" and "shorts"), and the legally significant
  **event closing report** that is cryptographically sealed and later uploaded to a separate VPS
  system (`sabong-bir-compliance`) for tax/audit purposes.

The two apps talk to each other over a same-server HTTP "bridge" with HMAC-signed requests (full
protocol in §8). In short: matching **calls** a fight to betting (who's fighting, their names/owners/
weights); betting **opens/closes wagering** and **declares the result**; the result flows back to
matching, which records win/draw/loss points for the derby. Once betting has opened on a fight,
matching can no longer change, renumber, or recall it — betting fully owns the fight from that
point until it is finished or cancelled.

A third, mostly independent subsystem handles **event closing / sealing**: when an event ends, the
app builds a full financial report from the database, has two different admins confirm it, signs
it with an Ed25519 key unique to this server, and writes an encrypted package addressed to the BIR
compliance VPS. Sealed events become immutable (enforced both in PHP and by MySQL triggers).

The app also implements its own **encrypted, signed database backup/restore** system (no
`mysqldump` dependency at restore time) and a **teller cash ledger** (append-only, one row per cash
movement, enforced immutable by DB triggers) used to reconcile cash at the end of each event.

## 2. Tech stack & key configuration

| Component | Choice |
|---|---|
| Framework | Laravel 10.30, PHP 8.1 |
| DB | MariaDB 10.11 (MySQL-compatible), InnoDB, utf8mb4 |
| Realtime | `beyondcode/laravel-websockets` 1.14 (self-hosted WebSocket server) + `predis/predis`; `BROADCAST_DRIVER=pusher` driver talking to the **local** websocket server (not the real Pusher cloud) |
| API auth | Laravel Sanctum's `personal_access_tokens` table used as a bespoke bearer-token store (Sanctum's own guard middleware is **not** used — see §5) |
| HTTP client (bridge) | Guzzle via Laravel's `Http` facade |
| Queue | `QUEUE_CONNECTION=sync` — there is no real queue; "queued" bridge messages are rows in a DB outbox table drained by a dedicated daemon process, not Laravel's queue system |
| Crypto | libsodium (`sodium_*`) for Ed25519 signing (closing reports, backups) and X25519 sealed-box encryption (closing packages); HMAC-SHA256 for the bridge |
| Frontend | Server-rendered Blade views (a "Sneat"-style Bootstrap admin theme) + jQuery/AJAX; a separate Android app (`public/app.apk` / `public/sabong.apk`) and desktop app consume the JSON API |
| Process model | nginx → PHP-FPM, a WebSocket server process, and a `bridge:work` daemon loop, all kept alive by supervisord. **No OS cron / Laravel scheduler is used** — `app/Console/Kernel.php::schedule()` is empty. |

### Key `.env` variables

| Variable | Purpose |
|---|---|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Standard Laravel |
| `ARENA_NAME` | Shown in UI, closing reports, teller statements (`config('variables.templateName')` via `config/variables.php`) |
| `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. `DB_PASSWORD` doubles as the MariaDB root password in the docker-compose setup. |
| `BROADCAST_DRIVER=pusher` | Driver name; points at the local websocket server via the `PUSHER_*` vars below, not pusher.com |
| `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`, `PUSHER_APP_CLUSTER` | Local laravel-websockets server config |
| `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=file`, `CACHE_DRIVER=file` | No background job queue; file-based session/cache |
| `SESSION_LIFETIME=120`, `SESSION_SECURE_COOKIE` | Admin/teller **web** session cookie lifetime (minutes) |
| `TRUSTED_PROXIES` | For a reverse-proxy deployment |
| `SEAL_SERVER_ID` | This server's identity string embedded in every sealed closing payload |
| `SEAL_VPS_PUBLIC_KEY` | Base64 X25519 public key of the BIR compliance VPS (or file `storage/app/keys/vps.pub`) |
| `SEAL_REQUIRE_SECOND_APPROVER` (default true) | Two-person rule to seal an event |
| `SEAL_LEGACY_SERVER_ID` | Sender id used only when re-sealing old events reconstructed from backups |
| `BACKUP_REQUIRE_SECOND_APPROVER` (default true) | Two-person rule to restore a backup |
| `BACKUP_ALLOW_RAW_SQL_IMPORT` (default false; forced false in production) | Testing-only raw `.sql`/`.sql.gz` import path |
| `BET_MIN` (1), `BET_MAX` (1,000,000) | Accepted range for one bet, in pesos |
| `CASH_MAX` (1,000,000) | Accepted range for one cash-in/cash-out |
| `API_TOKEN_HOURS` (18) | Mobile/desktop app bearer-token lifetime |
| `REQUIRE_CLOSED_BEFORE_RESULT` (true) | A fight's betting must be `Closed` before a result can be declared (first declaration only, not corrections) |
| `RECEIPT_RANDOM_DIGITS` (6) | Random digits appended to a bet receipt code |
| `DASHBOARD_SHOW_LAST_EVENT` (false, demo only) | When no event is Active, show the latest Closed event read-only on the dashboard |
| `MATCHING_BRIDGE_ENABLED`, `MATCHING_BRIDGE_URL`, `MATCHING_TO_BETTING_KEY`, `BETTING_TO_MATCHING_KEY` | The bridge to the matching system (see §8) |

---

## 3. Database schema

The live schema is **not** fully expressed as Laravel migrations. Most tables were created once as
a raw SQL dump (`docker/mysql/init/01-schema.sql`, loaded automatically by MariaDB on first
container start) and only the newer features were added via proper migrations
(`database/migrations/*.php`). A Rust rewrite should treat the dump + migrations below, applied in
order, as the single source of truth. All money columns are `DECIMAL(16,2)`; a Rust implementation
should use a fixed-point/integer-cents type (the app itself has started migrating internal
calculations to integer centavos via `App\Services\Closing\Money`, see §7.4/§7.11) — **the live
betting/payout code paths still do raw PHP float/`DECIMAL` arithmetic**, see §10.

### 3.1 Base schema (`docker/mysql/init/01-schema.sql`)

#### `accounts` — one row per login (both Admin and Teller accounts)
| Column | Type | Notes |
|---|---|---|
| `account_id` | `int(11)` PK AI | |
| `username` | `varchar(60)` | unique in practice (checked in app code, not DB) |
| `password` | `varchar(60)` | bcrypt hash (Laravel `Hash::make`) |
| `account_type` | `varchar(20)` | `'Admin'` or `'Teller'` |
| `is_active` | `tinyint(1)` | 0/1 |
| `account_date_created` | `datetime` | |

#### `admins` — profile row for an Admin account
| Column | Type | Notes |
|---|---|---|
| `admin_id` | `int(11)` PK AI | |
| `account_id` | `int(11)` | → `accounts.account_id` (no FK constraint) |
| `admin_name` | `varchar(200)` | |

#### `tellers` — profile row for a Teller account
| Column | Type | Notes |
|---|---|---|
| `teller_id` | `int(11)` PK AI | |
| `account_id` | `int(11)` | → `accounts.account_id` |
| `teller_name` | `varchar(200)` | |
| `contact_number` | `varchar(60)` | |
| `phone_uid` | `varchar(200)` | device identifier the teller app is locked to once set; empty/NULL = unlocked |
| `qr_token_hash` | `char(64)` nullable unique | SHA-256 of the QR login secret (migration `2026_10_03_000002`) |
| `qr_token` | `text` nullable | the QR secret, `Crypt::encryptString()`-encrypted with `APP_KEY` so it can be re-displayed |
| `qr_generated_at` | `datetime` nullable | |

(`qr_token_hash`/`qr_token` are `$hidden` on the `Teller` Eloquent model — excluded from any
`toArray()`/`toJson()`, never logged.)

#### `events` — one row per derby event/day
| Column | Type | Notes |
|---|---|---|
| `event_id` | `int(11)` PK AI | |
| `event_name` | `varchar(60)` | |
| `event_description` | `text` | |
| `event_date` | `date` | |
| `event_status` | `varchar(60)` | `'Active'` \| `'Completed'` \| `'Closed'` — index `NewIndex1` on this column. Only one event may be `Active` at a time (enforced in `EventController@store`/`@update`, not the DB). |
| `admin_cash_on_hand` | `decimal(16,2)` | admin's starting cash (revolving fund) |
| `teller_initial_cash_on_hand` | `decimal(16,2)` | default opening float handed to each teller added to the event |
| `prizes` | `decimal(16,2)` | |
| `rd` | `decimal(16,2)` | "RD" = a reserve/float figure used in the closing-report math (§7.11) |
| `event_percentage` | `decimal(5,3)` | house commission rate as a fraction, e.g. `0.070` = 7%; must satisfy `0 <= rate < 1` |
| `matching_event_id` | `int(11)` nullable | added by migration `2026_10_03_000001`; links this betting event to one matching-system derby (set on the first bridge `call`) |

#### `event_tellers` — a teller's assignment + running cash balance for one event
| Column | Type | Notes |
|---|---|---|
| `event_teller_id` | `int(11)` PK AI | **this is the id used everywhere else** (bets, cash-ins, claims, tokens) — a teller's identity *within one event* |
| `event_id` | `int(11)` | index `NewIndex1(event_id, teller_id)` |
| `teller_id` | `int(11)` | → `tellers.teller_id` |
| `teller_balance` | `decimal(16,2)` | cash on hand right now (bets add, voids/payouts/cash-out subtract, cash-in adds) |
| `teller_match_balance` | `decimal(16,2)` | running total for the *current* fight only; reset to 0 whenever a new fight is created/called |

#### `matches` (Eloquent model name `Fight`) — one row per fight within an event
| Column | Type | Notes |
|---|---|---|
| `match_id` | `int(11)` PK AI | internal id, a.k.a. the bridge's `fight_uid` when linked to matching |
| `event_id` | `int(11)` | index `NewIndex1(event_id, match_status)` |
| `match_number` | `int(11)` | the fight number shown to bettors ("Fight #N"); must be unique per event (checked in `BridgeController`, not DB) |
| `match_winner` | `varchar(5)` | `'Meron'` \| `'Wala'` \| `'N/A'` (draw/cancelled) \| `''` (not yet decided) |
| `meron_total_bet`, `wala_total_bet` | `decimal(16,2)` | running sum of valid (non-voided) bets on each side |
| `meron_odds`, `wala_odds` | `float` | payout multiplier per side, recomputed after every bet/void/toggle (§7.3) |
| `match_status` | `varchar(20)` | `'Ongoing'` \| `'Completed'` \| `'Draw'` \| `'Cancelled'` |
| `match_bet_status` | `varchar(20)` | `'Open'` \| `'Closed'` — whether tellers may currently place bets at all |
| `meron_bet_status`, `wala_bet_status` | `tinyint(1)` (cast to `integer` in the model) | per-side kill-switch: `1` = that side is **disabled** even while `match_bet_status = Open` |
| `match_created_datetime` | `datetime` | |
| `is_display` | `tinyint(1)` | which single fight (across the whole event) is currently shown on the TV screen |
| `meron_entry`, `wala_entry` | `varchar(255)` nullable (widened from 60 by migration `2026_10_03_000001`) | display names of the two cocks |
| `source_fight_uid` | `int(11)` nullable | matching system's `match_id`, permanent once set (migration `2026_10_03_000001`); index `matches_event_source_idx(event_id, source_fight_uid)` |
| `source_call_version` | `int(11)` default 0 | matching's call/resend version counter, used to ignore stale/out-of-order CALLs |
| `meron_details`, `wala_details` | `text` nullable | JSON blob from matching: `{entry, owner, weight, wingband, legband, type}` |
| `called_at` | `datetime` nullable | when matching called this fight |
| `bet_opened_at` | `datetime` nullable | set the **first** time betting opens on this fight; once set, matching may no longer edit/recall/renumber it |
| `hold_reason` | `varchar(255)` nullable | non-null while the fight is on HOLD (cocks in the pit don't match what was called) |

#### `bets`
| Column | Type | Notes |
|---|---|---|
| `bet_id` | `int(11)` PK AI | |
| `event_teller_id` | `int(11)` | the teller who took the bet; index `NewIndex1(event_teller_id, match_id, bet_status, bet_side, bet_receipt_code)` |
| `match_id` | `int(11)` | |
| `bet_receipt_code` | `varchar(100)` | `{event_teller_id}{match_number}{N random digits}`, unique (checked in app) |
| `bet_side` | `varchar(5)` | `'Meron'` \| `'Wala'` |
| `bet_amount` | `decimal(16,2)` | wager, pesos |
| `bet_win_amount` | `decimal(16,2)` | gross winnings if this bet won (0 otherwise) |
| `bet_payout_amount` | `decimal(16,2)` | amount actually payable (win amount for winners, full stake back for draw/cancel/void) |
| `bet_payout_datetime` | `datetime` nullable | set when claimed OR when voided |
| `bet_status` | `tinyint(1)` | `1` = valid/live, `2` = voided |
| `bet_datetime` | `datetime` | |
| `bet_is_winner` | `tinyint(1)` | 1 only for a genuine win (not draw/cancel/void refunds) |
| `bet_void_datetime` | `datetime` nullable | |
| `bet_payout_by` | `int(11)` nullable | `event_teller_id` of whoever paid it out (claim) or voided it; `NULL` = unclaimed |

#### `claims` — a payout event for a bet (at most one per bet)
| Column | Type | Notes |
|---|---|---|
| `claim_id` | `int(11)` PK AI | |
| `bet_id` | `int(11)` | **`UNIQUE` key `claims_bet_id_unique`** (added by migration `2026_09_29_000001`) — the DB-level guarantee against double payout |
| `event_teller_id` | `int(11)` | the teller who physically paid it (may differ from the bet's own teller) |
| `claim_amount` | `decimal(16,2)` | |
| `claim_datetime` | `datetime` | |

#### `cash_ins`
| Column | Type | Notes |
|---|---|---|
| `cash_in_id` | `int(11)` PK AI | |
| `admin_id` | `int(11)` nullable | `NULL` while a teller-initiated request is still `Pending` |
| `event_teller_id` | `int(11)` | |
| `cash_in_amount` | `decimal(16,2)` | |
| `cash_in_datetime` | `datetime` | request/creation time |
| `cash_in_type` | `varchar(60)` | `'AdminCashInInit'` (opening float when a teller joins an event) \| `'AdminCashIn'` (admin-initiated, web) \| `'TellerCashIn'` (teller-requested, pending approval) |
| `cash_in_status` | `varchar(20)` | `'Pending'` \| `'Approved'` \| `'Declined'` |
| `approved_at` | `datetime` nullable | added by migration `2026_09_29_000002`; set when the status leaves `Pending` |

#### `cash_outs` — mirror of `cash_ins`
| Column | Type | Notes |
|---|---|---|
| `cash_out_id` PK AI, `event_teller_id`, `admin_id` nullable, `cash_out_amount`, `cash_out_status` (`Pending`/`Approved`/`Declined`), `cash_out_datetime`, `approved_at` (added by `2026_09_29_000002`) | same semantics as `cash_ins`, direction reversed |

#### `teller_remittances` — counted cash turned in by a teller at end of event
| Column | Type | Notes |
|---|---|---|
| `teller_remittance_id` PK AI | |
| `event_teller_id` | |
| `denom_1000`…`denom_1` (`int(11)` each, for denominations 1000, 500, 200, 100, 50, 20, 10, 5, 1 pesos) | physical bill/coin counts |

#### `teller_shorts` — shortage (or overage) notes against one remittance
| `teller_short_id` PK AI, `teller_remittance_id`, `short_amount` (`decimal(16,2)`, may be negative for an overage), `short_remarks` (`text`) |

#### `admin_remittances` / `admin_shorts`
Same shape as the teller versions, but keyed by `event_id` (one remittance per event, representing
the admin's own cash box) instead of `event_teller_id`.

#### `salaries`
| `salary_id` PK AI, `event_id`, `employee_type` (`varchar(20)`), `employee_name` (`varchar(60)`), `daily_rate`, `overtime_rate`, `total_overtime`, `bonus` (all `decimal(16,2)`) |

#### `transactions` — the application's activity/audit log (a single flat table, not per-feature)
| `transaction_id` PK AI, `account_id` (`int(11)`, `-1` for unauthenticated/failed attempts), `transaction_type` (`varchar(60)`: `'Web App'`, `'Teller App'`, `'Admin App'`, `'Desktop App'`, `'API'`, `'Matching Link'`), `transaction_message` (`text`, free-form — often has a PHP object's string/JSON representation appended), `transaction_datetime` (`datetime`) |

This table is simultaneously: the human-readable activity log shown on the **Logs** page, the
**only** source of certain audit facts recomputed at closing time (fight open/close/declare
timestamps and counts — see `ClosingReportBuilder::fightAudit()`, which regex-parses
`transaction_message` for strings like `"Matches - Fight #12 bet status updated to Open!{...}"`),
and the append-only range that gets bundled into each sealed closing report's "detail" file.

#### `event_closings` — one row per sealed event, **immutable after insert**
See §3.3/§7.11 for the full field list and business meaning; the base dump already contains this
table and two BEFORE UPDATE/DELETE triggers that `SIGNAL` an error (`'Sealed event closings cannot
be modified'` / `'...cannot be deleted'`) for *any* row change outside the whitelisted
download/acknowledge columns. The Eloquent model additionally enforces the same rule in PHP
(`EventClosing::booted()`), so the guard exists at **both** layers.

#### `teller_ledger` — append-only per-teller cash journal (added by migration `2026_09_29_000002`)
See §3.4/§7.6. Two triggers make every row immutable and non-deletable at the DB level as well.

#### Framework/infrastructure tables
`failed_jobs`, `migrations`, `personal_access_tokens` (Sanctum's table, repurposed — see §5),
`users` (Laravel's default scaffold table — **unused** by this app; no controller references it),
`websockets_statistics_entries` (beyondcode/laravel-websockets internal stats).

#### SQL views (all names end `_view`; all computed, never written to directly)
`admins_view`, `tellers_view`, `event_tellers_view` — each joins the account row onto its
profile table (so username/password/is_active travel with admin/teller rows). `bets_view` — joins
`bets` → `event_tellers` → `tellers` → `matches` → `events`, i.e. a fully denormalized "bet with
everything about its teller, fight and event" row; this is the view almost every read endpoint
queries instead of the raw `bets` table. `cash_ins_view` / `cash_outs_view` similarly denormalize
cash movements with teller/admin/event names and `events.admin_cash_on_hand`. `claims_view`
denormalizes a payout with its teller, fight, and original bet. `teller_remittances_view` /
`admin_remittances_view` attach event/teller context to the two remittance tables.

#### Stored SQL functions
`get_teller_name(event_teller_id INT) RETURNS VARCHAR(255)` — looks up `event_tellers_view.teller_name`.
`get_transaction_account_name(account_id INT, transaction_type VARCHAR(60)) RETURNS VARCHAR(255)` —
resolves a log row's human-readable actor name: `admins_view.admin_name` for `'Web App'`/`'Admin
App'` types, `event_tellers_view.teller_name` for `'Teller App'`, else `NULL`. Both are used from
raw `selectRaw(...)` calls in `BetController@listAll` / `TransactionController@fetchLogs` and the
dashboard. Migration `2026_09_28_000002_recreate_stored_functions_as_app_user` re-creates both
functions (byte-identical body) so they are owned by the application's DB user instead of `root` —
otherwise the backup system cannot read `SHOW CREATE FUNCTION` for them (see §7.12) and refuses to
back up at all.

### 3.2 Migration `2026_09_29_000001_harden_tokens_and_claims`
One-time data migration + DDL: (a) rewrites every existing `personal_access_tokens.token` to its
SHA-256 hash and backfills `expires_at` (tokens used/created in the last 24h get 18 more hours;
everything else expires immediately) — this is what made §5's bearer-token model possible; (b)
adds the `UNIQUE (bet_id)` constraint on `claims` after first verifying there are currently zero
duplicate `bet_id`s (aborts the whole migration with a `RuntimeException` otherwise).

### 3.3 Migration `2026_09_28_000001_create_event_closings_table`
Creates `event_closings` with columns: `event_closing_id` PK, `event_id` (**unique** — one seal per
event, ever), `sequence_no` (**unique**, global monotonic sequence across *all* events, forming a
hash-chain), `payload` (`longtext`, the exact canonical-JSON string that was hashed/signed),
`payload_sha256` (**unique**), `detail_sha256`, `prev_payload_sha256` (hash of the previous seal in
the chain, or 64 zero characters for the first ever seal — `EventSealer::GENESIS`), `signature`
(base64 Ed25519 detached signature over `payload`), `key_fingerprint` (first 16 hex chars of
SHA-256 of the signing public key), `seal_code` (human-presentable, see §7.11), `detail_file`
(absolute path of the companion JSONL detail file), `server_id`, `closed_by_account_id`,
`approved_by_account_id` nullable, `closed_at`, and four columns that remain mutable after sealing:
`downloaded_at`, `downloaded_by_account_id`, `ack_code`, `ack_at`, `ack_by_account_id`. Plus the two
immutability triggers described above.

### 3.4 Migration `2026_09_29_000002_create_teller_ledger`
Creates `teller_ledger`: `ledger_id` bigint PK, `event_id`, `event_teller_id`, `line_no`
(**unique** per `(event_teller_id, line_no)`, 1-based sequential per teller), `entry_at`, `type`
(one of: `opening`, `bet`, `void`, `payout_win`, `payout_refund`, `cash_in`, `cash_out`,
`cash_declined`, `adjustment`), `fight_no` nullable, `match_id` nullable, `reference` nullable
(receipt code / `CI-123` / `CO-45`), `amount_in` and `amount_out` (`decimal(16,2)`, default 0 —
exactly one of the pair is non-zero per line, by convention, never enforced in SQL),
`balance_before`, `balance_after` (both required, no default), `related_event_teller_id` nullable
(for a payout made by a different teller than who sold the bet), `recorded_by_account_id`,
`approved_by_account_id` nullable, `note` nullable (≤255 chars), `source_table`/`source_id`
nullable — **unique** together with `type` (`ledger_source_unique`), meaning the same
bet/claim/cash-request row can never produce two ledger lines of the same type (the actual
duplicate-prevention mechanism — not `line_no`). Also adds `approved_at` to both `cash_ins` and
`cash_outs` in the same migration. Two triggers forbid UPDATE/DELETE on any row.

### 3.5 Migration `2026_10_03_000001_create_matching_bridge`
Adds the `matches` and `events` bridge columns listed in §3.1 above, and creates:

`bridge_outbox` (betting → matching messages): `id` bigint PK, `msg_key` (**unique** string,
`betting-{type}-{fight_uid}-{uuid}`), `type` (`'status'` \| `'result'`), `fight_uid` nullable,
`payload` (`longtext` JSON, exactly the bytes sent), `created_at`, `sent_at` nullable (null = still
pending), `attempts` (default 0), `last_error` nullable; indexes on `(sent_at, id)` and
`(type, fight_uid)`.

`bridge_inbox` (matching → betting messages already processed): `id` bigint PK, `msg_key`
(**unique**), `type` (`'call'` \| `'recall'`), `payload`, `http_status` (int), `response`
(`text`, the exact JSON body returned the first time), `received_at`. Looking a `msg_key` up here
first is what makes replays of the same message idempotent (§8).

### 3.6 Migration `2026_10_03_000002_add_login_qr_to_tellers`
Adds `qr_token_hash`, `qr_token`, `qr_generated_at` to `tellers` (documented in §3.1).

---

## 4. Domain model narrative

**Account / Admin / Teller.** `accounts` is the login table for *both* kinds of users; `account_type`
picks which profile table (`admins` or `tellers`) has the matching `account_id`. A Teller's
*business* identity, though, is never `teller_id` alone — it is `event_teller_id`, because the same
physical teller re-joins a fresh row (with a fresh cash balance) in `event_tellers` for every event
they work. All of a teller's bets, cash movements, claims and ledger lines are keyed by
`event_teller_id`, so a teller's history is naturally partitioned by event. Admins have no
per-event row; one admin account acts across every event.

**Event.** The unit of a single derby day. Exactly one event may be `Active` at a time (an app-level
invariant, not a DB constraint). All betting, cash handling, and payouts in the whole system only
happen against the one Active event. `Completed` is a status an admin can still choose, but in
practice almost everything transitions straight to `Closed` via the sealing flow (§7.11), since
sealing forces `event_status = 'Closed'` directly.

**Fight (`matches` table / `Fight` model).** One round of cockfighting with two sides, by
convention always called **Meron** and **Wala**. A fight's lifecycle has two mostly-independent
state machines running over it at once: `match_status` (`Ongoing → {Completed, Draw, Cancelled}`,
whether the *fight itself* is still being played) and `match_bet_status` (`Open ⇄ Closed`, whether
*wagering* is currently accepted) — plus two booleans (`meron_bet_status`, `wala_bet_status`) that
can additionally disable just one side mid-open, and (when the matching bridge is enabled) a
`hold_reason` state that means "matching's call doesn't match what's physically in the pit; betting
should not open yet." Only one fight per event may be `Ongoing` at a time.

**Bet.** A single wager by a bettor, recorded by whichever teller took the cash, against one side of
one fight, for a peso amount. `bet_status` separates *live* bets (`1`) from *voided* bets (`2`) —
voiding is a teller-initiated full refund of a mistaken bet, only possible while the fight is still
`Ongoing` and betting is `Closed` but **not yet** cancelled/not-yet-claimed (see §7.3 for the exact
gate). Once a fight settles, every live bet gets a `bet_payout_amount` (which may be the win amount,
the full stake back for a draw/cancel, or stay `0` for a clean loser) and is eligible for a one-time
claim.

**Claim.** The act of actually handing cash to a bettor for a settled, payable bet. A bet may be
claimed by **any** teller currently working the event, not only the one who sold it — the paying
teller's balance absorbs the cost, and the ledger records a cross-teller note when that happens.
Enforced unique per bet at the DB level.

**Account cash flows — CashIn / CashOut / Remittance / Short.** `cash_ins`/`cash_outs` are requests to
move money between the admin's cash box and a teller's on-hand cash — either admin-initiated (and
auto-approved) or teller-initiated (and left `Pending` until an admin approves/declines it).
`AdminCashInInit` is the special cash-in type used exactly once per teller-per-event, to hand them
their opening float. At the end of an event, each teller physically counts their cash and the
denominations are recorded into `teller_remittances`; any discrepancy against the system-computed
"expected cash" becomes a `teller_shorts` row (a positive `short_amount` is a shortage, negative is
an overage — sign convention only enforced by human entry, not validated). `admin_remittances` /
`admin_shorts` are the same idea one level up, for the admin's own cash box against the event-wide
revolving fund.

**Salary.** A per-event, per-employee (teller or other staff type) payroll line used only by the
operational financial report (`MatchController@report`) — not part of the sealed closing report at
all.

**Transaction (the log).** Every controller action that changes state appends a free-text line here.
It is simultaneously the human-facing audit trail and (regex-mined) the source of several
closing-report facts that were never given their own structured columns (bet-open/close timestamps,
result-declaration counts, reprint counts).

**TellerLedger (`teller_ledger`).** A second, *structured*, append-only record of every cash movement
for a teller, written inside the very same DB transaction as the balance change it describes, always
including the balance immediately before and after. It exists specifically so that an event's cash
trail can be mechanically re-verified line by line (`TellerLedger::verify()`) instead of trusting
only the final `teller_balance` number.

**EventClosing.** The sealed, cryptographically signed, append-only summary of one finished event:
every fight's pool/commission/winnings/refunds, every teller's cash reconciliation, event-wide
totals, a list of detected exceptions/anomalies, and a set of named balance "checks" (pass/fail).
Three blocking checks must pass before a seal is even allowed. Once sealed, an event (and every row
that belongs to it — fights, bets, claims, cash movements) becomes fully read-only, enforced by the
`GuardsSealedEvent` trait in PHP and by DB triggers on `event_closings` itself.

---

## 5. Auth & roles

There are **three independent authentication mechanisms** in this codebase, none of which is
Laravel's/Sanctum's standard guard flow:

1. **Web (Blade UI), session-based, Admin only.** `POST /login` → `AccountController@loginAccount`
   checks `accounts.username` + bcrypt `password`, requires `account_type === 'Admin'`, and on
   success stores `account_id`, `account_type`, `welcome_name`, `name`, and the whole `Admin` row
   (`admin`) into the PHP session. Every subsequent admin web request is gated by one of two
   middleware aliases checking `session('account_type')`: `isLoggedIn` (redirects *away* from the
   login page if already logged in) and `isAdmin` (redirects *to* `/` if not `session('account_type')
   == 'Admin'`). A third alias `isAll` only checks that *some* `account_type` is present in session,
   but is registered on an **empty** route group in `routes/web.php` — effectively dead. Logout
   (`GET /logout`) simply `session()->flush()`s and logs the event. There is no teller web login at
   all — tellers only ever use the mobile/desktop API.

2. **Mobile/desktop JSON API, bearer-token based, Teller or Admin.** `POST /api/login-app` (teller,
   username+password, plus an implicit device-lock check — see below), `POST /api/login-qr` (teller,
   QR-code based, see below), `POST /api/login-app-admin` (admin). All three, on success, mint a
   60-character random token via `Str::random(60)`, store **only its SHA-256 hash** in
   `personal_access_tokens` (reusing Sanctum's table/model purely as a convenient hashed-token
   store, not via Sanctum's guard), with `tokenable_type` set to the literal string `'EventAdmin'` or
   `'EventTeller'` (**not** an Eloquent model class, despite the column's normal morph-map purpose)
   and `tokenable_id` set to `admin_id` or `event_teller_id` respectively, and `expires_at = now() +
   API_TOKEN_HOURS` hours. The plaintext token is returned to the client once and never stored.
   Every protected API route is gated by the `account.token:{role}` middleware alias
   (`CheckAccountToken`, role = `teller` | `admin` | `any`), which: extracts the `Authorization:
   Bearer <token>` header, hashes it, looks it up, rejects if not found or expired (401); rejects if
   the token's role doesn't match the route's required role (403, and logs a `transactions` row
   describing the mismatch); for a teller token, re-joins `event_tellers → events → tellers →
   accounts` to confirm the *event* is still `Active` and the *account* is still active (401
   otherwise), then **overwrites** `event_teller_id`/`teller_id` on the request with the values
   derived from the token (if the client's own request body claims a different id for either field,
   the request is rejected with 403 — the identity never comes from client-supplied input); for an
   admin token, the analogous check re-joins `admins → accounts` and overwrites `admin_id`. In both
   cases the resolved `account_id` is stashed on the request as an attribute (`$request->attributes
   ->get('account_id')`), which is how server-side code logs "who did this" for API calls (there is
   no PHP session for API requests). On every successful check the token's `last_used_at` is
   touched (`saveQuietly()`, no model events). `POST /api/logout` (`account.token:any`) deletes the
   token row outright — logging out does not merely expire it.

   **Device lock.** A teller's `tellers.phone_uid` starts empty. The **first** successful
   password-login or QR-scan from a given `phone_uid` locks that teller to that device
   (`phone_uid` is saved). From then on, logging in from a *different* `phone_uid` is refused
   (401) unless an admin clears it (`POST /accounts/tellers/{id}/clear-phone-uid`, web only). If a
   *different* teller's account tries to log in from a `phone_uid` that is already linked to
   someone else, that is also refused with a message naming the other teller. The QR login path
   performs the same check under a row lock (`Teller::lockForUpdate()`) so two devices scanning the
   same QR simultaneously cannot both win the link.

   **QR login.** `POST /api/login-qr` takes `{qr_code, phone_uid}`. The teller's login QR is a
   string `SBQR1:` + 48 random characters, generated on demand by an admin
   (`POST /accounts/tellers/{id}/login-qr`, creating it on first call) or regenerated
   (`POST /accounts/tellers/{id}/login-qr/regenerate`, which immediately invalidates the old code —
   there is only ever one valid QR per teller, found by `qr_token_hash = SHA256(presented code)`).
   A successful scan performs the *same* device-lock logic as the password login and issues the
   same kind of bearer token; a teller never needs to change password via QR.

   **Change password (teller only, authenticated).** `POST /api/change-password` additionally
   re-registers the caller's `phone_uid` from the request body as a side effect (so changing a
   password from a new device also (re-)links that device) — this is **not** gated by the same
   phone-uid-conflict check used at login; it unconditionally overwrites `tellers.phone_uid`.

3. **Matching-system bridge, HMAC-signed, no user identity.** See §8. Fully separate from the
   above; these endpoints under `/api/bridge/matching/*` are rate-limited (`throttle:240,1`) and
   authenticated purely by a shared HMAC secret per direction, never by a user account.

**A fourth, narrow gate**: the "Unclaimed" page (listing unclaimed winnings across the whole event
history) is additionally gated by `unclaimed.password` middleware (`UnclaimedPasswordCheck`), which
simply requires the *currently logged-in admin* to have re-entered their own password once this
session (`session('unclaimed_authenticated')`); there is no separate shared password.

All API JSON responses are forced regardless of the client's `Accept` header by the `api`
middleware group prepending `ForceJsonResponse` (sets `Accept: application/json` on the request
before Laravel's validation/exception handling runs), so a validation failure on a mobile/desktop
endpoint comes back as JSON, not an HTML redirect.

---

## 6. Full route map

All controllers below are referenced by short class name (bare string controller@action syntax —
Laravel resolves them against the default `App\Http\Controllers` namespace).

### 6.1 `routes/web.php` (session-based; Blade views; grouped by middleware)

Ungrouped (public):
| Method | Path | Action |
|---|---|---|
| GET | `/logout` | `AccountController@logout` |
| GET | `/lang/ar`, `/lang/en` | `PageController@dashboard` (locale switch is actually a no-op route; real locale handling is session-based, see `LocaleMiddleware`) |
| GET | `/tv` | `PageController@tv` |
| GET | `/tv-new` | `PageController@tvNew` |
| GET | `/tv/get-started` | `MatchController@getStarted` |
| GET | `/tv/fight-history` | `MatchController@fightHistory` |

`middleware('isLoggedIn')`:
| Method | Path | Action |
|---|---|---|
| GET | `/`, `/login` | `PageController@login` |
| POST | `/login` (+`throttle:10,1`) | `AccountController@loginAccount` |

`middleware('isAll')`: empty group (dead code).

`middleware('isAdmin')` — everything below requires an Admin session:

| Method | Path | Action |
|---|---|---|
| GET | `/dashboard` | `PageController@dashboard` |
| GET | `/accounts/admins` | `AdminController@index` |
| POST | `/accounts/admins/list` | `AdminController@list` |
| POST | `/accounts/admins/store` | `AdminController@store` |
| POST | `/accounts/admins/update` | `AdminController@update` |
| POST | `/accounts/admins/find/{admin_id}` | `AdminController@find` |
| DELETE | `/accounts/admins/{admin_id}` | `AdminController@delete` |
| GET | `/accounts/tellers` | `TellerController@index` |
| POST | `/accounts/tellers/list` | `TellerController@list` |
| POST | `/accounts/tellers/store` | `TellerController@store` |
| POST | `/accounts/tellers/update` | `TellerController@update` |
| POST | `/accounts/tellers/find/{teller_id}` | `TellerController@find` |
| POST | `/accounts/tellers/{teller_id}/clear-phone-uid` | `TellerController@clearPhoneUID` |
| POST | `/accounts/tellers/{teller_id}/login-qr` | `TellerController@loginQr` |
| POST | `/accounts/tellers/{teller_id}/login-qr/regenerate` | `TellerController@regenerateLoginQr` |
| DELETE | `/accounts/tellers/{teller_id}` | `TellerController@delete` |
| GET | `/events` | `EventController@index` |
| POST | `/events/list` | `EventController@list` |
| POST | `/events/{event}/tellers` | `EventTellerController@listByEvent` |
| POST | `/events/{event}/update-tellers` | `EventTellerController@updateTellers` |
| POST | `/events/store` | `EventController@store` |
| POST | `/events/update` | `EventController@update` |
| POST | `/events/find/{event_id}` | `EventController@find` |
| DELETE | `/events/{event_id}` | `EventController@delete` |
| GET | `/matches` | `MatchController@index` |
| POST | `/matches/list` | `MatchController@list` |
| GET | `/matches/get-started` | `MatchController@getStarted` |
| GET | `/matches/new-match` | `MatchController@newMatch` |
| POST | `/matches/{match_id}/toggle-meron` | `MatchController@toggleMeron` |
| POST | `/matches/{match_id}/toggle-wala` | `MatchController@toggleWala` |
| POST | `/matches/{match_id}/{match_bet_status}/update-bet-status` | `MatchController@updateMatchBetStatus` |
| POST | `/matches/{match_id}/{match_status}/{winner}/update-match-status` | `MatchController@updateMatchStatus` |
| POST | `/matches/{match_id}/update-display` | `MatchController@updatedisplay` |
| POST | `/matches/{match_id}/hold` (numeric constraint) | `MatchController@hold` |
| GET | `/matches/bridge-status` | `MatchController@bridgeStatus` |
| POST | `/matches/find/{match_id}` | `MatchController@find` |
| DELETE | `/matches/{match_id}` | `MatchController@delete` *(no `delete()` method exists on `MatchController` — dead/broken route, see §10)* |
| GET | `/reports` | `EventController@reports` |
| POST | `/reports/report/{event_id}` | `MatchController@report` |
| GET | `/cash-transactions` | `PageController@cashTransactions` |
| GET | `/cash-transactions/tellers/load` | `EventTellerController@getTellers` |
| POST | `/cash-transactions/tellers/list` | `EventTellerController@listTellersByEvent` |
| POST | `/cash-transactions/cash-in` | `CashInController@storeByAdmin` |
| POST | `/cash-transactions/cash-in/approve` | `CashInController@approveCashIn` |
| POST | `/cash-transactions/cash-out/approve` | `CashOutController@approveCashOut` |
| POST | `/cash-transactions/cash-in/decline` | `CashInController@declineCashIn` |
| POST | `/cash-transactions/cash-out/decline` | `CashOutController@declineCashOut` |
| GET | `/teller-transactions` | `PageController@tellerTransactions` |
| POST | `/teller-transactions/get-data` | `EventController@getData` |
| POST | `/teller-transactions/history` | `BetController@history` |
| GET | `/event-audit` | `EventAuditController@index` |
| GET | `/event-audit/{event_id}` | `EventAuditController@show` |
| GET | `/event-audit/{event_id}/verify` | `EventAuditController@verify` |
| GET | `/teller-ledger` | `TellerLedgerController@index` |
| GET | `/teller-ledger/all` | `TellerLedgerController@all` |
| GET | `/teller-ledger/{event_teller_id}/print` | `TellerLedgerController@print` |
| GET | `/logs` | `PageController@logs` |
| POST | `/logs/list` | `TransactionController@fetchLogs` |
| GET | `/bets` | `PageController@bets` |
| POST | `/bets/list/{event_id}` | `BetController@listAll` |
| GET | `/unclaimed-password` | `PageController@unclaimedPassword` |
| POST | `/unclaimed-password` | `PageController@checkUnclaimedPassword` |
| GET | `/unclaimed` (+`unclaimed.password`) | `PageController@unclaimed` |
| POST | `/unclaimed/list/{event_id}` | `BetController@listAllUnclaimed` |
| GET | `/salaries` | `PageController@salaries` |
| POST | `/salaries/list` | `SalaryController@list` |
| POST | `/salaries/store/{event_id}` | `SalaryController@store` |
| POST | `/salaries/update` | `SalaryController@update` |
| POST | `/salaries/find/{salary_id}` | `SalaryController@find` |
| DELETE | `/salaries/{salary_id}` | `SalaryController@delete` |
| GET | `/remittances` | `PageController@remittances` |
| POST | `/remittances/tellers/list` | `TellerRemittanceController@list` |
| POST | `/remittances/admins/list` | `AdminRemittanceController@list` |
| POST | `/remittances/generate/{event_id}` | `TellerRemittanceController@generate` |
| POST | `/remittances/teller/update` | `TellerRemittanceController@update` |
| POST | `/remittances/admin/update` | `AdminRemittanceController@update` |
| POST | `/remittances/teller/find/{teller_remittance_id}` | `TellerRemittanceController@find` |
| POST | `/remittances/admin/find/{admin_remittance_id}` | `AdminRemittanceController@find` |
| DELETE | `/remittances/{teller_remittance_id}` | `TellerRemittanceController@delete` |
| GET | `/remittances/teller-shorts/list/{teller_remittance_id}` | `TellerShortController@list` |
| POST | `/remittances/teller-shorts/store` | `TellerShortController@store` |
| DELETE | `/remittances/teller-shorts/delete/{teller_short_id}` | `TellerShortController@delete` |
| GET | `/remittances/admin-shorts/list/{admin_remittance_id}` | `AdminShortController@list` |
| POST | `/remittances/admin-shorts/store` | `AdminShortController@store` |
| DELETE | `/remittances/admin-shorts/delete/{admin_short_id}` | `AdminShortController@delete` |
| GET | `/closing-reports` | `ClosingReportController@index` |
| GET | `/closing-reports/preview/{event_id}` | `ClosingReportController@preview` |
| POST | `/closing-reports/seal/{event_id}` | `ClosingReportController@seal` |
| GET | `/closing-reports/{event_closing_id}` | `ClosingReportController@show` |
| GET | `/closing-reports/{event_closing_id}/download` | `ClosingReportController@download` |
| POST | `/closing-reports/{event_closing_id}/acknowledge` | `ClosingReportController@acknowledge` |
| GET | `/backups` | `BackupController@index` |
| POST | `/backups` | `BackupController@store` |
| POST | `/backups/{file}/verify` (`.bak.enc`) | `BackupController@verify` |
| POST | `/backups/import/{file}/check` (`.sql`/`.sql.gz`) | `BackupController@checkImport` |
| POST | `/backups/import/{file}/restore` | `BackupController@restoreImport` |
| POST | `/backups/{file}/restore` (`.bak.enc`) | `BackupController@restore` |

A comment in `routes/web.php` records that the **Reset** page (`ResetController`) was deliberately
removed from routing — the controller class still exists (and still works if wired up — see §10)
but is unreachable.

### 6.2 `routes/api.php` (prefix `/api`, `ForceJsonResponse` + Sanctum's stateful middleware applied
via the `api` group, though the stateful-session piece is unused since every client is a bearer-token app)

`middleware('throttle:10,1')`:
| Method | Path | Action |
|---|---|---|
| POST | `/login-app` | `AccountController@loginApp` |
| POST | `/login-qr` | `AccountController@loginAppQr` |
| POST | `/login-app-admin` | `AccountController@loginAppAdmin` |

`middleware('account.token:any')`:
| POST | `/logout` | `AccountController@logoutApp` |

`middleware('throttle:240,1')`, prefix `/bridge/matching` (see §8):
| POST | `/bridge/matching/ping` | `BridgeController@ping` |
| POST | `/bridge/matching/call` | `BridgeController@call` |
| POST | `/bridge/matching/recall` | `BridgeController@recall` |

`middleware('account.token:teller')`:
| POST | `/change-password` | `AccountController@changePasswordApp` |
| POST | `/register-desktop-uid` | `AccountController@registerDesktopUID` |
| POST | `/create-bet` | `BetController@store` |
| POST | `/teller-data` | `EventTellerController@getData` |
| POST | `/reprint-last-receipt` | `BetController@reprint` |
| POST | `/reprint-teller` | `BetController@reprintTeller` |
| POST | `/latest-match` | `MatchController@getLatestMatch` |
| POST | `/bet-history` | `BetController@betHistory` |
| POST | `/bet-history-ongoing` | `BetController@betHistoryOngoing` |
| POST | `/cash-in` | `CashInController@storeByTeller` |
| POST | `/cash-in/history` | `CashInController@cashInHistory` |
| POST | `/cash-out` | `CashOutController@storeByTeller` |
| POST | `/cash-out/history` | `CashOutController@cashOutHistory` |
| POST | `/claim-winning` | `ClaimController@claimWinning` |
| POST | `/claim-winning-reprint` | `ClaimController@claimWinningReprint` |
| POST | `/claimed-receipts` | `ClaimController@cashOutHistory` |
| POST | `/unclaimed-receipts` | `BetController@unclaimedReceipts` |
| POST | `/void-bet` | `BetController@voidBet` |
| POST | `/voided-bets` | `BetController@voidedBets` |

`middleware('account.token:admin')`:
| POST | `/cash-in-admin` | `CashInController@storeByAdminViaApp` |
| POST | `/cash-in-admin/history` | `CashInController@cashInHistoryAdmin` |
| POST | `/cash-in-admin/approval` | `CashInController@approvalViaApp` |
| POST | `/admin-data` | `AdminController@getData` |
| POST | `/cash-out-admin` | `CashOutController@storeByAdminViaApp` |
| POST | `/cash-out-admin/history` | `CashOutController@cashOutHistoryAdmin` |
| POST | `/cash-out-admin/approval` | `CashOutController@approvalViaApp` |
| POST | `/event-tellers-admin` | `EventTellerController@listTellersAdmin` |
| POST | `/reprint-admin` | `BetController@reprintAdmin` |
| POST | `/matches-admin/list` | `MatchController@listApp` |
| POST | `/matches-admin/get-started` | `MatchController@getStartedApp` |
| POST | `/matches-admin/latest` | `MatchController@getLatestMatch` |
| POST | `/matches-admin/toggle-meron` | `MatchController@toggleMeron` |
| POST | `/matches-admin/toggle-wala` | `MatchController@toggleWala` |
| POST | `/matches-admin/update-bet-status` | `MatchController@updateMatchBetStatus` |
| POST | `/matches-admin/update-match-status` | `MatchController@updateMatchStatus` |
| POST | `/matches-admin/update-display` | `MatchController@updatedisplay` |
| POST | `/events-admin/list` | `EventController@list` |
| POST | `/events-admin/find` | `EventController@find` |
| POST | `/events-admin/store` | `EventController@store` |
| POST | `/events-admin/update` | `EventController@update` |
| POST | `/events-admin/delete` | `EventController@delete` |
| POST | `/events-admin/tellers` | `EventTellerController@listByEventApp` |
| POST | `/events-admin/update-tellers` | `EventTellerController@updateTellersApp` |

### 6.3 `routes/channels.php`
Contains only the Laravel scaffold example (`App.Models.User.{id}`, authorized by `(int)$user->id
=== (int)$id`). **Unused** — none of the app's real broadcast channels (`cash-ins`, `cash-outs`,
`event-updates`, `match-updates`, `fight-history`, `tv-display`, `event-tellers`) are private or
presence channels requiring this auth endpoint; they are all plain public `Channel`s (see §9). A
Rust rewrite reproducing this system faithfully should likewise treat these channels as
unauthenticated/public, unless deliberately hardening them.

### 6.4 `routes/console.php`
Only the default Laravel `inspire` Artisan command. No scheduled tasks are defined anywhere
(`Console/Kernel::schedule()` body is empty).

---

## 7. Business workflows end-to-end

### 7.1 Opening/managing an event
1. Admin opens **Events**, submits `event_name`, `event_description`, `event_percentage` (must be
   numeric, `0 <= p < 1`), `event_date`, `event_status` (`Active` or `Completed` only —
   `EventController@store`/`@update` reject anything else with HTTP 422), `admin_cash_on_hand`,
   `teller_initial_cash_on_hand`, `prizes`, `rd`.
2. If `event_status = Active`, the server refuses the request (422) if another event already has
   `event_status = Active` (checked with a plain `where()->exists()` — **not** inside a DB
   transaction/lock, so a true race between two admins is theoretically possible, see §10).
3. On success, a row is inserted into `events`; a `transactions` log line is written.
4. Assigning tellers to the event: `EventTellerController@updateTellers` receives the full desired
   list of `teller_id`s for the event and reconciles it against `event_tellers` inside one DB
   transaction:
   - Tellers currently in `event_tellers` for this event who have **no bets at all** and are **not**
     in the new list are removed: their `teller_balance` is zeroed, and if they have *any* existing
     ledger rows or a non-zero balance, an `adjustment` ledger line is written recording the
     returned cash; their `event_tellers` row is deleted; any `cash_ins` rows belonging to their
     removed `event_teller_id` are deleted outright (a comment in the code explicitly notes an
     earlier bug where this used to delete cash-ins for *every other event's* tellers too — fixed by
     scoping the delete to only the ids just removed).
   - A teller who has bets **cannot** be removed even if omitted from the new list — they stay
     assigned regardless.
   - Any `teller_id` in the new list not already in `event_tellers` for this event gets a fresh
     `event_tellers` row (`teller_balance = teller_match_balance = 0` is **not** what's inserted —
     `teller_balance` is set to `event.teller_initial_cash_on_hand`, `teller_match_balance = 0`),
     plus an immediately-`Approved` `cash_ins` row of type `AdminCashInInit` for the same amount, and
     a corresponding `opening` ledger line via `TellerLedger::cashDecision('in', ..., 'Approved')`.
   - Broadcasts `CashInUpdated`.

### 7.2 A fight's lifecycle on the betting side
Two very different starting points, mutually exclusive by `config('bridge.enabled')`:

**A. Matching bridge disabled (local/manual mode).** The operator clicks "Get Started" /
"New Match" and the server (`MatchController@newMatch` / `newStaticMatch`) simply creates the next
`matches` row for the Active event with `match_number = previous + 1`, zeroed pools/odds,
`match_bet_status` either `Open` (manual "new match" button) or `Closed` (the very first
placeholder fight auto-created by `getStarted`/`fightHistory` when none exists yet), `match_status
= Ongoing`, `is_display = 0`, and resets every teller's `teller_match_balance` to 0 for the event.
Broadcasts `MatchUpdated` (+ `TellerBalanceUpdated`).

**B. Matching bridge enabled.** Fights are *never* created by the operator; they only ever arrive via
a signed `POST /api/bridge/matching/call` from the matching system (full contract in §8). The
operator's job is then purely to open/close betting and declare the result on whatever fight
matching has called.

Once a fight exists and is `Ongoing`:
3. **Toggling a side** (`toggleMeron`/`toggleWala`): only while `match_status === 'Ongoing'` and the
   event is `Active` (else 422). Recomputes `meron_total_bet`/`wala_total_bet` from the live `bets`
   table, recomputes odds (§7.3's formula), flips the one boolean, saves, broadcasts
   `TVDisplayUpdated` + `MatchUpdated`.
4. **Opening/closing betting** (`updateMatchBetStatus`, value must be `Open`/`Closed`): only while
   `Ongoing` and event `Active`. Also unsets `is_display` on every fight in the whole table first
   (TV display follows bet-status changes). Recomputes totals/odds the same way. Refuses to reopen
   (`Open`) a fight that currently has a `hold_reason` set — matching must fix and re-send it first.
   If the fight is **not** bridge-linked, this endpoint also accepts/overwrites `meron_entry` /
   `wala_entry` from the request (manual mode only — a bridge-linked fight's entries always come
   from matching and are never editable here). The **first** time a fight transitions to `Open`,
   `bet_opened_at` is stamped — from this moment the fight is permanently locked against matching
   edits/recall (§8). Queues a `status` bridge message (`MatchingBridge::queueStatus`, state =
   lowercase of `match_bet_status`) and schedules a flush after the response is sent. Broadcasts
   `MatchUpdated` + `TVDisplayUpdated`.
5. **Hold** (`MatchController@hold`, bridge-linked fights only): records a free-text reason
   (≤200 chars), only while `match_status === 'Ongoing'` **and** `bet_opened_at` is still null (i.e.
   betting has never opened on this fight). Queues a `held` bridge status message. Broadcasts
   `MatchUpdated`.
6. **Declaring the result** (`updateMatchStatus`) — see §7.4, it performs settlement in the same
   step.
7. After a result is declared (unless it's a correction, or the bridge is enabled, or the caller
   explicitly passed `dont_create`), the next placeholder fight is auto-created
   (`newStaticMatch()`), and both `MatchUpdated` and `MatchesUpdated` are broadcast for the new list.

### 7.3 Odds computation (identical formula used in `toggleMeron`, `toggleWala`,
`updateMatchBetStatus`, `updateMatchStatus`, and live on every new bet in `BetController@store`/
`voidBet`)
Given the event's commission rate `r = events.event_percentage` and the current valid-bet totals
`M = meron_total_bet`, `W = wala_total_bet` (both re-summed from `bets` where `bet_status = 1`,
grouped by side, **not** incrementally trusted from the stored column — except inside
`BetController@store`/`voidBet` which *do* increment/decrement the stored totals directly under a
row lock instead of re-summing, see below):

```
if M > 0 and W > 0:
    meron_net = M - M*r         # i.e. M * (1 - r)
    wala_net  = W - W*r
    pool_net  = meron_net + wala_net
    meron_odds = round(pool_net / M, 2)
    wala_odds  = round(pool_net / W, 2)
else:
    # one side has zero valid bets — odds are not meaningful yet
    meron_odds = wala_odds = 1          # (BetController@store path only; the other paths simply skip recompute and leave the previous odds value in place)
```

This is a **parimutuel** pool: every winning bettor is paid from the *combined* pool of both sides
minus the house commission, in proportion to the winning side's own total — algebraically,
`odds_side = (M + W) * (1 - r) / side_total`, which is what the code computes (just split into two
intermediate products). Odds are PHP floats, rounded to 2 decimals with `round()` (banker's/half-up
depending on PHP's rounding mode — standard `round()` is half-away-from-zero), **not** via the
`Money` service's `bcmath`-based helpers; this is one of the places §10 flags as a precision risk.

Inside `BetController@store` and `BetController@voidBet` specifically, `meron_total_bet` /
`wala_total_bet` are maintained **incrementally** (`+= $amount` / `-= $amount` in PHP, under a
`Fight::lockForUpdate()` row lock acquired at the very top of the bet/void transaction) rather than
re-summed from `bets`, which is why `ClosingReportBuilder` separately flags a `match_total_mismatch`
if the stored running total ever drifts from the true sum of live bets (see §7.11) — this is a
designed cross-check, not a bug, but it does mean the two code paths (incremental vs. re-sum) must
be kept behaviourally identical in any rewrite.

### 7.4 Placing a bet
`POST /create-bet` (teller token required). Request fields: `side` (`Meron`|`Wala`, required),
`amount` (numeric, `BET_MIN <= amount <= BET_MAX`, required), `match_id` (integer, required;
accepted if it equals **either** the current open fight's `match_id` **or** its `match_number` —
supporting older clients that only know the fight number). `event_teller_id` is taken from the
bearer token, never from the request body.

Validation failures return `422` with the first Laravel validator error message. Steps, all inside
one DB transaction:
1. `amount = round((float) amount, 2)`.
2. Require an Active event, else fail with a plain-200 JSON `{success:false, message:...}` (**not**
   an HTTP error status — this endpoint always returns 200 except on validation failure or an
   uncaught exception, which returns 500).
3. Lock (`FOR UPDATE`) the single fight for this event where `match_bet_status = Open AND
   match_status = Ongoing`, ordered by `match_id DESC` (i.e. the most recent such fight — there
   should only ever be one). Fail if none found.
4. Load `event_tellers` row for the token's `event_teller_id`; fail if missing. Load the `tellers`
   row; fail if `phone_uid` is empty (device never activated).
5. Re-check the `match_id`/`match_number` match (see above) — fail otherwise ("Fight is already
   lapsed or not existing").
6. Re-check the requested side isn't individually disabled (`meron_bet_status`/`wala_bet_status ==
   1`) — this check happens **again** here even though the fight was already filtered to
   `match_bet_status = Open`, because a side can be disabled independently of the overall status.
7. Generate a cryptographically random receipt code: loop `random_int(0,9)` for
   `RECEIPT_RANDOM_DIGITS` digits, prefix with `{event_teller_id}{match_number}`, retry the whole
   digit generation until the resulting code is not already present in `bets`.
8. Insert the `bets` row (`bet_status = 1`, `bet_is_winner = 0`, payout fields zero/null).
9. Log to `transactions` (`"Teller App"`, includes the stringified bet object).
10. Increment the fight's running total for the chosen side by `amount` and recompute odds per
    §7.3 (writing `meron_odds`/`wala_odds`/`meron_total_bet`/`wala_total_bet`, saved).
11. Atomically increment the teller's `teller_balance` **and** `teller_match_balance` by `amount`
    via a raw `DB::raw('teller_balance + <amount>')` update (single UPDATE statement, safe under
    concurrent bets on the same teller because of the fight-row lock serializing all bets on that
    fight — but note this does **not** itself lock the `event_tellers` row; concurrent bets from the
    *same* teller against *different simultaneously-open fights* could theoretically race, though
    only one fight is ever open at a time in practice).
12. Write a `bet` ledger line via `TellerLedger::record()` (amount_in = stake, source =
    `bets`/this bet id).
13. Commit. Broadcast `MatchUpdated` (new fight state) and, after recomputing every event teller's
    `total_bet`/`total_payout` aggregates, `TellerBalanceUpdated`.
14. Response: `{success:true, message:"Bet created successfully!", bet:{...plus match_number,
    event_name, teller_name attached for the receipt printer}}`.

### 7.5 Closing betting / settling a fight's result
`POST /matches/{match_id}/{match_status}/{winner}/update-match-status` (web) or
`/matches-admin/update-match-status` (admin app, same controller method). `match_status` must be
one of `Completed`, `Draw`, `Cancelled`; if `Completed`, `winner` must be `Meron`, `Wala`, `Draw`,
or `Cancelled` (the latter two allow declaring a draw/cancel *through* the "Completed" form
field — the code then rewrites `match_status = winner` and clears `winner` before proceeding, so a
request of `status=Completed&winner=Draw` ends up identical to `status=Draw`). If not `Completed`,
`winner` must be empty, `-`, `Draw`, or `Cancelled`.

Preconditions (else 422 and no write):
- Fight must exist; its event must be `Active`.
- **First declaration**: if `REQUIRE_CLOSED_BEFORE_RESULT` is true (default), `match_bet_status`
  must already be `Closed`.
- **Correction** (the fight's `match_status` is already one of the three settled values): only the
  **latest** settled fight in the event (by `match_id`, i.e. creation order, not `match_number` —
  fights can be played out of number order) may be corrected; and only if **no claim exists yet**
  for any bet on that fight (checked by a join `claims ⋈ bets` filtered to this `match_id`). A
  correction forces `dont_create = 1` (never spawns a new placeholder fight).
- If the matching bridge is enabled, `dont_create` is also always forced to `1` — the next fight is
  only ever created by a future `call`, never by settling this one.

Settlement, inside one DB transaction:
1. Re-sum `meron_total_bet`/`wala_total_bet` from live bets and recompute odds (§7.3) one more time
   before settling (so the odds locked into payouts reflect the truly final pool).
2. `match_status = <requested>`; branch on it:
   - **Completed**: `match_winner = winner`. First, reset every live bet on this fight to
     `bet_win_amount = 0, bet_payout_amount = 0, bet_is_winner = 0` (defensive reset, matters most
     for corrections). Then for every live bet on the **winning** side:
     `odds = (winner == 'Meron') ? meron_odds : wala_odds` (the odds just recomputed in step 1);
     `bet_win_amount = floor(bet_amount * odds)`; `bet_payout_amount = floor(bet_win_amount)`
     (a no-op floor since it's already an integer, kept for clarity/legacy reasons);
     `bet_is_winner = 1`. **Losing-side bets are left exactly as the reset left them** (payout 0,
     not a winner) — there is no explicit "mark losers" step, it falls out of the reset.
   - **Cancelled**: `match_winner = 'N/A'`. Every live bet (both sides): `bet_win_amount = 0`,
     `bet_payout_amount = bet_amount` (full refund), `bet_is_winner = 0`.
   - **Draw**: identical to Cancelled (full refund both sides, `match_winner = 'N/A'`). The system
     does not distinguish a draw's refund from a cancellation's refund in the bet rows at all; they
     only differ in `match_status` (and, downstream, in how `ClosingReportBuilder` labels/aggregates
     them, and in the result value sent to matching — `draw` vs `cancelled`).
3. `is_display = 1` (bring this fight onto the TV automatically).
4. Save the fight.
5. Queue a `result` bridge message: `result` field is `strtolower(match_winner)` if Completed, else
   `strtolower(match_status)` (so matching receives one of `meron`/`wala`/`draw`/`cancelled`),
   `version` = however many `result`-type outbox rows already exist for this `fight_uid` **plus
   one** (i.e. version counts attempts, not unique corrections — re-sending after an outbox retry
   does not re-use the same version number, see §10), `previous` = the prior
   `"{match_status} / {match_winner}"` string if this is a correction, else `null`, `declared_by` =
   the acting account's username.
6. Log to `transactions`; if a correction, a **second** log line prefixed `"RESULT CORRECTION:"` is
   also written (this second line, specifically, is what `ClosingReportBuilder::fightAudit()` counts
   as `result_corrections` at closing time, via `declaration_count - 1`).
7. Broadcast `TVDisplayUpdated`. Commit. Schedule a bridge flush. If `dont_create` was not set,
   create the next placeholder fight (resets all tellers' `teller_match_balance` to 0 as a side
   effect of `newStaticMatch()`). Broadcast `MatchUpdated` (the *new current* fight, which after a
   non-`dont_create` settlement is the freshly created placeholder, not the one just settled!) and
   `MatchesUpdated` (full list for the event).

Note the payout math uses **PHP floats** end to end (`floor($bet->bet_amount * $odds)`), not the
`Money` cents helpers — `App\Services\Closing\Money::floorPesosTimesOdds()` exists specifically to
*reproduce* this exact float behavior when re-verifying stored payouts later at closing time, and
`Money::centsTimesOdds()` encodes the *other* (earlier-version) formula the report also accepts as
valid. A faithful rewrite must replicate `floor(bet_amount_as_float * round(odds_as_float, 2))`
bit-for-bit, not simply redo the arithmetic in fixed-point and expect the same cent values.

### 7.6 Teller cash-in / cash-out flow and the teller ledger
**Cash-in**, three entry points, same net effect (increase `event_tellers.teller_balance`):
- `storeByAdmin` (web): always created `Approved` immediately, type `AdminCashIn`, admin id from
  session.
- `storeByTeller` (API): created `Pending`, type `TellerCashIn`, `admin_id = null`; requires the
  event still `Active` or refused with a plain-200 failure JSON.
- `storeByAdminViaApp` (admin app API): same as `storeByAdmin` but admin id from the request
  (already verified by the token middleware to match the caller).
- A `Pending` teller-initiated request is later resolved by `approveCashIn` (web) /
  `approvalViaApp` (admin app) / `declineCashIn` (web): all three perform an **atomic
  conditional UPDATE** (`WHERE cash_in_status = 'Pending'`) so exactly one approval/decline can ever
  win a race on the same request (`$won !== 1` ⇒ 409/plain-failure "already processed"); only on
  `Approved` is the teller balance incremented (inside the same transaction as the status flip).
  Every outcome (approve or decline) calls `TellerLedger::cashDecision('in', $cashIn, $status)`,
  which stamps `approved_at`, and writes either an `opening`/`cash_in` ledger line (if Approved; the
  type is `opening` specifically when `cash_in_type === 'AdminCashInInit'`) or a `cash_declined`
  line (if Declined) with a `CI-{id}` reference.
  Every resolution broadcasts `CashInUpdated` + refreshed `TellerBalanceUpdated`.

**Cash-out** is the mirror image (decreases `teller_balance`), with one extra guard not present on
cash-in: every path that actually debits the balance (`storeByAdminViaApp` immediately,
`approveCashOut`/`approvalViaApp` on approval) uses a conditional
`WHERE teller_balance >= cash_out_amount` update and treats `$ok !== 1` as "insufficient cash on
hand", rolling back with a 409/plain-failure response. The teller-initiated `storeByTeller` path
checks `amount > teller_balance` up front as well (not atomically, since nothing is debited yet at
request time — the balance is only touched on approval, where the atomic check runs again).

**Ledger invariant.** Every balance-changing cash-in/cash-out resolution writes its `teller_ledger`
line in the *same* DB transaction as the balance UPDATE, via `TellerLedger::record()`, which itself
takes a row lock (`event_tellers ... FOR UPDATE`) to compute `balance_before` deterministically as
`balance_after - amount_in + amount_out`, assigns the next `line_no` for that
`event_teller_id` (`MAX(line_no) + 1`), and inserts. `TellerLedger::verify($event_teller_id)`
mechanically re-checks that every line's `balance_after = balance_before + amount_in - amount_out`,
that every line's `balance_before` equals the *previous* line's `balance_after`, and that the last
line's `balance_after` equals the teller's current live `teller_balance` — used both on-demand (the
Teller Ledger admin screens) and as one of the automated checks baked into the closing report
(§7.11).

### 7.7 Teller remittance to admin
At (or near) the end of an event, `TellerRemittanceController@generate` is called once per event: it
ensures every currently-assigned `event_teller_id` has exactly one `teller_remittances` row
(creating an all-zero one if missing), deletes any leftover remittance rows for tellers no longer
assigned to the event, and ensures a single all-zero `admin_remittances` row exists for the event
too. The admin then edits each teller's remittance with the *physically counted* denomination
breakdown via `TellerRemittanceController@update` (nine `denom_*` integer fields). There is **no
server-side computation of "expected vs counted"** at this layer — that reconciliation only happens
later, read-only, inside `ClosingReportBuilder` (§7.11) and on the printable ledger statement
(`TellerLedgerController@print`), which independently totals the nine denominations (`Σ denom_d *
d`) and compares it against the teller's final ledger balance.

### 7.8 Teller/Admin "shorts" (shortages) handling
Purely a flat CRUD layer: `TellerShortController`/`AdminShortController` `list`/`store`/`delete` a
free-form `short_amount` (`decimal`, sign convention is by human convention — positive = shortage,
per the comment in `ClosingReportBuilder`, but nothing enforces the sign) and `short_remarks`
against a specific `teller_remittance_id`/`admin_remittance_id`. Multiple shorts may exist per
remittance (e.g. recording partial explanations); `ClosingReportBuilder` sums them all per teller
into `recorded_shorts` and separately computes its own `short_over = counted − expected` for
comparison — the two numbers are not required to agree, and a mismatch is **not** one of the
blocking checks for sealing (it shows up only as informational `cash_short_over_tellers` in the
report's `exceptions` section).

### 7.9 Admin remittance
Same pattern as teller remittance but a single row per event (`AdminRemittanceController@update`,
fields prefixed `admin_denom_*` in the request instead of `_denom_*`). `AdminRemittanceController
@list` additionally attaches a computed `cash_on_hand = (event.admin_cash_on_hand − Σapproved
cash_ins) + Σapproved cash_outs` to every row returned (there is normally only one row per event).

### 7.10 Salary handling
Flat CRUD (`SalaryController`) against `salaries`, scoped by `event_id`. `store` has a special
`include_all_tellers` flag: when set, instead of creating one salary row from the posted
`employee_name`, it creates **one identical salary row per currently-assigned teller** of the event
(same `employee_type`/`daily_rate`/`overtime_rate`/`total_overtime`/`bonus`, `employee_name` taken
from each teller's own name) — i.e. a bulk "give every teller the same day-rate/bonus" shortcut.
Salaries feed only the **operational** report (`MatchController@report`, §7.11.1); they are **not**
part of the sealed closing-report payload at all.

### 7.11 Event closing report & sealing

There are effectively **two different "closing report" concepts** in this codebase, computing
overlapping but distinct numbers, and a Rust rewrite must keep both:

#### 7.11.1 The operational report (`MatchController@report`, feeds `/reports`)
A dashboard-style aggregate query (not sealed, not stored, recomputed live) returning: total Meron/
Wala bets on **Completed** fights only, fight counts by status, bet counts (incl. by side), voided
bet total, claimed vs unclaimed winnings, `Σ salaries` (`daily_rate + overtime_rate*total_overtime +
bonus` per row), and a chain of derived figures: `organizer_share = total_collection *
event_percentage`; `net_for_sharing = organizer_share − total_salary`; a hard-coded **19% / 81%**
split of `net_for_sharing` into `system_share` / `operator_share` (these percentages are not in
`.env` or any config — they are literal constants `0.19`/`0.81` in the controller); cash reconciliation
against the admin's physically-counted remittance (`supposed_cash_on_hand` from approved cash
movements vs `actual_cash_on_hand` from `admin_remittances` denominations) with `difference` and
`short = difference − event.rd`.

#### 7.11.2 The sealed closing report (`ClosingReportBuilder` → `EventSealer`, feeds `/closing-reports`)
This is the legally significant one. All money in this path is computed in **integer centavos**
via `App\Services\Closing\Money` (§ below) and only formatted back to fixed 2-decimal strings for
the final payload — unlike the live betting code, which stays in PHP floats throughout (§7.3–7.5).

**`Money` helpers** (`app/Services/Closing/Money.php`):
- `cents($v)`: `bcmul((string)$v, '100', 0)` cast to int — exact decimal-to-cents conversion, no
  float rounding error.
- `fmt($cents)`: formats back to a `"-?D+.DD"` string.
- `percentOf($cents, $rate)`: `round(bcmul($cents, $rate, 4), 0, PHP_ROUND_HALF_UP)` — commission,
  half-up rounded to the centavo.
- `odds($odds)`: `number_format(round((float)$odds, 2), 2)` — purely a display formatter for the
  float odds column.
- `floorPesosTimesOdds($betAmount, $odds)` / `centsTimesOdds($betAmount, $odds)`: the two historical
  payout formulas from §7.5, reproduced here in float arithmetic (not cents) specifically so stored
  `bet_payout_amount` values can be independently re-derived and compared — **this function
  deliberately does not use fixed-point math**, because its job is to replicate what the live
  (float-based) settlement code actually produced, bugs and all.

**`ClosingReportBuilder::build(eventId, logFromId, logToId)`** — pure read, no writes; can run
against the live DB or (via `->on('legacy')`) a separately-loaded "legacy" connection for
reconstructing historical events from an old backup (§7.11.4). Builds, per fight (`status` is one
of `Ongoing/Completed/Draw/Cancelled`; "settled" = the latter three):
- `meron_total`/`wala_total`/`net_pool` from valid bets only (voided excluded).
- For **Completed**: `commission = percentOf(pool, event_percentage)`; `winnings = Σ
  bet_payout_amount` over valid winning-side bets; `breakage = pool − commission − winnings`
  (breakage = rounding residue from flooring individual payouts); independently recomputes winnings
  both ways (`floorPesosTimesOdds` and `centsTimesOdds`) and flags `payout_mismatch` if the stored
  total matches **neither**; flags `breakage_out_of_bounds` if `|breakage|` exceeds a generous bound
  derived from `(winning bet count × 1 peso) + side_total/200 + 1 peso` (rounding tolerance).
- For **Draw/Cancelled**: `refunds = Σ bet_payout_amount` (should equal the full pool);
  `breakage = pool − refunds`; flags `refund_mismatch` if `refunds ≠ pool`.
- `house_take = commission + breakage` (what the house actually kept, independent of the
  commission-rate formula).
- Flags `match_total_mismatch` if the stored `meron_total_bet`/`wala_total_bet` columns disagree
  with the true sum of valid bets (see §7.3's note on incremental vs. re-summed totals).
- Flags `overpaid` if `Σ claims > payable` for that fight (can happen after a correction that
  reduces payable amounts post-payout).
- `unused` = true for a settled-looking-unsettled fight with **zero** bets at all — every event's
  last (freshly auto-created) placeholder fight is always `unused`, and this does **not** block
  sealing.
- Per-fight `audit` block (first/last bet timestamp from the bets themselves; betting
  opened/closed timestamps and open-count and result-declaration count/timestamp/declarer, all
  mined from `transactions.transaction_message` via the regex in §3.1).

Per teller: `opening_cash` (approved `AdminCashInInit` cash-ins), `cash_in` (other approved
cash-ins), `cash_out` (approved cash-outs), `gross_bets`/`voids`/`net_bets`, `payouts` (Σ their
claims), `expected_cash = opening + cash_in + net_bets − payouts − cash_out`, `system_balance`
(live `teller_balance`, flagged mismatched if ≠ expected), `counted_cash` (Σ denominations from
their remittance, if one exists), `short_over = counted − expected`, `recorded_shorts` (Σ their
`teller_shorts`).

Event-wide `totals` sum every fight/teller figure, plus fight-status counts and
`unsettled_pool` (net pool of fights not yet in a settled state).

`exceptions`: void count/amount, total result corrections, total betting re-opens (`open_count −
1` per fight, summed), count of log lines containing "reprint", duplicate-claim count (same `bet_id`
claimed more than once — should be impossible given the DB unique constraint, but checked anyway),
pending cash-request count, list of tellers with a non-zero `short_over`, list of tellers with a
`teller_balance_mismatch`, plus every per-fight flag list collected from above.

`checks`: a named pass/fail array — `all_fights_settled`, `no_pending_cash_requests`,
`net_bets_balance` (net bets = Σ(refunds+winnings+commission+breakage) + unsettled pool),
`fights_equal_tellers` (Σ fight net pools = Σ teller net bets), `payable_equals_paid_plus_unclaimed`,
`teller_payouts_equal_claims`, `no_duplicate_claims`, `no_overpaid_fights`, `payouts_match_odds`,
`refunds_equal_pool`, `breakage_within_bounds`, `match_totals_consistent`,
`teller_balances_consistent`, and (only if a `teller_ledger` row exists for at least one teller)
`teller_ledger_consistent` (each teller's last ledger `balance_after` must equal their recorded
`system_balance`).

**`ClosingReportBuilder::blockingChecks()`** — exactly three names are allowed to block sealing:
`all_fights_settled`, `no_pending_cash_requests`, `no_duplicate_claims`. Every other failing check is
recorded in the sealed payload but does **not** prevent sealing (it is evidence for an auditor, not
an automatic blocker).

**`EventSealer::seal(eventId, closedByAccountId, approvedByAccountId)`** (triggered from
`ClosingReportController@seal`, web only):
1. Web form requires `confirmation = "CLOSE"` and, if `SEAL_REQUIRE_SECOND_APPROVER` (default true),
   `approver_username`/`approver_password` for a **second**, currently-active Admin account whose
   `account_id` differs from the closer's own session account — verified by `Hash::check` against
   `accounts.password`. Any failure returns to the form with an error, no DB write.
2. Inside one DB transaction: lock the `events` row; refuse if already sealed
   (`EventClosing::isSealed`). Lock the latest `event_closings` row to get the next global
   `sequence_no` and `prev_payload_sha256` (or the 64-zero GENESIS hash for the very first seal
   ever).
3. Compute the log range to include: from `(previous seal's covered log_range[1]) + 1` (or `1` if
   none) through the current `MAX(transaction_id)`.
4. Build the report (§ above); abort with a `RuntimeException` (rolling back, no partial writes) if
   any **blocking** check fails, listing which ones and why.
5. Write the detail file: `{output_path}/{event_date}_E{event_id:04}_detail_S{sequence:04}.jsonl`,
   one canonical-JSON line per raw DB row across bets/claims/cash_ins/cash_outs/event_tellers/
   matches/remittances/shorts/logs, mode `0400` (read-only, even to its owner) — **fails outright**
   (`fopen(..., 'x')`) if a file of that name already exists, rather than overwrite it.
6. Assemble the `payload` object: `format` (`sealing.payload_format`, currently
   `"sabonglara.closing/1"`), `server_id`, `sequence_no`, `prev_payload_sha256`, `closed_at`,
   `closed_by`/`approved_by` (resolved to **usernames**, not raw ids, inside the signed payload),
   the `event`/`fights`/`tellers`/`totals`/`exceptions`/`checks` sections verbatim from the report,
   and a `detail` sub-object (`sha256` of the detail file, its basename, the log range, and a
   per-table row-count map).
7. Canonicalize (`Canonical::encode`: recursively sort object keys by byte value with `ksort(...,
   SORT_STRING)`, no escaped slashes/unicode, no extra whitespace — guarantees the exact same bytes,
   and therefore the exact same SHA-256, regardless of which machine computes it) and hash with
   SHA-256. Sign the canonical JSON bytes with `sodium_crypto_sign_detached` using this server's
   Ed25519 secret key (`storage/app/keys/sign.key`, generated once via `php artisan seal:keygen` and
   never meant to leave the server).
8. **Freeze the event**: `events.event_status = 'Closed'` is written *before* the `event_closings`
   row is inserted, in the same transaction — from this instant, `GuardsSealedEvent` (and the DB
   triggers on `event_closings` itself) make every fight/bet/claim/cash row for this event
   permanently immutable.
9. Insert the `event_closings` row (all the hash/signature/sequence fields from above, plus
   `seal_code = sealCode(hash)`: the first 16 hex characters of the hash, uppercased, hyphenated
   into 4-character groups, e.g. `"A1B2-C3D4-E5F6-0789"` — meant to be read aloud/typed by a human).
10. Log a `transactions` line recording the full seal identity. Commit.
11. Outside the transaction (so it survives even if something above the DB layer later fails):
    append one line to an **out-of-database**, append-only JSONL "seal registry"
    (`{output_path}/seal-registry.jsonl`) recording `sequence_no`, `event_id`, `payload_sha256`,
    `closed_at`. This file is the real tamper-detection backstop for the backup/restore system (see
    §7.12) — a restored database that lost a seal cannot pass the registry check even though the
    `event_closings` table itself is gone or rolled back.
12. On **any** failure after the detail file was written, the orphaned detail file is deleted before
    the exception propagates (so a failed seal leaves no half-written artifact).

**Downloading the package** (`ClosingReportController@download`): wraps the sealed row into an
*encrypted* package addressed to the BIR compliance VPS — `EventSealer::package()` builds an inner
canonical-JSON envelope (`format`, `payload`, `payload_sha256`, `signature`, `key_fingerprint`),
then `sodium_crypto_box_seal()`s it (anonymous sealed-box, X25519) with the **VPS's** public key
(`SEAL_VPS_PUBLIC_KEY` / `storage/app/keys/vps.pub`), wraps that ciphertext in an outer plaintext
envelope (`server_id`, `event_id`, `sequence_no`, `seal_code`, `payload_sha256`, `key_fingerprint`,
`encryption` tag, base64 `ciphertext`), and names the file
`{event_date}_E{event_id:04}_pkg_S{sequence:04}_{closed_at:Ymd'T'His}_{server_id}.pkg.enc`. Only the
VPS's private key can ever decrypt this — the betting server itself cannot read it back. Stamps
`downloaded_at`/`downloaded_by_account_id` on the `event_closings` row (one of the few mutable
fields) and logs the download's own SHA-256.

**Acknowledging** (`ClosingReportController@acknowledge`): records a free-text `ack_code` (≤200
chars, `[A-Za-z0-9\-:._]+`) the VPS gives back after accepting the package — refuses if already
acknowledged once.

**Re-verifying a sealed event** (`EventAuditController@verify`, read-only, GET): recomputes the live
report over the *same* stored log range and checks: the stored `payload_sha256` really hashes
`payload`; the `seal_code` really derives from that hash; the stored `signature` really verifies
against *this server's current* signing public key (if the key fingerprint doesn't match the
server's current key, this check is reported as failed-but-informational, suggesting verifying on
the VPS instead — e.g. after a signing-key rotation); the hash-chain really continues from the
previous sequence number's hash; each of `event`/`fights`/`tellers`/`totals`/`exceptions`/`checks`
in a **freshly rebuilt** report canonicalizes to byte-identical JSON versus the sealed payload (with
a human-readable diff of which fight/teller rows changed, if any); and the on-disk detail file still
hashes to `detail_sha256`.

**`EventSealer::verifyPackage()`** is the reference implementation the BIR VPS team is meant to port:
decrypt with the VPS's own keypair, re-hash, check the seal code, check the key fingerprint is a
registered server key, verify the Ed25519 signature, optionally check chain continuity against the
previously accepted package's hash, and independently re-derive every cross-total balance check
(`fight_rows_balance`, `totals_equal_fight_sums`, `net_bets_balance`, `tellers_equal_fights`,
`teller_rows_balance`, `commission_rate_applied`) purely from the numbers inside the payload — i.e.
the VPS does **not** trust the betting server's own `checks` array; it recomputes the same
arithmetic independently from the sealed figures.

#### 7.11.3 Dead/unreachable UI note
The **Reset** page/controller (`ResetController`, `routes/content/reset/index.blade.php`) is present
in the codebase and fully functional (truncates `bets`, `cash_ins`, `cash_outs`, `claims`, `matches`,
`personal_access_tokens`, `transactions`, `event_tellers`, `teller_shorts`, `salaries`,
`teller_remittances`, resets `event_tellers.teller_balance`/`teller_match_balance` to 0, clears
`tellers.phone_uid`, and attempts a `mysqldump`-based pre-reset backup first) but is **not wired into
`routes/web.php` at all** — see §10.

#### 7.11.4 Legacy import (reconstructing old events from a backup)
`LegacySealer::sealLoadedBackup()` is driven by the `legacy:seal-file` Artisan command (itself
normally invoked by an external shell script that first restores an old `.sql` dump into a separate
scratch database `sabong_legacy_tmp`). It builds a `ClosingReportBuilder` report against that
scratch connection, using the single `event_id` with the most fight rows in it, over the **entire**
transaction-log range of that backup (`1..MAX(transaction_id)`). Skips (returns the existing
registry entry) if a backup with the same SHA-256 was already sealed. Uses its **own** signing key
(`legacy-sign.key`, created once via `php artisan legacy:keygen`) and its **own** sender identity
(`SEAL_LEGACY_SERVER_ID`, default `"blueknife-legacy"`) and its **own** sequence counter/hash-chain
(prefixed `L####` instead of `S####`) — entirely separate from the live server's seal chain, so a
legacy import can never be confused with (or inserted into) the live chain. The payload additionally
carries an `origin` block (`backup_file`, `backup_sha256`, `reconstructed_at`, and an explicit note
that this was *not* witnessed/sealed at the arena at the time). Checks are recorded but **never
block** a legacy seal (history cannot be corrected after the fact) — this is the one place sealing
cannot fail on a balance mismatch.

### 7.12 Backup / restore

`App\Services\Backup\DatabaseBackup`, backed by `EncryptedStream` (libsodium
secretstream XChaCha20-Poly1305, gzip-compressed, authenticated — any truncation or tampering is
detected on read) and `SqlDumpReader` (testing-only raw-SQL whitelist parser).

**Create** (`backup:create` CLI or the Backups web page): acquires an exclusive file lock
(`.lock` in the backup directory — refuses concurrently with "another backup or restore is already
running"), opens a **single raw PDO connection** (not through Eloquent) and starts a
`REPEATABLE READ` transaction `WITH CONSISTENT SNAPSHOT` (a true point-in-time, non-blocking
read), then streams, in order, into one `.bak.enc` file: a `header` record (format tag, database
name, timestamp); every stored routine's `SHOW CREATE {FUNCTION|PROCEDURE}` text (fails the whole
backup if any routine's definition can't be read — e.g. still owned by `root`, which is exactly why
migration `2026_09_28_000002` exists); every base table's `SHOW CREATE TABLE` plus its rows in
batches of `backup.batch_rows` (500) — non-UTF8 string bytes are individually wrapped
`{"b64": "..."}` so the JSON stream itself stays valid UTF-8; every view's `SHOW CREATE VIEW` (with
any `DEFINER=...` clause stripped so restoring doesn't require that MySQL user to exist); every
trigger's full `CREATE TRIGGER`; then an `end` record with final per-table row counts. Commits the
read transaction, closes the stream, computes the whole file's SHA-256, and writes a companion
`{name}.manifest.json` (file name, hash, size, backup `type` — `manual`/`scheduled`/`prerestore`/
`preclose`, timestamp, operator account id, server id, which event was Active at backup time, every
table's row count, object counts, **the full `sequence_no → payload_sha256` map of every seal that
exists in `event_closings` at backup time** — this is the mechanism restore-time un-sealing
protection is built on — the last-applied migration name, the signing key's fingerprint, and
finally the manifest's own Ed25519 signature over everything *except* the signature field itself).
The `.bak.enc` file is `chmod 0400` after writing.

**List/Verify**: `list()` reads every `*.manifest.json`, re-validates it (falls back to marking it
`_valid:false` with the error message if signature/hash checks fail), sorted newest first.
`verify($file)` fully decrypts and re-derives row counts from the stream and compares them against
the manifest's `row_counts` *and* its own `end` marker — a full read-through integrity check that
changes nothing.

**Restore** (`backup:restore` CLI, preferred for a real disaster since it works even if the web app
itself is unusable, or the Backups web page): requires a `reason` of ≥10 characters (recorded
permanently) and, unless explicitly overridden, a **second** admin's credentials
(`BACKUP_REQUIRE_SECOND_APPROVER`) distinct from the person restoring; the web form additionally
requires explicit acknowledgment (`betting_stopped` checkbox) if an event is currently Active.
Procedure:
1. Full `verify()` pass (decrypt + count-check) **before touching the live database at all**.
2. Compare the backup manifest's `seals` map against the live append-only seal registry
   (§7.11.2 step 11, which lives *outside* the database): any `sequence_no` present in the registry
   whose hash the backup doesn't carry (or carries differently) is a seal this restore would
   **un-seal**, and the restore is refused unless `--allow-unsealing`/the equivalent flag is passed —
   i.e. the registry file is deliberately kept outside the DB specifically so a stale backup can't
   silently erase a seal just by restoring over it.
3. Put the whole application into Laravel maintenance mode (`artisan down --retry=30`).
4. Take a **fresh `prerestore` backup of the current state** before changing anything.
5. Drop every table/view/trigger/routine in the database and rebuild purely from the backup stream
   (routines and tables created and populated first via prepared-statement batched inserts; views
   created in repeated passes, tolerating temporary dependency-ordering failures, until all succeed
   or 10 passes are exhausted; triggers last).
6. Compare resulting row counts against the manifest; re-run `assertSealsIntact()` (every
   `event_closings` row's `payload`/`payload_sha256`/signature must still verify against the live
   signing key) — **any** failure here triggers an automatic rollback by re-applying the just-taken
   `prerestore` backup, recorded in the journal as `failed_rolled_back` (or
   `failed_rollback_failed` if even that fails), and the original error is re-thrown to the caller.
7. On success, append a permanent journal entry (`restore-journal.jsonl`, outside the DB, `chmod
   0600`) with timestamp, file, hash, operator, approver, reason, source (`cli`/`web`), whether
   unsealing was allowed, which seals (if any) were knowingly lost, result, and elapsed seconds; log
   to `transactions`.
8. Maintenance mode is lifted in a `finally` block regardless of outcome.

**TESTING-ONLY raw SQL import** (`backup:import-sql` CLI / `/backups/import/*` web routes, gated by
`BACKUP_ALLOW_RAW_SQL_IMPORT` and unconditionally refused when `APP_ENV=production`): parses an
arbitrary `mysqldump`/`mariadb-dump`-style `.sql`/`.sql.gz` file with a hand-written SQL-statement
tokenizer (`SqlDumpReader`, handles quoting, `/* */` and `--`/`#` comments, custom `DELIMITER`
blocks, and strips `DEFINER=` clauses) and **whitelists** every statement's leading keyword before
executing anything — only `SET` (except `GLOBAL`/`PASSWORD`/`@@GLOBAL.`), `DROP|CREATE TABLE`,
`DROP VIEW|FUNCTION|PROCEDURE|TRIGGER`, `CREATE [OR REPLACE] [ALGORITHM=...] [SQL SECURITY ...]
VIEW`, `CREATE FUNCTION|PROCEDURE|TRIGGER`, `INSERT|REPLACE [IGNORE] INTO`, `LOCK|UNLOCK TABLES`,
and `ALTER TABLE` are accepted; anything else (including any statement anywhere containing `INTO
OUTFILE`/`INTO DUMPFILE`/`LOAD_FILE(`/`LOAD DATA`, even inside a routine body) aborts the entire
import before a single statement runs. Follows the *exact same* maintenance-mode /
pre-import-backup / rollback-on-failure / seal-protection / permanent-journal pattern as a real
restore, then additionally runs `php artisan migrate --force` afterward (to bring an older dump's
schema up to the current one) before re-checking seal integrity.

### 7.13 Reset flow
As noted in §7.11.3, the only reset implementation in this app (`ResetController`) is present but
**unreachable** via any route — the comment left in `routes/web.php` records that it was
deliberately removed because "it wiped event data (and its 'backup' never worked)," directing
operators to **System → Backups** instead. There is therefore no supported "reset the current
event back to empty" workflow in production; the supported path for discarding bad data is
restoring a prior backup. (Contrast with the matching-system's own reset flow, which is a live,
routed feature on that side — this asymmetry is intentional per the comment, not an oversight to
reproduce.)

---

## 8. The Bridge protocol

Both apps run on the same physical server and talk over plain HTTP (not HTTPS — they share a
Docker network, `arena-bridge`) using symmetric, per-direction HMAC secrets. There is **no shared
database** between the two apps; the only coupling is this bridge.

### 8.1 Signing (identical construction on both sides)
```
timestamp = unix seconds, as a string
signature = hex( HMAC-SHA256(secret_key, timestamp + "." + raw_request_body_bytes) )
headers:
  X-Bridge-Timestamp: <timestamp>
  X-Bridge-Signature: <signature>
```
The verifier recomputes the same HMAC over the **exact raw bytes** of the body it received (not a
re-serialization) and compares with a constant-time equality check (`hash_equals` / Node's
`crypto.timingSafeEqual`, after first checking the two hex strings are the same length). A request
is also rejected if the timestamp is not all-digits or is more than `max_clock_skew` seconds (300 on
both sides) away from the verifier's own clock — this bounds replay-ability of a captured
request/signature pair to a 10-minute window.

Two independent keys exist, one per direction:
- `MATCHING_TO_BETTING_KEY` — matching signs its outgoing CALL/RECALL requests with this; betting
  verifies incoming bridge requests with it (`config('bridge.key_from_matching')` on the betting
  side maps to this same value; on the Node side it's `config.bridge.keyFromBetting` mapped... **note
  the naming is deliberately crossed**: each side's "key_from_X" config entry is literally the *other*
  side's "key_to_me" — i.e. this is the single value `MATCHING_TO_BETTING_KEY`, known to both
  processes, used by matching to *sign* and by betting to *verify*).
- `BETTING_TO_MATCHING_KEY` — the reverse: betting signs outgoing STATUS/RESULT requests with this;
  matching verifies incoming bridge requests with it.

Both are generated with `openssl rand -hex 32` and placed in each app's own `.env` (both apps must
have the *same two* values; they are not derived, just copied).

### 8.2 Endpoints

| Direction | Method & path (relative to the receiver) | Who calls it | Purpose |
|---|---|---|---|
| matching → betting | `POST /api/bridge/matching/ping` | matching (manual/health check) | Liveness + which betting event (if any) is Active/linked |
| matching → betting | `POST /api/bridge/matching/call` | matching | "This fight is next" — create/update it on the betting side |
| matching → betting | `POST /api/bridge/matching/recall` | matching | Withdraw a called fight (only before betting opens) |
| betting → matching | `POST /bridge/betting/status` | betting | Fight betting state changed: open / closed / held |
| betting → matching | `POST /bridge/betting/result` | betting | Fight result (first declaration or a correction) |

All five request/response bodies are JSON. Every request body additionally carries `msg_key`
(matching's outbox generates it as `matching-{type}-{matchId}-{uuid}`; betting's as
`betting-{type}-{fightUid}-{uuid}`) and `type` (redundant with the URL, included for
self-description/logging) *inside* the JSON payload itself, appended by each side's own `queue()`
helper — i.e. `msg_key`/`type` are not separate from the "business" payload fields, they're merged
into the same JSON object before signing.

### 8.3 Idempotent delivery (outbox/inbox pattern, symmetric on both sides)

Each side keeps a `bridge_outbox` table of messages **it** needs to deliver and a `bridge_inbox`
table of messages **it** has already answered:

- **Sending.** A state change is written to the owning table (`matches`, `events`, etc.) and a row
  is inserted into `bridge_outbox` **inside the same DB transaction**, so the queued message and the
  change it describes can never disagree. A separate background loop (`bridge:work` on the Laravel
  side — a long-running daemon under supervisord, polling every 3 seconds; a `setInterval` of 3000ms
  on the Node side) later calls `flush()`: selects up to 50 unsent rows ordered by id, and delivers
  them **strictly in order**, stopping at the **first** failure (network error, timeout, or any
  5xx/unexpected status) so that a later message can never be delivered before an earlier one that
  is still stuck — this preserves the fight timeline. A received 4xx ("refused", e.g. 409 /
  422 / 401) is treated as **final**: it is recorded (`sent_at` is still stamped, with the error
  text kept) and delivery continues to the next row — refusals do not block the queue, only
  transport failures/5xx do.
- **On a refusal specifically to a `call` or `recall`** (matching → betting direction only), the
  Node side's `bridge.onRefused` hook reverts the *local* fight state it had optimistically
  committed before sending (e.g. a refused `call` resets `bet_state` back to `'none'`, a refused
  `recall` restores `bet_state = 'called'`) — so the two sides never end up believing different
  things about whether a fight was actually accepted.
- **Receiving.** The receiving endpoint first checks whether this exact `msg_key` is already present
  in its own `bridge_inbox`; if so, it returns the **exact same** stored `(http_status, response
  body)` without re-running any business logic at all — this is what makes at-least-once delivery
  safe to retry. Otherwise it signature-verifies the request, executes the business logic **inside
  one DB transaction**, and — **only if the result status is < 500** — records the outcome into
  `bridge_inbox` (a genuine server error is deliberately *not* cached, so a transient failure can be
  retried and get a different answer next time; a deliberate business refusal, 4xx, **is** cached,
  so a retried refusal always replays the identical refusal).
- A manual "flush now" is also triggered opportunistically right after any Laravel request that
  changed bridge-relevant state, via `app()->terminating(fn () => MatchingBridge::flush())` — i.e.
  the operator doesn't have to wait for the next 3-second poll to see their own action reach
  matching, though the background loop is still the thing that guarantees eventual delivery.

### 8.4 `call` payload (matching → betting) and betting's handling

Request body (merged with `msg_key`/`type` as above):
```json
{
  "event": { "id": <matching_event_id>, "name": "...", "date": "YYYY-MM-DD" },
  "fight": {
    "uid": <matching match_id, permanent>,
    "no": <fight_no, 1-based, unique per event>,
    "version": <matching's call_version counter>,
    "meron": { "entry": "...", "owner": "...", "weight": <number|null>, "wingband": "...", "legband": "...", "type": "..." },
    "wala":  { "entry": "...", "owner": "...", "weight": <number|null>, "wingband": "...", "legband": "...", "type": "..." }
  }
}
```
`BridgeController::applyCall`:
1. Rejects (422) if `fight.uid`/`fight.no` are missing/non-positive, either side object is missing,
   or `event.id` is missing.
2. Requires a betting `Active` event; refuses (409 by default — see `refuse()`'s default status) if
   none.
3. **Links** the Active betting event to the matching event the first time a call arrives
   (`events.matching_event_id = event.id`, logged); refuses permanently if a *different* matching
   event id is ever sent while already linked — **one betting event ↔ exactly one matching derby**,
   for the entire life of the betting event.
4. Looks up an existing `matches` row by `(event_id, source_fight_uid = fight.uid)`:
   - If it's already settled (`Completed`/`Draw`/`Cancelled`): refused, "already played."
   - If betting has already opened on it (`bet_opened_at` set) **or** it already has any bets at
     all: refused, "betting already opened... can no longer be changed from matching" (this is the
     hard boundary described in §1/§7.2 — matching permanently loses editing rights to a fight the
     instant betting opens).
   - If the incoming `version` is **lower** than the stored `source_call_version`: silently accepted
     as a no-op (200, "Older call ignored") — protects against an out-of-order retry overwriting a
     newer call.
   - If the requested `fight.no` is already used by a *different* fight of this event: refused.
   - Otherwise: updates `match_number`, `meron_entry`/`wala_entry` (truncated to 255 chars),
     `meron_details`/`wala_details` (JSON of the full side object), `source_call_version`,
     `hold_reason = null` (a re-send always clears any prior hold) on the existing row.
5. If no existing row for that `uid`: requires that **no other fight of this event** is currently
   `Ongoing` with a `source_fight_uid` set, or `bet_opened_at` set, or any bets — refused otherwise
   ("still in play... finish or cancel it first"); also checks the fight-number-taken rule against
   that other ongoing fight if one exists but is still an *empty, never-called* placeholder.
   Resets every teller's `teller_match_balance` to 0 for the event (same side effect as a locally
   created new fight). If an ongoing **placeholder** fight already exists (created by the operator
   viewing the Matches board before any call arrived), that placeholder row is *converted in place*
   into the called fight (reusing its `match_id`) rather than creating a second row; otherwise a
   brand-new `matches` row is created. Either way: `source_fight_uid = uid`, `called_at = now()`,
   `match_bet_status = 'Closed'` (a freshly called fight always starts with betting closed — the
   operator must explicitly open it), `match_status = 'Ongoing'`, `is_display = 0`.
6. Returns `201` (new fight) or `200` (update/no-op) with `{ok:true, message, match_id}`, or the
   appropriate `4xx/409` refusal `{ok:false, message}` otherwise. Broadcasts `MatchUpdated` +
   `MatchesUpdated` on any state change (best-effort — a broadcast failure is caught and reported,
   never fails the HTTP response).

### 8.5 `recall` payload (matching → betting)
```json
{ "fight_uid": <uid> }
```
If betting has no fight at all for that `uid` under the Active event: `200`, "nothing to recall"
(idempotent no-op, not an error). If the fight exists but betting has already opened
(`bet_opened_at` set) or it has bets: refused — "only the betting operator can cancel it (bets are
refunded)," i.e. recall is **only** a pre-open-betting operation; once betting has opened, the
*betting side's own* cancel-the-fight path (declaring `match_status = Cancelled`, §7.5) is the only
way to abandon it, and that refunds every bet rather than silently discarding them. Otherwise the
`matches` row is **deleted outright** (not soft-cancelled) and the response reports the fight number
withdrawn.

### 8.6 `status` payload (betting → matching)
```json
{ "fight_uid": <uid>, "fight_no": <n>, "state": "open"|"closed"|"held",
  "hold_reason": "..."|null, "meron_total": "1234.00", "wala_total": "...",
  "meron_odds": <float|0>, "wala_odds": <float|0>, "at": "YYYY-MM-DD HH:MM:SS" }
```
Only queued if the fight is bridge-linked (`source_fight_uid` set); otherwise `queueStatus()` is a
silent no-op (manual-mode fights never generate bridge traffic at all). Matching's handler
(`fightBridgeService.applyStatus`) ignores the update entirely if its own copy of the fight is
already `finished`/`cancelled`; ignores a `held` state specifically if its own `bet_state` isn't
currently `called` (prevents a stale hold message reviving a fight matching has moved past);
otherwise sets its own `bet_state` to the given state, stores `bet_hold_reason` (only when held),
and stores `bet_meron_odds`/`bet_wala_odds` (only if > 0) purely for display on matching's own
screens — matching never uses these odds for anything financial.

### 8.7 `result` payload (betting → matching)
```json
{ "fight_uid": <uid>, "fight_no": <n>, "result": "meron"|"wala"|"draw"|"cancelled",
  "version": <int>, "previous": "<string>"|null, "declared_by": "<username>"|null,
  "at": "YYYY-MM-DD HH:MM:SS" }
```
Matching ignores the message outright if `version <= its own stored result_version` (protects
against an out-of-order retry re-applying an older result over a newer correction). Otherwise:
- `result = "cancelled"`: sets its own `status='cancelled'`, clears scores, `bet_state='cancelled'`,
  releases both cocks back to `'available'` so they can be re-paired into new fights — **no points
  awarded**.
- Otherwise: matching must translate betting's side label back to *its own* notion of which actual
  cock was Meron, because **which cock is "Meron" is fixed once, at call time**
  (`called_meron_chicken_id`), while betting's `meron`/`wala` labels always refer to whichever cock
  was Meron *at that moule* — if matching's current board has since re-labelled which cock displays
  as Meron (e.g. after re-sending a call with `tv_meron_chicken_id` flipped), betting's "meron"
  still means the cock that was Meron when the fight was actually called. Scores are then
  `1/0` (meron win), `0/1` (wala win), or `0.5/0.5` (draw); `status='done'`, `bet_state='finished'`,
  both cocks marked `'fought'` (ineligible for further matching this event). If this is a
  **correction** (`version > 1`) of a fight matching had itself marked `cancelled`, matching refuses
  (409) if either cock has already been re-matched into a different fight since the cancellation —
  "fix this fight manually" — rather than silently un-cancelling a cock that's already fighting
  someone else.
- `result_version`, `result_source='betting'`, and (only on a correction)
  `result_corrected_at`/`result_corrected_from` are stamped either way.

### 8.8 State-machine summary (matching's view, from `fightBridgeService.js`'s own header comment)
```
none ──call──► called ──(betting opens)──► open ──► closed ──(result)──► finished
                 │  ▲                                                        │
        recall ◄─┘  └── re-send (after a HOLD from betting)          correction (version 2+)
 Cancelled by betting → cancelled (no points; both cocks re-matchable)
```
Betting's own parallel state is simpler and keyed off `matches.match_status` /
`match_bet_status` / `bet_opened_at` / `hold_reason` as described in §7.2 — there is deliberately
**no single shared enum**; each side's state machine is its own, and the bridge messages are the
only synchronization between them. A faithful Rust rewrite of either side must preserve this
asymmetry rather than unifying the two into one shared state type.

---

## 9. Realtime / broadcasting

Transport: `beyondcode/laravel-websockets`, addressed through Laravel's "pusher" broadcast driver
pointed at the local server (`PUSHER_HOST`/`PUSHER_PORT`, defaults `127.0.0.1:6001`). Every event in
`app/Events/*.php` implements `ShouldBroadcastNow` (synchronous — broadcast happens inline in the
HTTP request, not queued; consistent with `QUEUE_CONNECTION=sync`) and broadcasts on a **plain
public `Channel`** (never `PrivateChannel`/`PresenceChannel`), so `routes/channels.php`'s
authorization closures are never consulted for any of them — any client holding the Pusher app key
can subscribe to any of these channel names without proving who they are:

| Event class | Channel name | `broadcastAs` | Payload shape | Fired by |
|---|---|---|---|---|
| `MatchUpdated` | `match-updates` | `MatchUpdated` | `{match: <Fight row>}` | every endpoint that creates/mutates a single fight (new match, toggles, bet-status/match-status changes, hold, display toggle, a bet being placed/voided, bridge call/recall) |
| `MatchesUpdated` | `fight-history` | `MatchesUpdated` | `{match: <array of Fight rows>}` (key is literally `match`, not `matches`, despite the event name) | after settling a result and creating the next placeholder; after a bridge call/recall |
| `TVDisplayUpdated` | `tv-display` | `TVDisplayUpdated` | `{match: <Fight row>}` | side toggles, bet-status change, result declared, TV-display toggle |
| `TellerBalanceUpdated` | `event-tellers` | `TellerBalanceUpdated` | `{event_tellers: <array, each row augmented with ad-hoc total_bet/total_payout (and sometimes total_cash_in/total_cash_out)>}` | every bet, void, claim, cash-in/out create or resolve, teller-list reload |
| `CashInUpdated` | `cash-ins` | `CashInUpdated` | `{cash_ins: <array of cash_ins_view rows>}` | every cash-in create/approve/decline, and after `updateTellers` (new opening cash-ins) |
| `CashOutUpdated` | `cash-outs` | `CashOutUpdated` | `{cash_outs: <array of cash_outs_view rows>}` | every cash-out create/approve/decline |
| `EventUpdated` | `event-updates` | `.EventUpdated` (leading dot — a Laravel Echo "no-namespace" convention) | `{event: <Event row>}` | **defined but never actually dispatched anywhere in the controllers** — dead event, kept for completeness/future use |

The only consumers implied by the view set are: the **Matches** operator board (`match-updates`,
`fight-history`), the **TV** display page(s) (`tv-display`), the **Cash Transactions** admin screen
(`cash-ins`, `cash-outs`, `event-tellers`), and presumably the mobile apps for live balance/fight
refresh without polling. A Rust rewrite reproducing this faithfully should expose the same channel
names/payload shapes as an unauthenticated pub/sub surface (e.g. over its own WebSocket server or a
Redis pub/sub bridge), and should note that `EventUpdated` exists in the "API surface" but has no
real trigger today — a new implementation can choose to either wire it up for real or omit it
without changing observed behavior.

---

## 10. Surprising, inconsistent, or workaround behavior to replicate faithfully

These are places where the current implementation's *exact* behavior — not the "obviously correct"
behavior — is what the real system has been running on, and a rewrite that silently "fixes" them
will diverge from production:

1. **Two unrelated arithmetic systems for money.** Live betting/odds/payout code
   (`BetController`, `MatchController`) does everything in raw PHP floats with `round()`/`floor()`
   on `float`/`decimal` columns. The closing-report code (`Money`, `ClosingReportBuilder`,
   `EventSealer`) redoes all of it in exact integer centavos via `bcmath`, specifically so it can
   *detect* float-rounding drift against the live figures as a reportable (non-blocking) exception.
   A rewrite must keep both: the live settlement math must reproduce PHP float semantics exactly
   (including `round()`'s half-away-from-zero behavior and `floor()` on a float product), while the
   audit/closing math must use exact fixed-point arithmetic that can disagree with it by design.

2. **The `call` state-machine is not shared between the two apps.** Matching's `bet_state` enum
   (`none/called/open/closed/held/finished/cancelled`) and betting's own `match_status`/
   `match_bet_status`/`bet_opened_at`/`hold_reason` fields are two independent state machines kept
   loosely in sync purely by the bridge messages in §8.6/§8.7 — they are not required (or checked) to
   always agree, and the protocol is explicitly built to tolerate temporary disagreement (stale
   messages are dropped by version/sequence checks, not reconciled).

3. **`bridge_outbox.result` versioning counts delivery attempts, not distinct corrections.**
   `MatchingBridge::queueResult()` computes `version` as "how many `result` rows already exist for
   this `fight_uid`, plus one" at the moment of *queuing* — if an outbox row has to be retried
   several times before matching 200s it, that is still only one queued row, but if the operator
   corrects a result twice in a row, two separate rows (versions 1 then 2) are queued even though
   only one of them might represent a "real" correction depending on timing. Matching's own
   `applyResult` is defensive against this (`version <= stored version` is ignored), but the
   version number itself is **not** a reliable count of how many times a human corrected the result.

4. **`EventTeller::create()` in `EventTellerController@updateTellers` passes `is_disabled` => 0,**
   but neither the `event_tellers` table nor the `EventTeller` model's `$fillable` list has any such
   column. Because Eloquent mass-assignment silently drops any key not in `$fillable`, this has
   **no effect and causes no error** — it is simply dead code, not a bug that manifests. A rewrite
   should not add an `is_disabled` column expecting it to do anything; if teller-disabling is
   wanted, it would need to be designed from scratch.

5. **A single-admin-session race on "only one Active event."** `EventController@store`/`@update`
   check `Event::where('event_status','Active')->exists()` with a plain read, outside any
   transaction/row lock, before inserting/updating. Two admins creating/activating an event in the
   same instant could both pass the check and end up with two simultaneously `Active` events, which
   many other code paths assume cannot happen (`Event::where('event_status','Active')->first()`
   everywhere else). In practice this requires two admins racing within the same request window on
   a single physical server, so it has likely never been observed, but a correctness-focused rewrite
   should decide deliberately whether to close this gap (e.g. with a unique partial index) rather
   than reproduce the race by accident.

6. **The `DELETE /matches/{match_id}` web route has no corresponding controller method.**
   `routes/web.php` wires `Route::delete('/{match_id}', 'MatchController@delete')`, but
   `MatchController` defines no `delete()` method at all — this route would fatally error (`Call to
   undefined method`) if ever actually invoked. Nothing in the shipped UI appears to call it. Dead,
   broken route; do not reproduce the breakage, but also note that **fights are never deletable**
   through any working endpoint — the closest equivalent is a bridge `recall` (pre-betting-open
   only) or declaring the fight `Cancelled` (post-open, with refunds).

7. **The Reset feature is fully implemented but completely unrouted.** `ResetController` (truncates
   nearly every live-event table, best-effort `mysqldump`s first) is dead code reachable by no HTTP
   route — explicitly removed from `routes/web.php` per an inline comment, in favor of the
   Backups/restore workflow. If a rewrite wants a "wipe and start over" operator feature, it should
   be designed fresh against the current backup/restore primitives (which know about seal
   protection, etc.) rather than resurrecting this truncation list, which predates (and is unaware
   of) `teller_ledger`, `event_closings`, and the bridge tables entirely — running it today against
   the current schema would also leave `teller_ledger`/`event_closings`/`bridge_outbox`/
   `bridge_inbox` rows behind, now orphaned from the wiped `matches`/`bets`/`event_tellers` they
   referenced.

8. **Realtime channels are all public/unauthenticated.** None of the seven broadcast events use a
   `PrivateChannel`/`PresenceChannel`, so `routes/channels.php`'s authorization is never invoked for
   any of them in practice — anyone who can reach the WebSocket port and knows the (effectively
   public, embedded-in-every-page) Pusher app key can subscribe to live bet totals, cash-in/out
   requests, and match state for the arena. This matches the "offline arena LAN" threat model the
   README describes, but a rewrite exposed on any less-trusted network should treat this as a gap to
   close deliberately, not an accident to preserve.

9. **`EventUpdated` is dead code.** The broadcast event class and its `event-updates` channel exist
   but nothing in any controller ever dispatches it.

10. **Several model `use` imports reference classes that do not exist in this codebase**
    (`App\Models\School`, `Setting`, `Application`, `DivisionOffice`, `RegionalOffice` in
    `PageController`; `App\Models\LogMessage` in `AccountController`) — harmless leftovers from the
    commercial admin-theme template this app was built on top of (PHP doesn't error on an unused
    `use` of a non-existent class unless it's actually instantiated), but they are a reliable signal
    of which parts of the controller files are original template boilerplate never fully cleaned up.
    `routes/web.php`'s `/lang/ar` and `/lang/en` GET routes are a similar leftover: they both just
    call `PageController@dashboard` and do not actually switch any locale (real locale switching, if
    it is used at all, is entirely driven by `LocaleMiddleware` reading `session('locale')`, which
    nothing in the shipped routes ever sets).

11. **Draw and Cancelled are financially identical but semantically distinct.** Both refund every
    live bet's full stake (`bet_payout_amount = bet_amount`, `bet_is_winner = 0`,
    `bet_win_amount = 0`) and set `match_winner = 'N/A'`; they differ only in the `match_status`
    string itself, in the label (`draw` vs `cancelled`) sent to matching over the bridge (which *does*
    treat them very differently — a cancelled fight's cocks go back to `available` for re-pairing,
    while matching has no special handling at all for a bridge-reported `draw` beyond recording
    0.5/0.5 scores), and in how `ClosingReportBuilder` buckets them for reporting (`fights_draw`
    vs `fights_cancelled` counts, though both contribute to the same `refunds`/`unused` logic). A
    rewrite must keep them as genuinely separate result values all the way through, not collapse
    them into one "voided" outcome on the betting side even though their betting-side money effect
    is currently 100% identical.

12. **A bet can be claimed (paid out) by a teller other than the one who sold it,** and the paying
    teller's own cash balance absorbs the payout regardless — this is normal, intentional behavior
    (bettors don't have to return to the exact booth they bet at), but it means a teller's
    `expected_cash` reconciliation formula (§7.11.2) only balances **in aggregate across the whole
    event**, not necessarily per teller in an intuitive way if one teller systematically cashes out
    bets sold by others; the `TellerLedger` records a note on cross-teller payouts specifically so
    this is auditable, not hidden.

13. **Stored-function ownership is deployment-sensitive.** `get_teller_name`/
    `get_transaction_account_name` were originally created by MariaDB's `root` user when the base
    schema dump is first loaded, which makes `SHOW CREATE FUNCTION` unreadable to the app's own
    unprivileged DB user — and the backup system (§7.12) **aborts the entire backup** if it can't
    read a routine's definition. Migration `2026_09_28_000002` exists purely to re-create both
    functions (byte-identical bodies) owned by the app user. A Rust rewrite that doesn't need MySQL
    stored functions at all sidesteps this, but should preserve the two functions' exact *semantics*
    (resolve a teller/admin/account display name for a log row) somewhere in its own log-rendering
    code, since `transactions.transaction_message` as stored never contains a resolved name, only
    numeric ids plus the raw PHP-object-to-string dump of whatever Eloquent model was logged
    alongside the message (see finding 14).

14. **Log messages are not structured.** `transactions.transaction_message` is free text that very
    often has a PHP object concatenated onto the end via implicit `__toString()`-via-string-concat
    (e.g. `"Teller - Teller created. " . $teller . " " . $account`) — which, for a plain Eloquent
    model with no custom `__toString()`, actually throws a fatal error in modern PHP if ever
    triggered... except Eloquent models **do** have a default `__toString()` (via the
    `Jsonable`/`Arrayable` fallback used by Laravel's `Model`), so in practice this renders as the
    JSON representation of the model. `ClosingReportBuilder::fightAudit()` additionally
    regex-parses specific message prefixes (`"Matches - Fight #N bet status updated to X!"`, `"...
    match status updated to X!"`) to recover structured facts that were never given real columns —
    a rewrite should give these facts first-class structured storage (fight-level audit columns or
    a dedicated events/audit table) rather than reproducing string-parsing of a human log line.
