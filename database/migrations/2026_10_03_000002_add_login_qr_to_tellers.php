<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teller login QR codes. Each teller has one QR (valid until regenerated) that
 * logs in to the teller app and links the phone on first scan.
 * qr_token_hash: SHA-256 of the QR secret, used to find the teller on scan.
 * qr_token: the secret encrypted with APP_KEY, so admins can show/print it again.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('tellers', function (Blueprint $t) {
      $t->char('qr_token_hash', 64)->nullable()->unique()->after('phone_uid');
      $t->text('qr_token')->nullable()->after('qr_token_hash');
      $t->dateTime('qr_generated_at')->nullable()->after('qr_token');
    });
  }

  public function down(): void
  {
    Schema::table('tellers', function (Blueprint $t) {
      $t->dropUnique(['qr_token_hash']);
      $t->dropColumn(['qr_token_hash', 'qr_token', 'qr_generated_at']);
    });
  }
};
