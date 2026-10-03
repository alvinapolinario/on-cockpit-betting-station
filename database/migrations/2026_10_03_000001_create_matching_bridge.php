<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link with the matching system (same server). Fights are called from
 * matching (entries, owners, weights, bands) and results go back to it.
 * bridge_outbox keeps every message until matching confirms it;
 * bridge_inbox makes incoming messages safe to receive twice.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('matches', function (Blueprint $t) {
      $t->integer('source_fight_uid')->nullable()->after('wala_entry');   // matching match_id (permanent)
      $t->integer('source_call_version')->default(0)->after('source_fight_uid');
      $t->text('meron_details')->nullable()->after('source_call_version'); // JSON: entry, owner, weight, bands, type
      $t->text('wala_details')->nullable()->after('meron_details');
      $t->dateTime('called_at')->nullable()->after('wala_details');
      $t->dateTime('bet_opened_at')->nullable()->after('called_at');       // first time betting opened (locks the fight)
      $t->string('hold_reason', 255)->nullable()->after('bet_opened_at');
      $t->index(['event_id', 'source_fight_uid'], 'matches_event_source_idx');
    });
    // Entry names from matching can be longer than the old 60 characters.
    DB::statement('ALTER TABLE matches MODIFY meron_entry VARCHAR(255) NULL, MODIFY wala_entry VARCHAR(255) NULL');

    Schema::table('events', function (Blueprint $t) {
      $t->integer('matching_event_id')->nullable();
    });

    Schema::create('bridge_outbox', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('msg_key', 100)->unique();
      $t->string('type', 30);
      $t->integer('fight_uid')->nullable();
      $t->longText('payload');
      $t->dateTime('created_at');
      $t->dateTime('sent_at')->nullable();
      $t->unsignedInteger('attempts')->default(0);
      $t->text('last_error')->nullable();
      $t->index(['sent_at', 'id']);
      $t->index(['type', 'fight_uid']);
    });

    Schema::create('bridge_inbox', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('msg_key', 100)->unique();
      $t->string('type', 30);
      $t->longText('payload');
      $t->integer('http_status');
      $t->text('response');
      $t->dateTime('received_at');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('bridge_inbox');
    Schema::dropIfExists('bridge_outbox');
    Schema::table('events', fn (Blueprint $t) => $t->dropColumn('matching_event_id'));
    Schema::table('matches', function (Blueprint $t) {
      $t->dropIndex('matches_event_source_idx');
      $t->dropColumn(['source_fight_uid', 'source_call_version', 'meron_details', 'wala_details', 'called_at', 'bet_opened_at', 'hold_reason']);
    });
  }
};
