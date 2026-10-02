@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();

$sortedMatches = collect($matches ?? [])->sortBy('match_number')->values();
$recentMatches = collect($matches ?? [])->take(12)->values();
$trendRows = collect($categories ?? [])->values()->map(function ($fightNo, $index) use ($meronData, $walaData) {
    return [
        'n' => (int) $fightNo,
        'm' => (float) ($meronData[$index] ?? 0),
        'w' => (float) ($walaData[$index] ?? 0),
    ];
})->sortBy('n')->values();
$categories = $trendRows->pluck('n')->all();
$meronData = $trendRows->pluck('m')->all();
$walaData = $trendRows->pluck('w')->all();
$event_percentage = (float) ($event->event_percentage ?? 0);

$revenue = $sortedMatches->map(function ($match) use ($event_percentage) {
    if (in_array(strtolower($match->match_status ?? ''), ['cancelled', 'draw'])) {
        return 0;
    }
    $meron = (float) ($match->meron_total_bet ?? 0);
    $wala = (float) ($match->wala_total_bet ?? 0);
    return round(($meron + $wala) * $event_percentage, 2);
});

$completedMatches = $sortedMatches->where('match_status', 'Completed')->values();
$meronBets = $completedMatches->pluck('meron_total_bet')->values();
$walaBets = $completedMatches->pluck('wala_total_bet')->values();
$completedMatchNumbers = $completedMatches->pluck('match_number')->values();
$revenueNumbers = $sortedMatches->pluck('match_number')->values();

$meronWins = collect($matches ?? [])->where('match_winner', 'Meron')->count();
$walaWins = collect($matches ?? [])->where('match_winner', 'Wala')->count();
$completedCount = collect($matches ?? [])->where('match_status', 'Completed')->count();
$ongoingCount = collect($matches ?? [])->where('match_status', 'Ongoing')->count();
$cancelledCount = collect($matches ?? [])->where('match_status', 'Cancelled')->count();
$drawCount = collect($matches ?? [])->where('match_status', 'Draw')->count();
$estimatedRevenue = collect($revenue)->sum();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'Home')

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-style')
<style>
  .home-dash {
    --dash-bg: #151821;
    --dash-card: #1d2230;
    --dash-line: rgba(255,255,255,0.08);
    --dash-muted: #93a0b8;
    --dash-text: #f4f6fb;
    --dash-gold: #d4af37;
    --dash-meron: #ef4444;
    --dash-wala: #3b82f6;
  }

  .home-dash .dash-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 22px;
    margin-bottom: 18px;
    border-radius: 18px;
    background: linear-gradient(135deg, #1a2030 0%, #121620 100%);
    border: 1px solid var(--dash-line);
  }

  .home-dash .dash-kicker {
    margin: 0 0 4px;
    color: var(--dash-gold);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .home-dash .dash-hero h2,
  .home-dash .dash-card h3,
  .home-dash .dash-panel h3,
  .home-dash .dash-label {
    letter-spacing: normal;
    text-transform: none;
  }

  .home-dash .dash-hero h2 {
    margin: 0;
    color: var(--dash-text);
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
  }

  .home-dash .dash-hero p {
    margin: 6px 0 0;
    color: var(--dash-muted);
    font-size: 14px;
  }

  .home-dash .dash-event {
    min-width: 240px;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(212, 175, 55, 0.08);
    border: 1px solid rgba(212, 175, 55, 0.22);
  }

  .home-dash .dash-event strong {
    display: block;
    color: var(--dash-text);
    font-size: 15px;
    line-height: 1.35;
  }

  .home-dash .kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
  }

  .home-dash .dash-card {
    padding: 16px 18px;
    border-radius: 16px;
    background: var(--dash-card);
    border: 1px solid var(--dash-line);
    min-height: 118px;
  }

  .home-dash .dash-label {
    margin: 0 0 8px;
    color: var(--dash-muted);
    font-size: 12px;
    font-weight: 700;
  }

  .home-dash .dash-value {
    margin: 0;
    color: var(--dash-text);
    font-size: 28px;
    font-weight: 800;
    line-height: 1.15;
    word-break: break-word;
  }

  .home-dash .dash-card.is-fight .dash-value { color: var(--dash-gold); }
  .home-dash .dash-card.is-gross .dash-value { color: #60a5fa; }
  .home-dash .dash-card.is-bets .dash-value { color: #fb7185; }
  .home-dash .dash-card.is-claimed .dash-value { color: #4ade80; }
  .home-dash .dash-card.is-open .dash-value { color: #fbbf24; }

  .home-dash .dash-note {
    margin: 8px 0 0;
    color: var(--dash-muted);
    font-size: 12px;
  }

  .home-dash .chart-grid,
  .home-dash .lower-grid {
    display: grid;
    gap: 14px;
    margin-bottom: 14px;
  }

  .home-dash .chart-grid { grid-template-columns: 1fr; }
  .home-dash .split-grid { grid-template-columns: 1fr 1fr; }
  .home-dash .lower-grid { grid-template-columns: 360px 1fr; }

  .home-dash .dash-panel {
    padding: 16px 18px 10px;
    border-radius: 16px;
    background: var(--dash-card);
    border: 1px solid var(--dash-line);
  }

  .home-dash .dash-panel h3 {
    margin: 0 0 8px;
    color: var(--dash-text);
    font-size: 16px;
    font-weight: 700;
  }

  .home-dash .table-wrap {
    max-height: 560px;
    overflow: auto;
  }

  .home-dash table {
    margin: 0;
    color: var(--dash-text);
  }

  .home-dash .table > :not(caption) > * > * {
    background: transparent;
    border-bottom-color: var(--dash-line);
    color: var(--dash-text);
    white-space: nowrap;
  }

  .home-dash thead th {
    color: var(--dash-muted);
    font-size: 12px;
    font-weight: 700;
    position: sticky;
    top: 0;
    background: #222838;
    z-index: 1;
  }

  .home-dash .pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .home-dash .pill-meron { background: rgba(239, 68, 68, 0.16); color: #fca5a5; }
  .home-dash .pill-wala { background: rgba(59, 130, 246, 0.16); color: #93c5fd; }
  .home-dash .pill-done { background: rgba(74, 222, 128, 0.14); color: #86efac; }
  .home-dash .pill-live { background: rgba(251, 191, 36, 0.16); color: #fcd34d; }
  .home-dash .pill-muted { background: rgba(148, 163, 184, 0.12); color: #cbd5e1; }

  @media (max-width: 1199.98px) {
    .home-dash .kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .home-dash .lower-grid,
    .home-dash .split-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 991.98px) {
    .home-dash .kpi-grid { grid-template-columns: 1fr 1fr; }
    .home-dash .dash-hero { flex-direction: column; }
    .home-dash .dash-event { min-width: 0; width: 100%; }
  }

  @media (max-width: 575.98px) {
    .home-dash .kpi-grid { grid-template-columns: 1fr; }
    .home-dash .dash-value { font-size: 24px; }
  }
</style>
@endsection

@section('content')
<div class="home-dash">
  <div class="dash-hero">
    <div>
      <p class="dash-kicker">Dashboard</p>
      <h2>Welcome back, {{ session()->get('welcome_name') }}</h2>
      <p>Live event totals, fight results, and remittance watch-points.</p>
    </div>
    <div class="dash-event">
      <p class="dash-kicker">Current event</p>
      <strong>{{ $event->event_name ?? 'No active event' }}</strong>
    </div>
  </div>

  <div class="kpi-grid">
    <article class="dash-card is-fight">
      <p class="dash-label">Current fight</p>
      <p class="dash-value">{{ $current_match->match_number ?? '—' }}</p>
      <p class="dash-note">{{ $current_match ? 'Ongoing' : 'No fight in progress' }}</p>
    </article>
    <article class="dash-card is-gross">
      <p class="dash-label">Total gross</p>
      <p class="dash-value">₱{{ number_format($gross_bets ?? 0, 2) }}</p>
      <p class="dash-note">All bets placed · ₱{{ number_format($voided_bets ?? 0, 2) }} voided ({{ $voided_count ?? 0 }})</p>
    </article>
    <article class="dash-card is-bets">
      <p class="dash-label">Total bets</p>
      <p class="dash-value">₱{{ number_format($total_bets ?? 0, 2) }}</p>
      <p class="dash-note">Completed fights only</p>
    </article>
    <article class="dash-card is-claimed">
      <p class="dash-label">Claimed</p>
      <p class="dash-value">₱{{ number_format($claimed ?? 0, 2) }}</p>
      <p class="dash-note">Est. house: ₱{{ number_format($estimatedRevenue, 2) }}</p>
    </article>
    <article class="dash-card is-open">
      <p class="dash-label">Unclaimed</p>
      <p class="dash-value">₱{{ number_format($unclaimed ?? 0, 2) }}</p>
      <p class="dash-note">{{ $completedCount }} completed · {{ $drawCount }} draw</p>
    </article>
  </div>

  <div class="chart-grid">
    <section class="dash-panel">
      <h3>Betting trends</h3>
      <div id="bet-chart"></div>
    </section>
  </div>

  <div class="chart-grid split-grid">
    <section class="dash-panel">
      <h3>Total bets by fight</h3>
      <div id="total-bets-by-winner-chart"></div>
    </section>
    <section class="dash-panel">
      <h3>Estimated revenue</h3>
      <div id="revenue-chart"></div>
    </section>
  </div>

  <div class="lower-grid">
    <div>
      <section class="dash-panel">
        <h3>Winner distribution</h3>
        <div id="winner-distribution-chart"></div>
      </section>
      <section class="dash-panel mt-3">
        <h3>Fight status</h3>
        <div id="fight-status-chart"></div>
      </section>
    </div>
    <section class="dash-panel">
      <h3>Recent fights</h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Fight</th>
              <th>Meron</th>
              <th>Wala</th>
              <th>Winner</th>
              <th>Odds</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentMatches as $match)
              <tr>
                <td>{{ $match->match_number }}</td>
                <td>₱{{ number_format((float) $match->meron_total_bet, 2) }}</td>
                <td>₱{{ number_format((float) $match->wala_total_bet, 2) }}</td>
                <td>
                  @if ($match->match_winner == 'Meron')
                    <span class="pill pill-meron">Meron</span>
                  @elseif ($match->match_winner == 'Wala')
                    <span class="pill pill-wala">Wala</span>
                  @else
                    <span class="pill pill-muted">N/A</span>
                  @endif
                </td>
                <td>
                  @if ($match->match_winner == 'Meron')
                    {{ $match->meron_odds }}
                  @elseif ($match->match_winner == 'Wala')
                    {{ $match->wala_odds }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if ($match->match_status == 'Completed')
                    <span class="pill pill-done">Completed</span>
                  @elseif ($match->match_status == 'Ongoing')
                    <span class="pill pill-live">Ongoing</span>
                  @else
                    <span class="pill pill-muted">{{ $match->match_status }}</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6">No fights yet for this event.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
@endsection

@section('page-script')
<script>
  const dashTheme = {
    chart: {
      toolbar: { show: false },
      zoom: { enabled: false },
      foreColor: '#93a0b8',
      background: 'transparent',
      fontFamily: 'inherit'
    },
    grid: { borderColor: 'rgba(255,255,255,0.08)', strokeDashArray: 4 },
    legend: { labels: { colors: '#f4f6fb' } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    tooltip: { theme: 'dark' }
  };

  function moneyAxis(val) {
    const n = Number(val) || 0;
    if (Math.abs(n) >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (Math.abs(n) >= 1000) return Math.round(n / 1000) + 'k';
    return n.toFixed(0);
  }

  new ApexCharts(document.querySelector('#bet-chart'), {
    ...dashTheme,
    chart: { ...dashTheme.chart, type: 'line', height: 320 },
    series: [
      { name: 'Meron', data: @json($meronData) },
      { name: 'Wala', data: @json($walaData) }
    ],
    colors: ['#ef4444', '#3b82f6'],
    markers: { size: 0, hover: { size: 5 } },
    xaxis: {
      categories: @json($categories),
      tickAmount: 12,
      title: { text: 'Fight number' }
    },
    yaxis: {
      title: { text: 'Total bets' },
      labels: { formatter: moneyAxis }
    }
  }).render();

  new ApexCharts(document.querySelector('#total-bets-by-winner-chart'), {
    ...dashTheme,
    chart: { ...dashTheme.chart, type: 'bar', height: 300, stacked: true },
    series: [
      { name: 'Meron', data: @json($meronBets) },
      { name: 'Wala', data: @json($walaBets) }
    ],
    colors: ['#ef4444', '#3b82f6'],
    xaxis: {
      categories: @json($completedMatchNumbers),
      tickAmount: 10,
      title: { text: 'Fight number' }
    },
    yaxis: {
      labels: { formatter: moneyAxis }
    }
  }).render();

  new ApexCharts(document.querySelector('#revenue-chart'), {
    ...dashTheme,
    chart: { ...dashTheme.chart, type: 'area', height: 300 },
    series: [{ name: 'Revenue', data: @json($revenue) }],
    colors: ['#d4af37'],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
    xaxis: {
      categories: @json($revenueNumbers),
      tickAmount: 10,
      title: { text: 'Fight number' }
    },
    yaxis: {
      labels: { formatter: moneyAxis }
    }
  }).render();

  new ApexCharts(document.querySelector('#winner-distribution-chart'), {
    ...dashTheme,
    chart: { ...dashTheme.chart, type: 'donut', height: 260 },
    labels: ['Meron wins', 'Wala wins'],
    series: [{{ $meronWins }}, {{ $walaWins }}],
    colors: ['#ef4444', '#3b82f6'],
    stroke: { width: 0 },
    legend: { position: 'bottom' }
  }).render();

  new ApexCharts(document.querySelector('#fight-status-chart'), {
    ...dashTheme,
    chart: { ...dashTheme.chart, type: 'donut', height: 260 },
    labels: ['Completed', 'Ongoing', 'Cancelled'],
    series: [{{ $completedCount }}, {{ $ongoingCount }}, {{ $cancelledCount }}],
    colors: ['#22c55e', '#fbbf24', '#ef4444'],
    stroke: { width: 0 },
    legend: { position: 'bottom' }
  }).render();
</script>
@endsection
