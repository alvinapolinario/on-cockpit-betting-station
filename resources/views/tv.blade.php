@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$isMenu = false;
$isNavbar = false;
@endphp
@extends('layouts.blankLayout')

@section('title', 'TV')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('page-style')
<style>
  @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Rajdhani:wght@600;700&display=swap');

  html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #07080d !important;
    color: #f4f1ea;
    font-family: 'Rajdhani', 'Oswald', Impact, sans-serif;
  }

  .tv-stage {
    min-height: 100vh;
    padding: 1.4vh 1.6vw 1.2vh;
    display: flex;
    flex-direction: column;
    gap: 1.2vh;
    background:
      radial-gradient(1200px 520px at 50% -10%, rgba(212, 175, 55, 0.16), transparent 55%),
      linear-gradient(180deg, #10131c 0%, #07080d 42%, #050608 100%);
  }

  .tv-mast {
    display: grid;
    grid-template-columns: 1.1fr 1.4fr 1.1fr;
    align-items: center;
    gap: 1vw;
    padding: 1.2vh 1.4vw;
    border: 1px solid rgba(212, 175, 55, 0.28);
    background: rgba(10, 12, 18, 0.72);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
  }

  .tv-kicker,
  .tv-status-label {
    margin: 0;
    letter-spacing: 0.28em;
    text-transform: uppercase;
    font-size: clamp(12px, 1.1vw, 18px);
    color: #d4af37;
  }

  .tv-event {
    margin: 0.2vh 0 0;
    font-size: clamp(16px, 1.5vw, 28px);
    color: #c8c2b4;
    text-transform: uppercase;
  }

  .tv-fight {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .tv-fight-mark {
    width: clamp(110px, 11vw, 180px);
    height: clamp(110px, 11vw, 180px);
    display: grid;
    place-items: center;
    margin: 0.4vh 0 0;
    border-radius: 50%;
    border: 3px solid #d4af37;
    background:
      radial-gradient(circle at 35% 30%, rgba(255, 236, 179, 0.22), transparent 46%),
      radial-gradient(circle at 50% 50%, #1a1408 0%, #0b0c10 72%);
    box-shadow:
      0 0 0 6px rgba(212, 175, 55, 0.12),
      0 0 28px rgba(212, 175, 55, 0.28);
  }

  .tv-fight-number {
    margin: 0;
    line-height: 1;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(42px, 5.2vw, 88px);
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #f3e6c0;
  }

  .tv-status {
    text-align: right;
  }

  #match-bet-status {
    display: inline-block;
    min-width: 8vw;
    padding: 0.4vh 1.2vw;
    border: 1px solid #3de28a;
    color: #3de28a;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(28px, 4vw, 64px);
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  #match-bet-status.orange {
    border-color: #ff7a18;
    color: #ff7a18;
    background: rgba(255, 122, 24, 0.12);
    box-shadow: 0 0 24px rgba(255, 122, 24, 0.25);
  }

  .tv-lanes {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1vw;
    min-height: 0;
  }

  .tv-lane {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 3vh 2vw;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.08);
  }

  .tv-lane::after {
    content: "";
    position: absolute;
    inset: auto -20% -35% auto;
    width: 55%;
    height: 55%;
    background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 68%);
    pointer-events: none;
  }

  .bg-red {
    background:
      linear-gradient(160deg, #7a1024 0%, #c81e3a 46%, #3b0b16 100%) !important;
  }

  .bg-blue {
    background:
      linear-gradient(160deg, #0b3d4a 0%, #128aa3 46%, #05242c 100%) !important;
  }

  .grey-bg {
    background: linear-gradient(160deg, #3a3d44 0%, #1c1e24 100%) !important;
    filter: grayscale(0.45);
  }

  .tv-side {
    margin: 0;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(48px, 6vw, 110px);
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #fff;
    line-height: 0.9;
  }

  .tv-entry {
    margin: 0.4vh 0 1.2vh;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(36px, 5.4vw, 92px);
    line-height: 0.95;
    text-transform: uppercase;
    word-break: break-word;
  }

  .tv-amount {
    margin: 0.8vh 0 0;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(72px, 9.2vw, 168px);
    font-weight: 700;
    line-height: 0.88;
    color: #ffe7a3;
  }

  .tv-odds {
    margin: 1.2vh 0 0;
    font-size: clamp(28px, 3.6vw, 64px);
    letter-spacing: 0.08em;
    color: #fff;
  }

  .tv-disabled {
    display: none;
    margin-top: 1vh;
    letter-spacing: 0.22em;
    font-size: clamp(18px, 2vw, 34px);
    color: #fff;
  }

  .tv-core {
    width: 1.1vw;
    min-width: 10px;
    display: flex;
    align-items: stretch;
    justify-content: center;
    background: linear-gradient(180deg, transparent, rgba(212, 175, 55, 0.45), transparent);
  }

  .tv-pot-label {
    margin: 0;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    font-size: clamp(12px, 1vw, 16px);
    color: #9b9383;
  }

  .tv-pot {
    margin: 0;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(22px, 2.4vw, 40px);
    color: #f3e6c0;
  }

  .tv-tape {
    padding: 1.1vh 1vw 0.4vh;
    border-top: 1px solid rgba(212, 175, 55, 0.2);
  }

  .tv-tape-title {
    margin: 0 0 0.8vh;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    font-size: clamp(11px, 1vw, 15px);
    color: #9b9383;
  }

  #fight-history-grid {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: flex-start;
    overflow-x: auto;
    max-width: 100%;
    scrollbar-width: none;
  }

  #fight-history-grid::-webkit-scrollbar {
    display: none;
  }

  .fight-history-column {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 46px;
  }

  .fight-history-ball {
    width: 46px;
    min-height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
  }

  .red-ball { background: #c81e3a; box-shadow: 0 0 12px rgba(200, 30, 58, 0.45); }
  .blue-ball { background: #128aa3; box-shadow: 0 0 12px rgba(18, 138, 163, 0.45); }
  .gray-ball { background: #6b7280; }
  .green-ball { background: #1f8a4c; }

  .yellow { color: #d4af37; }
  .red { color: #c81e3a; }
  .green { color: #1f8a4c; }
  .blue { color: #128aa3; }
  .white { color: #fff; }
  .grey { color: #9b9383; }
  .orange { color: #ff7a18; }
  .dark { color: #1a1c22; }
  .grey-bg { background-color: #3a3d44 !important; }

  .hidden { display: none !important; }

  @keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.15; }
  }

  .blinking-text { animation: blink 1s infinite; }

  .full-screen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #fff;
  }

  .winner-overlay-content {
    width: min(90vw, 1400px);
    text-transform: uppercase;
  }

  .full-screen.winner-meron {
    background:
      radial-gradient(circle at 50% 20%, rgba(255,255,255,0.16), transparent 36%),
      linear-gradient(160deg, #9f1730, #3d0812);
  }

  .full-screen.winner-wala {
    background:
      radial-gradient(circle at 50% 20%, rgba(255,255,255,0.16), transparent 36%),
      linear-gradient(160deg, #0f6f84, #042228);
  }

  .full-screen.winner-cancelled,
  .full-screen.winner-neutral {
    background: linear-gradient(160deg, #4b5563, #111827);
  }

  .full-screen.winner-draw {
    background: linear-gradient(160deg, #1f8a4c, #0b2e1a);
  }

  .full-screen h1 {
    margin: 0;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(42px, 6vw, 96px);
    letter-spacing: 0.12em;
  }

  .full-screen h2 {
    margin: 2vh 0;
    font-family: 'Oswald', Impact, sans-serif;
    font-size: clamp(84px, 16vw, 240px);
    line-height: 0.86;
  }

  .full-screen #fight-number {
    font-size: clamp(42px, 7vw, 120px);
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
let isFullScreenMode = false;

function init() {
    $.ajax({
        url: `/tv/fight-history`,
        type: "GET",
        dataType: "json",
        success: function (response) {
            if (response && response.match) {
                renderCurrentMatch(response.match);
            }

            renderFightHistory((response && response.matches) || []);
        },
        error: function () {
            // Open/close betting already arrives over Echo. Do not toast on a one-off fetch miss.
        }
    });
}

function renderCurrentMatch(match) {
    $("#match-bet-status").text(match.match_bet_status);

    if(match.match_bet_status == 'Closed')
    {
      $("#match-bet-status").addClass("orange");
    }
    else
    {
      $("#match-bet-status").removeClass("orange");
    }

    $('#match-number').text(match.match_number);

    $('#meron-bet').text(formatCurrency(match.meron_total_bet));
    $('#wala-bet').text(formatCurrency(match.wala_total_bet));


    $('#meron-odds').text(match.meron_odds);
    $('#wala-odds').text(match.wala_odds);

    $('#total-bets').text(formatCurrency(match.meron_total_bet + match.wala_total_bet));

    $("#meron-bet-status").text(match.meron_bet_status ? "DISABLED" : "").toggle(!!match.meron_bet_status);
    $("#wala-bet-status").text(match.wala_bet_status ? "DISABLED" : "").toggle(!!match.wala_bet_status);

    if (match.meron_bet_status) {
        $("#card_meron").removeClass("bg-red").addClass("grey-bg");
    } else {
        $("#card_meron").removeClass("grey-bg").addClass("bg-red");
    }

    if (match.wala_bet_status) {
        $("#card_wala").removeClass("bg-blue").addClass("grey-bg");
    } else {
        $("#card_wala").removeClass("grey-bg").addClass("bg-blue");
    }
}

window.Echo.channel("match-updates")
.listen(".MatchUpdated", (e) => {
    renderCurrentMatch(e.match);
});

let meronWins = 0;
let walaWins = 0;

function renderFightHistory(matches) {
    if (!Array.isArray(matches) || matches.length === 0) return;

    let fightHistoryGrid = document.getElementById("fight-history-grid");
    fightHistoryGrid.innerHTML = "";

    let currentColumn;
    let lastResult = null;

    const reversedMatches = [...matches].reverse();

    reversedMatches.forEach((fight) => {


        if (!fight.match_winner && fight.match_status !== "Draw" && fight.match_status !== "Cancelled") return;

        let winnerColor;
        let resultType;

        if (fight.match_winner === "Meron") {
            winnerColor = "red-ball";
            resultType = "Meron";
        } else if (fight.match_winner === "Wala") {
            winnerColor = "blue-ball";
            resultType = "Wala";
        } else if (fight.match_status === "Draw") {
            winnerColor = "green-ball";
            resultType = "Draw";
        } else if (fight.match_status === "Cancelled") {
            winnerColor = "gray-ball";
            resultType = "Cancelled";
        }

       if (!currentColumn || currentColumn.children.length >= 5 || resultType !== lastResult) {
          currentColumn = document.createElement("div");
          currentColumn.classList.add("fight-history-column");
          fightHistoryGrid.appendChild(currentColumn);
          lastResult = resultType;
      }


        let ball = document.createElement("div");
        ball.classList.add("fight-history-ball", winnerColor);
        ball.textContent = fight.match_number;

        currentColumn.appendChild(ball);
    });

    fightHistoryGrid.scrollLeft = fightHistoryGrid.scrollWidth;





    // document.getElementById("meron-total-wins").textContent = meronWins;
    // document.getElementById("wala-total-wins").textContent = walaWins;
}

window.Echo.channel("fight-history")
.listen(".MatchesUpdated", (e) => {
    renderFightHistory(e.match);
});

init();


window.Echo.channel("tv-display")
    .listen(".TVDisplayUpdated", (e) => {

        // e.match contains the updated match data.
        if (e.match.is_display == 1) {
            // Display the match in full-screen mode.
            let fightNumber = e.match.match_number;
            let winner = e.match.match_winner;
            let odds = e.match.match_winner === "Meron" ? e.match.meron_odds : (e.match.match_winner === "Wala" ? e.match.wala_odds : null);
            showFullScreen(fightNumber, winner, odds, e.match.match_status);
        } else {
            if (e.match) {
                renderCurrentMatch(e.match);
            }
            hideFullScreen();
        }
    });

function formatCurrency(amount) {
    let formatted = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);

    return formatted.replace(/₱\s?/, '');
}

function showFullScreen(fightNumber, winner, odds, match_status) {
    isFullScreenMode = true;
    $("#main").addClass("hidden");
    $(".full-screen").remove();

    var winnerClass = "winner-neutral";
    var winnerSide = winner ? winner.toUpperCase() : "MATCH";
    var winnerText = winner ? "WINS" : "DISPLAYED";

    if(match_status == 'Draw')
    {
      winnerSide = "DRAW";
      winnerText = "";
      winnerClass = "winner-draw";
    }
    else if(match_status == 'Cancelled')
    {
      winnerSide = "CANCELLED";
      winnerText = "";
      winnerClass = "winner-cancelled";
    }
    else if(winner === "Meron")
    {
      winnerClass = "winner-meron";
    }
    else if(winner === "Wala")
    {
      winnerClass = "winner-wala";
    }

    let overlay = $(`
        <div class="full-screen ${winnerClass}">
            <div class="winner-overlay-content">
                <h1 class="fw-bolder yellow">Fight #<span class="fw-bold">${fightNumber}</span></h1>
                <h2>${winnerSide}</h2>
                <h1>${winnerText}</h1>
                <h1 class="yellow">${odds ? "PAYOUT " + odds : ""}</h1>
            </div>
        </div>
    `);

    $("body").append(overlay);
}

function hideFullScreen() {
    $(".full-screen").fadeOut(500, function () {
        $(this).remove();
        $("#main").removeClass("hidden");
        setTimeout(() => {
            const fightHistoryGrid = document.getElementById("fight-history-grid");
            fightHistoryGrid.scrollLeft = fightHistoryGrid.scrollWidth;
        }, 50); // 50ms is enough for DOM to reflow
        isFullScreenMode = false;
    });
}
</script>
@endsection

@section('content')
<div class="tv-stage" id="main">
  <header class="tv-mast">
    <div>
      <p class="tv-kicker">Live board</p>
      <p class="tv-event">{{ $event->event_name ?? 'No active event' }}</p>
    </div>
    <div class="tv-fight">
      <p class="tv-kicker">Fight</p>
      <div class="tv-fight-mark">
        <span class="tv-fight-number" id="match-number">—</span>
      </div>
    </div>
    <div class="tv-status">
      <p class="tv-status-label">Betting</p>
      <span id="match-bet-status">—</span>
    </div>
  </header>

  <section class="tv-lanes">
    <article class="tv-lane bg-red" id="card_meron">
      <p class="tv-side">MERON</p>
      <p class="tv-amount" id="meron-bet">0</p>
      <p class="tv-odds" id="meron-odds">0.00</p>
      <span id="meron-bet-status" class="tv-disabled blinking-text white"></span>
    </article>

    <div class="tv-core">
      <p class="tv-pot hidden" id="total-bets"></p>
    </div>

    <article class="tv-lane bg-blue" id="card_wala">
      <p class="tv-side">WALA</p>
      <p class="tv-amount" id="wala-bet">0</p>
      <p class="tv-odds" id="wala-odds">0.00</p>
      <span id="wala-bet-status" class="tv-disabled blinking-text white"></span>
    </article>
  </section>

  <footer class="tv-tape">
    <p class="tv-tape-title">Result tape</p>
    <div id="fight-history-grid" class="fight-history-grid"></div>
  </footer>
</div>
@endsection
