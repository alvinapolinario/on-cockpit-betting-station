@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$peso = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Teller Ledger · All tellers')

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-2">
    <span class="text-muted fw-light">Cash / Teller Ledger /</span> All tellers
  </h4>

  <div class="card mb-3"><div class="card-body">
    <form method="GET" action="{{ route('teller-ledger.all') }}" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Event</label>
        <select name="event_id" class="form-select">
          @foreach ($events as $e)
            <option value="{{ $e->event_id }}" @selected($e->event_id == $eventId)>{{ $e->event_date }} · #{{ $e->event_id }} {{ $e->event_name }} ({{ $e->event_status }})</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6"><button class="btn btn-primary">Show</button></div>
    </form>
  </div></div>

  <div class="card"><div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead>
          <tr>
            <th>Teller</th><th class="text-end">Lines</th><th class="text-end">Opening</th><th class="text-end">Bets</th><th class="text-end">Voids</th>
            <th class="text-end">Payouts</th><th class="text-end">Cash-in</th><th class="text-end">Cash-out</th>
            <th class="text-end">Cash on hand (ledger)</th><th class="text-end">Recorded balance</th><th>Check</th><th></th>
          </tr>
        </thead>
        <tbody>
        @forelse ($rows as $r)
          @php $t = $r->totals; $pay = (float) ($t['payout_win']['out'] ?? 0) + (float) ($t['payout_refund']['out'] ?? 0); @endphp
          <tr>
            <td>{{ $r->teller->teller_name }}</td>
            <td class="text-end">{{ $r->lines }}</td>
            <td class="text-end">{{ $peso($t['opening']['in'] ?? 0) }}</td>
            <td class="text-end">{{ $peso($t['bet']['in'] ?? 0) }}</td>
            <td class="text-end">{{ $peso($t['void']['out'] ?? 0) }}</td>
            <td class="text-end">{{ $peso($pay) }}</td>
            <td class="text-end">{{ $peso($t['cash_in']['in'] ?? 0) }}</td>
            <td class="text-end">{{ $peso($t['cash_out']['out'] ?? 0) }}</td>
            <td class="text-end fw-semibold">{{ $peso($r->ledger_balance) }}</td>
            <td class="text-end">{{ $peso($r->current_balance) }}</td>
            <td>
              @if (!$r->lines)<span class="badge bg-secondary">no lines</span>
              @elseif ($r->ok)<span class="badge bg-success">OK</span>
              @else<span class="badge bg-danger">MISMATCH</span>@endif
              @if ($r->started_mid_event)<span class="badge bg-warning">started mid-event</span>@endif
            </td>
            <td class="text-nowrap">
              <a href="{{ route('teller-ledger', ['event_id' => $eventId, 'event_teller_id' => $r->teller->event_teller_id]) }}">Ledger</a> ·
              <a href="{{ route('teller-ledger.print', $r->teller->event_teller_id) }}" target="_blank">Print</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="12" class="text-center text-muted">No tellers in this event.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div></div>
</div>
@endsection
