<?php

// Link with the matching system running on the same server.
// Messages are signed (HMAC-SHA256) with one key per direction.
return [
  'enabled' => (bool) env('MATCHING_BRIDGE_ENABLED', false),
  // Base URL of the matching app as seen from this container, e.g. http://sabong-app:3000
  'matching_url' => rtrim((string) env('MATCHING_BRIDGE_URL', ''), '/'),
  // Key matching uses to sign messages TO betting (we verify with it).
  'key_from_matching' => (string) env('MATCHING_TO_BETTING_KEY', ''),
  // Key we use to sign messages TO matching.
  'key_to_matching' => (string) env('BETTING_TO_MATCHING_KEY', ''),
  // Signed messages older/newer than this are refused (seconds).
  'max_clock_skew' => 300,
];
