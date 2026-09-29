@php
  $e = $report['event'];
  $t = $report['totals'];
  $x = $report['exceptions'];
  $sealed = $closing !== null;
  $peso = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
  $failedChecks = array_filter($report['checks'], fn ($c) => !$c['passed']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $sealed ? 'Closing Report' : 'PREVIEW' }} · Event #{{ $e['event_id'] }} {{ $e['name'] }}</title>
<style>
  :root { --ink:#111; --muted:#555; --line:#bbb; --bad:#b00020; --ok:#1b5e20; }
  * { box-sizing: border-box; }
  body { font: 12px/1.4 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: var(--ink); background:#fff; margin: 0; padding: 16px; }
  .sheet { max-width: 1100px; margin: 0 auto; }
  h1 { font-size: 18px; margin: 0; }
  h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid var(--ink); text-transform: uppercase; letter-spacing: .04em; }
  .meta { display: flex; flex-wrap: wrap; gap: 4px 18px; color: var(--muted); margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid var(--line); padding: 3px 5px; text-align: right; white-space: nowrap; }
  th { background: #f2f2f2; font-weight: 600; }
  td.l, th.l { text-align: left; }
  tfoot td { font-weight: 700; background: #fafafa; }
  .wrap { overflow-x: auto; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 4px 24px; }
  .row { display: flex; justify-content: space-between; border-bottom: 1px dotted var(--line); padding: 2px 0; }
  .row b { font-variant-numeric: tabular-nums; }
  .neg { color: var(--bad); }
  .pass { color: var(--ok); font-weight: 600; }
  .fail { color: var(--bad); font-weight: 600; }
  .banner { padding: 8px 10px; border: 2px solid var(--bad); color: var(--bad); font-weight: 700; margin-bottom: 10px; text-align: center; }
  .seal { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; border: 2px solid var(--ink); padding: 10px; }
  .seal code { font-size: 20px; font-weight: 700; letter-spacing: .08em; }
  .hash { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 10px; word-break: break-all; color: var(--muted); }
  .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 36px; }
  .sign div { border-top: 1px solid var(--ink); padding-top: 4px; text-align: center; }
  .toolbar { margin-bottom: 12px; }
  .toolbar button { padding: 6px 14px; }
  .flash { padding: 8px; background: #e8f5e9; border: 1px solid var(--ok); margin-bottom: 10px; }
  @media print {
    body { padding: 0; font-size: 10px; }
    .toolbar, .flash { display: none; }
    h2 { break-after: avoid; }
    tr { break-inside: avoid; }
    .wrap { overflow: visible; }
  }
  @media (max-width: 600px) { .sign { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="sheet">
  <div class="toolbar"><button onclick="window.print()">Print</button></div>
  @if (session('success')) <div class="flash">{{ session('success') }}</div> @endif

  @unless ($sealed)
    <div class="banner">PREVIEW — NOT SEALED. Live figures; not valid for filing.</div>
  @endunless

  <h1>{{ config('variables.templateName') }} — Event Closing Report</h1>
  <div class="meta">
    <span>Event #{{ $e['event_id'] }} “{{ $e['name'] }}”</span>
    <span>Date: {{ $e['date'] }}</span>
    <span>Commission rate: {{ rtrim(rtrim(number_format((float) $e['commission_rate'] * 100, 3), '0'), '.') }}%</span>
    <span>Fights: {{ $t['fights_total'] }}{{ !empty($t['fights_unused']) ? ' (' . $t['fights_unused'] . ' unused)' : '' }}</span>
    <span>Tellers: {{ count($report['tellers']) }}</span>
    @if ($sealed)
      <span>Closed: {{ $report['closed_at'] }} by {{ $report['closed_by'] }}{{ $report['approved_by'] ? ' / approved by ' . $report['approved_by'] : '' }}</span>
    @endif
  </div>

  <h2>A. Per-fight summary</h2>
  <div class="wrap"><table>
    <thead><tr>
      <th>Fight</th><th>Meron</th><th>Wala</th><th>Net pool</th><th class="l">Result</th><th>Odds</th>
      <th>Commission</th><th>Rounding/adj.</th><th>House take</th><th>Refunds</th><th>Winnings</th><th>Paid</th><th>Unclaimed</th>
    </tr></thead>
    <tbody>
    @foreach ($report['fights'] as $f)
      <tr>
        <td>{{ $f['fight_no'] }}</td>
        <td>{{ $peso($f['meron_total']) }}</td>
        <td>{{ $peso($f['wala_total']) }}</td>
        <td>{{ $peso($f['net_pool']) }}</td>
        <td class="l">{{ $f['winner'] ?? (!empty($f['unused']) ? 'Unused (no bets)' : $f['status']) }}</td>
        <td>{{ $f['winner'] ? ($f['winner'] === 'Meron' ? $f['meron_odds'] : $f['wala_odds']) : '—' }}</td>
        <td>{{ $peso($f['commission']) }}</td>
        <td class="{{ (float) $f['breakage'] < 0 ? 'neg' : '' }}">{{ $peso($f['breakage']) }}</td>
        <td>{{ $peso($f['house_take']) }}</td>
        <td>{{ $peso($f['refunds']) }}</td>
        <td>{{ $peso($f['winnings']) }}</td>
        <td>{{ $peso($f['paid']) }}</td>
        <td>{{ $peso($f['unclaimed']) }}</td>
      </tr>
    @endforeach
    </tbody>
    <tfoot><tr>
      <td>Total</td>
      <td>{{ $peso(array_sum(array_map(fn ($f) => (float) $f['meron_total'], $report['fights']))) }}</td>
      <td>{{ $peso(array_sum(array_map(fn ($f) => (float) $f['wala_total'], $report['fights']))) }}</td>
      <td>{{ $peso($t['net_bets']) }}</td><td></td><td></td>
      <td>{{ $peso($t['commission']) }}</td>
      <td class="{{ (float) $t['breakage'] < 0 ? 'neg' : '' }}">{{ $peso($t['breakage']) }}</td>
      <td>{{ $peso($t['house_take']) }}</td>
      <td>{{ $peso($t['refunds']) }}</td>
      <td>{{ $peso($t['winnings']) }}</td>
      <td>{{ $peso($t['paid']) }}</td>
      <td>{{ $peso($t['unclaimed']) }}</td>
    </tr></tfoot>
  </table></div>

  <h2>B. Audit per fight</h2>
  <div class="wrap"><table>
    <thead><tr>
      <th>Fight</th><th class="l">Betting opened</th><th class="l">Betting closed</th><th>Times opened</th><th>Bets M/W</th>
      <th>Voids</th><th class="l">Result declared</th><th class="l">By</th><th>Corrections</th>
    </tr></thead>
    <tbody>
    @foreach ($report['fights'] as $f)
      @php $a = $f['audit']; @endphp
      <tr>
        <td>{{ $f['fight_no'] }}</td>
        <td class="l">{{ $a['betting_opened_at'] ?? '—' }}</td>
        <td class="l">{{ $a['betting_closed_at'] ?? '—' }}</td>
        <td class="{{ $a['betting_open_count'] > 1 ? 'neg' : '' }}">{{ $a['betting_open_count'] }}</td>
        <td>{{ $f['bets_meron'] }}/{{ $f['bets_wala'] }}</td>
        <td>{{ $f['voids_count'] }}{{ $f['voids_count'] ? ' (' . $peso($f['voids_amount']) . ')' : '' }}</td>
        <td class="l">{{ $a['declared_at'] ?? '—' }}</td>
        <td class="l">{{ $a['declared_by'] ?? '—' }}</td>
        <td class="{{ $a['result_corrections'] ? 'neg' : '' }}">{{ $a['result_corrections'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>

  <h2>C. Event totals</h2>
  <div class="grid">
    <div>
      <div class="row"><span>Gross bets taken</span><b>{{ $peso($t['gross_bets']) }}</b></div>
      <div class="row"><span>Less voided bets ({{ $x['voids_count'] }})</span><b>({{ $peso($t['voided_bets']) }})</b></div>
      <div class="row"><span><strong>Net bets</strong></span><b>{{ $peso($t['net_bets']) }}</b></div>
      <div class="row"><span>Refunds (draw / cancelled)</span><b>{{ $peso($t['refunds']) }}</b></div>
      <div class="row"><span>Winnings due</span><b>{{ $peso($t['winnings']) }}</b></div>
      @if ((float) $t['unsettled_pool'] != 0)
        <div class="row"><span class="neg">Unsettled pool</span><b class="neg">{{ $peso($t['unsettled_pool']) }}</b></div>
      @endif
    </div>
    <div>
      <div class="row"><span>Commission at {{ rtrim(rtrim(number_format((float) $e['commission_rate'] * 100, 3), '0'), '.') }}%</span><b>{{ $peso($t['commission']) }}</b></div>
      <div class="row"><span>Rounding / odds adjustment</span><b class="{{ (float) $t['breakage'] < 0 ? 'neg' : '' }}">{{ $peso($t['breakage']) }}</b></div>
      <div class="row"><span><strong>House take (actual)</strong></span><b>{{ $peso($t['house_take']) }}</b></div>
      <div class="row"><span>Total payable to bettors</span><b>{{ $peso($t['payable']) }}</b></div>
      <div class="row"><span>Paid out ({{ $t['payout_count'] }} payouts)</span><b>{{ $peso($t['paid']) }}</b></div>
      <div class="row"><span><strong>Unclaimed at close</strong></span><b>{{ $peso($t['unclaimed']) }}</b></div>
    </div>
  </div>

  <h2>D. Teller summary</h2>
  <div class="wrap"><table>
    <thead><tr>
      <th class="l">Teller</th><th>Opening</th><th>Cash-in</th><th>Cash-out</th><th>Gross bets</th><th>Voids</th><th>Net bets</th>
      <th>Payouts</th><th>Expected cash</th><th>System balance</th><th>Counted</th><th>Short/over</th>
    </tr></thead>
    <tbody>
    @foreach ($report['tellers'] as $tl)
      <tr>
        <td class="l">{{ $tl['teller_code'] }} {{ $tellerNames[$tl['event_teller_id']] ?? '' }}</td>
        <td>{{ $peso($tl['opening_cash']) }}</td>
        <td>{{ $peso($tl['cash_in']) }}</td>
        <td>{{ $peso($tl['cash_out']) }}</td>
        <td>{{ $peso($tl['gross_bets']) }}</td>
        <td>{{ $peso($tl['voids']) }}</td>
        <td>{{ $peso($tl['net_bets']) }}</td>
        <td>{{ $peso($tl['payouts']) }}</td>
        <td>{{ $peso($tl['expected_cash']) }}</td>
        <td class="{{ $tl['system_balance'] !== $tl['expected_cash'] ? 'neg' : '' }}">{{ $peso($tl['system_balance']) }}</td>
        <td>{{ $peso($tl['counted_cash']) }}</td>
        <td class="{{ $tl['short_over'] !== null && (float) $tl['short_over'] != 0 ? 'neg' : '' }}">{{ $peso($tl['short_over']) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>

  <h2>E. Exceptions and checks</h2>
  <div class="grid">
    <div>
      <div class="row"><span>Voided bets</span><b>{{ $x['voids_count'] }} ({{ $peso($x['voids_amount']) }})</b></div>
      <div class="row"><span>Result corrections</span><b class="{{ $x['result_corrections'] ? 'neg' : '' }}">{{ $x['result_corrections'] }}</b></div>
      <div class="row"><span>Betting re-opened</span><b class="{{ $x['betting_reopens'] ? 'neg' : '' }}">{{ $x['betting_reopens'] }}</b></div>
      <div class="row"><span>Receipt reprints</span><b>{{ $x['reprints'] }}</b></div>
      <div class="row"><span>Receipts paid more than once</span><b class="{{ $x['duplicate_claims'] ? 'neg' : '' }}">{{ $x['duplicate_claims'] }}</b></div>
      <div class="row"><span>Tellers short/over</span><b>{{ $x['cash_short_over_tellers'] ? implode(', ', $x['cash_short_over_tellers']) : 'none' }}</b></div>
    </div>
    <div>
      @foreach ($report['checks'] as $c)
        <div class="row">
          <span>{{ str_replace('_', ' ', $c['name']) }}</span>
          <b class="{{ $c['passed'] ? 'pass' : 'fail' }}">{{ $c['passed'] ? 'PASS' : 'FLAG' }}</b>
        </div>
      @endforeach
    </div>
  </div>
  @if ($failedChecks)
    <ul>
      @foreach ($failedChecks as $c)
        <li class="fail">{{ str_replace('_', ' ', $c['name']) }}: {{ $c['detail'] }}</li>
      @endforeach
    </ul>
  @endif

  <h2>F. Seal</h2>
  @if ($sealed)
    <div class="seal">
      <div>
        <div>Seal code</div>
        <code>{{ $closing->seal_code }}</code>
      </div>
      <div>
        <div>Package S{{ sprintf('%04d', $closing->sequence_no) }} · server {{ $closing->server_id }} · key {{ $closing->key_fingerprint }}</div>
        <div class="hash">Payload SHA-256: {{ $closing->payload_sha256 }}</div>
        <div class="hash">Detail SHA-256: {{ $closing->detail_sha256 }} ({{ $report['detail']['file'] }})</div>
        <div class="hash">Previous seal SHA-256: {{ $closing->prev_payload_sha256 }}</div>
      </div>
    </div>
    <p class="hash">The BIR Compliance System shows the seal code it received. It must match the code above.</p>
  @else
    <p class="fail">Not sealed. This preview has no seal code and must not be filed.</p>
  @endif

  <div class="sign">
    <div>Prepared by</div>
    <div>Supervisor</div>
    <div>Treasury witness</div>
  </div>
</div>
</body>
</html>
