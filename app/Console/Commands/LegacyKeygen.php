<?php

namespace App\Console\Commands;

use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

class LegacyKeygen extends Command
{
  protected $signature = 'legacy:keygen';
  protected $description = 'Create the signing key used ONLY for packages reconstructed from old database backups';

  public function handle(KeyStore $keys): int
  {
    try {
      $r = $keys->generateLegacyKey();
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }
    $this->info('Legacy signing key created (storage/app/keys/legacy-sign.key).');
    $this->line('Register it on the BIR Compliance System as the legacy sender (server id: ' . config('sealing.legacy_server_id') . '):');
    $this->line('  public key:  ' . $r['public']);
    $this->line('  fingerprint: ' . $r['fingerprint']);
    return self::SUCCESS;
  }
}
