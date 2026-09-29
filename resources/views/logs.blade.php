@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Logs')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/jquery.validate.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/additional-methods.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    fetchLogs(); // Load event options on page load
  });

  function fetchLogs() {
  $.ajax({
    type: "POST",
    dataType: 'json',
    url: `/logs/list`,
    headers: {
      "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
    },
    beforeSend: function () {
      $("#logs-table tbody").html(`<tr><td colspan="4" class="text-center">Loading...</td></tr>`);
    },
    success: function (data) {
      let rows = "";
      if (data.logs && data.logs.length > 0) {
        data.logs.forEach(log => {
          let datetime = "";
          if (log.transaction_datetime) {
            datetime = new Date(log.transaction_datetime).toLocaleString("en-US", {
              year: "numeric",
              month: "short",
              day: "2-digit",
              hour: "2-digit",
              minute: "2-digit",
              hour12: true
            });
          }

          rows += `
            <tr>
              <td>${log.transaction_id}</td>
              <td>${log.transaction_type}</td>
              <td>${log.account_name || '-'}</td>
              <td>${formatMessage(log.transaction_message)}</td>
              <td>${datetime}</td>
            </tr>
          `;
        });
      } else {
        rows = `<tr><td colspan="4" class="text-center">No logs found.</td></tr>`;
      }

      $("#logs-table tbody").html(rows);
    },
    error: function () {
      toastr.error("Failed to load logs.");
      $("#logs-table tbody").html(`<tr><td colspan="4" class="text-center text-danger">Error loading logs.</td></tr>`);
    }
  });
}

function formatMessage(message) {
  if (!message) return "-";

  // Regular expression to find JSON objects
  const jsonRegex = /({.*?})(?=\s*{|$)/gs;

  let plainText = message;
  let jsonBlocks = [];
  let match;

  // Extract all JSON parts
  while ((match = jsonRegex.exec(message)) !== null) {
    try {
      const jsonObj = JSON.parse(match[1]);
      const prettyJson = JSON.stringify(jsonObj, null, 2);
      jsonBlocks.push(prettyJson);
    } catch (e) {
      // Skip invalid JSON chunks
    }
  }

  // Remove all JSON parts to get the plain text part
  plainText = message.replace(jsonRegex, "").trim();

  // Build HTML
  let html = `<div>${plainText}</div>`;
  jsonBlocks.forEach((block, index) => {
    html += `
      <details style="margin-top: 4px;">
        <summary>View JSON ${jsonBlocks.length > 1 ? `(${index + 1})` : ""}</summary>
        <pre style="white-space: pre-wrap; word-wrap: break-word;">${block}</pre>
      </details>
    `;
  });

  return html;
}


$("#search-bets").off().on("keyup", function () {
  let value = $(this).val().toLowerCase();
  $("#logs-table tbody tr").filter(function () {
    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
  });
});


</script>
@endsection

@section('content')

<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Menu /</span> Logs
  </h4>


  <hr>


  <div class="row mb-3">
    <div class="col-md-4">
      <input type="text" id="search-bets" class="form-control" placeholder="Search bets...">
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped" id="logs-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Transaction Type</th>
                  <th>Account Name</th>
                  <th>Message</th>
                  <th>Date & Time</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>

</div>

@endsection
