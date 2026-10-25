@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'App Settings')

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/qrcode/qrcode.min.js') }}"></script>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    renderSetupQr(@json($qrPayload));
    document.getElementById('api_url').addEventListener('input', previewQr);
    document.getElementById('app_name').addEventListener('input', previewQr);
  });

  function previewQr() {
    var url = document.getElementById('api_url').value.trim();
    var name = document.getElementById('app_name').value.trim() || 'OCBS';
    renderSetupQr('SBAPI1:' + JSON.stringify({ url: url, name: name }));
  }

  function renderSetupQr(payload) {
    var box = document.getElementById('setup_qr');
    box.innerHTML = '';
    if (!payload) {
      return;
    }
    new QRCode(box, {
      text: payload,
      width: 240,
      height: 240,
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  function printSetupQr() {
    var canvas = document.querySelector('#setup_qr canvas');
    var image = canvas ? canvas.toDataURL('image/png') : document.querySelector('#setup_qr img').src;
    var name = document.getElementById('app_name').value.trim() || 'OCBS';
    var url = document.getElementById('api_url').value.trim();
    var win = window.open('', '_blank', 'width=420,height=580');
    win.document.write(
      '<html><head><title>Teller App Setup QR</title></head>' +
      '<body style="font-family:sans-serif;text-align:center;padding:24px">' +
      '<h2 style="margin:0 0 4px">' + name.replace(/</g, '') + '</h2>' +
      '<div style="color:#666;margin-bottom:16px">' + url.replace(/</g, '') + '</div>' +
      '<img src="' + image + '" style="width:260px;height:260px">' +
      '<p style="font-size:12px;color:#666">Scan with the teller app Server button<br>to set the API URL and app name.</p>' +
      '</body></html>'
    );
    win.document.close();
    win.onload = function () { win.focus(); win.print(); };
  }
</script>
@endsection

@section('content')
<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">System /</span> App Settings
  </h4>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (isset($errors) && $errors->any())
    <div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>
  @endif

  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Teller app connection</h5>
        </div>
        <div class="card-body">
          <p class="text-muted">These values are encoded into the QR. Tellers scan it from the Server button on the login screen.</p>
          <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="api_url">API URL</label>
              <input type="text" class="form-control" id="api_url" name="api_url" value="{{ $settings['api_url'] }}" placeholder="10.250.106.199:8088" required>
              <div class="form-text">Host and port only, no http://. Example: 10.250.106.199:8088</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="app_name">App name</label>
              <input type="text" class="form-control" id="app_name" name="app_name" value="{{ $settings['app_name'] }}" maxlength="40" required>
              <div class="form-text">Used in the web admin (menu, tab title, footer) and on the teller app title bar after scanning.</div>
            </div>
            <button type="submit" class="btn btn-primary">Save and generate QR</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Setup QR</h5>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printSetupQr()">Print</button>
        </div>
        <div class="card-body text-center">
          <div id="setup_qr" class="d-inline-block"></div>
          <div class="mt-3">
            <div class="fw-bold">{{ $settings['app_name'] }}</div>
            <div class="text-muted">{{ $settings['api_url'] }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
