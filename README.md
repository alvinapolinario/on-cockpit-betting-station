# On-Cockpit Betting Station

Onsite (offline) cockpit betting system for **Blueknife Gallera**: teller betting and payouts,
fight results, cash control, teller ledgers, and **sealed event closing reports** for the
Treasury Office and BIR.

Companion system: **[sabong-bir-compliance](https://github.com/alvinapolinario/sabong-bir-compliance)**
(runs on the VPS; receives the sealed event packages).

```
ARENA (offline network)                                   INTERNET
Betting server (this repo) ── teller phones (Wi-Fi)
  └ Close & seal event → encrypted package ──(laptop)──►  BIR Compliance System (VPS)
  ◄──────────────── acknowledgment code ────────────────  verifies, stores, reports
```

## Contents

1. [What runs where](#1-what-runs-where)
2. [First-time deployment (production, at the arena)](#2-first-time-deployment-production-at-the-arena)
3. [Connecting to the BIR Compliance System](#3-connecting-to-the-bir-compliance-system)
4. [Moving existing data to a new server](#4-moving-existing-data-to-a-new-server-optional)
5. [Event-day procedure](#5-event-day-procedure)
6. [Updating to a new version](#6-updating-to-a-new-version)
7. [Backups and restore](#7-backups-and-restore)
8. [Importing past events (legacy)](#8-importing-past-events-legacy)
9. [Rolling back](#9-rolling-back)
10. [Troubleshooting](#10-troubleshooting)
11. [Local sandbox (development)](#11-local-sandbox-development)
12. [Docker reference: database, ports and credentials](#12-docker-reference-database-ports-and-credentials)

---

## 1. What runs where

Two Docker containers (`docker-compose.yml`):

| Container | Contents | Ports (host) |
|---|---|---|
| `sabonglara-app` | nginx → PHP-FPM (Laravel), WebSocket server, SSH (key-only); all kept alive by supervisord | `80` web/API, `6001` WebSocket, `2222` SSH |
| `sabonglara-mysql` | MariaDB 10.11 | `127.0.0.1:3309` (this machine only) |

Stored outside git, on the server only:

| Path | What | Back up offline? |
|---|---|---|
| `.env` | passwords, app key, settings | yes |
| `storage/app/keys/` | `sign.key` (seals), `backup.key` (backups), `legacy-sign.key`, `vps.pub` | **yes: sealed offline copy** |
| `storage/app/backups/` | encrypted database backups | copy to a second disk |
| `storage/app/closings/` | sealed detail files, legacy packages | yes |
| database volume `sabonglara-mysql` | all live data | via backups |

> Without `storage/app/keys/backup.key` no backup can be restored. Without `sign.key` no further events can be sealed under this server's identity.

## 2. First-time deployment (production, at the arena)

Do steps 2.1–2.6 **while the server still has internet** (it downloads Docker images, PHP and
JavaScript packages). After that the server runs fully offline.

### 2.1 Prepare the server
- A dedicated machine (Ubuntu 24.04 LTS recommended) with **full-disk encryption**, on a **UPS**, in a locked cabinet.
- A **fixed IP address** on the arena network (DHCP reservation on the router), e.g. `192.168.10.10`.
- Install Docker and git:
  ```bash
  sudo apt-get update && sudo apt-get install -y docker.io docker-compose-v2 git
  sudo usermod -aG docker $USER   # log out and in again
  ```

### 2.2 Get the code
The repository is private: add the server's SSH public key as a read-only **Deploy key**
(GitHub → repository → Settings → Deploy keys), then:
```bash
sudo mkdir -p /opt && cd /opt
sudo git clone git@github.com:alvinapolinario/on-cockpit-betting-station.git betting-station
sudo chown -R $USER: /opt/betting-station && cd /opt/betting-station
```

### 2.3 Configure `.env`
```bash
cp .env.example .env
sudo chown root:33 .env && sudo chmod 640 .env   # private, but readable by the web user (www-data, id 33)
```
> `.env` must be readable by `www-data`: with `600 root:root` the site returns **500** ("No application encryption key").

Edit `.env` and set at least:

| Setting | Value |
|---|---|
| `DB_PASSWORD` | long random value: `openssl rand -hex 24` (**required**; also the MariaDB root password) |
| `APP_URL` | `http://<fixed IP>` |
| `ARENA_NAME` | e.g. `"Blueknife Gallera"` |
| `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` | random values (`openssl rand -hex 16`) |
| `SEAL_SERVER_ID` | unique name, e.g. `blueknife-srv01` |
| `SSH_PORT` | `127.0.0.1:2222` unless SSH from other machines is needed |

Keep `APP_ENV=production`, `APP_DEBUG=false`, `SEAL_REQUIRE_SECOND_APPROVER=true`,
`BACKUP_REQUIRE_SECOND_APPROVER=true`, `BACKUP_ALLOW_RAW_SQL_IMPORT=false`.

(Optional) SSH into the app container: put allowed public keys, one per line, in
`docker/ssh/authorized_keys` (see `docker/ssh/authorized_keys.example`).

### 2.4 Build and start
```bash
docker compose build app
docker compose up -d
docker logs -f sabonglara-app      # wait for: "websocket entered RUNNING state"
```
The first start creates the database **structure** from `docker/mysql/init/` (no data), installs
PHP/JS dependencies, builds the front-end and applies migrations.

> If `apt-get` hangs during the build: some networks block plain-HTTP access to
> `deb.debian.org`. The Dockerfile already switches Debian to HTTPS.

### 2.5 App key, first administrators, keys
```bash
docker exec sabonglara-app php artisan key:generate --force
docker compose restart app

# Administrators (two are needed for the two-person rule on sealing and restores)
docker exec -it sabonglara-app artisan admin:create --username=admin --name="System Administrator"
docker exec -it sabonglara-app artisan admin:create --username=supervisor --name="Arena Supervisor"

# Keys: run ONCE per server
docker exec sabonglara-app artisan seal:keygen     # prints the PUBLIC key to register on the VPS
docker exec sabonglara-app artisan backup:keygen
```
Copy `storage/app/keys/` to **two encrypted USB drives stored in a safe** (never to GitHub or the VPS).

### 2.6 Connect to the BIR Compliance System
See [section 3](#3-connecting-to-the-bir-compliance-system).

### 2.7 Lock down and go offline
- Teller Wi-Fi on its own network (VLAN / access point) with **no internet uplink**; allow only registered phones.
- Teller app server address: `http://<fixed IP>`.
- Sign in at `http://<fixed IP>` with the admin account; add tellers under *Accounts → Tellers*.
- Checklist: `docker ps` shows both containers `healthy`; *System → Backups → Create backup now* succeeds;
  *Records → Closing Reports* shows no key warnings.

## 3. Connecting to the BIR Compliance System

Two public keys are exchanged once (private keys never leave their machine):

1. **Betting server → VPS.** The public key printed by `artisan seal:keygen` (re-display it with
   `cat storage/app/keys/sign.pub`) is registered on the VPS:
   ```bash
   # on the VPS
   cd /opt/bir-compliance && sudo -u bircomp node scripts/register-server.js \
     --server-id <SEAL_SERVER_ID> --name "Blueknife Gallera betting server" --kind live --public-key <base64>
   ```
2. **VPS → betting server.** The VPS installer prints `SEAL_VPS_PUBLIC_KEY=...`. Put that line in this
   server's `.env`, then `docker compose up -d app`.
3. Check: *Records → Closing Reports* no longer warns about the VPS key.

## 4. Moving existing data to a new server (optional)

To start the new server with the data of an old one (before the first event on the new server):
```bash
# On the OLD server: consistent dump
docker exec sabonglara-mysql sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' > old.sql
# Copy old.sql to the NEW server (encrypted USB), then on the NEW server:
docker compose stop app
docker exec -i sabonglara-mysql sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE $MARIADB_DATABASE; CREATE DATABASE $MARIADB_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"'
grep -avE '^(CREATE DATABASE|USE )' old.sql | docker exec -i sabonglara-mysql sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
docker compose start app            # applies any newer migrations on start
shred -u old.sql
```

## 5. Event-day procedure

| When | Who | Where / how |
|---|---|---|
| Before gates open | Admin | *Events*: create the event, **check the commission rate** (e.g. `0.07` = 7%) and set it Active (only one Active event is allowed). |
| | Admin | *Events → tellers*: assign tellers. Each gets opening cash; the Teller Ledger starts with an "Opening cash" line. |
| | Admin | *System → Backups → Create backup now*. |
| During the event | Admin | *Matches*: open betting → close betting → declare the result (only after betting is closed). Only the latest settled fight can be corrected, and only before any payout. |
| | Tellers | Teller app: bets, payouts, cash-in/cash-out requests. |
| | Admin | Approve cash-in/cash-out requests; watch *Cash → Tellers' Cash on Hand* (all OK). |
| After the last fight | Admin | Settle every fight with bets; resolve all pending cash requests. |
| | Supervisor | *Remittances*: count each teller's cash by denomination. |
| | Admin | *Cash → Teller Ledger → Print statement* for every teller; **each teller signs**. |
| | Admin + 2nd admin | *Records → Closing Reports → Close & seal* (type `CLOSE`, second admin approves). The event is frozen. |
| | Admin | *Report*: print the closing report; supervisor and Treasury witness sign; note the **seal code**. |
| | Admin | *System → Backups → Create backup now*. |
| | Admin | *Closing Reports → Download package* (on the upload laptop, connected to the arena network). |
| Later, online | Accounting | Laptop **disconnected from the arena network**, then online: upload the package in the BIR Compliance System; compare the seal code with the signed report; note the **ACK code**. |
| Next time on site | Admin | *Closing Reports → Ack code*: enter the ACK code. |

## 6. Updating to a new version

Between events only (never during an event):
```bash
cd /opt/betting-station
docker exec sabonglara-app artisan backup:create        # safety backup first
git pull                                                # needs internet (or copy the new version)
rm -f public/mix-manifest.json                          # rebuild CSS/JS (menu, pages) on start
docker compose build app
docker compose up -d app                                # migrations and the asset build run on start
docker logs --tail 30 sabonglara-app                    # check for errors
docker exec sabonglara-app supervisorctl -c /etc/supervisor/sabonglara.conf status
```
Then check sign-in, *Matches*, *Closing Reports* and the teller app.

## 7. Backups and restore

- Create: *System → Backups → Create backup now*, or `docker exec sabonglara-app artisan backup:create`.
  Backups are encrypted with `backup.key` and signed with `sign.key`; they stay on this server
  (`storage/app/backups/`). **Copy that folder to a second disk after every event.**
- List / verify: `artisan backup:list`, `artisan backup:verify <file>`.
- Restore (between events): *System → Backups → Restore…* (reason + second admin + `RESTORE`), or in an
  emergency from the console:
  ```bash
  docker exec -it sabonglara-app artisan backup:restore <file.bak.enc> --reason="why"
  ```
  A backup of the current state is taken first; if the restore fails it is rolled back automatically.
  Backups older than a sealed event are refused (they would un-seal it).

## 8. Importing past events (legacy)

Past events available only as database backups can be sealed as **Legacy** packages (own key and
sequence, labelled "reconstructed from backup" in the BIR system):
```bash
docker exec sabonglara-app artisan legacy:keygen           # once; register its public key on the VPS with --kind legacy
# put the .sql backups in storage/app/backups/legacy/ and list them, oldest first, in ORDER.txt
bash scripts/legacy-import.sh
```
Packages are written to `storage/app/closings/legacy/packages/`; upload them in order.

## 9. Rolling back

```bash
git log --oneline                      # find the previous version
git checkout <commit>                  # or: git revert <bad commit>
docker compose build app && docker compose up -d app
```
If a migration of the bad version changed data, restore the backup taken before the update (section 7).
Write-once records (seals, ledger) are never removed by a rollback.

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| Site shows "500" / log says "No application encryption key" | `APP_KEY` empty (run `php artisan key:generate --force`) or `.env` not readable by www-data (`chown root:33 .env && chmod 640 .env`); then `docker compose restart app` |
| Backup fails: "Cannot read the definition of FUNCTION…" | Functions owned by root: `docker exec sabonglara-app artisan tinker --execute='(require base_path("database/migrations/2026_09_28_000002_recreate_stored_functions_as_app_user.php"))->up();'` |
| Site shows "503 Service Unavailable" | Stuck in maintenance mode: `docker exec sabonglara-app artisan up` |
| Devices cannot reach the server | The server's IP changed: check `ip addr` (Linux) and use the fixed IP; phones must be on the arena Wi-Fi |
| App says "Session expired" | Teller logs in again (tokens expire after `API_TOKEN_HOURS`) |
| Live TV / balances do not update | `docker exec sabonglara-app supervisorctl -c /etc/supervisor/sabonglara.conf status` (websocket must be RUNNING) |
| "Cannot seal: ..." | Settle every fight that has bets; approve/decline pending cash requests |
| Ledger shows MISMATCH | Do not edit the database. Report it; corrections are made as adjustment lines |
| Logs | `docker logs sabonglara-app` · `storage/logs/laravel.log` |

## 11. Local sandbox (development)

```bash
cp .env.example .env     # set APP_ENV=local, DB_PASSWORD, and optionally the sandbox-only switches:
                         # SEAL_REQUIRE_SECOND_APPROVER=false, BACKUP_REQUIRE_SECOND_APPROVER=false,
                         # BACKUP_ALLOW_RAW_SQL_IMPORT=true
docker compose up -d
docker exec sabonglara-app php artisan key:generate --force
docker exec -it sabonglara-app artisan admin:create --username=admin
docker exec sabonglara-app artisan seal:keygen && docker exec sabonglara-app artisan backup:keygen
docker exec sabonglara-app artisan seal:vps-test-keygen    # stand-in VPS key, sandbox only
```
Never copy sandbox keys or sandbox `.env` settings to production.

## 12. Docker reference: database, ports and credentials

Everything below comes from `docker-compose.yml` and `.env`. **Real passwords never go in this
README or in git**. They live only in `.env` on the server and in the sealed offline record
(see [12.5](#125-deployment-record-fill-in-on-paper-not-in-git)).

### 12.1 Containers

| Service | Container name | Image | Role |
|---|---|---|---|
| `app` | `sabonglara-app` | built from `docker/app/Dockerfile` | nginx, PHP-FPM (Laravel), WebSocket server, SSH (supervisord) |
| `mysql` | `sabonglara-mysql` | `mariadb:10.11` | database |

### 12.2 Database

| Item | Value | `.env` setting |
|---|---|---|
| Engine | MariaDB 10.11 | n/a |
| Database name | `sabong_lara_db` | `DB_DATABASE` |
| Application user | `sabong_root`: a normal user with all rights on `sabong_lara_db` only. Despite the name, it is **not** MariaDB's `root` | `DB_USERNAME` |
| Application user password | your generated value (`openssl rand -hex 24`) | `DB_PASSWORD` (**required**; `docker compose` refuses to start without it) |
| MariaDB `root` password | **the same value** as `DB_PASSWORD` | `DB_PASSWORD` |
| Host (from the app container) | `mysql` (the service name) | `DB_HOST` |
| Port inside Docker | `3306` | `DB_PORT` |
| Port on the server | `127.0.0.1:3309` (only this machine can connect) | `FORWARD_DB_PORT` |
| Data volume | `sabonglara-mysql` | n/a |
| Initial structure | `docker/mysql/init/01-schema.sql`, `02-grants.sql` (run only when the volume is first created) | n/a |

> Always set `FORWARD_DB_PORT=127.0.0.1:3309`. If it is missing, `docker-compose.yml` falls back to
> `3307` on **all** network interfaces, which would expose the database to the arena Wi-Fi.

**Connecting to the database**
```bash
# From the server, through the container (no open port needed):
docker exec -it sabonglara-mysql mariadb -u sabong_root -p sabong_lara_db

# As MariaDB root:
docker exec -it sabonglara-mysql mariadb -u root -p
```
A desktop tool (HeidiSQL, DBeaver) on the server itself connects to host `127.0.0.1`, port `3309`,
user `sabong_root`, database `sabong_lara_db`. From another computer, use an SSH tunnel to the
server; do not open the port.

### 12.3 Ports

| Host port | Container port | `.env` setting | Used for |
|---|---|---|---|
| `80` | `80` | `APP_PORT` | web admin and teller app API (`http://<fixed IP>`) |
| `6001` | `6001` | `WEBSOCKET_PORT` | live updates (laravel-websockets / Pusher protocol) |
| `2222` (`127.0.0.1:2222` recommended) | `22` | `SSH_PORT` | SSH into the app container, key-only (`docker/ssh/authorized_keys`) |
| `127.0.0.1:3309` | `3306` | `FORWARD_DB_PORT` | MariaDB, this machine only |

### 12.4 Other related settings

| Setting | Value / where it comes from |
|---|---|
| `APP_KEY` | generated with `docker exec sabonglara-app php artisan key:generate --force` |
| `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` | WebSocket credentials; random values (`openssl rand -hex 16`) |
| `PUSHER_HOST` / `PUSHER_PORT` | `127.0.0.1` / `6001` (fixed in `docker-compose.yml`) |
| `SEAL_SERVER_ID` | this server's name, registered on the BIR VPS (e.g. `blueknife-srv01`) |
| `SEAL_VPS_PUBLIC_KEY` | printed by the BIR system's installer |
| Keys | `storage/app/keys/` (`sign.key`, `backup.key`, `legacy-sign.key`, `vps.pub`) |
| Backups | `storage/app/backups/` |

**Changing the database password later.** MariaDB applies `DB_PASSWORD` **only when the volume is
first created**. Editing `.env` afterwards does not change it. Change it inside MariaDB first, then
update `.env`:
```bash
docker exec -it sabonglara-mysql mariadb -u root -p
#   ALTER USER 'sabong_root'@'%' IDENTIFIED BY '<new>';
#   ALTER USER 'root'@'%' IDENTIFIED BY '<new>';  ALTER USER 'root'@'localhost' IDENTIFIED BY '<new>';
# then set DB_PASSWORD=<new> in .env and:
docker compose up -d
```

> `docker compose down -v` **deletes the database volume and all data**. Use plain `docker compose down`.

### 12.5 Deployment record (fill in on paper, not in git)

Fill this in during deployment and keep it with the offline key copies:

| Item | Value |
|---|---|
| Server fixed IP | |
| `DB_DATABASE` / `DB_USERNAME` | `sabong_lara_db` / `sabong_root` |
| `DB_PASSWORD` (also MariaDB root) | |
| DB port on server | `127.0.0.1:3309` |
| `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` | |
| `SEAL_SERVER_ID` | |
| Admin usernames | |
| Date deployed / by | |
