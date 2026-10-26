<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate-bet protection. The teller app sends a random client_request_id with
 * each bet and resends the same id when it never got an answer. create-bet then
 * returns the bet already saved under that id instead of creating a second one.
 * Unique per teller; older clients send no id (NULL), which the index allows freely.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('bets', function (Blueprint $t) {
      $t->string('client_request_id', 64)->nullable()->after('bet_receipt_code');
      $t->unique(['event_teller_id', 'client_request_id'], 'bets_teller_request_unique');
    });
  }

  public function down(): void
  {
    Schema::table('bets', function (Blueprint $t) {
      $t->dropUnique('bets_teller_request_unique');
      $t->dropColumn('client_request_id');
    });
  }
};
