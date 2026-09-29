<?php

namespace App\Console\Commands;

use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

class SealKeygen extends Command
{
  protected $signature = 'seal:keygen {--force : Replace an existing key (breaks verification until the new public key is registered on the VPS)}';
  protected $description = 'Generate this betting server\'s Ed25519 signing key for event closing reports';

  public function handle(KeyStore $keys): int
  {
    try {
      $result = $keys->generateSigningKey((bool) $this->option('force'));
    } catch (\RuntimeException $e) {
      $this->error($e->getMessage());
      return self::FAILURE;
    }

    $this->info('Signing key created at ' . $keys->path('sign.key') . ' (permissions 600).');
    $this->line('Register this PUBLIC key on the BIR Compliance System (VPS):');
    $this->line('  public key:  ' . $result['public']);
    $this->line('  fingerprint: ' . $result['fingerprint']);
    $this->warn('Back up sign.key to sealed offline storage. Never copy it to the VPS or into the repo.');
    return self::SUCCESS;
  }
}
