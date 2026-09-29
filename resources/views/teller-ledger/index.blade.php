@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$peso = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Teller Ledger')

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-2">
    <span class="text-muted fw-light">Cash /</span> Teller Ledger
  </h4>
  <p class="text-muted">Every cash movement of a teller, in order, with the cash on hand after each one. Lines are permanent: corrections are added as adjustment lines.</p>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('teller-ledger') }}" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Event</label>
          <select name="event_id" class="form-select">
            @foreach ($events as $e)
              <option value="{{ $e->event_id }}" @selected($e->event_id == $eventId)>{{ $e->event_date }} · #{{ $e->event_id }} {{ $e->event_name }} ({{ $e->event_status }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Teller</label>
          <select name="event_teller_id" class="form-select">
            <option value="">Choose a teller…</option>
            @foreach ($tellers as $t)
              <option value="{{ $t->event_teller_id }}" @selected($t->event_teller_id == $etId)>{{ $t->teller_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5 d-flex gap-2">
          <button class="btn btn-primary">Show ledger</button>
          <a class="btn btn-outline-primary" href="{{ route('teller-ledger.all', ['event_id' => $eventId]) }}">All tellers</a>
          @if ($data)
            <a class="btn btn-outline-secondary" href="{{ route('teller-ledger.print', $etId) }}" target="_blank">Print statement</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  @if (!$data)
    <div class="alert alert-info">Choose an event and a teller.</div>
  @else
    @php $t = $data['totals']; $last = $data['lines']->last(); @endphp

    @if (!empty($data['problems']) || !$data['ends_at_current_balance'])
      <div class="alert alert-danger">
        <strong>Ledger check failed.</strong>
        @if (!$data['ends_at_current_balance'])
          The last ledger balance ({{ $peso($last->balance_after ?? null) }}) differs from the teller's recorded balance ({{ $peso($data['current_balance']) }}).
        @endif
        @if (!empty($data['problems'])) Problem lines: {{ implode(', ', array_keys($data['problems'])) }}. @endif
      </div>
    @elseif ($data['lines']->isNotEmpty())
      <div class="alert alert-success mb-3">Ledger check passed: every line adds up and the last balance equals the teller's recorded balance.</div>
    @endif
    @if ($data['started_mid_event'])
      <div class="alert alert-warning">This ledger started after the event began (it was switched on mid-event), so it has no opening-cash line. Its first line carries the balance at that moment.</div>
    @endif

    <div class="row g-3 mb-3">
      @foreach ([['Opening cash', 'opening', 'in'], ['Bets', 'bet', 'in'], ['Voids', 'void', 'out'], ['Payouts – win', 'payout_win', 'out'], ['Payouts – refund', 'payout_refund', 'out'], ['Cash-in', 'cash_in', 'in'], ['Cash-out', 'cash_out', 'out']] as [$label, $k, $side])
        <div class="col-6 col-md-3 col-xl">
          <div class="card h-100"><div class="card-body py-2">
            <div class="text-muted small">{{ $label }} ({{ $t[$k]['count'] ?? 0 }})</div>
            <div class="fs-5 fw-semibold">{{ $side === 'out' ? '−' : '+' }}{{ $peso($t[$k][$side] ?? 0) }}</div>
          </div></div>
        </div>
      @endforeach
      <div class="col-12 col-md-3 col-xl">
        <div class="card h-100 border-primary"><div class="card-body py-2">
          <div class="text-muted small">Cash on hand now</div>
          <div class="fs-4 fw-bold text-primary">{{ $peso($last->balance_after ?? $data['current_balance']) }}</div>
        </div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('teller-ledger') }}" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="event_id" value="{{ $eventId }}">
          <input type="hidden" name="event_teller_id" value="{{ $etId }}">
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option value="">All types</option>
              @foreach ($types as $k => $label)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Fight #</label>
            <input name="fight" type="number" min="1" class="form-control" value="{{ $filters['fight'] ?? '' }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Receipt / reference</label>
            <input name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="60">
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('teller-ledger', ['event_id' => $eventId, 'event_teller_id' => $etId]) }}">Clear</a>
            <span class="align-self-center text-muted small">{{ $data['filtered']->count() }} of {{ $data['lines']->count() }} lines</span>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead>
              <tr>
                <th>#</th><th>Date / time</th><th>Type</th><th>Fight #</th><th>Reference</th>
                <th class="text-end">In</th><th class="text-end">Out</th>
                <th class="text-end">Cash before</th><th class="text-end">Cash after</th><th>By</th><th>Note</th>
              </tr>
            </thead>
            <tbody>
            @forelse ($data['filtered'] as $l)
              @php $bad = $data['problems'][$l->line_no] ?? null; @endphp
              <tr class="{{ $bad ? 'table-danger' : ($l->type === 'cash_declined' ? 'text-muted' : '') }}">
                <td>{{ $l->line_no }}</td>
                <td class="text-nowrap">{{ $l->entry_at }}</td>
                <td class="text-nowrap">{{ $types[$l->type] ?? $l->type }}</td>
                <td>{{ $l->fight_no ?? '—' }}</td>
                <td class="text-nowrap">{{ $l->reference ?? '—' }}</td>
                <td class="text-end text-success">{{ (float) $l->amount_in ? $peso($l->amount_in) : '' }}</td>
                <td class="text-end text-danger">{{ (float) $l->amount_out ? $peso($l->amount_out) : '' }}</td>
                <td class="text-end">{{ $peso($l->balance_before) }}</td>
                <td class="text-end fw-semibold">{{ $peso($l->balance_after) }}</td>
                <td class="text-nowrap">{{ $accounts[$l->recorded_by_account_id] ?? '—' }}@if ($l->approved_by_account_id && $l->approved_by_account_id != $l->recorded_by_account_id)<br><small class="text-muted">approved {{ $accounts[$l->approved_by_account_id] ?? '' }}</small>@endif</td>
                <td>
                  {{ $l->note }}
                  @if ($l->related_event_teller_id && $l->related_event_teller_id != $l->event_teller_id)
                    <br><small class="text-warning">receipt issued by {{ $names[$l->related_event_teller_id] ?? '#' . $l->related_event_teller_id }}</small>
                  @endif
                  @if ($bad)<br><small class="text-danger">{{ implode('; ', $bad) }}</small>@endif
                </td>
              </tr>
            @empty
              <tr><td colspan="11" class="text-center text-muted">No ledger lines{{ $data['lines']->isNotEmpty() ? ' match the filter' : ' yet' }}.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
</div>
@endsection
