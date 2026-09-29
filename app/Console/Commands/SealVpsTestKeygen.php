<?php

namespace App\Console\Commands;

use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

class SealVpsTestKeygen extends Command
{
  protected $signature = 'seal:vps-test-keygen';
  protected $description = 'SANDBOX ONLY: create a stand-in VPS key pair to test package encryption end to end';

  public function handle(KeyStore $keys): int
  {
    if (app()->environment('production')) {
      $this->error('Refusing to run in production. Use the real VPS public key (SEAL_VPS_PUBLIC_KEY).');
      return self::FAILURE;
    }
    $pub = $keys->generateVpsTestKey();
    $this->info('Test VPS key pair written: vps.pub (public) and vps-test.key (private, sandbox only).');
    $this->line('  public key: ' . $pub);
    $this->warn('Never register this sandbox key on the real VPS.');
    return self::SUCCESS;
  }
}
