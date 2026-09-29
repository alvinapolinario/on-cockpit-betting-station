<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // One line per cash movement of a teller, written in the same database
    // transaction as the movement itself, with the cash on hand before and after.
    Schema::create('teller_ledger', function (Blueprint $table) {
      $table->bigIncrements('ledger_id');
      $table->integer('event_id');
      $table->integer('event_teller_id');
      $table->unsignedInteger('line_no');                 // 1, 2, 3 ... per teller per event
      $table->dateTime('entry_at');
      $table->string('type', 20);                         // opening, bet, void, payout_win, payout_refund, cash_in, cash_out, cash_declined, adjustment
      $table->integer('fight_no')->nullable();            // the bet's / receipt's fight
      $table->integer('match_id')->nullable();
      $table->string('reference', 60)->nullable();        // receipt code, CI-123, CO-45 ...
      $table->decimal('amount_in', 16, 2)->default(0);
      $table->decimal('amount_out', 16, 2)->default(0);
      $table->decimal('balance_before', 16, 2);
      $table->decimal('balance_after', 16, 2);
      $table->integer('related_event_teller_id')->nullable(); // payouts: teller who issued the receipt
      $table->integer('recorded_by_account_id')->nullable();
      $table->integer('approved_by_account_id')->nullable();
      $table->string('note', 255)->nullable();
      $table->string('source_table', 30)->nullable();
      $table->unsignedBigInteger('source_id')->nullable();

      $table->unique(['event_teller_id', 'line_no'], 'ledger_teller_line_unique');
      // The same bet / claim / cash request can never be logged twice for the same kind of entry.
      $table->unique(['source_table', 'source_id', 'type'], 'ledger_source_unique');
      $table->index(['event_id', 'event_teller_id'], 'ledger_event_teller_idx');
      $table->index('reference', 'ledger_reference_idx');
    });

    DB::unprepared("
      CREATE TRIGGER teller_ledger_no_update BEFORE UPDATE ON teller_ledger FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger lines cannot be modified; add an adjustment line'
    ");
    DB::unprepared("
      CREATE TRIGGER teller_ledger_no_delete BEFORE DELETE ON teller_ledger FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger lines cannot be deleted'
    ");

    // When the cash actually moved (approval), not just when it was requested.
    Schema::table('cash_ins', function (Blueprint $table) {
      $table->dateTime('approved_at')->nullable();
    });
    Schema::table('cash_outs', function (Blueprint $table) {
      $table->dateTime('approved_at')->nullable();
    });
  }

  public function down(): void
  {
    throw new RuntimeException('teller_ledger is append-only and cannot be rolled back.');
  }
};
