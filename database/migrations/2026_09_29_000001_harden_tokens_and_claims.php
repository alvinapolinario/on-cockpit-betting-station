<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // 1. Login tokens were stored in plain text with no expiry. Store only the
    //    SHA-256 hash (same value PHP's hash('sha256') gives, so apps that are
    //    logged in keep working) and give each token an expiry: tokens used or
    //    created in the last 24 hours stay valid for 18 more hours, older ones expire now.
    DB::update("
      UPDATE personal_access_tokens
      SET token = SHA2(token, 256),
          expires_at = CASE
            WHEN COALESCE(last_used_at, created_at) >= NOW() - INTERVAL 24 HOUR THEN NOW() + INTERVAL 18 HOUR
            ELSE NOW()
          END
      WHERE CHAR_LENGTH(token) <> 64
    ");

    // 2. One payout per bet, enforced by the database (blocks double payouts
    //    even if two tellers submit the same receipt at the same moment).
    $dupes = DB::table('claims')->select('bet_id')->groupBy('bet_id')->havingRaw('COUNT(*) > 1')->count();
    if ($dupes > 0) {
      throw new RuntimeException("{$dupes} bet(s) already have more than one payout. Resolve them before adding the unique index.");
    }
    Schema::table('claims', function (Blueprint $table) {
      $table->unique('bet_id', 'claims_bet_id_unique');
    });
  }

  public function down(): void
  {
    Schema::table('claims', function (Blueprint $table) {
      $table->dropUnique('claims_bet_id_unique');
    });
    // Token hashing cannot be reversed (by design).
  }
};
