@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$peso = fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2);
$t = $sealed['totals'] ?? null;
$verify = session('verify');
$labels = ['summary' => 'Summary', 'fights' => 'Fights', 'bets' => 'Bets', 'payouts' => 'Payouts', 'cash' => 'Cash in / out', 'tellers' => 'Tellers', 'logs' => 'Logs'];
$tabUrl = fn ($k) => route('event-audit.show', ['event_id' => $event->event_id, 'tab' => $k]);
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Event Audit #' . $event->event_id)

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-2">
    <span class="text-muted fw-light"><a href="{{ route('event-audit') }}">Event Audit</a> /</span> #{{ $event->event_id }} {{ $event->event_name }}
  </h4>

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
      <div>
        <div><strong>{{ $event->event_date }}</strong> · <span class="badge bg-label-secondary">Closed · read-only</span></div>
        @if ($closing)
          <div class="mt-1">Seal <strong>S{{ sprintf('%04d', $closing->sequence_no) }}</strong> · code <code class="fs-6">{{ $closing->seal_code }}</code> · sealed {{ $closing->closed_at }} by {{ $sealed['closed_by'] ?? '—' }}</div>
        @else
          <div class="mt-1 text-warning">Not sealed: this event can be browsed, but there is no seal to verify it against.</div>
        @endif
      </div>
      <div class="d-flex flex-wrap gap-2">
        @if ($closing)
          <a class="btn btn-primary" href="{{ route('event-audit.verify', $event->event_id) }}">Re-verify seal</a>
          <a class="btn btn-outline-primary" href="{{ route('closing-reports.show', $closing->event_closing_id) }}" target="_blank">Sealed closing report</a>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('teller-ledger', ['event_id' => $event->event_id]) }}">Teller ledger</a>
      </div>
    </div>
  </div>

  @if ($verify)
    <div class="alert {{ $verify['passed'] ? 'alert-success' : 'alert-danger' }}">
      <strong>{{ $verify['passed'] ? 'Verified: today\'s data matches the seal.' : 'Verification FAILED.' }}</strong>
      <span class="text-muted">({{ $verify['at'] }})</span>
      <ul class="mb-0 mt-2">
        @foreach ($verify['results'] as $res)
          <li><span class="{{ $res['passed'] ? 'text-success' : 'text-danger fw-semibold' }}">{{ $res['passed'] ? '✔' : '✘' }} {{ str_replace('_', ' ', $res['name']) }}</span>@if (!$res['passed']): {{ $res['detail'] }}@endif</li>
        @endforeach
      </ul>
    </div>
  @endif

  <ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $k)
      <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ $tabUrl($k) }}">{{ $labels[$k] }}</a></li>
    @endforeach
  </ul>

  @if (in_array($tab, ['bets', 'payouts'], true))
    <form method="GET" class="row g-2 mb-3">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="col-md-4"><input name="q" value="{{ $q }}" class="form-control" placeholder="{{ $tab === 'bets' ? 'Receipt code, fight number or teller' : 'Receipt code or teller' }}"></div>
      <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
      @if ($q !== '')<div class="col-auto"><a class="btn btn-link" href="{{ $tabUrl($tab) }}">Clear</a></div>@endif
    </form>
  @endif

  <div class="card">
    @switch($tab)

      @case('summary')
        <div class="card-body">
          @if ($t)
            <h6 class="text-muted text-uppercase">Sealed totals</h6>
            <div class="row g-3 mb-3">
              @foreach (['gross_bets' => 'Gross bets', 'voided_bets' => 'Voided', 'net_bets' => 'Net bets', 'commission' => 'Commission', 'breakage' => 'Rounding / adj.', 'house_take' => 'House take', 'refunds' => 'Refunds', 'winnings' => 'Winnings', 'paid' => 'Paid', 'unclaimed' => 'Unclaimed'] as $k => $label)
                <div class="col-6 col-md-3 col-xl-2"><div class="border rounded p-2"><div class="small text-muted">{{ $label }}</div><div class="fw-bold">{{ $peso($t[$k] ?? null) }}</div></div></div>
              @endforeach
            </div>
            <p class="mb-1">Fights: {{ $t['fights_total'] }} ({{ $t['fights_completed'] }} completed, {{ $t['fights_draw'] }} draw, {{ $t['fights_cancelled'] }} cancelled, {{ $t['fights_unused'] }} unused)</p>
            <h6 class="text-muted text-uppercase mt-3">Checks at sealing</h6>
            <ul class="mb-0">
              @foreach ($sealed['checks'] as $c)
                <li class="{{ $c['passed'] ? '' : 'text-danger' }}">{{ $c['passed'] ? '✔' : '✘' }} {{ str_replace('_', ' ', $c['name']) }}@if (!$c['passed']): {{ $c['detail'] }}@endif</li>
              @endforeach
            </ul>
          @endif
          <h6 class="text-muted text-uppercase mt-3">Records in the database today</h6>
          <p class="mb-0">{{ number_format($counts['fights']) }} fights · {{ number_format($counts['bets']) }} bets · {{ number_format($counts['payouts']) }} payouts · {{ number_format($counts['tellers']) }} tellers</p>
        </div>
        @break

      @case('fights')
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>#</th><th>Status</th><th>Winner</th><th class="text-end">Meron</th><th class="text-end">Wala</th><th class="text-end">Odds M / W</th><th class="text-end">Pool</th><th class="text-end">Commission</th><th class="text-end">Rounding</th><th class="text-end">Paid</th><th class="text-end">Unclaimed</th><th>Declared</th></tr></thead>
            <tbody>
              @foreach ($rows as $m)
                @php $s = $sealedFights->get((int) $m->match_number); @endphp
                <tr>
                  <td>{{ $m->match_number }}</td><td>{{ $m->match_status }}</td><td>{{ $m->match_winner ?: '—' }}</td>
                  <td class="text-end">{{ $peso($m->meron_total_bet) }}</td><td class="text-end">{{ $peso($m->wala_total_bet) }}</td>
                  <td class="text-end text-nowrap">{{ $m->meron_odds }} / {{ $m->wala_odds }}</td>
                  <td class="text-end">{{ $peso($s['net_pool'] ?? null) }}</td><td class="text-end">{{ $peso($s['commission'] ?? null) }}</td>
                  <td class="text-end">{{ $peso($s['breakage'] ?? null) }}</td><td class="text-end">{{ $peso($s['paid'] ?? null) }}</td><td class="text-end">{{ $peso($s['unclaimed'] ?? null) }}</td>
                  <td class="text-nowrap small">{{ $s['audit']['declared_at'] ?? '' }} {{ isset($s['audit']['declared_by']) ? '· ' . $s['audit']['declared_by'] : '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer small text-muted">Pool, commission, rounding, paid and unclaimed are the sealed figures; teams, totals and odds are from the database.</div>
        @break

      @case('bets')
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Receipt</th><th>Fight</th><th>Teller</th><th>Side</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Win amount</th><th>Placed</th><th>Paid by / at</th></tr></thead>
            <tbody>
              @forelse ($rows as $b)
                <tr>
                  <td><code>{{ $b->bet_receipt_code }}</code></td><td>{{ $b->match_number }}</td><td>{{ $b->teller_name }}</td><td>{{ $b->bet_side }}</td>
                  <td class="text-end">{{ $peso($b->bet_amount) }}</td>
                  <td>@if ((int) $b->bet_status === 2)<span class="badge bg-label-danger">void</span>@elseif ($b->bet_is_winner)<span class="badge bg-label-success">won</span>@else<span class="badge bg-label-secondary">valid</span>@endif</td>
                  <td class="text-end">{{ (float) $b->bet_win_amount ? $peso($b->bet_win_amount) : '—' }}</td>
                  <td class="text-nowrap small">{{ $b->bet_datetime }}</td>
                  <td class="text-nowrap small">{{ $b->bet_payout_datetime ? $b->bet_payout_datetime : '' }}</td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No bets found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-footer">{{ $rows->links('pagination::bootstrap-5') }}</div>
        @break

      @case('payouts')
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>#</th><th>Receipt</th><th>Fight</th><th>Side</th><th>Paid by teller</th><th class="text-end">Amount</th><th>Paid at</th></tr></thead>
            <tbody>
              @forelse ($rows as $c)
                <tr><td>{{ $c->claim_id }}</td><td><code>{{ $c->bet_receipt_code }}</code></td><td>{{ $c->match_number }}</td><td>{{ $c->bet_side }}</td><td>{{ $c->teller_name }}</td><td class="text-end">{{ $peso($c->claim_amount) }}</td><td class="text-nowrap small">{{ $c->claim_datetime }}</td></tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No payouts found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-footer">{{ $rows->links('pagination::bootstrap-5') }}</div>
        @break

      @case('cash')
        <div class="card-body pb-0"><h6 class="text-muted text-uppercase">Cash in</h6></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>#</th><th>Teller</th><th>Type</th><th class="text-end">Amount</th><th>Status</th><th>Admin</th><th>Requested</th></tr></thead>
            <tbody>
              @forelse ($cashIns as $c)
                <tr><td>{{ $c->cash_in_id }}</td><td>{{ $c->teller_name }}</td><td>{{ $c->cash_in_type }}</td><td class="text-end">{{ $peso($c->cash_in_amount) }}</td><td>{{ $c->cash_in_status }}</td><td>{{ $c->admin_name ?? '—' }}</td><td class="text-nowrap small">{{ $c->cash_in_datetime }}</td></tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-3">None.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-body pb-0"><h6 class="text-muted text-uppercase">Cash out</h6></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>#</th><th>Teller</th><th class="text-end">Amount</th><th>Status</th><th>Admin</th><th>Requested</th></tr></thead>
            <tbody>
              @forelse ($cashOuts as $c)
                <tr><td>{{ $c->cash_out_id }}</td><td>{{ $c->teller_name }}</td><td class="text-end">{{ $peso($c->cash_out_amount) }}</td><td>{{ $c->cash_out_status }}</td><td>{{ $c->admin_name ?? '—' }}</td><td class="text-nowrap small">{{ $c->cash_out_datetime }}</td></tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-3">None.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @break

      @case('tellers')
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Code</th><th>Teller</th><th class="text-end">Opening</th><th class="text-end">Cash in</th><th class="text-end">Cash out</th><th class="text-end">Net bets</th><th class="text-end">Payouts</th><th class="text-end">Expected cash</th><th class="text-end">Counted</th><th class="text-end">Short / over</th></tr></thead>
            <tbody>
              @forelse ($sealed['tellers'] ?? [] as $tl)
                <tr>
                  <td>{{ $tl['teller_code'] }}</td><td>{{ $tellerNames[$tl['event_teller_id']] ?? ('#' . $tl['event_teller_id']) }}</td>
                  <td class="text-end">{{ $peso($tl['opening_cash']) }}</td><td class="text-end">{{ $peso($tl['cash_in']) }}</td><td class="text-end">{{ $peso($tl['cash_out']) }}</td>
                  <td class="text-end">{{ $peso($tl['net_bets']) }}</td><td class="text-end">{{ $peso($tl['payouts']) }}</td><td class="text-end fw-semibold">{{ $peso($tl['expected_cash']) }}</td>
                  <td class="text-end">{{ $tl['counted_cash'] === null ? 'not counted' : $peso($tl['counted_cash']) }}</td>
                  <td class="text-end {{ $tl['short_over'] !== null && (float) $tl['short_over'] !== 0.0 ? 'text-danger fw-semibold' : '' }}">{{ $tl['short_over'] === null ? '—' : $peso($tl['short_over']) }}</td>
                </tr>
              @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No sealed teller figures (event not sealed).</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-footer small text-muted">Expected cash = opening + cash in + net bets − payouts − cash out (sealed). "Not counted" means no remittance count was recorded for that teller. {{ $remittances->count() }} remittance record(s) in the database.</div>
        @break

      @case('logs')
        @if ($rows)
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>#</th><th>When</th><th>Account</th><th>Source</th><th>Message</th></tr></thead>
              <tbody>
                @foreach ($rows as $l)
                  <tr><td>{{ $l->transaction_id }}</td><td class="text-nowrap small">{{ $l->transaction_datetime }}</td><td>{{ $l->account_id }}</td><td>{{ $l->transaction_type }}</td><td class="small">{{ $l->transaction_message }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="card-footer"><span class="small text-muted">Sealed log range #{{ $logRange[0] }}–#{{ $logRange[1] }}.</span> {{ $rows->links('pagination::bootstrap-5') }}</div>
        @else
          <div class="card-body text-muted">No sealed log range (event not sealed).</div>
        @endif
        @break

    @endswitch
  </div>
</div>
@endsection
