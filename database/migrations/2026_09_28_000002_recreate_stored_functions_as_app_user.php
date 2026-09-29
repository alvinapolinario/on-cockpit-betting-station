<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two stored functions were created by root@localhost, so the app's
 * database user cannot read their definitions and backups could not include
 * them. Recreate them with IDENTICAL bodies (from docker/mysql/01-dump.sql)
 * owned by the app user. Behaviour does not change.
 */
return new class extends Migration
{
  public function up(): void
  {
    DB::unprepared('DROP FUNCTION IF EXISTS `get_teller_name`');
    DB::unprepared("
      CREATE FUNCTION `get_teller_name`(id INT) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
          DETERMINISTIC
      BEGIN
          DECLARE tel_name VARCHAR(255);

          SELECT teller_name INTO tel_name
          FROM event_tellers_view
          WHERE event_teller_id = id
          LIMIT 1;
          RETURN tel_name;
      END
    ");

    DB::unprepared('DROP FUNCTION IF EXISTS `get_transaction_account_name`');
    DB::unprepared("
      CREATE FUNCTION `get_transaction_account_name`(acc_id INT, trans_type VARCHAR(60)) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
          DETERMINISTIC
      BEGIN
          DECLARE acc_name VARCHAR(255);
          IF trans_type IN ('Web App', 'Admin App') THEN
              SELECT admin_name INTO acc_name
              FROM admins_view
              WHERE account_id = acc_id
              LIMIT 1;
          ELSEIF trans_type = 'Teller App' THEN
              SELECT teller_name INTO acc_name
              FROM event_tellers_view
              WHERE account_id = acc_id
              LIMIT 1;
          ELSE
              SET acc_name = NULL;
          END IF;
          RETURN acc_name;
      END
    ");
  }

  public function down(): void
  {
    // Nothing to undo: the functions are identical, only the owner changed.
  }
};
