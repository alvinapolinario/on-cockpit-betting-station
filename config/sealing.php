<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Event closing / sealing
  |--------------------------------------------------------------------------
  |
  | Keys live OUTSIDE the repo and outside the web root. The server signing
  | key is generated once per production server with `php artisan seal:keygen`.
  | The VPS public key is supplied by the BIR Compliance System team.
  |
  */

  // Identifies this betting server inside every package (e.g. "arena01-srv01").
  'server_id' => env('SEAL_SERVER_ID', 'sandbox-srv01'),

  // Directory holding sign.key / sign.pub (and vps.pub). Must not be web-accessible.
  'key_path' => env('SEAL_KEY_PATH', storage_path('app/keys')),

  // Base64 VPS public key (X25519). If empty, falls back to {key_path}/vps.pub.
  'vps_public_key' => env('SEAL_VPS_PUBLIC_KEY'),

  // Where sealed detail files and packages are written. Must not be web-accessible.
  'output_path' => env('SEAL_OUTPUT_PATH', storage_path('app/closings')),

  // Sender identity for packages reconstructed from old database backups
  // (separate key and sequence from the live server).
  'legacy_server_id' => env('SEAL_LEGACY_SERVER_ID', 'blueknife-legacy'),

  // Two-person rule: a second, different Admin must approve the seal.
  'require_second_approver' => (bool) env('SEAL_REQUIRE_SECOND_APPROVER', true),

  // Package format identifiers (bump when the payload structure changes).
  'payload_format' => 'sabonglara.closing/1',
  'package_format' => 'sabonglara.closing-package/1',
];
