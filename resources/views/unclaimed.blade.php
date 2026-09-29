@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Unclaimed')

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
  .hidden{
    display: none !important;
  };
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/jquery.validate.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery.validate/additional-methods.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/barcode/JsBarcode.all.min.js')}}"></script>



@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    loadEvents(); // Load event options on page load

    $("#event_id").change(function() {
      let eventId = $(this).val();
      if (eventId) {
        fetchReportData(eventId);
      }
    });
  });

  function loadEvents() {
    $("#event_id").prop("disabled", true);
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: "/events/list",
      headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
      },
      success: function (response) {
        let dropdown = $('#event_id');
        dropdown.empty().append('<option disabled selected>Please Select</option>');

        $.each(response, function (index, event) {
          dropdown.append(`<option value="${event.event_id}" data-status="${event.event_status}">${event.event_date} - ${event.event_status} - ${event.event_name}</option>`);
        });

        $("#event_id").prop("disabled", false);
      },
      error: function() {
        toastr.error("Failed to load events.");
      }
    });
  }

  function fetchReportData(eventId) {
    $.ajax({
      type: "POST",
      dataType: 'json',
      url: `/unclaimed/list/${eventId}`,
      headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
      },
      beforeSend: function () {
        $(".fw-bolder.text-primary").text("Loading...");
        $("#bets-table tbody").html(`<tr><td colspan="6" class="text-center">Loading...</td></tr>`);
      },
      success: function (data) {

        $("#main").removeClass("hidden");

        $("#unclaimed-amount").text(formatCurrency(data.unclaimed));



        // Populate bets table
        let rows = "";
        let fightNumbers = new Set();

        if (data.bets && data.bets.length > 0) {
          data.bets.forEach(bet => {

            fightNumbers.add(bet.match_number);


            let sideClass = bet.bet_side === "Meron" ? "text-primary fw-bold" : bet.bet_side === "Wala" ? "text-danger fw-bold" : "";

            let statusText = "";
            let statusClass = "";
            let winnerOdds = "";

            if (bet.bet_status == 1) {
              statusText = "Ok";
              statusClass = "text-success fw-bold";
            } else if (bet.bet_status == 2) {
              statusText = "Void";
              statusClass = "text-warning fw-bold";
            } else {
              statusText = bet.bet_status;
              statusClass = "";
            }

            if (bet.bet_is_winner == 1) {
              statusText += " - Winner";
              if(bet.match_winner == 'Meron')
              {
                winnerOdds = bet.meron_odds;
              }
              else
              {
                winnerOdds = bet.wala_odds;
              }
            } else if (bet.bet_payout_amount > 0 && bet.bet_status == 1 && bet.match_status === 'Cancelled') {
              statusText += " - Cancelled";
            } else if (bet.bet_payout_amount > 0 && bet.bet_status == 1 && bet.match_status === 'Draw') {
              statusText += " - Draw";
            } else if (bet.bet_payout_amount > 0 && bet.bet_status == 2) {
              statusText += "";
            }



            let datetime = new Date(bet.bet_datetime).toLocaleString("en-US", {
              year: "numeric",
              month: "short",
              day: "2-digit",
              hour: "2-digit",
              minute: "2-digit",
              hour12: true
            });

            let payout_datetime = "";

            if (bet.bet_payout_datetime) {
              payout_datetime = new Date(bet.bet_payout_datetime).toLocaleString("en-US", {
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
            <td>${bet.bet_receipt_code}</td>
            <td>${bet.match_number}</td>
            <td class="${sideClass}">${bet.bet_side}</td>
            <td>${formatCurrency(bet.bet_amount)}</td>
            <td class="${statusClass}">${statusText}</td>
            <td>${datetime}</td>
            <td>${bet.teller_name}</td>
            <td>
              ${winnerOdds}
              ${
                bet.bet_payout_amount != 0
                  ? `<b class="fw-bolder text-success">${formatCurrency(bet.bet_payout_amount)}</b>`
                  : ''
              }
            </td>
            <td>${payout_datetime}</td>
            <td>
              ${
            bet.bet_payout_amount > 0 && !bet.payout_name
            ? '<button class="btn btn-danger btn-sm unclaimed-badge" data-receipt="' + bet.bet_receipt_code + '">Unclaimed</button>'
            : bet.payout_name || ''
            }
            </td>
          </tr>
        `;

          });
        } else {
          rows = `<tr><td colspan="6" class="text-center">No bets found for this event.</td></tr>`;
        }


        let fightDropdown = $("#filter-fight");
        fightDropdown.empty().append(`<option value="">All Fights</option>`);
        Array.from(fightNumbers).sort((a, b) => a - b).forEach(fight => {
          fightDropdown.append(`<option value="${fight}">Fight #${fight}</option>`);
        });


        $("#bets-table tbody").html(rows);

        // Handle filter toggle
        $("#show-unclaimed-only").off().on("change", function () {
          if ($(this).is(":checked")) {
            $("#bets-table tbody tr").each(function () {
              const isUnclaimed = $(this).find(".unclaimed-badge").length > 0;
              $(this).toggle(isUnclaimed);
            });
          } else {
            $("#bets-table tbody tr").show();
          }
        });

        // Handle barcode modal
        $(".unclaimed-badge").on("click", function () {
          const receiptCode = $(this).data("receipt");

          JsBarcode("#barcodeCanvas", receiptCode, {
            format: "CODE128",
            width: 2,
            height: 150,
            displayValue: false
          });

          $("#receipt-code-text").text(receiptCode);
          showBootstrapModal('#barcodeModal');
        });


      },
      error: function () {
        toastr.error("Failed to load report data.");
        $(".fw-bolder.text-primary").text("-");
        $("#bets-table tbody").html(`<tr><td colspan="6" class="text-center text-danger">Error loading bets.</td></tr>`);
      }
    });
  }

  // Refresh button
  $("#refresh-btn").off().on("click", function () {
    let selectedId = $("#event_id").val();
    if (selectedId) {
      fetchReportData(selectedId);
    } else {
      toastr.warning("Please select an event first.");
    }
  });

  // Search functionality
  $("#search-bets").off().on("keyup", function () {
    let value = $(this).val().toLowerCase();
    $("#bets-table tbody tr").filter(function () {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
  });


  $("#filter-fight").off().on("change", function () {
  const selectedFight = $(this).val();
  $("#bets-table tbody tr").each(function () {
    const rowFight = $(this).find("td:nth-child(2)").text(); // Fight # is 2nd column
    if (!selectedFight || rowFight === selectedFight) {
      $(this).show();
    } else {
      $(this).hide();
    }
  });
});

  function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);

    return formatted.replace(/₱\s?/, '');
  }

</script>
@endsection

@section('content')

<div class="container-fluid">
  <h4 class="py-3 breadcrumb-wrapper mb-4">
    <span class="text-muted fw-light">Menu /</span> Unclaimed
  </h4>

  <div class="row">
    <div class="col-md-6">
      <div class="mb-3">
        <label class="form-label">Event</label>
        <select class="form-select select2" id="event_id" name="event_id"></select>
      </div>
    </div>
  </div>

  <hr>

  <div class="hidden" id="main">

    <div class="row mb-3">
      <div class="col-md-4">
        <h3 class="fw-light">Unclaimed Amount: <span class="text-primary" id="unclaimed-amount">₱0.00</span></h3>
      </div>
    </div>


    <div class="row mb-3">
      <div class="col-md-4 d-flex align-items-center gap-2">
        <button class="btn btn-outline-primary" id="refresh-btn">
          <i class="bx bx-refresh"></i> Refresh
        </button>
        <select class="form-select" id="filter-fight" style="max-width: 150px;">
          <option value="">All Fights</option>
        </select>
      </div>

      <div class="col-md-4">
      </div>

      <div class="col-md-4">
        <input type="text" id="search-bets" class="form-control" placeholder="Search bets...">
      </div>
    </div>



    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped" id="bets-table">
                <thead>
                  <tr>
                    <th>Receipt</th>
                    <th>Fight #</th>
                    <th>Bet Side</th>
                    <th>Bet Amount</th>
                    <th>Bet Status</th>
                    <th>Bet Time</th>
                    <th>Teller</th>
                    <th>Payout Amount</th>
                    <th>Payout Time</th>
                    <th>Payout By</th>
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
</div>

<div class="modal fade" id="barcodeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center">
        <h5 class="mb-3">Bet Receipt</h5>
        <canvas id="barcodeCanvas"></canvas>
        <div id="receipt-code-text" class="mt-2 text-muted"></div>
      </div>
    </div>
  </div>
</div>


@endsection
