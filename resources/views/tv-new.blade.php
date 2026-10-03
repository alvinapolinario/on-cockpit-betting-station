@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$isMenu = false;
$isNavbar = false;
@endphp
@extends('layouts.layoutMaster')

@section('title', 'TV')

@section('vendor-style')

<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
@endsection

@section('page-style')
<style>

  body
  {
    background-color: black !important;
  }

  .yellow
  {
    color: #f2e42c;
  }

  .red
  {
    color: #bd0001;
  }

  .green
  {
    color: #136d3b;
  }

  .dark
  {
    color: #381719;
  }

  .bg-red
  {
    background-color: #bd0001 !important;
  }


  .blue
  {
    color: #000ac4;
  }

  .bg-blue
  {
    background-color: #000ac4 !important;
  }

  .white
  {
    color: white;
  }

  .grey
  {
    color: rgb(179, 157, 157);
  }


  .grey-bg
  {
    background-color: rgb(179, 157, 157);
  }

  .orange
  {
    color: orange;
  }

  .hidden {
    display: none !important;
  }

  @keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0; }
    100% { opacity: 1; }
  }

  .blinking-text {
    animation: blink 1s infinite;
  }

  /* Full-screen winner display */
  .full-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 5rem;
    color: white;
    transition: all 0.5s ease-in-out;
  }

  .winner-overlay-content {
    width: 100%;
    padding: 5vw;
    text-transform: uppercase;
  }

  .full-screen.winner-meron {
    background-color: #bd0001 !important;
  }

  .full-screen.winner-wala {
    background-color: #000ac4 !important;
  }

  .full-screen.winner-cancelled,
  .full-screen.winner-neutral {
    background-color: grey !important;
  }

  .full-screen.winner-draw {
    background-color: #136d3b !important; /* Or any green shade you want */
  }

  .full-screen h1 {
    font-size: clamp(72px, 11vw, 180px);
    font-weight: bold;
    line-height: 0.95;
    margin: 0;
  }

  .full-screen h2 {
    font-size: clamp(96px, 18vw, 280px);
    font-weight: 900;
    line-height: 0.9;
    margin: 32px 0;
  }

  .full-screen #fight-number {
    font-size: clamp(42px, 7vw, 120px);
  }

  .total-winner-ball {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        font-weight: bold;
        color: white;
    }

  .fight-history-grid {
        display: grid;
        grid-template-columns: repeat(25, 40px); /* 30 columns */
        grid-template-rows: repeat(15, 40px); /* 15 rows */
        gap: 5px;
        justify-content: center;
        margin-top: 20px;
        max-width: 1300px; /* Adjusted for 30 columns */
        margin-left: auto;
        margin-right: auto;
    }

    /* Ball styles */
    .fight-history-ball {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        color: white;
    }

    /* Red ball for Meron */
    .red-ball {
        background-color: #bd0001;
    }

    /* Blue ball for Wala */
    .blue-ball {
        background-color: #000ac4;
    }

    .gray-ball {
        background-color: gray;
    }

    .green-ball {
        background-color: #136d3b;
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
        success: function (response) {
            if (response.match) {
                renderCurrentMatch(response.match);
            }

            renderFightHistory(response.matches || []);
        },
        error: function () {
            toastr.error("Failed to load fight history.");
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


    $('#meron-odds').text("PAYOUT=" + match.meron_odds);
    $('#wala-odds').text("PAYOUT=" + match.wala_odds);

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


    meronWins = 0;
    walaWins = 0;

    const reversedMatches = [...matches].reverse();


    reversedMatches.forEach((fight) => {
        if (!fight.match_winner && fight.match_status !== "Draw" && fight.match_status !== "Cancelled") return;

        let winnerColor;
        if (fight.match_winner === "Meron") {
            winnerColor = "red-ball";
            meronWins++;
        } else if (fight.match_winner === "Wala") {
            winnerColor = "blue-ball";
            walaWins++;
        } else if (fight.match_status === "Draw") {
            winnerColor = "green-ball";
        } else {
            winnerColor = "gray-ball";
        }

        let ball = document.createElement("div");
        ball.classList.add("fight-history-ball", winnerColor);
        ball.textContent = fight.match_number;

        fightHistoryGrid.appendChild(ball);
    });


    while (fightHistoryGrid.children.length > 450) {
        fightHistoryGrid.removeChild(fightHistoryGrid.firstChild);
    }


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
            init();
            hideFullScreen();
        }
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
        isFullScreenMode = false;
    });
}
</script>
@endsection

@section('content')
<div class="container-fluid">


  <div class="row" id="main">

    <div class="row">
      <div class="col-6">
      <iframe
        src="https://yourdomain.com/overlay.html"
        width="100%"
        height="500"
        frameborder="0"
        style="border:0; background: transparent;"
        allowtransparency="true">
      </iframe>
    </div>


      <div class="col-6">
        <div class="col-md-12">
      <div class="row">
        <div class="col-6 text-center">
          <h2 class="yellow" style="font-size: 80px;">FIGHT # <b id="match-number" style="font-size: 80px;"></b></h2>
        </div>
        <div class="col-6 text-center">
          <h2 class="yellow" style="font-size: 80px;">
            <b id="match-bet-status" style="font-size: 80px; text-transform: uppercase"></b>
          </h2>
        </div>
      </div>


      <div class="row">
        <div class="col-md-6">

          <div class="card bg-red" id="card_meron">

            <div class="card-body text-center">
              <br>
              <br>
              <h4 class="fw-bolder" style="font-size: 80px; margin-top: -20px;">MERON</h4>
              <h2 class="fw-bolder yellow" id="meron-bet" style="font-size: 100px; margin-top: -20px;"></h2>
              <h3 id="meron-odds" style="font-size: 70px; margin-top: -20px;  margin-bottom: -15px;"></h3>
              <span id="meron-bet-status" class=" fw-bold blinking-text white" style="font-size: 60px; margin-top: -80px"></span>
              <br>
            </div>
          </div>
        </div>

        <div class="col-md-6">


          <div class="card bg-blue" id="card_wala">

            <div class="card-body text-center">
              <br>
              <br>
              <h4 class="fw-bolder" style="font-size: 80px; margin-top: -20px;">WALA</h4>
              <h2 class="fw-bolder yellow" id="wala-bet" style="font-size: 100px; margin-top: -20px;"></h2>
              <h3 id="wala-odds" style="font-size: 70px; margin-top: -20px; margin-bottom: -15px;"></h3>
              <span id="wala-bet-status" class=" fw-bold blinking-text white" style="font-size: 60px; margin-top: -80px"></span>
              <br>
            </div>
          </div>
        </div>
      </div>
    </div>
      </div>
    </div>


    <div class="row m-5">
      <div class="col-md-12 text-center">
          <h2 class="white" style="font-size: 20px; margin-top: -20px;">Fight History</h2>


          {{-- <div class="d-flex justify-content-center align-items-center mb-4">
            <div class="total-winner-ball red-ball me-4">
                <span id="meron-total-wins">0</span>
            </div>
            <div class="total-winner-ball blue-ball">
                <span id="wala-total-wins">0</span>
            </div> --}}
        </div>



          <div id="fight-history-grid" class="fight-history-grid"></div>
      </div>
    </div>


  </div>
</div>
@endsection
