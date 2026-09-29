@php
  $peso = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
  $lines = $v['lines'];
  $problems = $v['problems'];
  $ok = empty($problems) && $v['ends_at_current_balance'];
  $shortTotal = $shorts->sum(fn ($s) => (float) $s->short_amount);
  $diff = $counted === null ? null : $counted - (float) $expected;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teller Statement · {{ $teller->teller_name }} · {{ $teller->event_date }}</title>
<style>
  :root { --ink:#111; --muted:#555; --line:#bbb; --bad:#b00020; --ok:#1b5e20; }
  * { box-sizing: border-box; }
  body { font: 11px/1.35 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: var(--ink); background: #fff; margin: 0; padding: 16px; }
  .sheet { max-width: 1100px; margin: 0 auto; }
  h1 { font-size: 18px; margin: 0; }
  h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 2px solid var(--ink); text-transform: uppercase; letter-spacing: .04em; }
  .meta { display: flex; flex-wrap: wrap; gap: 4px 18px; color: var(--muted); margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid var(--line); padding: 2px 5px; text-align: left; vertical-align: top; }
  th { background: #f2f2f2; font-weight: 600; }
  td.n, th.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 4px 24px; }
  .row { display: flex; justify-content: space-between; border-bottom: 1px dotted var(--line); padding: 2px 0; }
  .row b { font-variant-numeric: tabular-nums; }
  .big { font-size: 14px; }
  .bad { color: var(--bad); font-weight: 600; }
  .good { color: var(--ok); font-weight: 600; }
  .muted { color: var(--muted); }
  .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; margin-top: 44px; }
  .sign div { border-top: 1px solid var(--ink); padding-top: 4px; text-align: center; }
  .sign small { display: block; color: var(--muted); }
  .toolbar { margin-bottom: 12px; display: flex; gap: 10px; align-items: center; }
  .toolbar button { padding: 6px 14px; }
  @media print {
    body { padding: 0; font-size: 9px; }
    .toolbar { display: none; }
    tr { break-inside: avoid; }
    .keep { break-inside: avoid; }
    @page { size: A4 portrait; margin: 10mm; }
  }
</style>
</head>
<body>
<div class="sheet">
  <div class="toolbar">
    <button onclick="window.print()">Print</button>
    <span class="muted">Tip: choose “Save as PDF” in the print dialog to keep a PDF copy.</span>
  </div>

  <h1>{{ config('variables.templateName') }} — Teller Statement</h1>
  <div class="meta">
    <span>Teller: <strong>{{ $teller->teller_name }}</strong></span>
    <span>Event #{{ $teller->event_id }} “{{ $teller->event_name }}”</span>
    <span>Date: {{ $teller->event_date }}</span>
    <span>Status: {{ $teller->event_status }}</span>
    <span>Ledger lines: {{ $lines->count() }}</span>
  </div>

  <h2>Summary</h2>
  <div class="grid keep">
    <div>
      @foreach ([['Opening cash', 'opening', 'in'], ['Bets taken', 'bet', 'in'], ['Cash-in received', 'cash_in', 'in']] as [$label, $k, $side])
        <div class="row"><span>{{ $label }} ({{ $totals[$k]['count'] ?? 0 }})</span><b>+ {{ $peso($totals[$k][$side] ?? 0) }}</b></div>
      @endforeach
      @if (isset($totals['adjustment']))
        <div class="row"><span>Adjustments ({{ $totals['adjustment']['count'] }})</span><b>+ {{ $peso($totals['adjustment']['in']) }} / − {{ $peso($totals['adjustment']['out']) }}</b></div>
      @endif
    </div>
    <div>
      @foreach ([['Voided bets returned', 'void', 'out'], ['Payouts – winnings', 'payout_win', 'out'], ['Payouts – refunds', 'payout_refund', 'out'], ['Cash-out handed over', 'cash_out', 'out']] as [$label, $k, $side])
        <div class="row"><span>{{ $label }} ({{ $totals[$k]['count'] ?? 0 }})</span><b>− {{ $peso($totals[$k][$side] ?? 0) }}</b></div>
      @endforeach
    </div>
  </div>

  <h2>Transaction log</h2>
  <table>
    <thead>
      <tr><th>#</th><th>Date / time</th><th>Type</th><th>Fight</th><th>Reference</th><th class="n">In</th><th class="n">Out</th><th class="n">Cash after</th><th>Note</th></tr>
    </thead>
    <tbody>
    @foreach ($lines as $l)
      <tr class="{{ isset($problems[$l->line_no]) ? 'bad' : '' }}">
        <td>{{ $l->line_no }}</td>
        <td>{{ $l->entry_at }}</td>
        <td>{{ $types[$l->type] ?? $l->type }}</td>
        <td>{{ $l->fight_no ?? '' }}</td>
        <td>{{ $l->reference }}</td>
        <td class="n">{{ (float) $l->amount_in ? $peso($l->amount_in) : '' }}</td>
        <td class="n">{{ (float) $l->amount_out ? $peso($l->amount_out) : '' }}</td>
        <td class="n">{{ $peso($l->balance_after) }}</td>
        <td>{{ $l->note }}@if ($l->related_event_teller_id && $l->related_event_teller_id != $l->event_teller_id) (receipt of {{ $names[$l->related_event_teller_id] ?? 'teller #' . $l->related_event_teller_id }})@endif</td>
      </tr>
    @endforeach
    @if ($lines->isEmpty())
      <tr><td colspan="9" class="muted">No ledger lines recorded for this teller.</td></tr>
    @endif
    </tbody>
  </table>

  <h2>Closing</h2>
  <div class="grid keep">
    <div>
      <div class="row big"><span><strong>Expected cash on hand</strong></span><b>{{ $peso($expected) }}</b></div>
      <div class="row"><span>Recorded balance in the system</span><b>{{ $peso($v['current_balance']) }}</b></div>
      <div class="row"><span>Ledger check</span><b class="{{ $ok ? 'good' : 'bad' }}">{{ $ok ? 'PASSED' : 'FAILED' }}</b></div>
      @if (!$ok)
        <div class="bad">
          @if (!$v['ends_at_current_balance']) The last ledger balance differs from the recorded balance. @endif
          @if (!empty($problems)) Problem lines: {{ implode(', ', array_keys($problems)) }}. @endif
        </div>
      @endif
      @if ($v['started_mid_event'])<div class="muted">Ledger started mid-event (no opening line).</div>@endif
    </div>
    <div>
      @if ($counted === null)
        <div class="row"><span>Cash counted (remittance)</span><b>not yet counted</b></div>
        <div class="muted">Count the cash by denomination under Remittances, then print again.</div>
      @else
        @foreach ($denoms as $d => $n)
          @if ($n)<div class="row"><span>₱{{ number_format($d) }} × {{ $n }}</span><b>{{ $peso($d * $n) }}</b></div>@endif
        @endforeach
        <div class="row big"><span><strong>Cash counted</strong></span><b>{{ $peso($counted) }}</b></div>
        <div class="row big"><span><strong>{{ $diff < 0 ? 'SHORT' : ($diff > 0 ? 'OVER' : 'Short / over') }}</strong></span><b class="{{ $diff == 0 ? 'good' : 'bad' }}">{{ $peso($diff) }}</b></div>
        @if ($shortTotal)<div class="row"><span>Recorded shortage notes</span><b>{{ $peso($shortTotal) }}</b></div>@endif
      @endif
    </div>
  </div>

  <p class="muted" style="margin-top:14px">I confirm that this statement is a true record of the cash I handled during this event, and that the cash counted above was handed over.</p>
  <div class="sign keep">
    <div><strong>{{ $teller->teller_name }}</strong><small>Teller — signature over printed name</small></div>
    <div>&nbsp;<small>Supervisor</small></div>
    <div>&nbsp;<small>Admin</small></div>
  </div>
  <p class="muted" style="margin-top:14px">Printed {{ now()->format('Y-m-d H:i') }} by {{ $printedBy ?? 'admin' }}.</p>
</div>
</body>
</html>
