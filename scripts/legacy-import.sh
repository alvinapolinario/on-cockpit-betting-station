#!/bin/bash
# Seals past events from database backups as LEGACY closing packages.
#
#   bash scripts/legacy-import.sh            # uses storage/app/backups/legacy/ORDER.txt
#
# For each backup (in ORDER.txt order = event-date order):
#   1. load it into the scratch database sabong_legacy_tmp (live database untouched)
#   2. add empty copies of any table an older backup does not have
#   3. seal it: php artisan legacy:seal-file
# Safe to re-run: days already sealed are skipped. Stops at the first error.
set -euo pipefail
cd "$(dirname "$0")/.."
DIR=storage/app/backups/legacy
ORDER="${ORDER_FILE:-$DIR/ORDER.txt}"
SCRATCH=sabong_legacy_tmp
NEEDED="events matches bets claims event_tellers cash_ins cash_outs teller_remittances teller_shorts transactions accounts tellers"

root_sql() { docker exec -i sabonglara-mysql sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$@"' _ "$@"; }

# Read the list on file descriptor 3: the docker commands below read standard input.
while IFS= read -r f <&3; do
  [ -z "$f" ] && continue
  sha=$(shasum -a 256 "$DIR/$f" | cut -d' ' -f1)
  root_sql -e "DROP DATABASE IF EXISTS $SCRATCH; CREATE DATABASE $SCRATCH CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
  # The backups name the live database in CREATE DATABASE / USE lines: drop those
  # two lines so everything loads into the scratch database only.
  grep -avE '^(CREATE DATABASE|USE )' "$DIR/$f" | root_sql "$SCRATCH"
  for t in $NEEDED; do
    root_sql -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SCRATCH' AND TABLE_NAME='$t'" | grep -q '^1$' \
      || root_sql -e "CREATE TABLE $SCRATCH.\`$t\` LIKE sabong_lara_db.\`$t\`"
  done
  docker exec sabonglara-app artisan legacy:seal-file "$f" "$sha"
done 3< "$ORDER"

root_sql -e "DROP DATABASE IF EXISTS $SCRATCH; CREATE DATABASE $SCRATCH;"
echo "Done. Packages: storage/app/closings/legacy/packages/  Registry: storage/app/closings/legacy/registry.jsonl"
