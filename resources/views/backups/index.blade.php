@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Backups')

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">System /</span> Backups
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

  @unless ($hasBackupKey && $hasSigningKey)
    <div class="alert alert-warning">
      Backups are disabled until this server has
      @unless ($hasBackupKey) a backup key (<code>php artisan backup:keygen</code>) @endunless
      @unless ($hasBackupKey || $hasSigningKey) and @endunless
      @unless ($hasSigningKey) a signing key (<code>php artisan seal:keygen</code>) @endunless.
      Keep a sealed offline copy of both keys: without the backup key, backups cannot be restored.
    </div>
  @endunless

  <div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="text-muted">
        Full encrypted database backups. They stay on this server and are never uploaded; the BIR Compliance System receives closing packages instead.
        Only backups created and signed by this server can be restored.
      </div>
      <form method="POST" action="{{ route('backups.store') }}">
        @csrf
        <button class="btn btn-primary" {{ $hasBackupKey && $hasSigningKey ? '' : 'disabled' }}>Create backup now</button>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr><th>Created</th><th>Type</th><th>Event</th><th>Rows</th><th>Size</th><th>Seals</th><th>Integrity</th><th class="text-end">Actions</th></tr>
          </thead>
          <tbody>
          @forelse ($backups as $i => $b)
            <tr>
              <td>{{ $b['created_at'] ?? '—' }}<br><small class="text-muted text-break">{{ $b['file'] ?? '' }}</small></td>
              <td><span class="badge bg-{{ ($b['type'] ?? '') === 'prerestore' ? 'warning' : 'secondary' }}">{{ $b['type'] ?? '—' }}</span></td>
              <td>{{ $b['active_event'] ? '#' . $b['active_event']['event_id'] . ' ' . $b['active_event']['event_name'] : '—' }}</td>
              <td>{{ isset($b['row_counts']) ? number_format(array_sum($b['row_counts'])) : '—' }}</td>
              <td>{{ isset($b['size']) ? number_format($b['size'] / 1024, 0) . ' KB' : '—' }}</td>
              <td>{{ isset($b['seals']) ? count((array) $b['seals']) : '—' }}</td>
              <td>
                @if ($b['_valid'])
                  <span class="badge bg-success">Signed · hash OK</span>
                @else
                  <span class="badge bg-danger">INVALID</span><br><small class="text-danger">{{ $b['_error'] }}</small>
                @endif
              </td>
              <td class="text-end text-nowrap">
                @if ($b['_valid'])
                  <form method="POST" action="{{ route('backups.verify', $b['file']) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary">Verify</button>
                  </form>
                  <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#restore-{{ $i }}">Restore…</button>
                @endif
              </td>
            </tr>
            @if ($b['_valid'])
            <tr class="collapse" id="restore-{{ $i }}">
              <td colspan="8" class="bg-light">
                <form method="POST" action="{{ route('backups.restore', $b['file']) }}" class="row g-2 align-items-end">
                  @csrf
                  <div class="col-12">
                    <strong class="text-danger">Restore the database to {{ $b['created_at'] }}.</strong>
                    Everything recorded after this time will be replaced. The system goes into maintenance mode during the restore,
                    and the current database is automatically backed up first so the restore can be undone.
                  </div>
                  @if ($activeEvent)
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="betting_stopped" value="1" id="bs-{{ $i }}" required>
                        <label class="form-check-label text-danger" for="bs-{{ $i }}">
                          Event #{{ $activeEvent->event_id }} {{ $activeEvent->event_name }} is active. I confirm betting and payouts have stopped.
                        </label>
                      </div>
                    </div>
                  @endif
                  <div class="col-md-12">
                    <label class="form-label">Reason (recorded permanently)</label>
                    <input name="reason" class="form-control" required minlength="10" maxlength="500" placeholder="e.g. server disk failure during event; restoring last good backup">
                  </div>
                  @if ($requireApprover)
                    <div class="col-md-3">
                      <label class="form-label">Second approver (admin)</label>
                      <input name="approver_username" class="form-control" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Approver password</label>
                      <input name="approver_password" type="password" class="form-control" required autocomplete="off">
                    </div>
                  @endif
                  <div class="col-md-3">
                    <label class="form-label">Type RESTORE to confirm</label>
                    <input name="confirmation" class="form-control" required autocomplete="off">
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-danger w-100">Restore database</button>
                  </div>
                </form>
              </td>
            </tr>
            @endif
          @empty
            <tr><td colspan="8" class="text-center text-muted">No backups yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @if ($rawImportAllowed)
  <div class="card mb-4 border border-danger">
    <div class="card-header bg-label-danger">
      <strong>Import a raw MySQL dump — TESTING ONLY</strong>
      <small class="d-block">Disabled in production. These files are not signed by this server, so their origin cannot be verified.</small>
    </div>
    <div class="card-body">
      <p class="mb-2">
        Copy the <code>.sql</code> or <code>.sql.gz</code> file into the import folder, then reload this page:<br>
        <code>storage/app/backups/import/</code>
        <small class="text-muted">(on this Mac: <code>~/Downloads/sabonglara-main/sabonglara/storage/app/backups/import/</code>)</small>
      </p>
      <p class="text-muted mb-3">
        <strong>Check</strong> reads the whole file and rejects anything a normal dump of this database would not contain (other databases, users, grants, file access).
        <strong>Import</strong> replaces the entire database, then runs migrations to bring an older dump up to date. The current database is backed up first and restored automatically if anything fails.
      </p>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead><tr><th>File</th><th>Size</th><th>Copied</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          @forelse ($importFiles as $i => $f)
            <tr>
              <td class="text-break">{{ $f['file'] }}
                @unless ($f['valid_name'])<br><small class="text-danger">Rename it: letters, numbers, dot, dash, underscore only.</small>@endunless
              </td>
              <td>{{ number_format($f['size'] / 1024, 0) }} KB</td>
              <td>{{ $f['modified'] }}</td>
              <td class="text-end text-nowrap">
                @if ($f['valid_name'])
                  <form method="POST" action="{{ route('backups.import.check', $f['file']) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary">Check</button>
                  </form>
                  <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#import-{{ $i }}">Import…</button>
                @endif
              </td>
            </tr>
            @if ($f['valid_name'])
            <tr class="collapse" id="import-{{ $i }}">
              <td colspan="4" class="bg-light">
                <form method="POST" action="{{ route('backups.import.restore', $f['file']) }}" class="row g-2 align-items-end">
                  @csrf
                  <div class="col-12">
                    <strong class="text-danger">Replace the entire database with {{ $f['file'] }}.</strong>
                    Everything currently in the database will be replaced (a backup is taken first).
                  </div>
                  @if ($activeEvent)
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="betting_stopped" value="1" id="ibs-{{ $i }}" required>
                        <label class="form-check-label text-danger" for="ibs-{{ $i }}">
                          Event #{{ $activeEvent->event_id }} {{ $activeEvent->event_name }} is active. I confirm betting and payouts have stopped.
                        </label>
                      </div>
                    </div>
                  @endif
                  <div class="col-md-12">
                    <label class="form-label">Reason (recorded permanently)</label>
                    <input name="reason" class="form-control" required minlength="10" maxlength="500" placeholder="e.g. testing with the production dump from 2025-06-01">
                  </div>
                  @if ($requireApprover)
                    <div class="col-md-3">
                      <label class="form-label">Second approver (admin)</label>
                      <input name="approver_username" class="form-control" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Approver password</label>
                      <input name="approver_password" type="password" class="form-control" required autocomplete="off">
                    </div>
                  @endif
                  <div class="col-md-3">
                    <label class="form-label">Type RESTORE to confirm</label>
                    <input name="confirmation" class="form-control" required autocomplete="off">
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-danger w-100">Import and replace database</button>
                  </div>
                </form>
              </td>
            </tr>
            @endif
          @empty
            <tr><td colspan="4" class="text-center text-muted">No files in the import folder.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  <div class="card">
    <div class="card-header"><strong>Restore journal</strong> <small class="text-muted">kept outside the database, so it survives restores</small></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead><tr><th>When</th><th>Backup</th><th>By / approved</th><th>Reason</th><th>Result</th></tr></thead>
          <tbody>
          @forelse ($journal as $j)
            <tr>
              <td>{{ $j['at'] ?? '' }}</td>
              <td class="text-break">{{ $j['file'] ?? '' }}</td>
              <td>#{{ $j['account_id'] ?? '?' }} / {{ isset($j['approved_by']) ? '#' . $j['approved_by'] : '—' }} ({{ $j['source'] ?? '' }})</td>
              <td>{{ $j['reason'] ?? '' }}</td>
              <td class="{{ ($j['result'] ?? '') === 'restored' ? 'text-success' : 'text-danger' }}">
                {{ $j['result'] ?? '' }}{{ isset($j['error']) ? ': ' . $j['error'] : '' }}
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">No restores yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
