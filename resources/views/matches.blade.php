@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Matches')

@section('vendor-style')
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

  .layout-page .container-p-y:has(.matches-app) {
    padding-top: 1rem;
  }

  .matches-app {
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
    --meron: #DC2626;
    --wala: #2563EB;
    --open: #16A34A;
    border-radius: 24px;
    overflow: hidden;
    background: var(--sheet);
    box-shadow: 0 18px 40px rgba(11, 23, 48, 0.12);
  }

  .matches-board {
    background: linear-gradient(180deg, var(--navy) 0%, var(--navy-mid) 58%, var(--navy-end) 100%);
    padding: 18px 22px 22px;
    color: #fff;
  }

  .matches-board-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .matches-kicker {
    margin: 0;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
  }

  .matches-event {
    margin: 2px 0 0;
    color: var(--meter);
    font-size: 13px;
  }

  .matches-date {
    margin: 0;
    color: #9EC8FF;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    white-space: nowrap;
  }

  .matches-fight-label {
    margin: 18px 0 0;
    text-align: center;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    font-size: 11px;
    font-weight: 700;
    color: #9EC8FF;
  }

  .matches-fight-number {
    margin: 0;
    text-align: center;
    font-size: clamp(42px, 7vw, 64px);
    line-height: 1;
    font-weight: 800;
    color: #fff;
  }

  .matches-bars {
    display: flex;
    width: 80px;
    height: 4px;
    margin: 6px auto 0;
    gap: 6px;
  }

  .matches-bars span:first-child {
    flex: 1;
    background: var(--meron);
    border-radius: 4px;
  }

  .matches-bars span:last-child {
    flex: 1;
    background: var(--wala);
    border-radius: 4px;
  }

  .matches-led-wrap {
    display: flex;
    justify-content: center;
    margin-top: 12px;
  }

  #match-bet-status {
    display: inline-block;
    min-width: 88px;
    border-radius: 999px;
    padding: 5px 16px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    background: #2a3f66;
    color: #fff;
    text-align: center;
  }

  #match-bet-status.bg-success {
    background: var(--open) !important;
  }

  #match-bet-status.bg-warning {
    background: var(--meron) !important;
  }

  #match-bet-status.bg-danger {
    background: #B91C1C !important;
  }

  #total-bets,
  .matches-total {
    display: block;
    margin-top: 8px;
    text-align: center;
    color: var(--meter);
    font-size: 12px;
    font-weight: 700;
  }

  .matches-meters {
    display: flex;
    align-items: stretch;
    margin-top: 18px;
    padding: 14px 8px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .matches-side {
    flex: 1;
    text-align: center;
  }

  .matches-side-label {
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 10px;
    font-weight: 700;
  }

  .matches-side.is-meron .matches-side-label,
  .matches-side.is-meron .matches-odds,
  .matches-side.is-meron #meron-bet-status {
    color: #FCA5A5;
  }

  .matches-side.is-wala .matches-side-label,
  .matches-side.is-wala .matches-odds,
  .matches-side.is-wala #wala-bet-status {
    color: #93C5FD;
  }

  .matches-side-total {
    margin-top: 4px;
    font-size: 18px;
    font-weight: 800;
    color: #fff;
  }

  .matches-odds {
    margin-top: 2px;
    font-size: 11px;
  }

  .matches-side-status {
    display: none;
    margin-top: 4px;
    font-size: 10px;
    font-weight: 700;
  }

  .matches-divider {
    width: 1px;
    margin: 4px 0;
    background: rgba(255, 255, 255, 0.13);
  }

  .matches-sheet {
    background: var(--sheet);
    padding: 22px 16px 24px;
  }

  .matches-start {
    text-align: center;
    padding: 28px 12px 8px;
  }

  .matches-start p {
    margin: 0 0 16px;
    color: var(--muted);
    font-size: 14px;
  }

  .matches-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 22px;
  }

  .matches-entries {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .matches-entry {
    background: var(--surface);
    border: 1px solid var(--stroke);
    border-radius: 18px;
    padding: 8px 12px 6px;
  }

  .matches-entry label {
    display: block;
    margin: 0;
    font-size: 11px;
    font-weight: 700;
  }

  .matches-entry.is-meron label {
    color: var(--meron);
  }

  .matches-entry.is-wala label {
    color: var(--wala);
  }

  .matches-entry input {
    width: 100%;
    border: 0;
    background: transparent;
    min-height: 40px;
    padding: 0;
    color: var(--text);
    font-size: 15px;
    box-shadow: none !important;
  }

  .matches-entry input:focus {
    outline: none;
  }

  .matches-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 12px;
    margin-top: 12px;
  }

  .matches-app .matches-actions .btn {
    margin: 0;
    border: 0;
    border-radius: 16px;
    min-height: 48px;
    font-weight: 700;
    box-shadow: none;
  }

  .matches-app #open-bet {
    background: var(--open);
    color: #fff;
  }

  .matches-app #close-bet {
    background: var(--meron);
    color: #fff;
  }

  .matches-app #toggle-meron.btn-danger {
    background: #B91C1C;
    color: #fff;
  }

  .matches-app #toggle-wala.btn-primary {
    background: #1D4ED8;
    color: #fff;
  }

  .matches-app #toggle-meron.btn-success,
  .matches-app #toggle-wala.btn-success {
    background: var(--open);
    color: #fff;
  }

  .matches-app #meron-wins {
    background: var(--meron);
    color: #fff;
  }

  .matches-app #wala-wins {
    background: var(--wala);
    color: #fff;
  }

  .matches-app #draw-fight,
  .matches-app #cancel-fight {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--stroke) !important;
  }

  .matches-app .matches-actions .btn:disabled {
    opacity: 0.38;
  }

  .matches-history-title {
    margin: 22px 0 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
  }

  #fight-history {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 720px;
    overflow: auto;
    padding-right: 4px;
  }

  .fight-card {
    background: var(--surface);
    border: 1px solid var(--stroke);
    border-radius: 18px;
    padding: 14px;
  }

  .fight-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .fight-card-number {
    margin: 0;
    color: var(--text);
    font-size: 16px;
    font-weight: 700;
  }

  .fight-card-status {
    border-radius: 999px;
    padding: 4px 10px;
    background: #eef4ff;
    color: var(--accent);
    font-size: 11px;
    font-weight: 700;
  }

  .fight-card-winner {
    margin: 8px 0 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
  }

  .fight-card-winner.is-meron {
    color: var(--meron);
  }

  .fight-card-winner.is-wala {
    color: var(--wala);
  }

  .fight-card-winner.is-draw {
    color: #B45309;
  }

  .fight-card-winner.is-cancel {
    color: var(--muted);
  }

  .fight-card-totals {
    margin: 2px 0 0;
    color: var(--muted);
    font-size: 12px;
  }

  .fight-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
  }

  .matches-tv {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
  }

  .matches-app .change-result {
    border: 1px solid rgba(47, 123, 255, 0.2);
    background: rgba(47, 123, 255, 0.08);
    color: var(--accent);
    border-radius: 14px;
    font-size: 12px;
    font-weight: 700;
    min-width: 128px;
  }

  .matches-app .matches-entry-detail { font-size: 12px; margin-top: 4px; opacity: .85; line-height: 1.35; }
  .matches-app .matches-hold { margin: 10px 0; padding: 8px 12px; border-radius: 10px; background: #fef3c7; color: #92400e; font-weight: 700; }
  .matches-app .matches-called-note { font-size: 12px; margin: 8px 0 0; opacity: .8; }
  .matches-app .matches-link { margin-top: 6px; font-size: 12px; font-weight: 700; }
  .matches-app .matches-link.is-ok { color: #16a34a; }
  .matches-app .matches-link.is-wait { color: #d97706; }
  .matches-app #get-started {
    border: 0;
    border-radius: 16px;
    min-width: 220px;
    min-height: 48px;
    background: var(--accent);
    color: #fff;
    font-weight: 700;
  }

  @media (max-width: 991.98px) {
    .matches-grid {
      grid-template-columns: 1fr;
    }

    #fight-history {
      max-height: none;
    }
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


  window.Echo.channel("match-updates")
  .listen(".MatchUpdated", (e) => {

    $("#match-id").val(e.match.match_id);
    $("#match-bet-status").text(e.match.match_bet_status)
    .removeClass("bg-success bg-warning bg-danger")
    .addClass(e.match.match_bet_status == "Open" ? "bg-success" :
    e.match.match_bet_status == "Closed" ? "bg-warning" :
    "bg-danger");
    $('#match-number').text(e.match.match_number);
    $('#meron-bet').text(formatCurrency(e.match.meron_total_bet));
    $('#wala-bet').text(formatCurrency(e.match.wala_total_bet));



    $('#meron_entry').val(e.match.meron_entry);
    $('#wala_entry').val(e.match.wala_entry);
    renderCalledFight(e.match);


    $('#meron-odds').text(e.match.meron_odds);
    $('#wala-odds').text(e.match.wala_odds);
    $('#total-bets').text(formatCurrency(
    parseFloat(e.match.meron_total_bet) + parseFloat(e.match.wala_total_bet)
));


    if (e.match.match_bet_status === "Closed") {
      $("#toggle-meron, #toggle-wala, #close-bet").prop("disabled", true);
      $("#meron-wins, #wala-wins, #cancel-fight, #draw-fight ,#open-bet").prop("disabled", false);
    } else if (e.match.match_bet_status === "Open") {
      $("#toggle-meron, #toggle-wala, #close-bet").prop("disabled", false);
      $("#meron-wins, #wala-wins, #cancel-fight, #draw-fight, #open-bet").prop("disabled", true);
    }


    if (e.match.hold_reason) {
      $("#open-bet").prop("disabled", true);
    }

    if (e.match.meron_bet_status) {
      $("#toggle-meron").text("Enable Meron Bet").removeClass("btn-danger").addClass("btn-success");
      $("#meron-bet-status").text("Betting Disabled").show(); // Show message
    } else {
      $("#toggle-meron").text("Disable Meron Bet").removeClass("btn-success").addClass("btn-danger");
      $("#meron-bet-status").text("").hide(); // Hide message

    }

    // Toggle Wala Bet Button and Message
    if (e.match.wala_bet_status) {
      $("#toggle-wala").text("Enable Wala Bet").removeClass("btn-primary").addClass("btn-success");

      $("#wala-bet-status").text("Betting Disabled").show(); // Show message
    } else {
      $("#toggle-wala").text("Disable Wala Bet").removeClass("btn-success").addClass("btn-primary");
      $("#wala-bet-status").text("").hide(); // Hide message
    }




  });

  function renderFightHistory(matches) {
      if (!Array.isArray(matches)) {
          return;
      }

      let fightTable = $("#fight-history");
      fightTable.empty(); // Clear existing rows

      matches.forEach((match) => {
          let winnerClass = match.match_winner === "Meron" ? "is-meron"
                         : match.match_winner === "Wala" ? "is-wala"
                         : match.match_status === "Draw" ? "is-draw"
                         : match.match_status === "Cancelled" ? "is-cancel"
                         : "";

          let winnerOdds = match.match_winner === "Meron" ? match.meron_odds
                          : match.match_winner === "Wala" ? match.wala_odds
                          : "";

          let winnerText = match.match_status === "Draw"
              ? "Draw · tickets refunded"
              : match.match_status === "Cancelled"
              ? "Cancelled · tickets refunded"
              : match.match_winner === "Meron" || match.match_winner === "Wala"
              ? `Winner: ${match.match_winner} · Odds ${winnerOdds || "0"}`
              : "Winner: —";

          let isDisplayed = match.is_display ? "checked" : "";

          let changeResultBtn = match.match_status !== "Ongoing" ? `
              <button class="btn btn-sm change-result"
                  data-match-id="${match.match_id}"
                  data-match-number="${match.match_number}"
                  data-current-winner="${match.match_winner}">
                  Change result
              </button>`
              : "";

          let displayCheckbox = match.match_status !== "Ongoing" ? `
              <label class="matches-tv">
                <input type="checkbox" class="toggle-display"
                    data-match-id="${match.match_id}" ${isDisplayed}>
                TV
              </label>`
              : "";

          let actions = (changeResultBtn || displayCheckbox) ? `
              <div class="fight-card-actions">
                ${displayCheckbox}
                ${changeResultBtn}
              </div>` : "";

          let meronTotal = formatCurrency(match.meron_total_bet || 0);
          let walaTotal = formatCurrency(match.wala_total_bet || 0);

          let row = `
              <article class="fight-card">
                  <div class="fight-card-top">
                    <h3 class="fight-card-number">Fight #${match.match_number}</h3>
                    <span class="fight-card-status">${match.match_status}${match.match_bet_status ? " · " + match.match_bet_status : ""}</span>
                  </div>
                  <p class="fight-card-winner ${winnerClass}">${winnerText}</p>
                  <p class="fight-card-totals">Meron ${meronTotal}  |  Wala ${walaTotal}</p>
                  ${actions}
              </article>
          `;
          fightTable.append(row);
      });
  }

  function loadFightHistory() {
      $.ajax({
          url: `/matches/list`,
          type: "POST",
          headers: {
              "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
              renderFightHistory(response);
          },
          error: function (xhr) {
              toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to load fight history.");
          }
      });
  }

  window.Echo.channel("fight-history")
  .listen("MatchesUpdated", (e) => {
      renderFightHistory(e.match);
  });

  loadFightHistory();

  $("#toggle-meron").click(function () {
    let matchId = $("#match-id").val();

    $.ajax({
      url: `/matches/${matchId}/toggle-meron`,
      type: "POST",
      headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
      },
      success: function (response) {
        toastr.success(response.message);
      },
      error: function (xhr) {
        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to toggle Meron bet.");
      }
    });
  });

  // Toggle Wala Bet
  $("#toggle-wala").click(function () {
    let matchId = $("#match-id").val();

    $.ajax({
      url: `/matches/${matchId}/toggle-wala`,
      type: "POST",
      headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
      },
      success: function (response) {
        toastr.success(response.message);
      },
      error: function (xhr) {
        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to toggle Wala bet.");
      }
    });
  });

  function sideDetail(json) {
    if (!json) return '';
    let d; try { d = JSON.parse(json); } catch (err) { return ''; }
    const esc = (v) => $('<div>').text(v == null ? '' : String(v)).html();
    return [d.owner ? 'Owner: ' + esc(d.owner) : '', d.weight ? esc(d.weight) + ' g' : '', d.type ? esc(d.type) : '',
            d.wingband ? 'WB ' + esc(d.wingband) : '', d.legband ? 'LB ' + esc(d.legband) : ''].filter(Boolean).join(' · ');
  }

  // Fights called from matching: show their details, lock the entry names, allow Hold until betting opens.
  function renderCalledFight(m) {
    const linked = !!m.source_fight_uid;
    $('#meron-detail').html(linked ? sideDetail(m.meron_details) : '');
    $('#wala-detail').html(linked ? sideDetail(m.wala_details) : '');
    $('#meron_entry, #wala_entry').prop('readonly', linked);
    $('#called-note').toggleClass('hidden', !(linked && m.match_status === 'Ongoing' && !m.bet_opened_at));
    $('#hold-fight').toggleClass('hidden', !(linked && m.match_status === 'Ongoing' && !m.bet_opened_at && !m.hold_reason));
    $('#hold-banner').toggleClass('hidden', !m.hold_reason).text(m.hold_reason ? 'ON HOLD: ' + m.hold_reason + ' (waiting for matching to fix and re-send)' : '');
  }

  $("#hold-fight").click(function () {
    let matchId = $("#match-id").val();
    Swal.fire({
      title: "Put this fight on hold?",
      text: "It goes back to the matching operator to fix. Betting cannot be opened until it is re-sent.",
      input: "text",
      inputPlaceholder: "Reason, e.g. wrong wingband on Meron",
      inputValidator: (v) => !v || !v.trim() ? "Please give a reason." : undefined,
      showCancelButton: true,
      confirmButtonText: "Hold"
    }).then((result) => {
      if (!result.isConfirmed) return;
      $.ajax({
        url: `/matches/${matchId}/hold`, type: "POST", data: { reason: result.value },
        headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
        success: (response) => toastr.success(response.message),
        error: (xhr) => toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to hold the fight.")
      });
    });
  });

  function pollBridge() {
    $.get('/matches/bridge-status').done(function (s) {
      const el = $('#bridge-status');
      if (!s.enabled) { el.addClass('hidden'); return; }
      el.removeClass('hidden is-ok is-wait');
      if (s.pending > 0) el.addClass('is-wait').text('Matching link: ' + s.pending + ' update(s) waiting' + (s.last_error ? ' (' + s.last_error.substring(0, 80) + ')' : ''));
      else el.addClass('is-ok').text('Matching link: OK');
    });
  }
  pollBridge();
  setInterval(pollBridge, 5000);

  $("#open-bet").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to open the bet for this match.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#28a745",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, open it!"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Open/update-bet-status`,
          type: "POST",
          data: {
            meron_entry: $('#meron_entry').val(),
            wala_entry: $('#wala_entry').val(),
          },
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to change bet status.");
          }
        });
      }
    });
  });

  $("#close-bet").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to close the bet for this match.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes, close it!"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Closed/update-bet-status`,
          type: "POST",
          data: {
            meron_entry: $('#meron_entry').val(),
            wala_entry: $('#wala_entry').val(),
          },
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to change bet status.");
          }
        });
      }
    });
  });

  function getStarted()
  {
    $.ajax({
        url: `/matches/get-started`,
        type: "GET",
        success: function () {
          if ($("#main").hasClass("hidden")) {
            $("#main").removeClass("hidden");
            $("#get-started, #get-started-wrap").addClass("hidden");
          }
        },
        error: function (xhr) {
          toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to get started.");
        }
      });
  }

  $("#get-started").click(function () {
    getStarted();
  });


  $("#meron-wins").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to set Meron as the winner.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Completed/Meron/update-match-status`,
          type: "POST",
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to set match status.");
          }
        });
      }
    });
  });

  $("#wala-wins").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to set Wala as the winner.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Completed/Wala/update-match-status`,
          type: "POST",
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to set match status.");
          }
        });
      }
    });
  });

  $("#cancel-fight").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to cancel this fight.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Cancelled/-/update-match-status`,
          type: "POST",
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to set match status.");
          }
        });
      }
    });
  });

  $("#draw-fight").click(function () {
    let matchId = $("#match-id").val();

    Swal.fire({
      title: "Are you sure?",
      text: "You are about to draw this fight.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes"
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `/matches/${matchId}/Draw/-/update-match-status`,
          type: "POST",
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
          },
          success: function (response) {
            toastr.success(response.message);
          },
          error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to set match status.");
          }
        });
      }
    });
  });

  $(document).on("click", ".change-result", function () {
    let matchId = $(this).data("match-id");
    let matchNumber = $(this).data("match-number");
    let currentWinner = $(this).data("current-winner");

    Swal.fire({
        title: `Change Result for Fight #${matchNumber}`,
        text: "Select a new winner",
        icon: "question",
        input: "select",
        inputOptions: {
            "Meron": "Meron",
            "Wala": "Wala",
            "Draw": "Draw",
            "Cancelled": "Cancelled"
        },
        inputValue: currentWinner,
        showCancelButton: true,
        confirmButtonText: "Confirm",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#d33"
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let newWinner = result.value;
            let matchStatus = "Completed"; // Since it's being updated

            $.ajax({
                url: `/matches/${matchId}/${matchStatus}/${newWinner}/update-match-status`,
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                },
                data: {
                  dont_create: 1,
                },
                success: function (response) {
                    toastr.success(response.message);
                    getStarted();

                },
                error: function (xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to change result.");
                }
            });
        }
    });
});


$(document).on("change", ".toggle-display", function () {
    let matchId = $(this).data("match-id");
    let isDisplay = $(this).is(":checked") ? 1 : 0;

    $.ajax({
        url: `/matches/${matchId}/update-display`,
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        },
        data: { is_display: isDisplay },
        success: function (response) {
            toastr.success(response.message);
               window.Echo.channel("fight-history").listen("MatchesUpdated", () => {}); // Refresh table
        },
        error: function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Failed to update TV display status.");
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

    return formatted.replace(/₱\s?/, ''); // Removes ₱ symbol
}


</script>
@endsection

@section('content')

<div class="matches-app">
  <section class="matches-board">
    <div class="matches-board-top">
      <div>
        <p class="matches-kicker">Matches</p>
        <p class="matches-event">{{ $event->event_name ?? 'No event loaded' }}</p>
      </div>
      @if(!empty($event->event_date))
        <p class="matches-date">{{ \Carbon\Carbon::parse($event->event_date)->format('M j, Y') }}</p>
      @endif
    </div>

    <p class="matches-fight-label">Fight</p>
    <p class="matches-fight-number" id="match-number">—</p>
    <div class="matches-bars"><span></span><span></span></div>
    <div class="matches-led-wrap">
      <span id="match-bet-status" class="badge">No active fight</span>
    </div>
    <div class="matches-link hidden" id="bridge-status" title="Link with the matching system"></div>
    <span class="matches-total">Total <span id="total-bets">0.00</span></span>

    <div class="matches-meters">
      <div class="matches-side is-meron">
        <div class="matches-side-label">Meron</div>
        <div class="matches-side-total" id="meron-bet">0.00</div>
        <div class="matches-odds">Odds <span id="meron-odds">0.00</span></div>
        <span id="meron-bet-status" class="matches-side-status"></span>
      </div>
      <div class="matches-divider"></div>
      <div class="matches-side is-wala">
        <div class="matches-side-label">Wala</div>
        <div class="matches-side-total" id="wala-bet">0.00</div>
        <div class="matches-odds">Odds <span id="wala-odds">0.00</span></div>
        <span id="wala-bet-status" class="matches-side-status"></span>
      </div>
    </div>
  </section>

  <section class="matches-sheet">
    @if(!empty($event->event_date))
      <div class="matches-start" id="get-started-wrap">
        <p>Open the live fight board for this event.</p>
        <button class="btn" id="get-started">View Matches</button>
      </div>
    @endif

    <div class="hidden" id="main">
      <input type="hidden" id="match-id" value="">

      <div class="matches-grid">
        <div>
          <div class="matches-entries">
            <div class="matches-entry is-meron">
              <label for="meron_entry">Meron entry</label>
              <input type="text" id="meron_entry" name="meron_entry">
              <div class="matches-entry-detail" id="meron-detail"></div>
            </div>
            <div class="matches-entry is-wala">
              <label for="wala_entry">Wala entry</label>
              <input type="text" id="wala_entry" name="wala_entry">
              <div class="matches-entry-detail" id="wala-detail"></div>
            </div>
          </div>
          <div class="matches-hold hidden" id="hold-banner"></div>
          <p class="matches-called-note hidden" id="called-note">Called from the matching system. Check that the cocks in the pit match these details before opening. If not, press <strong>Hold</strong>.</p>

          <div class="matches-actions">
            <button class="btn btn-warning hidden" id="hold-fight">Hold</button>
            <button class="btn" id="open-bet">Open</button>
            <button class="btn" id="close-bet">Close</button>
            <button class="btn btn-danger" id="toggle-meron">Disable Meron Bet</button>
            <button class="btn btn-primary" id="toggle-wala">Disable Wala Bet</button>
            <button class="btn" id="meron-wins">Meron wins</button>
            <button class="btn" id="wala-wins">Wala wins</button>
            <button class="btn" id="draw-fight">Draw</button>
            <button class="btn" id="cancel-fight">Cancel</button>
          </div>
        </div>

        <div>
          <h4 class="matches-history-title">Fight history</h4>
          <div id="fight-history"></div>
        </div>
      </div>
    </div>
  </section>
</div>

@endsection
