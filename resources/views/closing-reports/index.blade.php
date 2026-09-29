@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Closing Reports')

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Records /</span> Closing Reports
  </h4>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if (isset($errors) && $errors->any())
    <div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>
  @endif

  @unless ($hasSigningKey)
    <div class="alert alert-warning">
      This server has no signing key, so events cannot be sealed yet.
      Run <code>php artisan seal:keygen</code> on the server and register the public key on the BIR Compliance System.
    </div>
  @endunless
  @unless ($hasVpsKey)
    <div class="alert alert-warning">
      The BIR Compliance System (VPS) public key is not configured, so packages cannot be downloaded.
      Set <code>SEAL_VPS_PUBLIC_KEY</code> (sandbox: <code>php artisan seal:vps-test-keygen</code>).
    </div>
  @endunless

  <div class="card">
    <div class="card-body">
      <p class="text-muted mb-3">
        Closing an event freezes it: no more bets, voids, payouts, result changes or cash movements.
        The report is hashed and signed, then downloaded as an encrypted package for upload to the BIR Compliance System.
      </p>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th>Date</th><th>Event</th><th>Status</th><th>Seal code</th><th>Package</th><th>Acknowledgment</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          @forelse ($events as $event)
            @php $closing = $closings->get($event->event_id); @endphp
            <tr>
              <td>{{ $event->event_date }}</td>
              <td>#{{ $event->event_id }} {{ $event->event_name }}</td>
              <td>
                @if ($closing)
                  <span class="badge bg-{{ $closing->status() === 'Acknowledged' ? 'success' : 'info' }}">{{ $closing->status() }}</span>
                @else
                  <span class="badge bg-secondary">{{ $event->event_status }} · not sealed</span>
                @endif
              </td>
              <td><code>{{ $closing->seal_code ?? '—' }}</code></td>
              <td>{{ $closing ? 'S' . sprintf('%04d', $closing->sequence_no) : '—' }}</td>
              <td>
                @if ($closing && $closing->ack_code)
                  <code>{{ $closing->ack_code }}</code><br><small class="text-muted">{{ $closing->ack_at }}</small>
                @elseif ($closing)
                  <form method="POST" action="{{ route('closing-reports.acknowledge', $closing->event_closing_id) }}" class="d-flex gap-1">
                    @csrf
                    <input name="ack_code" class="form-control form-control-sm" placeholder="Ack code from VPS" required maxlength="200">
                    <button class="btn btn-sm btn-outline-success">Save</button>
                  </form>
                @else
                  —
                @endif
              </td>
              <td class="text-end text-nowrap">
                @if ($closing)
                  <a class="btn btn-sm btn-primary" href="{{ route('closing-reports.show', $closing->event_closing_id) }}" target="_blank">Report</a>
                  <a class="btn btn-sm btn-outline-primary {{ $hasVpsKey ? '' : 'disabled' }}" href="{{ route('closing-reports.download', $closing->event_closing_id) }}">Download package</a>
                @else
                  <a class="btn btn-sm btn-outline-secondary" href="{{ route('closing-reports.preview', $event->event_id) }}" target="_blank">Preview</a>
                  <button class="btn btn-sm btn-danger" data-bs-toggle="collapse" data-bs-target="#seal-{{ $event->event_id }}" {{ $hasSigningKey ? '' : 'disabled' }}>Close &amp; seal</button>
                @endif
              </td>
            </tr>
            @unless ($closing)
            <tr class="collapse" id="seal-{{ $event->event_id }}">
              <td colspan="7" class="bg-light">
                <form method="POST" action="{{ route('closing-reports.seal', $event->event_id) }}" class="row g-2 align-items-end">
                  @csrf
                  <div class="col-md-12">
                    <strong>Close and seal event #{{ $event->event_id }} {{ $event->event_name }}.</strong>
                    This cannot be undone. Check the Preview first: every fight must be settled and no cash request may be pending.
                  </div>
                  @if ($requireApprover)
                    <div class="col-md-3">
                      <label class="form-label">Second approver (admin) username</label>
                      <input name="approver_username" class="form-control" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Approver password</label>
                      <input name="approver_password" type="password" class="form-control" required autocomplete="off">
                    </div>
                  @endif
                  <div class="col-md-3">
                    <label class="form-label">Type CLOSE to confirm</label>
                    <input name="confirmation" class="form-control" required autocomplete="off">
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-danger w-100">Close &amp; seal event</button>
                  </div>
                </form>
              </td>
            </tr>
            @endunless
          @empty
            <tr><td colspan="7" class="text-center text-muted">No events.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
