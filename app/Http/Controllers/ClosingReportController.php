<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\EventClosing;
use App\Services\Closing\EventSealer;
use App\Services\Closing\KeyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClosingReportController extends Controller
{
  public function __construct(private EventSealer $sealer, private KeyStore $keys) {}

  public function index()
  {
    $events = DB::table('events')->orderByDesc('event_date')->orderByDesc('event_id')->get();
    $closings = EventClosing::all()->keyBy('event_id');

    $this->createLog(session()->get('account_id'), "Web App", "Opened the Closing Reports page");

    return view('closing-reports.index', [
      'events' => $events,
      'closings' => $closings,
      'hasSigningKey' => $this->keys->hasSigningKey(),
      'hasVpsKey' => $this->keys->hasVpsKey(),
      'requireApprover' => config('sealing.require_second_approver'),
    ]);
  }

  /** Live, unsealed report. Clearly marked as a preview; nothing is stored. */
  public function preview(int $event_id)
  {
    $report = $this->sealer->preview($event_id);
    return view('closing-reports.show', [
      'report' => $report,
      'closing' => null,
      'tellerNames' => $this->tellerNames($event_id),
    ]);
  }

  public function seal(Request $r, int $event_id)
  {
    $r->validate([
      'confirmation' => 'required|in:CLOSE',
      'approver_username' => config('sealing.require_second_approver') ? 'required|string' : 'nullable|string',
      'approver_password' => config('sealing.require_second_approver') ? 'required|string' : 'nullable|string',
    ], ['confirmation.in' => 'Type CLOSE to confirm.']);

    $closedBy = (int) session()->get('account_id');
    $approvedBy = null;

    if (config('sealing.require_second_approver') || $r->filled('approver_username')) {
      $approver = Account::where('username', $r->approver_username)->where('is_active', 1)->where('account_type', 'Admin')->first();
      if (!$approver || !Hash::check($r->approver_password, $approver->password)) {
        $this->createLog($closedBy, "Web App", "Event seal rejected for event #{$event_id}: invalid approver credentials");
        return back()->with('error', 'Approver credentials are invalid.');
      }
      if ((int) $approver->account_id === $closedBy) {
        return back()->with('error', 'The approver must be a different admin than the person closing the event.');
      }
      $approvedBy = (int) $approver->account_id;
    }

    try {
      $closing = $this->sealer->seal($event_id, $closedBy, $approvedBy);
    } catch (\Throwable $e) {
      $this->createLog($closedBy, "Web App", "Event seal failed for event #{$event_id}: " . $e->getMessage());
      return back()->with('error', $e->getMessage());
    }

    return redirect()->route('closing-reports.show', $closing->event_closing_id)
      ->with('success', "Event sealed. Seal code {$closing->seal_code}. Print this report for signatures.");
  }

  /** The sealed report, rendered only from the stored signed payload. */
  public function show(int $event_closing_id)
  {
    $closing = EventClosing::findOrFail($event_closing_id);
    $report = $closing->payloadData();

    $this->createLog(session()->get('account_id'), "Web App", "Viewed sealed closing report S" . sprintf('%04d', $closing->sequence_no));

    return view('closing-reports.show', [
      'report' => $report,
      'closing' => $closing,
      'tellerNames' => $this->tellerNames($closing->event_id),
    ]);
  }

  /** Encrypted package for the upload laptop. Only the VPS can decrypt it. */
  public function download(int $event_closing_id)
  {
    $closing = EventClosing::findOrFail($event_closing_id);

    try {
      $pkg = $this->sealer->package($closing);
    } catch (\RuntimeException $e) {
      return back()->with('error', $e->getMessage());
    }

    $accountId = (int) session()->get('account_id');
    $closing->update(['downloaded_at' => now()->format('Y-m-d H:i:s'), 'downloaded_by_account_id' => $accountId]);
    $this->createLog($accountId, "Web App", "Downloaded closing package {$pkg['filename']} sha256 " . hash('sha256', $pkg['contents']) . " seal {$closing->seal_code}");

    return response($pkg['contents'], 200, [
      'Content-Type' => 'application/octet-stream',
      'Content-Disposition' => 'attachment; filename="' . $pkg['filename'] . '"',
      'Cache-Control' => 'no-store',
    ]);
  }

  /** Records the acknowledgment code returned by the BIR Compliance System. */
  public function acknowledge(Request $r, int $event_closing_id)
  {
    $r->validate(['ack_code' => 'required|string|max:200|regex:/^[A-Za-z0-9\-:._]+$/']);
    $closing = EventClosing::findOrFail($event_closing_id);

    if ($closing->ack_code) {
      return back()->with('error', 'This closing was already acknowledged.');
    }

    $accountId = (int) session()->get('account_id');
    $closing->update(['ack_code' => $r->ack_code, 'ack_at' => now()->format('Y-m-d H:i:s'), 'ack_by_account_id' => $accountId]);
    $this->createLog($accountId, "Web App", "Recorded BIR Compliance acknowledgment for S" . sprintf('%04d', $closing->sequence_no) . ": {$r->ack_code}");

    return back()->with('success', 'Acknowledgment recorded.');
  }

  /** Teller names for printing only; never part of the signed payload. */
  private function tellerNames(int $eventId): array
  {
    return DB::table('event_tellers')
      ->join('tellers', 'tellers.teller_id', '=', 'event_tellers.teller_id')
      ->where('event_tellers.event_id', $eventId)
      ->pluck('tellers.teller_name', 'event_tellers.event_teller_id')
      ->all();
  }
}
