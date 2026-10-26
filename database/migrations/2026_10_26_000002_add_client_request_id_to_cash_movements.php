<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate-request protection for teller cash-in and cash-out requests, as for bets.
 * The teller app sends a random client_request_id and resends the same id when it got
 * no answer; the server then reports the request already saved instead of creating a
 * second Pending request an admin could approve twice. Unique per teller; NULL for
 * older clients.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('cash_ins', function (Blueprint $t) {
      $t->string('client_request_id', 64)->nullable();
      $t->unique(['event_teller_id', 'client_request_id'], 'cash_ins_teller_request_unique');
    });
    Schema::table('cash_outs', function (Blueprint $t) {
      $t->string('client_request_id', 64)->nullable();
      $t->unique(['event_teller_id', 'client_request_id'], 'cash_outs_teller_request_unique');
    });
  }

  public function down(): void
  {
    Schema::table('cash_ins', function (Blueprint $t) {
      $t->dropUnique('cash_ins_teller_request_unique');
      $t->dropColumn('client_request_id');
    });
    Schema::table('cash_outs', function (Blueprint $t) {
      $t->dropUnique('cash_outs_teller_request_unique');
      $t->dropColumn('client_request_id');
    });
  }
};
