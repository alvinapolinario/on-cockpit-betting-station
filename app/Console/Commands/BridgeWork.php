<?php

namespace App\Console\Commands;

use App\Services\Bridge\MatchingBridge;
use Illuminate\Console\Command;
use Throwable;

/** Delivers queued messages to the matching system and retries until confirmed. Run by supervisord. */
class BridgeWork extends Command
{
  protected $signature = 'bridge:work {--once : Deliver what is pending and exit}';
  protected $description = 'Deliver queued betting → matching messages (status, results), retrying until confirmed';

  public function handle(): int
  {
    do {
      if (!MatchingBridge::enabled()) {
        if ($this->option('once')) { $this->warn('Matching link is disabled (MATCHING_BRIDGE_ENABLED / URL / keys).'); return self::SUCCESS; }
        sleep(30);
        continue;
      }
      try {
        $r = MatchingBridge::flush();
        if ($r['sent'] || $this->option('once')) $this->line(now()->format('H:i:s') . " sent {$r['sent']}, pending {$r['pending']}" . ($r['error'] ? " (last error: {$r['error']})" : ''));
      } catch (Throwable $e) {
        $this->error($e->getMessage());
      }
      if (!$this->option('once')) sleep(3);
    } while (!$this->option('once'));
    return self::SUCCESS;
  }
}
