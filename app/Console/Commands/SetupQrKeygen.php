<?php

namespace App\Console\Commands;

use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

class SetupQrKeygen extends Command
{
  protected $signature = 'setup-qr:keygen {--force : Replace an existing key (teller apps built with the old public key stop accepting new QRs)}';
  protected $description = 'Generate the key that signs teller-app server setup QRs';

  public function handle(KeyStore $keys): int
  {
    try {
      $public = $keys->generateSetupQrKey((bool) $this->option('force'));
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }
    $this->info('Setup QR key created at ' . $keys->path('setup-qr.key') . '.');
    $this->line('Public key for the teller app (res/values/setup_qr.xml, setup_qr_public_key):');
    $this->line($public);
    $this->warn('Copy setup-qr.key and setup-qr.pub to every server whose QRs this teller app build must accept.');
    return self::SUCCESS;
  }
}
