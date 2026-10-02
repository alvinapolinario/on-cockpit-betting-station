@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Event Audit')

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-2">
    <span class="text-muted fw-light">Records /</span> Event Audit
  </h4>
  <p class="text-muted">Closed events, read-only. Open one to browse its fights, bets, payouts, cash and tellers, and to re-verify that today's data still matches its seal. Nothing on these pages can change betting data.</p>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Date</th><th>Event</th><th>Seal</th><th>Seal code</th><th>Closed</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($events as $e)
            <tr>
              <td class="text-nowrap">{{ $e->event_date }}</td>
              <td>#{{ $e->event_id }} {{ $e->event_name }}</td>
              <td>
                @if ($e->event_closing_id)
                  <span class="badge bg-label-success">S{{ sprintf('%04d', $e->sequence_no) }}</span>
                @else
                  <span class="badge bg-label-warning" title="Closed before sealing was introduced, or outside the closing-report screen">not sealed</span>
                @endif
              </td>
              <td><code>{{ $e->seal_code ?? '—' }}</code></td>
              <td class="text-nowrap">{{ $e->closed_at ?? '—' }}@if ($e->closed_by) <span class="text-muted">by {{ $e->closed_by }}</span>@endif</td>
              <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('event-audit.show', $e->event_id) }}">Open audit</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No closed events yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
