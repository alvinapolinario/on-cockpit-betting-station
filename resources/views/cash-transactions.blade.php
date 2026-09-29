@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Cash Transactions')

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

@section('page-style')
<style>
  .hidden {
    display: none !important;
  }

  .layout-page .container-p-y:has(.cash-app) {
    padding-top: 1rem;
  }

  .cash-app {
    --navy: #0B1730;
    --navy-mid: #142445;
    --navy-end: #1A2F55;
    --meter: #8BA3C7;
    --sheet: #F4F7FB;
    --surface: #FFFFFF;
    --stroke: #E2E8F2;
    --text: #12203A;
    --muted: #6B7C99;
    --accent: #2F7BFF;
    --open: #16A34A;
    border-radius: 24px;
    overflow: hidden;
    background: var(--sheet);
    box-shadow: 0 18px 40px rgba(11, 23, 48, 0.12);
    color: var(--text);
  }

  .cash-board {
    padding: 20px 22px 18px;
    background: linear-gradient(180deg, var(--navy) 0%, var(--navy-mid) 58%, var(--navy-end) 100%);
    color: #fff;
  }

  .cash-kicker {
    margin: 0;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-size: 13px;
    font-weight: 700;
  }

  .cash-board h2 {
    margin: 2px 0 4px;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
  }

  .cash-board p {
    margin: 0 0 14px;
    color: var(--meter);
    font-size: 13px;
  }

  .cash-board label {
    color: #9EC8FF;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .cash-board .form-select,
  .cash-board .select2-container .select2-selection--single {
    min-height: 44px;
    border-radius: 14px;
    border: 0;
  }

  .cash-sheet {
    padding: 18px 16px 22px;
  }

  .cash-kpi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    margin-bottom: 16px;
    border-radius: 18px;
    background: var(--surface);
    border: 1px solid var(--stroke);
  }

  .cash-kpi span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .cash-kpi strong {
    color: var(--accent);
    font-size: 28px;
    font-weight: 800;
  }

  .cash-panel {
    margin-bottom: 16px;
    padding: 16px;
    border-radius: 18px;
    background: var(--surface);
    border: 1px solid var(--stroke);
  }

  .cash-panel-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
  }

  .cash-panel h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--text);
  }

  .cash-add {
    border: 0;
    border-radius: 14px;
    min-height: 40px;
    padding: 0 14px;
    background: var(--accent);
    color: #fff;
    font-weight: 700;
  }

  .cash-app table {
    margin: 0;
  }

  .cash-app thead th {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--stroke);
    white-space: nowrap;
  }

  .cash-app tbody td {
    color: var(--text);
    border-bottom: 1px solid var(--stroke);
    vertical-align: middle;
  }

  .cash-badge {
    display: inline-block;
    min-width: 84px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
  }

  .cash-badge.is-pending { background: #FEF3C7; color: #92400E; }
  .cash-badge.is-approved { background: #DCFCE7; color: #166534; }
  .cash-badge.is-other { background: #E2E8F2; color: #475569; }

  .cash-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .cash-app .cash-btn {
    margin: 0;
    border: 0;
    border-radius: 12px;
    min-height: 32px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .cash-btn.approve-cash-in,
  .cash-btn.approve-cash-out {
    background: var(--open);
    color: #fff;
  }

  .cash-btn.decline-cash-in,
  .cash-btn.decline-cash-out {
    background: #B91C1C;
    color: #fff;
  }

  .modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    background: var(--sheet, #F4F7FB);
  }

  .modal .modal-header {
    background: linear-gradient(180deg, #0B1730 0%, #142445 100%);
    color: #fff;
    border: 0;
  }

  .modal .modal-title {
    color: #fff;
    font-weight: 700;
  }

  .modal .btn-close {
    filter: invert(1);
  }

  .modal .form-control,
  .modal .form-select {
    border-radius: 12px;
    min-height: 42px;
  }

  .modal .btn-primary {
    border: 0;
    border-radius: 14px;
    min-height: 42px;
    background: #2F7BFF;
    font-weight: 700;
  }

  .modal .btn-secondary {
    border-radius: 14px;
    min-height: 42px;
    background: #fff;
    color: #12203A;
    border: 1px solid #E2E8F2;
  }

  @media (max-width: 767.98px) {
    .cash-panel-top,
    .cash-kpi {
      flex-direction: column;
      align-items: flex-start;
    }
    .cash-add { width: 100%; }
  }
</style>
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
  window.hideBootstrapModal = window.hideBootstrapModal || function (target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).hide();
  };

  init();

  window.Echo.channel("event-tellers")
    .listen(".TellerBalanceUpdated", (e) => {

      getData();

      if (!e.event_tellers || !Array.isArray(e.event_tellers)) {
          return;
      }

      let eventTellersTable = $("#table-tellers tbody");
      eventTellersTable.empty(); // Clear existing rows

      e.event_tellers.forEach((event_teller) => {

        let formattedAmount = new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(event_teller.teller_balance);


          let row = `
              <tr>
                  <td>${event_teller.teller_name}</td>
                  <td>${formattedAmount}</td>
              </tr>
          `;
          eventTellersTable.append(row);
      });
  });

  window.Echo.channel("cash-ins")
    .listen(".CashInUpdated", (e) => {

        if (!e.cash_ins || !Array.isArray(e.cash_ins)) {
            return;
        }

        let cashInTable = $("#table-cash-ins tbody");
        cashInTable.empty(); // Clear existing rows

        e.cash_ins.forEach((cash_in) => {
            let statusClass = cash_in.cash_in_status === "Pending" ? "is-pending"
                             : cash_in.cash_in_status === "Approved" ? "is-approved"
                             : "is-other";

            let formattedDate = new Date(cash_in.cash_in_datetime).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });

            let formattedAmount = new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(cash_in.cash_in_amount);

            let approveButton = cash_in.cash_in_status === "Pending"
                ? `<div class="cash-actions">
                <button class="btn cash-btn decline-cash-in" data-id="${cash_in.cash_in_id}">Decline</button>
                <button class="btn cash-btn approve-cash-in" data-id="${cash_in.cash_in_id}">Approve</button>
                </div>`
                : "";

            let row = `
                <tr>
                    <td>${approveButton}</td>
                    <td><span class="cash-badge ${statusClass}">${cash_in.cash_in_status}</span></td>
                    <td><small>${cash_in.cash_in_id}</small></td>
                    <td>${cash_in.teller_name}</td>
                    <td>${formattedAmount}</td>

                    <td><small>${formattedDate}</small></td>

                </tr>
            `;
            cashInTable.append(row);
        });
    });

    window.Echo.channel("cash-outs")
    .listen(".CashOutUpdated", (e) => {

        if (!e.cash_outs || !Array.isArray(e.cash_outs)) {
            return;
        }

        let cashOutTable = $("#table-cash-outs tbody");
        cashOutTable.empty(); // Clear existing rows

        e.cash_outs.forEach((cash_out) => {
            let statusClass = cash_out.cash_out_status === "Pending" ? "is-pending"
                             : cash_out.cash_out_status === "Approved" ? "is-approved"
                             : "is-other";

            let formattedDate = new Date(cash_out.cash_out_datetime).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });

            let formattedAmount = new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(cash_out.cash_out_amount);

            let approveButton = cash_out.cash_out_status === "Pending"
                ? `<div class="cash-actions">
                <button class="btn cash-btn decline-cash-out" data-id="${cash_out.cash_out_id}">Decline</button>
                <button class="btn cash-btn approve-cash-out" data-id="${cash_out.cash_out_id}">Approve</button>
                </div>`
                : "";

            let row = `
                <tr>
                    <td>${approveButton}</td>
                    <td><span class="cash-badge ${statusClass}">${cash_out.cash_out_status}</span></td>
                    <td><small>${cash_out.cash_out_id}</small></td>
                    <td>${cash_out.teller_name}</td>
                    <td>${formattedAmount}</td>

                    <td><small>${formattedDate}</small></td>

                </tr>
            `;
            cashOutTable.append(row);
        });
    });

// Handle Approve Button Click
$(document).on("click", ".approve-cash-in", function () {
    let cashInId = $(this).data("id");

    Swal.fire({
        title: "Approve Cash In?",
        text: "Are you sure you want to approve this cash-in request?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Approve",
        cancelButtonText: "Cancel",
        customClass: {
            confirmButton: "btn btn-success me-3",
            cancelButton: "btn btn-label-secondary"
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "/cash-transactions/cash-in/approve",
                data: { cash_in_id: cashInId },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    toastr.success("Cash In Approved!");
                    window.Echo.channel("cash-ins").emit(".CashInUpdated"); // Refresh table
                },
                error: function () {
                    toastr.error("Failed to approve cash-in request.");
                }
            });
        }
    });
});

$(document).on("click", ".approve-cash-out", function () {
    let cashOutId = $(this).data("id");

    Swal.fire({
        title: "Approve Cash Out?",
        text: "Are you sure you want to approve this cash-out request?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Approve",
        cancelButtonText: "Cancel",
        customClass: {
            confirmButton: "btn btn-success me-3",
            cancelButton: "btn btn-label-secondary"
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "/cash-transactions/cash-out/approve",
                data: { cash_out_id: cashOutId },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    toastr.success("Cash Out Approved!");
                    window.Echo.channel("cash-outs").emit(".CashOutUpdated");
                },
                error: function () {
                    toastr.error("Failed to approve cash-out request.");
                }
            });
        }
    });
});

$(document).on("click", ".decline-cash-in", function () {
    let cashInId = $(this).data("id");

    Swal.fire({
        title: "Decline Cash In?",
        text: "Are you sure you want to decline this cash-in request?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Decline",
        cancelButtonText: "Cancel",
        customClass: {
            confirmButton: "btn btn-success me-3",
            cancelButton: "btn btn-label-secondary"
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "/cash-transactions/cash-in/decline",
                data: { cash_in_id: cashInId },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    toastr.success("Cash In Declined!");
                    window.Echo.channel("cash-ins").emit(".CashInUpdated"); // Refresh table
                },
                error: function () {
                    toastr.error("Failed to decline cash-in request.");
                }
            });
        }
    });
});

$(document).on("click", ".decline-cash-out", function () {
    let cashOutId = $(this).data("id");

    Swal.fire({
        title: "Decline Cash Out?",
        text: "Are you sure you want to decline this cash-out request?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Decline",
        cancelButtonText: "Cancel",
        customClass: {
            confirmButton: "btn btn-success me-3",
            cancelButton: "btn btn-label-secondary"
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "/cash-transactions/cash-out/decline",
                data: { cash_out_id: cashOutId },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    toastr.success("Cash Out Declined!");
                    window.Echo.channel("cash-outs").emit(".CashOutUpdated");
                },
                error: function () {
                    toastr.error("Failed to decline cash-out request.");
                }
            });
        }
    });
});



  $('#addEntryForm').validate({
    onfocusout: false,
    rules:
    {
      event_teller_id:
      {
        required: true,
      },
      cash_in_amount:
      {
        required: true,
      },
    },
    submitHandler: function (form)
    {
      var formData = new FormData(form);
      $.ajax({
        type: "POST",
        url: "/cash-transactions/cash-in",
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        beforeSend: function()
        {
          toastr.info('Please Wait!');
        },
        success: function(data)
        {
          if (data > 0)
          {
            toastr.success('Transaction Success!');
            hideBootstrapModal('#addEntryModal');

          }
          else
          {
            toastr.error('Please try again!');
          }
        },
        error: function(data)
        {
          toastr.error('Something went wrong!');
        }
      });
    },
    messages:
    {
    },
  });


  function deleteEntry(id) {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/accounts/tellers/find/" + id,
      async: false,
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        Swal.fire({
          title: 'Are you sure to delete #' + id + '?',
          text: "You won't be able to revert this!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete it!',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            $.ajax({
              type: "DELETE",
              dataType: 'json',
              url: "/accounts/tellers/" + id,
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              },
              success: function(deleteResponse) {
                toastr.success('Transaction Success!');
                table.ajax.reload(null, false);
              },
              error: function(xhr) {
                toastr.error('Something went wrong!');
              }
            });
          }
        });
      },
      error: function(xhr) {
        toastr.error('Something went wrong!');
      }
    });
  }

  function init() {
    events();
    initializeSelect2ForAllModals();
  }

  function initializeSelect2ForAllModals() {
      $('.select2').each(function() {
        $(this).select2({ dropdownParent: $(this).parent()});
    })
  }

  function events() {
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: "/events/list",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        },
        async: true,
        success: function (response) {
            var dropdown = $('#event_id');
            dropdown.empty();
            dropdown.append('<option disabled selected>Please Select</option>');

            $.each(response, function (index) {
                var val = response[index].event_date + " - " + response[index].event_status + " - " + response[index].event_name;
                var id = response[index].event_id;
                dropdown.append("<option value='" + id + "' data-status='" + response[index].event_status + "'>" + val + "</option>");
            });

            // Check the status of the selected event when changing
            dropdown.change(function () {
                let selectedOption = $(this).find(":selected");
                let status = selectedOption.data("status");

                if (status === "Active") {
                    $("#button-add-cash").prop("disabled", false);
                } else {
                    $("#button-add-cash").prop("disabled", true);
                }
            });

            $("#event_id").prop("disabled", false);


        }
    });
}


  function eventTellers() {
    let eventId = $("#event_id").val(); // Get selected event ID

    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/cash-transactions/tellers/list",
        data: { event_id: eventId }, // Send event_id
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        async: true,
        success: function (response) {
            var dropdown = $("#event_teller_id");
            dropdown.empty(); // Clear previous options
            dropdown.append('<option disabled selected>Please Select</option>');

            if (response.length === 0) {
                dropdown.append('<option disabled>No tellers available</option>');
                return;
            }

            $.each(response, function (index, teller) {
                dropdown.append(`<option value="${teller.event_teller_id}">${teller.teller_name}</option>`);
            });

            dropdown.trigger("change"); // Refresh dropdown UI if using Select2
        },
        error: function () {
            toastr.error("Failed to load tellers.");
        },
    });

    $.ajax({
      url: `/cash-transactions/tellers/load`,
      type: "GET",
      data: { event_id: eventId },
      success: function () {
        $("#main").removeClass("hidden");
      },
      error: function () {
        toastr.error("Failed to load event.");
      }
    });
}


  $('#addEntryModal').on('hidden.bs.modal', function()
  {
    $("#addEntryForm").trigger("reset");
    $("#addEntryForm").find("select.select2").each(function() {
        $(this).val($(this).find("option:first").val()).trigger("change");
    });
  });


  $("#event_id").change(function () {

    eventTellers();
    getData();

  });

  function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);

    return formatted.replace(/₱\s?/, ''); // Removes ₱ symbol
}

  function getData() {
    let eventId = $("#event_id").val();

    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/teller-transactions/get-data",
        data: { event_id: eventId },
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        async: true,
        success: function (response) {
          $('#total-collections').text(formatCurrency(response.total_collections));
          $('#total-fights').text(response.total_fights);
          $('#admin-cash-on-hand').text(formatCurrency(response.cash_on_hand));
        },
        error: function () {
            toastr.error("Failed to fetch information.");
        },
    });


}

</script>
@endsection

@section('content')
<div class="cash-app">
  <div class="cash-board">
    <p class="cash-kicker">Cash desk</p>
    <h2>Cash Transactions</h2>
    <p>Approve teller cash-in and cash-out for the selected event.</p>
    <div>
      <label class="form-label" for="event_id">Event</label>
      <select class="form-select select2" id="event_id" name="event_id" disabled></select>
    </div>
  </div>

  <div class="hidden" id="main">
    <div class="cash-sheet">
      <div class="cash-kpi">
        <div>
          <span>Admin cash on hand</span>
          <strong id="admin-cash-on-hand"></strong>
        </div>
      </div>

      <section class="cash-panel">
        <div class="cash-panel-top">
          <h3>Teller cash on hand</h3>
        </div>
        <div class="table-responsive">
          <table class="table" id="table-tellers" name="table-tellers">
            <thead>
              <tr>
                <th style="width: 40%">Name</th>
                <th>Cash on Hand</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>

      <section class="cash-panel">
        <div class="cash-panel-top">
          <h3>Cash out requests</h3>
        </div>
        <div class="table-responsive">
          <table class="table" id="table-cash-outs" name="table-cash-outs">
            <thead>
              <tr>
                <th>Actions</th>
                <th>Status</th>
                <th style="width: 5%">#</th>
                <th>Name</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>

      <section class="cash-panel">
        <div class="cash-panel-top">
          <h3>Cash in requests</h3>
          <button class="cash-add" data-bs-toggle="modal" data-bs-target="#addEntryModal" id="button-add-cash">Add cash to teller</button>
        </div>
        <div class="table-responsive">
          <table class="table" id="table-cash-ins" name="table-cash-ins">
            <thead>
              <tr>
                <th>Actions</th>
                <th>Status</th>
                <th style="width: 5%">#</th>
                <th style="width: 20%">Name</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>

<div class="modal fade" id="addEntryModal" tabindex="-1" aria-labelledby="addEntryModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addEntryForm" name="addEntryForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add New Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Teller</label>
            <select class="form-select select2" id="event_teller_id" name="event_teller_id"></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" class="form-control" id="cash_in_amount" name="cash_in_amount">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="editEntryModal" tabindex="-1" aria-labelledby="editEntryModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editEntryForm" name="editEntryForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="_username" name="_username" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Password (Leave this empty if you don't need to update)</label>
            <input type="password" class="form-control" id="_password" name="_password">
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="_teller_name" name="_teller_name">
          </div>
          <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" class="form-control" id="_contact_number" name="_contact_number">
          </div>
          <div class="mb-3">
            <label class="form-label">Is Active</label>
            <select class="form-select select2" id="_is_active" name="_is_active">
              <option disabled selected>Please select</option>
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <input type="hidden" id="uid" name="uid">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
