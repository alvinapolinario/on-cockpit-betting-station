<?php

namespace App\Console\Commands;

use App\Services\Closing\EventSealer;
use App\Services\Closing\KeyStore;
use Illuminate\Console\Command;

/**
 * Verifies an encrypted closing package the way the BIR Compliance System
 * (VPS) must. In the sandbox it uses the stand-in VPS key; the VPS team can
 * port EventSealer::verifyPackage() as the reference implementation.
 */
class ClosingVerify extends Command
{
  protected $signature = 'closing:verify {file : Path to a .pkg.enc file}
    {--vps-key= : Path to the VPS private key pair (default: sandbox vps-test.key)}
    {--server-pub= : Path to the registered server public key (default: sign.pub)}
    {--prev= : Expected previous payload SHA-256 (chain check)}';
  protected $description = 'Verify a closing package: decrypt, check hash, signature, chain and arithmetic';

  public function handle(KeyStore $keys): int
  {
    $vpsKey = base64_decode(trim(file_get_contents($this->option('vps-key') ?: $keys->path('vps-test.key'))), true);
    $serverPub = base64_decode(trim(file_get_contents($this->option('server-pub') ?: $keys->path('sign.pub'))), true);

    $results = EventSealer::verifyPackage(file_get_contents($this->argument('file')), $vpsKey, $serverPub, $this->option('prev'));
    $this->table(['Check', 'Result'], array_map(fn ($c) => [$c['name'], $c['passed'] ? 'PASS' : 'FAIL: ' . $c['detail']], $results));

    $failed = array_filter($results, fn ($c) => !$c['passed']);
    $failed ? $this->error('REJECT: package failed verification.') : $this->info('ACCEPT: package verified.');
    return $failed ? self::FAILURE : self::SUCCESS;
  }
}
