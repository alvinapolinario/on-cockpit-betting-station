<?php

namespace App\Http\Controllers;

use App\Models\EventClosing;
use App\Services\Closing\Canonical;
use App\Services\Closing\ClosingReportBuilder;
use App\Services\Closing\EventSealer;
use App\Services\Closing\KeyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit of closed events: browse a closed event's fights, bets,
 * payouts, cash and tellers, and re-verify its seal against today's data.
 * Only GET routes; nothing here changes betting data.
 */
class EventAuditController extends Controller
{
  private const TABS = ['summary', 'fights', 'bets', 'payouts', 'cash', 'tellers', 'logs'];
  private const PER_PAGE = 100;

  public function __construct(private ClosingReportBuilder $builder, private KeyStore $keys) {}

  public function index()
  {
    $events = DB::table('events as e')
      ->leftJoin('event_closings as c', 'c.event_id', '=', 'e.event_id')
      ->leftJoin('accounts as a', 'a.account_id', '=', 'c.closed_by_account_id')
      ->where('e.event_status', 'Closed')
      ->orderByDesc('e.event_date')->orderByDesc('e.event_id')
      ->get(['e.event_id', 'e.event_name', 'e.event_date', 'c.event_closing_id', 'c.sequence_no', 'c.seal_code', 'c.closed_at', 'a.username as closed_by']);

    return view('event-audit.index', ['events' => $events]);
  }

  public function show(Request $r, int $event_id)
  {
    $event = DB::table('events')->where('event_id', $event_id)->first();
    abort_if(!$event || $event->event_status !== 'Closed', 404, 'Only closed events can be audited here.');

    $tab = in_array($r->query('tab'), self::TABS, true) ? $r->query('tab') : 'summary';
    $q = trim((string) $r->query('q', ''));
    $closing = EventClosing::where('event_id', $event_id)->first();
    $sealed = $closing ? $closing->payloadData() : null;

    $data = ['event' => $event, 'closing' => $closing, 'sealed' => $sealed, 'tab' => $tab, 'q' => $q, 'tabs' => self::TABS];

    switch ($tab) {
      case 'summary':
        $data['counts'] = [
          'fights' => DB::table('matches')->where('event_id', $event_id)->count(),
          'bets' => DB::table('bets_view')->where('event_id', $event_id)->count(),
          'payouts' => DB::table('claims as c')->join('event_tellers as et', 'et.event_teller_id', '=', 'c.event_teller_id')->where('et.event_id', $event_id)->count(),
          'tellers' => DB::table('event_tellers')->where('event_id', $event_id)->count(),
        ];
        break;

      case 'fights':
        $data['rows'] = DB::table('matches')->where('event_id', $event_id)->orderBy('match_number')->get();
        $data['sealedFights'] = collect($sealed['fights'] ?? [])->keyBy('fight_no');
        break;

      case 'bets':
        $rows = DB::table('bets_view')->where('event_id', $event_id);
        if ($q !== '') {
          $rows->where(fn ($w) => ctype_digit($q)
            ? $w->where('bet_receipt_code', 'like', "%{$q}%")->orWhere('match_number', (int) $q)
            : $w->where('bet_receipt_code', 'like', "%{$q}%")->orWhere('teller_name', 'like', "%{$q}%"));
        }
        $data['rows'] = $rows->orderBy('bet_id')->paginate(self::PER_PAGE)->withQueryString();
        break;

      case 'payouts':
        $rows = DB::table('claims_view as c')->join('event_tellers as et', 'et.event_teller_id', '=', 'c.event_teller_id')
          ->where('et.event_id', $event_id)->select('c.*');
        if ($q !== '') {
          $rows->where(fn ($w) => $w->where('c.bet_receipt_code', 'like', "%{$q}%")->orWhere('c.teller_name', 'like', "%{$q}%"));
        }
        $data['rows'] = $rows->orderBy('c.claim_id')->paginate(self::PER_PAGE)->withQueryString();
        break;

      case 'cash':
        $data['cashIns'] = DB::table('cash_ins_view')->where('event_id', $event_id)->orderBy('cash_in_id')->get();
        $data['cashOuts'] = DB::table('cash_outs_view')->where('event_id', $event_id)->orderBy('cash_out_id')->get();
        break;

      case 'tellers':
        $data['tellerNames'] = DB::table('event_tellers_view')->where('event_id', $event_id)->pluck('teller_name', 'event_teller_id');
        $data['remittances'] = DB::table('teller_remittances_view')->where('event_id', $event_id)->orderBy('teller_remittance_id')->get();
        break;

      case 'logs':
        $range = $sealed['detail']['log_range'] ?? null;
        $data['rows'] = $range
          ? DB::table('transactions')->whereBetween('transaction_id', $range)->orderBy('transaction_id')->paginate(self::PER_PAGE)->withQueryString()
          : null;
        $data['logRange'] = $range;
        break;
    }

    $this->createLog(session()->get('account_id'), 'Web App', "Viewed event audit #{$event_id} ({$tab})");
    return view('event-audit.show', $data);
  }

  /** Re-verify the seal of a closed event against today's database. */
  public function verify(int $event_id)
  {
    $event = DB::table('events')->where('event_id', $event_id)->first();
    abort_if(!$event || $event->event_status !== 'Closed', 404);
    $closing = EventClosing::where('event_id', $event_id)->first();

    $results = [];
    $ok = function (string $name, bool $passed, string $detail) use (&$results) {
      $results[] = ['name' => $name, 'passed' => $passed, 'detail' => $passed ? 'ok' : $detail];
      return $passed;
    };

    if (!$closing) {
      $ok('sealed', false, 'This event was closed without a seal (before sealing was introduced, or closed outside the closing-report screen). Its data can be browsed but not verified.');
    } else {
      $payload = $closing->payloadData();

      // 1. The stored payload is the one that was hashed, signed and coded.
      $hash = hash('sha256', $closing->payload);
      $ok('hash_matches', $hash === $closing->payload_sha256, 'stored payload does not match its SHA-256');
      $ok('seal_code_matches', EventSealer::sealCode($closing->payload_sha256) === $closing->seal_code, 'seal code does not match the payload hash');

      // 2. Signed by this server's signing key.
      if ($this->keys->hasSigningKey() && $this->keys->signingFingerprint() === $closing->key_fingerprint) {
        $sigOk = sodium_crypto_sign_verify_detached(base64_decode($closing->signature), $closing->payload, $this->keys->signingPublic());
        $ok('signature_valid', $sigOk, 'signature invalid: the stored payload was altered');
      } else {
        $ok('signature_valid', false, "signed with key {$closing->key_fingerprint}, which is not this server's current signing key; verify on the BIR system instead");
      }

      // 3. Chain: links to the previous seal.
      $prev = EventClosing::where('sequence_no', $closing->sequence_no - 1)->first();
      $expectedPrev = $prev ? $prev->payload_sha256 : EventSealer::GENESIS;
      $ok('chain_continues', $closing->prev_payload_sha256 === $expectedPrev && ($payload['prev_payload_sha256'] ?? null) === $expectedPrev,
        'previous-seal hash does not match seal S' . sprintf('%04d', $closing->sequence_no - 1));

      // 4. Today's data still produces exactly what was sealed.
      [$from, $to] = $payload['detail']['log_range'];
      $now = $this->builder->build($event_id, (int) $from, (int) $to);
      foreach (['event', 'fights', 'tellers', 'totals', 'exceptions', 'checks'] as $part) {
        $same = Canonical::encode($now[$part]) === Canonical::encode($payload[$part]);
        $detail = 'differs from the seal';
        if (!$same && in_array($part, ['fights', 'tellers'], true)) {
          $key = $part === 'fights' ? 'fight_no' : 'teller_code';
          $a = collect($payload[$part])->keyBy($key); $b = collect($now[$part])->keyBy($key);
          $diff = $a->keys()->merge($b->keys())->unique()->filter(fn ($k) => Canonical::encode($a->get($k)) !== Canonical::encode($b->get($k)));
          $detail = 'changed since the seal: ' . $diff->implode(', ');
        }
        $ok("data_unchanged_{$part}", $same, $detail);
      }

      // 5. The detail file (raw rows) is intact.
      $file = $closing->detail_file;
      $ok('detail_file_intact', is_file($file) && hash_file('sha256', $file) === $closing->detail_sha256,
        is_file($file) ? 'detail file differs from its sealed SHA-256' : 'detail file not found on this server');
    }

    $passed = !array_filter($results, fn ($x) => !$x['passed']);
    $this->createLog(session()->get('account_id'), 'Web App', "Re-verified seal of event #{$event_id}: " . ($passed ? 'PASSED' : 'FAILED'));

    return redirect()->route('event-audit.show', $event_id)->with('verify', ['at' => now()->format('Y-m-d H:i:s'), 'passed' => $passed, 'results' => $results]);
  }
}
