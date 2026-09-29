<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('event_closings', function (Blueprint $table) {
      $table->increments('event_closing_id');
      $table->integer('event_id')->unique();
      $table->unsignedInteger('sequence_no')->unique();

      // The signed payload, stored exactly as signed (canonical JSON).
      $table->longText('payload');
      $table->char('payload_sha256', 64)->unique();
      $table->char('detail_sha256', 64);
      $table->char('prev_payload_sha256', 64);
      $table->text('signature');
      $table->char('key_fingerprint', 16);
      $table->string('seal_code', 19);
      $table->string('detail_file');
      $table->string('server_id', 60);

      $table->integer('closed_by_account_id');
      $table->integer('approved_by_account_id')->nullable();
      $table->dateTime('closed_at');

      // Filled in after upload; the only columns allowed to change.
      $table->dateTime('downloaded_at')->nullable();
      $table->integer('downloaded_by_account_id')->nullable();
      $table->string('ack_code', 200)->nullable();
      $table->dateTime('ack_at')->nullable();
      $table->integer('ack_by_account_id')->nullable();
    });

    // Tamper protection at the database level: sealed columns can never be
    // updated and rows can never be deleted, even outside the application.
    DB::unprepared("
      CREATE TRIGGER event_closings_no_update BEFORE UPDATE ON event_closings FOR EACH ROW
      BEGIN
        IF NOT (NEW.event_id <=> OLD.event_id AND NEW.sequence_no <=> OLD.sequence_no
            AND NEW.payload <=> OLD.payload AND NEW.payload_sha256 <=> OLD.payload_sha256
            AND NEW.detail_sha256 <=> OLD.detail_sha256 AND NEW.prev_payload_sha256 <=> OLD.prev_payload_sha256
            AND NEW.signature <=> OLD.signature AND NEW.key_fingerprint <=> OLD.key_fingerprint
            AND NEW.seal_code <=> OLD.seal_code AND NEW.detail_file <=> OLD.detail_file
            AND NEW.server_id <=> OLD.server_id AND NEW.closed_by_account_id <=> OLD.closed_by_account_id
            AND NEW.approved_by_account_id <=> OLD.approved_by_account_id AND NEW.closed_at <=> OLD.closed_at) THEN
          SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sealed event closings cannot be modified';
        END IF;
      END
    ");

    DB::unprepared("
      CREATE TRIGGER event_closings_no_delete BEFORE DELETE ON event_closings FOR EACH ROW
      BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sealed event closings cannot be deleted';
      END
    ");
  }

  public function down(): void
  {
    // Intentionally not reversible: sealed closings are permanent records.
    throw new RuntimeException('event_closings is append-only and cannot be rolled back.');
  }
};
