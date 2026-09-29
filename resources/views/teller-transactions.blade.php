@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Teller Transactions')

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

  .layout-page .container-p-y:has(.teller-app) {
    padding-top: 1rem;
  }

  .teller-app {
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
    border-radius: 24px;
    overflow: hidden;
    background: var(--sheet);
    box-shadow: 0 18px 40px rgba(11, 23, 48, 0.12);
    color: var(--text);
  }

  .teller-board {
    padding: 20px 22px 18px;
    background: linear-gradient(180deg, var(--navy) 0%, var(--navy-mid) 58%, var(--navy-end) 100%);
    color: #fff;
  }

  .teller-kicker {
    margin: 0;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-size: 13px;
    font-weight: 700;
  }

  .teller-board h2 {
    margin: 2px 0 4px;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
  }

  .teller-board p {
    margin: 0 0 14px;
    color: var(--meter);
    font-size: 13px;
  }

  .teller-board label {
    color: #9EC8FF;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .teller-board .form-select {
    min-height: 44px;
    border-radius: 14px;
    border: 0;
  }

  .teller-sheet {
    padding: 18px 16px 22px;
  }

  .teller-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
  }

  .teller-kpi {
    padding: 16px 18px;
    border-radius: 18px;
    background: var(--surface);
    border: 1px solid var(--stroke);
  }

  .teller-kpi span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .teller-kpi strong {
    color: var(--accent);
    font-size: 26px;
    font-weight: 800;
  }

  .teller-panel {
    padding: 16px;
    border-radius: 18px;
    background: var(--surface);
    border: 1px solid var(--stroke);
  }

  .teller-panel h3 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 800;
  }

  .teller-app thead th {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--stroke);
    white-space: nowrap;
  }

  .teller-app tbody td {
    color: var(--text);
    border-bottom: 1px solid var(--stroke);
    vertical-align: middle;
  }

  .teller-app .view-history {
    border: 0;
    border-radius: 12px;
    min-height: 34px;
    padding: 0 12px;
    background: var(--accent);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
  }

  .modal .modal-content {
    border: 0;
    background: var(--sheet, #F4F7FB);
  }

  .modal .modal-header {
    background: linear-gradient(180deg, #0B1730 0%, #142445 100%);
    color: #fff;
    border: 0;
  }

  .modal .modal-title,
  .modal .modal-title .text-primary {
    color: #fff !important;
  }

  .modal .btn-close {
    filter: invert(1);
  }

  @media (max-width: 991.98px) {
    .teller-kpis { grid-template-columns: 1fr; }
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
  window.showBootstrapModal = window.showBootstrapModal || function (target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).show();
  };

  init();

  var cash_out = 0;
  var cash_in = 0;

  window.Echo.channel("event-tellers")
    .listen(".TellerBalanceUpdated", (e) => {
      getData();

      if (!e.event_tellers || !Array.isArray(e.event_tellers)) {
          return;
      }

      let eventTellersTable = $("#table-tellers tbody");
      eventTellersTable.empty();

      e.event_tellers.forEach((event_teller) => {

          let teller_balance = new Intl.NumberFormat('en-PH', {
                  style: 'currency',
                  currency: 'PHP',
                  minimumFractionDigits: 2
              }).format(event_teller.teller_balance);



              let total_payout = new Intl.NumberFormat('en-PH', {
                  style: 'currency',
                  currency: 'PHP',
                  minimumFractionDigits: 2
              }).format(event_teller.total_payout);


          let total_bet = new Intl.NumberFormat('en-PH', {
                  style: 'currency',
                  currency: 'PHP',
                  minimumFractionDigits: 2
              }).format(event_teller.total_bet);

          let current_bet = new Intl.NumberFormat('en-PH', {
              style: 'currency',
              currency: 'PHP',
              minimumFractionDigits: 2
          }).format(event_teller.teller_match_balance);

          let btn =  `
          <button class="btn view-history"
              data-match-id="${event_teller.event_teller_id}"
              data-teller-name="${event_teller.teller_name}"
              data-total-bet="${event_teller.total_bet}"
              data-total-payout="${event_teller.total_payout}"
              data-cash-on-hand="${event_teller.teller_balance}">
              View History
          </button>`;


          let row = `
              <tr>
                  <td>${event_teller.teller_name}</td>
                  <td>${total_bet}</td>
                  <td>${total_payout}</td>
                  <td>${current_bet}</td>
                  <td>${teller_balance}</td>
                  <td>${btn}</td>

              </tr>
          `;
          eventTellersTable.append(row);
      });
  });

  window.Echo.channel("match-updates")
  .listen(".MatchUpdated", (e) => {

      $("#match-bet-status").text(e.match.match_bet_status);

      $('#match-number').text("Fight # " + e.match.match_number);


      getData();
  });


  function init() {
    events();
    initializeSelect2ForAllModals();
  }


function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);

    return formatted.replace(/₱\s?/, ''); // Removes ₱ symbol
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
        data: { event_id: eventId },
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
        getData();
      },
      error: function () {
        toastr.error("Failed to load event.");
      }
    });
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


$(document).on("click", ".view-history", function () {
    const eventTellerId = $(this).data("match-id");
    const tellerName =  $(this).data("teller-name");
    const totalBet = $(this).data("total-bet");
    const totalPayout = $(this).data("total-payout");
    const cashOnHand = $(this).data("cash-on-hand");

    $.ajax({
        url: `/teller-transactions/history`,
        type: "POST",
        data: { event_teller_id: eventTellerId },
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        success: function (response) {
            const accordion = $("#tellerHistoryAccordion");
            accordion.empty();

            if (response.length === 0) {
                accordion.append("<p class='text-center'>No data available</p>");
                return;
            }

            $("#history-teller-name").text(tellerName);
            $("#history-total-bet").text(formatCurrency(totalBet));
            $("#history-total-payout").text(formatCurrency(totalPayout));
            $("#history-cash-on-hand").text(formatCurrency(cashOnHand));

            response.forEach((item, index) => {
                let betRows = "";

                            item.bets.forEach(bet => {
              let sideClass = "";
              if (bet.bet_side === "Meron") {
                sideClass = "text-primary fw-bold";
              } else if (bet.bet_side === "Wala") {
                sideClass = "text-danger fw-bold";
              }

              let statusText = "";
              let statusClass = "";

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

              if(bet.bet_is_winner == 1)
              {
                statusText += " - Winner";
              }
              else if(bet.bet_payout_amount > 0 && bet.bet_status == 1 && item.match_status == 'Cancelled')
              {
                statusText += " - Cancelled";
              }
              else if(bet.bet_payout_amount > 0 && bet.bet_status == 1 && item.match_status == 'Draw')
              {
                statusText += " - Draw";
              }
              else if(bet.bet_payout_amount > 0 && bet.bet_status == 2)
              {
                statusText += "";
              }

              betRows += `
                <tr>
                  <td>${bet.bet_receipt_code}</td>
                  <td class="${sideClass}">${bet.bet_side}</td>
                  <td>${formatCurrency(bet.bet_amount)}</td>
                  <td class="${statusClass}">${statusText}</td>
                  <td>${formatDatetime(bet.bet_datetime)}</td>
                  <td class="fw-bold text-success">
                    ${bet.bet_payout_amount > 0 ? formatCurrency(bet.bet_payout_amount) : ''}
                  </td>

                  <td>
                    ${
                      bet.bet_payout_amount > 0 && bet.teller_name == 'Unclaimed'
                        ? '<span class="badge bg-danger">Unclaimed</span>'
                        : bet.teller_name || ''
                    }
                  </td>

                </tr>
              `;
            });



            let oddsDisplay = "N/A";

            if (item.match_winner === "Meron") {
                oddsDisplay = item.meron_odds;
            } else if (item.match_winner === "Wala") {
                oddsDisplay = item.wala_odds;
            }

                const panel = `
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-${index}">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${index}" aria-expanded="false" aria-controls="collapse-${index}">
                          Fight #${item.match_number} —
                          <span class="ms-1 badge bg-label-info">Total: ${formatCurrency(item.total_bet)}</span>
                          <span class="ms-1 badge bg-label-danger">Meron: ${formatCurrency(item.total_meron)}</span>
                          <span class="ms-1 badge bg-label-primary">Wala: ${formatCurrency(item.total_wala)}</span>
                          <span class="ms-1 badge bg-label-success">Winner: ${item.match_winner}</span>
                          <span class="ms-1 badge bg-label-secondary">Odds: ${oddsDisplay}</span>
                          <span class="ms-1 badge bg-label-warning">Match: ${item.match_status}</span>
                      </button>
                    </h2>
                    <div id="collapse-${index}" class="accordion-collapse collapse" aria-labelledby="heading-${index}" data-bs-parent="#tellerHistoryAccordion">
                      <div class="accordion-body p-0">
                        <table class="table table-striped m-0">
                          <thead class="table-light">
                            <tr>
                              <th>Receipt</th>
                              <th>Bet Side</th>
                              <th>Bet Amount</th>
                              <th>Bet Status</th>
                              <th>Bet Time</th>
                              <th>Payout Amount</th>
                              <th>Payout By</th>
                              <th></th>

                            </tr>
                          </thead>
                          <tbody>
                            ${betRows}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                `;
                accordion.append(panel);
            });

            showBootstrapModal('#viewHistoryModal');
        },
        error: function () {
            toastr.error("Failed to load teller history.");
        }
    });
});


$("#event_id").change(function () {

eventTellers();

});


function formatDatetime(datetimeStr) {
  const date = new Date(datetimeStr);
  const options = {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true
  };
  return date.toLocaleString("en-US", options).replace(',', '');
}



</script>
@endsection

@section('content')
<div class="teller-app">
  <div class="teller-board">
    <p class="teller-kicker">Teller desk</p>
    <h2>Teller Transactions</h2>
    <p>Watch bets, payouts, and cash on hand for the selected event.</p>
    <p><a class="btn btn-sm btn-primary" href="{{ route('teller-ledger') }}">Open Teller Ledger</a> <a class="btn btn-sm btn-outline-primary" href="{{ route('teller-ledger.all') }}">All tellers' cash on hand</a></p>
    <div>
      <label class="form-label" for="event_id">Event</label>
      <select class="form-select select2" id="event_id" name="event_id" disabled></select>
    </div>
  </div>

  <div class="hidden" id="main">
    <div class="teller-sheet">
      <div class="teller-kpis">
        <div class="teller-kpi">
          <span>Valid bets</span>
          <strong id="total-collections"></strong>
        </div>
        <div class="teller-kpi">
          <span>Total fights</span>
          <strong id="total-fights"></strong>
        </div>
        <div class="teller-kpi">
          <span>Admin cash on hand</span>
          <strong id="admin-cash-on-hand"></strong>
        </div>
      </div>

      <section class="teller-panel">
        <h3>Teller activity</h3>
        <div class="table-responsive">
          <table class="table" id="table-tellers" name="table-tellers">
            <thead>
              <tr>
                <th style="width: 20%">Name</th>
                <th>Total Bet</th>
                <th>Total Payout</th>
                <th>Current Bet (<span id="match-number">Fight #3</span>)</th>
                <th>Cash on Hand</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>


<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Teller Bet History — <span id="history-teller-name" class="text-primary fw-bold"></span><br>
          <small>
            <span class="badge bg-label-info">Total Bet: <span id="history-total-bet"></span></span>
            <span class="badge bg-label-success">Payout: <span id="history-total-payout"></span></span>
            <span class="badge bg-label-warning">Cash on Hand: <span id="history-cash-on-hand"></span></span>
          </small>
        </h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="accordion" id="tellerHistoryAccordion">
          <!-- Dynamic content here -->
        </div>

      </div>
    </div>
  </div>
</div>


@endsection
