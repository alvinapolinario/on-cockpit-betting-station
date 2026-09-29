#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
sed -e 's/DEFINER=`[^`]*`@`[^`]*`//g' "$ROOT/etc/db9.sql" > "$(dirname "$0")/01-dump.sql"
echo "Wrote $(dirname "$0")/01-dump.sql"
