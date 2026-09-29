<?php

namespace App\Console\Commands;

use App\Services\Closing\EventSealer;
use Illuminate\Console\Command;

class ClosingPreview extends Command
{
  protected $signature = 'closing:preview {event : Event id} {--json : Print the full report as JSON}';
  protected $description = 'Show the unsealed closing report (X-reading) for an event, including balance checks';

  public function handle(EventSealer $sealer): int
  {
    $r = $sealer->preview((int) $this->argument('event'));

    if ($this->option('json')) {
      $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::SUCCESS;
    }

    $this->info("Event #{$r['event']['event_id']} {$r['event']['name']} ({$r['event']['date']}), commission {$r['event']['commission_rate']}");
    $this->table(['Total', 'Value'], collect($r['totals'])->map(fn ($v, $k) => [$k, $v])->values()->all());
    $this->table(['Check', 'Result'], array_map(fn ($c) => [$c['name'], $c['passed'] ? 'PASS' : 'FAIL: ' . $c['detail']], $r['checks']));
    return self::SUCCESS;
  }
}
