<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Database backup & restore
  |--------------------------------------------------------------------------
  |
  | Backups are full, encrypted (local backup key) and signed (server signing
  | key) snapshots of the database. They never leave the arena; the BIR
  | Compliance System receives closing packages instead.
  |
  */

  // Where backups, manifests and the restore journal live. Not web-accessible.
  'path' => env('BACKUP_PATH', storage_path('app/backups')),

  // Two-person rule for restores.
  'require_second_approver' => (bool) env('BACKUP_REQUIRE_SECOND_APPROVER', true),

  // Rows per insert batch during restore.
  'batch_rows' => 500,

  'format' => 'sabonglara.backup/1',

  // TESTING ONLY: allow importing raw mysqldump/mariadb-dump .sql files that
  // were not created by this app. Always refused when APP_ENV=production.
  // Files are placed in {path}/import/ (not uploaded through the browser).
  'allow_raw_sql_import' => (bool) env('BACKUP_ALLOW_RAW_SQL_IMPORT', false),
];
