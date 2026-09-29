<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Line-oriented, gzip-compressed, authenticated-encrypted file stream.
 *
 * Layout: "SLBK1\n" | secretstream header | repeated [uint32 length | encrypted chunk]
 * Cipher: libsodium secretstream XChaCha20-Poly1305. Every chunk is
 * authenticated, chunks cannot be reordered, and the last chunk carries the
 * FINAL tag, so truncation or any modified byte is detected on read.
 */
final class EncryptedStream
{
  private const MAGIC = "SLBK1\n";
  private const CHUNK = 65536;

  /** @var resource */
  private $fh;
  private $state;
  private $zlib;
  private string $buffer = '';

  private function __construct() {}

  public static function create(string $path, string $key): self
  {
    $s = new self();
    $old = umask(0077);
    $s->fh = fopen($path, 'x');
    umask($old);
    if (!$s->fh) throw new RuntimeException("Cannot create {$path}");
    [$s->state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
    fwrite($s->fh, self::MAGIC . $header);
    $s->zlib = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
    return $s;
  }

  public function writeLine(string $line): void
  {
    $this->buffer .= deflate_add($this->zlib, $line . "\n", ZLIB_NO_FLUSH);
    while (strlen($this->buffer) >= self::CHUNK) {
      $this->push(substr($this->buffer, 0, self::CHUNK), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
      $this->buffer = substr($this->buffer, self::CHUNK);
    }
  }

  public function close(): void
  {
    $this->buffer .= deflate_add($this->zlib, '', ZLIB_FINISH);
    $this->push($this->buffer, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    $this->buffer = '';
    fclose($this->fh);
  }

  private function push(string $plain, int $tag): void
  {
    $c = sodium_crypto_secretstream_xchacha20poly1305_push($this->state, $plain, '', $tag);
    fwrite($this->fh, pack('N', strlen($c)) . $c);
  }

  /**
   * Yields the decrypted lines. Throws if the file was altered, truncated,
   * or encrypted with a different key.
   */
  public static function readLines(string $path, string $key): \Generator
  {
    $fh = fopen($path, 'r');
    if (!$fh) throw new RuntimeException("Cannot open {$path}");
    try {
      if (fread($fh, strlen(self::MAGIC)) !== self::MAGIC) throw new RuntimeException('Not a backup file.');
      $header = fread($fh, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
      $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
      $zlib = inflate_init(ZLIB_ENCODING_GZIP);
      $buffer = '';
      $final = false;

      while (!$final) {
        $lenBytes = fread($fh, 4);
        if ($lenBytes === '' || $lenBytes === false || strlen($lenBytes) < 4) {
          throw new RuntimeException('Backup file is truncated (missing final chunk).');
        }
        $len = unpack('N', $lenBytes)[1];
        $chunk = $len > 0 ? fread($fh, $len) : '';
        if (strlen($chunk) !== $len) throw new RuntimeException('Backup file is truncated.');

        $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
        if ($res === false) throw new RuntimeException('Backup file failed authentication (altered, corrupted, or wrong backup key).');
        [$plain, $tag] = $res;
        $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;

        $buffer .= inflate_add($zlib, $plain, $final ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
        while (($pos = strpos($buffer, "\n")) !== false) {
          yield substr($buffer, 0, $pos);
          $buffer = substr($buffer, $pos + 1);
        }
      }
      if (fread($fh, 1) !== '' || $buffer !== '') throw new RuntimeException('Unexpected data after the final chunk.');
    } finally {
      fclose($fh);
    }
  }
}
