<?php

namespace App\Models;

use App\Services\Closing\KeyStore;

class AppSetting
{
  // SBAPI2 QRs are signed; the teller app rejects unsigned SBAPI1 QRs, which anyone could print.
  public const QR_PREFIX = 'SBAPI2:';

  public static function defaults(): array
  {
    return [
      'api_url' => self::defaultApiUrl(),
      'app_name' => 'OCBS',
    ];
  }

  public static function get(): array
  {
    $path = self::path();
    $stored = [];
    if (is_file($path)) {
      $decoded = json_decode((string) file_get_contents($path), true);
      if (is_array($decoded)) {
        $stored = $decoded;
      }
    }
    return array_merge(self::defaults(), $stored);
  }

  public static function put(string $apiUrl, string $appName): array
  {
    $data = [
      'api_url' => self::normalizeUrl($apiUrl),
      'app_name' => trim($appName) !== '' ? trim($appName) : 'OCBS',
    ];
    $dir = dirname(self::path());
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    file_put_contents(self::path(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $data;
  }

  public static function appName(): string
  {
    $name = trim((string) (self::get()['app_name'] ?? ''));
    return $name !== '' ? $name : 'OCBS';
  }

  /**
   * The signed setup QR text, or null when this server has no setup QR key yet.
   * The signature covers "SBAPI2\n{url}\n{name}"; the app checks it with the public key
   * built into it, so a QR naming another server cannot be printed without the key.
   */
  public static function qrPayload(): ?string
  {
    $keys = app(KeyStore::class);
    if (!$keys->hasSetupQrKey()) {
      return null;
    }
    $settings = self::get();
    $url = (string) $settings['api_url'];
    $name = (string) $settings['app_name'];
    return self::QR_PREFIX . json_encode([
      'url' => $url,
      'name' => $name,
      'sig' => $keys->signSetupQr("SBAPI2\n{$url}\n{$name}"),
    ], JSON_UNESCAPED_SLASHES);
  }

  public static function defaultApiUrl(): string
  {
    $parts = parse_url((string) config('app.url'));
    $host = $parts['host'] ?? '127.0.0.1';
    if (!empty($parts['port'])) {
      return $host . ':' . $parts['port'];
    }
    return $host;
  }

  public static function normalizeUrl(string $value): string
  {
    $value = trim($value);
    $value = preg_replace('#^https?://#i', '', $value);
    $value = rtrim($value, '/');
    return $value !== '' ? $value : self::defaultApiUrl();
  }

  private static function path(): string
  {
    return storage_path('app/app_settings.json');
  }
}
