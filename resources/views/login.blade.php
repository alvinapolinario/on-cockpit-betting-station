@php
$customizerHidden = 'customizer-hide';
$appName = config('variables.templateName');
$appMark = mb_strtoupper(mb_substr($appName, 0, 1));
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Login')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/css/login-screen.css') }}?v=3">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/jquery.validate/jquery.validate.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/jquery.validate/additional-methods.min.js') }}"></script>
@endsection

@section('page-script')
<script>
  $(document).ready(function () {
      const rememberedUsername = localStorage.getItem('rememberedUsername');
      if (rememberedUsername) {
          $('#username').val(rememberedUsername);
          $('#remember-me').prop('checked', true);
      }

      $('#togglePassword').on('click', function () {
          const input = $('#password');
          const hidden = input.attr('type') === 'password';
          input.attr('type', hidden ? 'text' : 'password');
          $(this).find('i').toggleClass('bx-hide', !hidden).toggleClass('bx-show', hidden);
      });

      $('#loginForm').validate({
          onfocusout: false,
          rules: {
              username: {
                  required: true,
                  nowhitespace: true,
              },
              password: {
                  required: true,
              },
          },
          submitHandler: function (form) {
              const button = $('#btnSignIn');
              const formData = new FormData(form);

              $.ajax({
                  type: "POST",
                  url: "/login",
                  data: formData,
                  cache: false,
                  contentType: false,
                  processData: false,
                  beforeSend: function () {
                      button.prop('disabled', true).text('Signing in…');
                  },
                  success: function (data) {
                      if (data > 0) {
                          if ($('#remember-me').is(':checked')) {
                              localStorage.setItem('rememberedUsername', $('#username').val());
                          } else {
                              localStorage.removeItem('rememberedUsername');
                          }
                          location.reload();
                      } else {
                          button.prop('disabled', false).text('Sign in');
                          toastr.error('Login failed. Check your username and password.');
                      }
                  },
                  error: function () {
                      button.prop('disabled', false).text('Sign in');
                      toastr.error('Something went wrong. Please try again.');
                  }
              });
          },
          messages: {},
      });
  });
</script>
@endsection

@section('content')
<div class="login-screen">
  <aside class="login-brand">
    <div class="login-brand-top">
      <span class="login-mark">{{ $appMark }}</span>
      <div class="login-brand-text">
        <p class="login-kicker">Admin console</p>
        <h1>{{ $appName }}</h1>
        <p class="login-brand-desc">Sign in to manage fights, tellers, claims, and live betting.</p>
      </div>
    </div>
    <ul class="login-points">
      <li><i class="bx bx-broadcast"></i> Live match control</li>
      <li><i class="bx bx-receipt"></i> Teller and claim tools</li>
      <li><i class="bx bx-qr-scan"></i> App setup from Settings</li>
    </ul>
    <div class="login-brand-foot">© {{ date('Y') }} {{ $appName }}</div>
  </aside>

  <main class="login-form-wrap">
    <div class="login-card">
      <h2>Sign in</h2>
      <p class="login-lead">Use your administrator account to continue.</p>

      <form id="loginForm" name="loginForm" autocomplete="on">
        @csrf
        <div class="login-field">
          <label for="username">Username</label>
          <div class="login-input">
            <i class="bx bx-user login-input-icon"></i>
            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" autocomplete="username" autofocus>
          </div>
        </div>
        <div class="login-field">
          <label for="password">Password</label>
          <div class="login-input">
            <i class="bx bx-lock-alt login-input-icon"></i>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autocomplete="current-password">
            <button type="button" class="login-toggle" id="togglePassword" aria-label="Show password">
              <i class="bx bx-hide"></i>
            </button>
          </div>
        </div>
        <div class="login-row">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember-me">
            <label class="form-check-label" for="remember-me">Remember username</label>
          </div>
        </div>
        <button class="btn login-submit d-grid w-100" id="btnSignIn" type="submit">Sign in</button>
      </form>
      <div class="login-card-foot">Authorized staff only</div>
    </div>
  </main>
</div>
@endsection
