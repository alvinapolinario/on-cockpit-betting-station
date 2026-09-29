<?php

namespace App\Services\Closing;

use RuntimeException;

/**
 * Ed25519 signing key of this betting server, plus the VPS's X25519 public
 * key used to encrypt packages so only the BIR Compliance System can read them.
 *
 *   {key_path}/sign.key  base64 secret key (chmod 600, never leaves the server)
 *   {key_path}/sign.pub  base64 public key (registered on the VPS)
 *   {key_path}/vps.pub   base64 VPS encryption public key (from the VPS team)
 */
class KeyStore
{
  public function path(string $file): string
  {
    return rtrim(config('sealing.key_path'), '/') . '/' . $file;
  }

  public function hasSigningKey(): bool
  {
    return is_file($this->path('sign.key'));
  }

  public function generateSigningKey(bool $force = false): array
  {
    if ($this->hasSigningKey() && !$force) {
      throw new RuntimeException('A signing key already exists. Replacing it breaks verification of future packages until the new public key is registered on the VPS. Use --force only if that is intended.');
    }
    $pair = sodium_crypto_sign_keypair();
    $this->writeSecret('sign.key', base64_encode(sodium_crypto_sign_secretkey($pair)));
    $this->writePublic('sign.pub', base64_encode(sodium_crypto_sign_publickey($pair)));
    return ['public' => base64_encode(sodium_crypto_sign_publickey($pair)), 'fingerprint' => $this->signingFingerprint()];
  }

  public function signingSecret(): string
  {
    if (!$this->hasSigningKey()) {
      throw new RuntimeException('No signing key on this server. Run: php artisan seal:keygen');
    }
    return base64_decode(trim(file_get_contents($this->path('sign.key'))), true);
  }

  public function signingPublic(): string
  {
    return base64_decode(trim(file_get_contents($this->path('sign.pub'))), true);
  }

  public function signingFingerprint(): string
  {
    return self::fingerprint($this->signingPublic());
  }

  public function vpsPublic(): string
  {
    $b64 = config('sealing.vps_public_key');
    if (!$b64 && is_file($this->path('vps.pub'))) {
      $b64 = trim(file_get_contents($this->path('vps.pub')));
    }
    $key = $b64 ? base64_decode($b64, true) : false;
    if (!$key || strlen($key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
      throw new RuntimeException('VPS public key is not configured. Set SEAL_VPS_PUBLIC_KEY or place vps.pub in the key directory. (Sandbox: php artisan seal:vps-test-keygen)');
    }
    return $key;
  }

  public function hasVpsKey(): bool
  {
    try { $this->vpsPublic(); return true; } catch (RuntimeException) { return false; }
  }

  /** SANDBOX ONLY: stand-in VPS key pair so encryption can be tested end to end. */
  public function generateVpsTestKey(): string
  {
    $pair = sodium_crypto_box_keypair();
    $this->writeSecret('vps-test.key', base64_encode($pair));
    $this->writePublic('vps.pub', base64_encode(sodium_crypto_box_publickey($pair)));
    return base64_encode(sodium_crypto_box_publickey($pair));
  }

  // ---- legacy-import signing key (separate identity from the live server) ----

  public function hasLegacyKey(): bool
  {
    return is_file($this->path('legacy-sign.key'));
  }

  public function generateLegacyKey(): array
  {
    if ($this->hasLegacyKey()) {
      throw new RuntimeException('A legacy signing key already exists.');
    }
    $pair = sodium_crypto_sign_keypair();
    $this->writeSecret('legacy-sign.key', base64_encode(sodium_crypto_sign_secretkey($pair)));
    $this->writePublic('legacy-sign.pub', base64_encode(sodium_crypto_sign_publickey($pair)));
    return ['public' => base64_encode(sodium_crypto_sign_publickey($pair)), 'fingerprint' => self::fingerprint(sodium_crypto_sign_publickey($pair))];
  }

  public function legacySecret(): string
  {
    if (!$this->hasLegacyKey()) throw new RuntimeException('No legacy signing key. Run: php artisan legacy:keygen');
    return base64_decode(trim(file_get_contents($this->path('legacy-sign.key'))), true);
  }

  public function legacyFingerprint(): string
  {
    return self::fingerprint(base64_decode(trim(file_get_contents($this->path('legacy-sign.pub'))), true));
  }

  public function hasBackupKey(): bool
  {
    return is_file($this->path('backup.key'));
  }

  /** Symmetric key for local database backups. Losing it makes every backup unreadable. */
  public function generateBackupKey(bool $force = false): string
  {
    if ($this->hasBackupKey() && !$force) {
      throw new RuntimeException('A backup key already exists. Replacing it makes existing backups unreadable. Use --force only if that is intended.');
    }
    $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
    $this->writeSecret('backup.key', base64_encode($key));
    return self::fingerprint($key);
  }

  public function backupKey(): string
  {
    if (!$this->hasBackupKey()) {
      throw new RuntimeException('No backup key on this server. Run: php artisan backup:keygen');
    }
    return base64_decode(trim(file_get_contents($this->path('backup.key'))), true);
  }

  public static function fingerprint(string $publicKey): string
  {
    return substr(hash('sha256', $publicKey), 0, 16);
  }

  private function ensureDir(): void
  {
    $dir = rtrim(config('sealing.key_path'), '/');
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    @chmod($dir, 0700);
  }

  private function writeSecret(string $file, string $content): void
  {
    $this->ensureDir();
    $path = $this->path($file);
    $old = umask(0077);
    file_put_contents($path, $content . "\n");
    umask($old);
    chmod($path, 0600);
  }

  private function writePublic(string $file, string $content): void
  {
    $this->ensureDir();
    file_put_contents($this->path($file), $content . "\n");
    chmod($this->path($file), 0644);
  }
}
