@php
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Login')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/jquery.validate/jquery.validate.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/jquery.validate/additional-methods.min.js') }}"></script>
@endsection

@section('page-script')
<script>
  $(document).ready(function () {
      // Populate the username field if saved in localStorage
      const rememberedUsername = localStorage.getItem('rememberedUsername');
      if (rememberedUsername) {
          $('#username').val(rememberedUsername);
          $('#remember-me').prop('checked', true);
      }

      // Validate and handle form submission
      $('#loginForm').validate({
          onfocusout: false,
          rules: {
              username: {
                  required: true,
                  nowhitespace: true,
              },
              password: {
                  required: true,
                  nowhitespace: true,
              },
          },
          submitHandler: function (form) {
              const formData = new FormData(form);

              $.ajax({
                  type: "POST",
                  url: "/login",
                  async: false,
                  data: formData,
                  cache: false,
                  contentType: false,
                  processData: false,
                  beforeSend: function () {
                      toastr.info('Logging in! Please wait!');
                  },
                  success: function (data) {
                      if (data > 0) {
                          // Save username if "Remember Me" is checked
                          if ($('#remember-me').is(':checked')) {
                              localStorage.setItem('rememberedUsername', $('#username').val());
                          } else {
                              localStorage.removeItem('rememberedUsername');
                          }
                          location.reload();
                      } else {
                          toastr.error('Login Failed!');
                      }
                  },
                  error: function () {
                      toastr.error('Something went wrong!');
                  }
              });
          },
          messages: {},
      });
  });
  </script>

@endsection

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner py-4">

      <!-- Register -->
      <div class="card">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center">
            <a href="{{url('/')}}" class="app-brand-link gap-2">
              <img src="{{ asset('assets/img/logo-full.png') }}" height="100px">
              <br>
              {{-- <span class="app-brand-text demo h3 mb-0 fw-bold">{{config('variables.templateName')}}</span> --}}
            </a>
          </div>
          <!-- /Logo -->
          <div class="text-center">
            <h4 class="mb-2">Welcome to {{config('variables.templateName')}}! 👋</h4>
            <p class="mb-4">Please sign-in to your account</p>
          </div>

          <form id="loginForm" name="loginForm" class="mb-3">
            @csrf
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autofocus>
            </div>
            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-me">
                <label class="form-check-label" for="remember-me">
                  Remember Me
                </label>
              </div>
            </div>
            <div class="mb-3">
              <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
            </div>
          </form>



        </div>
      </div>
      <!-- /Register -->
    </div>
  </div>
</div>
@endsection
