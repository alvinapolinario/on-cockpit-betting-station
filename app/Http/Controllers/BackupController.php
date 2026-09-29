<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Backup\DatabaseBackup;
use App\Services\Closing\KeyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BackupController extends Controller
{
  public function __construct(private DatabaseBackup $backups, private KeyStore $keys) {}

  public function index()
  {
    $this->createLog(session()->get('account_id'), "Web App", "Opened the Backups page");

    return view('backups.index', [
      'backups' => $this->keys->hasSigningKey() ? $this->backups->list() : [],
      'journal' => array_slice($this->backups->journalEntries(), 0, 20),
      'hasBackupKey' => $this->keys->hasBackupKey(),
      'hasSigningKey' => $this->keys->hasSigningKey(),
      'activeEvent' => DB::table('events')->where('event_status', 'Active')->orderByDesc('event_id')->first(),
      'requireApprover' => config('backup.require_second_approver'),
      'rawImportAllowed' => $this->backups->rawImportAllowed(),
      'importFiles' => $this->backups->rawImportAllowed() ? $this->backups->importFiles() : [],
      'importDir' => $this->backups->rawImportAllowed() ? $this->backups->importDir() : null,
    ]);
  }

  /** TESTING ONLY: parse and whitelist a raw .sql dump without changing anything. */
  public function checkImport(string $file)
  {
    try {
      $r = $this->backups->checkSqlDump($file);
    } catch (\Throwable $e) {
      return back()->with('error', "Check FAILED for {$file}: " . $e->getMessage());
    }
    return back()->with('success', "{$file} looks valid: {$r['statements']} statements, {$r['tables']} tables, {$r['views']} views, {$r['routines']} functions, {$r['triggers']} triggers. SHA-256 " . substr($r['sha256'], 0, 16) . '…');
  }

  /** TESTING ONLY: replace the database with a raw .sql dump. */
  public function restoreImport(Request $r, string $file)
  {
    $approvedBy = $this->authorizeRestore($r, $file);
    if (!is_int($approvedBy) && $approvedBy !== null) return $approvedBy;

    try {
      $res = $this->backups->restoreSqlDump($file, [
        'account_id' => (int) session()->get('account_id'),
        'approved_by' => $approvedBy,
        'reason' => $r->reason,
        'source' => 'web',
      ]);
    } catch (\Throwable $e) {
      return back()->with('error', $e->getMessage());
    }

    return redirect()->route('backups')->with('success',
      "TESTING import complete: {$res['file']} ({$res['statements']} statements, {$res['rows']} rows, {$res['seconds']}s). The previous state was saved as {$res['prerestore_backup']}.");
  }

  public function store()
  {
    try {
      $m = $this->backups->create('manual', (int) session()->get('account_id'));
    } catch (\Throwable $e) {
      return back()->with('error', 'Backup failed: ' . $e->getMessage());
    }
    return back()->with('success', "Backup created: {$m['file']} (" . array_sum($m['row_counts']) . ' rows).');
  }

  public function verify(string $file)
  {
    try {
      $r = $this->backups->verify($file);
    } catch (\Throwable $e) {
      $this->createLog(session()->get('account_id'), "Web App", "Backup verification FAILED for {$file}: " . $e->getMessage());
      return back()->with('error', "Verification FAILED for {$file}: " . $e->getMessage());
    }
    $this->createLog(session()->get('account_id'), "Web App", "Backup verified: {$file}");
    return back()->with('success', "Verified {$file}: signature, hash, encryption and {$r['rows']} rows in {$r['tables']} tables all check out.");
  }

  public function restore(Request $r, string $file)
  {
    $approvedBy = $this->authorizeRestore($r, $file);
    if (!is_int($approvedBy) && $approvedBy !== null) return $approvedBy;

    try {
      $res = $this->backups->restore($file, [
        'account_id' => (int) session()->get('account_id'),
        'approved_by' => $approvedBy,
        'reason' => $r->reason,
        'source' => 'web',
      ]);
    } catch (\Throwable $e) {
      return back()->with('error', $e->getMessage());
    }

    return redirect()->route('backups')->with('success',
      "Database restored from {$res['file']} ({$res['rows']} rows, {$res['seconds']}s). The previous state was saved as {$res['prerestore_backup']}.");
  }

  /**
   * Shared checks for any restore: confirmation, reason, betting stopped,
   * and a second admin. Returns the approver's account id (or null when not
   * required), or a redirect response when the request is rejected.
   */
  private function authorizeRestore(Request $r, string $file)
  {
    $needApprover = config('backup.require_second_approver');
    $activeEvent = DB::table('events')->where('event_status', 'Active')->exists();

    $r->validate([
      'confirmation' => 'required|in:RESTORE',
      'reason' => 'required|string|min:10|max:500',
      'approver_username' => $needApprover ? 'required|string' : 'nullable|string',
      'approver_password' => $needApprover ? 'required|string' : 'nullable|string',
      'betting_stopped' => $activeEvent ? 'accepted' : 'nullable',
    ], [
      'confirmation.in' => 'Type RESTORE to confirm.',
      'betting_stopped.accepted' => 'An event is active. Confirm that betting and payouts have stopped before restoring.',
    ]);

    $accountId = (int) session()->get('account_id');
    $approvedBy = null;
    if ($needApprover || $r->filled('approver_username')) {
      $approver = Account::where('username', $r->approver_username)->where('is_active', 1)->where('account_type', 'Admin')->first();
      if (!$approver || !Hash::check($r->approver_password, $approver->password)) {
        $this->createLog($accountId, "Web App", "Database restore REJECTED for {$file}: invalid approver credentials");
        return back()->with('error', 'Approver credentials are invalid.');
      }
      if ((int) $approver->account_id === $accountId) {
        return back()->with('error', 'The approver must be a different admin than the person restoring.');
      }
      $approvedBy = (int) $approver->account_id;
    }
    return $approvedBy;
  }
}
