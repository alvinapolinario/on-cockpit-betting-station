<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every bet receipt reprint: which receipt, who reprinted it (teller or admin) and when.
 * A teller may reprint a receipt only once; the teller app shows "Already reprinted once"
 * and disables Reprint after that. Admin reprints are recorded too.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::create('bet_reprints', function (Blueprint $t) {
      $t->id('bet_reprint_id');
      $t->unsignedBigInteger('bet_id');
      $t->string('bet_receipt_code', 64);
      $t->string('reprinted_via', 10); // 'teller' or 'admin'
      $t->unsignedBigInteger('event_teller_id')->nullable();
      $t->unsignedBigInteger('admin_id')->nullable();
      $t->dateTime('reprinted_at');
      $t->index('bet_id');
      $t->index('bet_receipt_code');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('bet_reprints');
  }
};
