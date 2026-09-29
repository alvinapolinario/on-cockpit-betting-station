@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'System Reset')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('page-style')
<style>
.danger-zone {
    background-color: #fef2f2;
    border: 2px solid #fecaca;
    border-radius: 8px;
    padding: 24px;
    margin: 20px 0;
}

.warning-text {
    color: #dc2626;
    font-weight: 600;
}

.confirmation-input {
    border: 2px solid #dc2626;
    background-color: #fff;
}

.reset-btn {
    background-color: #dc2626;
    border-color: #dc2626;
    color: white;
    font-weight: 600;
    padding: 12px 24px;
}

.reset-btn:hover {
    background-color: #b91c1c;
    border-color: #b91c1c;
}

.reset-btn:disabled {
    background-color: #9ca3af;
    border-color: #9ca3af;
    cursor: not-allowed;
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">System /</span> Reset
</h4>

<!-- Alerts -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Success!</strong> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Error!</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">System Reset</h5>
      </div>
      <div class="card-body">
        <div class="danger-zone">
          <div class="d-flex align-items-center mb-3">
            <i class="bx bx-error-circle text-danger me-2" style="font-size: 24px;"></i>
            <h5 class="warning-text mb-0">DANGER ZONE</h5>
          </div>

          <div class="mb-4">
            <h6 class="warning-text">⚠️ This action will permanently delete all system data!</h6>
            <p class="text-muted mb-3">The following data will be permanently removed:</p>
            <ul class="text-muted">
              <li>All bets and betting history</li>
              <li>All cash in/out transactions</li>
              <li>All claims and unclaimed winnings</li>
              <li>All matches and events</li>
              <li>All teller balances (reset to 0)</li>
              <li>All personal access tokens</li>
              <li>All teller phone UIDs</li>
              <li>All transaction history</li>
              <li>All event teller assignments</li>
              <li>All teller shorts and salaries</li>
              <li>All remittance records</li>
            </ul>
            <p class="warning-text"><strong>This action cannot be undone!</strong></p>
          </div>

          <form id="resetForm" action="{{ route('reset.execute') }}" method="POST">
            @csrf
            <div class="mb-3">
              <label for="confirmation" class="form-label warning-text">
                <strong>To confirm this action, type "RESET" in the box below:</strong>
              </label>
              <input
                type="text"
                class="form-control confirmation-input @error('confirmation') is-invalid @enderror"
                id="confirmation"
                name="confirmation"
                placeholder="Type RESET to confirm"
                autocomplete="off"
                oninput="toggleResetButton()"
              >
              @error('confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <button
              type="button"
              class="btn btn-danger reset-btn"
              id="resetButton"
              onclick="showConfirmDialog()"
              disabled
            >
              <i class="bx bx-trash me-1"></i>
              RESET SYSTEM
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
function toggleResetButton() {
    const input = document.getElementById('confirmation');
    const button = document.getElementById('resetButton');

    if (input.value.trim() === 'RESET') {
        button.disabled = false;
    } else {
        button.disabled = true;
    }
}

function showConfirmDialog() {
    Swal.fire({
        title: 'Are you absolutely sure?',
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>This will permanently delete ALL system data:</strong></p>
                <ul class="text-muted">
                    <li>All bets and transactions</li>
                    <li>All matches and events</li>
                    <li>All teller data and balances</li>
                    <li>All remittances and salaries</li>
                    <li>All cash in/out records</li>
                    <li>All claims and winnings</li>
                </ul>
                <p class="text-danger mt-3"><strong>THIS CANNOT BE UNDONE!</strong></p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, RESET Everything!',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false,
        focusCancel: true
    }).then(function(result) {
        if (result.isConfirmed) {
            // Show loading state
            const button = document.getElementById('resetButton');
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Resetting...';
            button.disabled = true;

            // Submit the form
            document.getElementById('resetForm').submit();
        }
    });
}// Reset button state on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleResetButton();
});
</script>
@endsection
