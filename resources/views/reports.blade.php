@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Reports')

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
        url: `/reports/report/${eventId}`,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        },
        beforeSend: function() {
            $(".fw-bolder.text-primary").text("Loading...");
        },
        success: function (data) {
            $("#main").removeClass("hidden");

            $("#total-collection").text(formatCurrency(data.total_collection));
            $("#commission").text(formatCurrency(data.organizer_share));
            $("#salaries").text(formatCurrency(data.salaries));
            $("#net-sharing").text(formatCurrency(data.net_for_sharing));
            $("#system-share").text(formatCurrency(data.system_share));
            $("#operator-share").text(formatCurrency(data.operator_share));
            $("#revolving-fund").text(formatCurrency(data.revolving_fund));
            $("#unclaimed").text(formatCurrency(data.unclaimed));
            $("#prizes").text(formatCurrency(data.prizes));
            $("#supposed-cash").text(formatCurrency(data.supposed_cash_on_hand));
            $("#actual-cash").text(formatCurrency(data.actual_cash_on_hand));
            $("#difference").text(formatCurrency(data.difference));
            $("#short").text(formatCurrency(data.short));

            $("#teller-count").text(data.teller_count);
            $("#fight-count").text(data.fight_count);
            $("#fight-count-completed").text(data.fight_count_completed);
            $("#fight-count-draw").text(data.fight_count_draw);
            $("#fight-count-cancelled").text(data.fight_count_cancelled);
            $("#bet-count").text(data.bet_count);
            $("#bet-count-meron").text(data.bet_count_meron);
            $("#bet-count-wala").text(data.bet_count_wala);
            $("#voided-bets").text(formatCurrency(data.voided_bets));
            $("#claimed-bets").text(formatCurrency(data.claimed_bets));
            $("#unclaimed-winnings").text(formatCurrency(data.unclaimed_winnings));
            $("#rd").text(formatCurrency(data.rd));
            $("#comission").text(formatCurrency(data.comission));

        },
        error: function() {
            toastr.error("Failed to load report data.");
            $(".fw-bolder.text-primary").text("-");
        }
    });
  }

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
    <span class="text-muted fw-light">Menu /</span> Reports
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

  <div class="row hidden" id="main">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h2>Machine Summary</h2>
          <hr>
          <h3 class="fw-light">TOTAL BETS: <span id="total-collection" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">COMMISSION <span id="comission"></span>%: <span id="commission" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">SALARIES: <span id="salaries" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">NET FOR SHARING: <span id="net-sharing" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">SYSTEM SHARE: <span id="system-share" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">OPERATOR SHARE: <span id="operator-share" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">REVOLVING FUND: <span id="revolving-fund" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">UNCLAIMED: <span id="unclaimed" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">PRIZES: <span id="prizes" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">SUPPOSED CASH ON HAND: <span id="supposed-cash" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">ACTUAL CASH ON HAND: <span id="actual-cash" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">DIFFERENCE: <span id="difference" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">RD: <span id="rd" class="fw-bolder text-primary">-</span></h3>
          <hr>
          <h3 class="fw-light">SHORT: <span id="short" class="fw-bolder text-primary">-</span></h3>

        </div>
      </div>
    </div>



    <div class="col-md-8">
      <div class="row">

        <div class="col-md-8">
          <div class="card">
            <div class="card-body">
              <div class="row">
                <div class="col-md-3">
                  <h4># of Fights</h4>
                  <h5 id="fight-count" class="fw-bolder text-primary">-</h5>
                </div>

                <div class="col-md-3">
                  <h4>Completed Fights</h4>
                  <h5 id="fight-count-completed" class="fw-bolder text-primary">-</h5>
                </div>

                <div class="col-md-3">
                  <h4>Draw Fights</h4>
                  <h5 id="fight-count-draw" class="fw-bolder text-primary">-</h5>
                </div>


                <div class="col-md-3">
                  <h4>Cancelled Fights</h4>
                  <h5 id="fight-count-cancelled" class="fw-bolder text-primary">-</h5>
                </div>

              </div>
            </div>
          </div>
        </div>


        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <h4># of Tellers</h4>
              <h5 id="teller-count" class="fw-bolder text-primary">-</h5>
            </div>
          </div>
        </div>


      </div>

      <div class="row mt-3">
        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <h4># of Bets</h4>
              <h5 id="bet-count" class="fw-bolder text-primary">-</h5>

              <br>

              <h4># of Meron Bets</h4>
              <h5 id="bet-count-meron" class="fw-bolder text-danger">-</h5>

              <br>

              <h4># of Wala Bets</h4>
              <h5 id="bet-count-wala" class="fw-bolder text-primary">-</h5>

            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <h4>Voided Bets</h4>
              <h5 id="voided-bets" class="fw-bolder text-info">-</h5>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <h4>Claimed Amount</h4>
              <h5 id="claimed-bets" class="fw-bolder text-primary">-</h5>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <h4>Unclaimed Amount</h4>
              <h5 id="unclaimed-winnings" class="fw-bolder text-primary">-</h5>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

@endsection
