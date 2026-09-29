<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authenticates the mobile / desktop apps.
 *
 * Usage: account.token:teller | account.token:admin | account.token:any
 *
 * The app sends "Authorization: Bearer <token>". Tokens are stored as SHA-256
 * hashes and expire. After authentication, the caller's identity comes from
 * the TOKEN, never from the request: event_teller_id / teller_id (tellers) or
 * admin_id (admins) are overwritten with the token owner's ids, and a request
 * that claims a different identity is rejected.
 */
class CheckAccountToken
{
  public function handle(Request $request, Closure $next, string $role = 'any')
  {
    $plain = $request->bearerToken();
    if (!$plain) {
      return response()->json(['success' => false, 'message' => 'Not logged in. Please log in again.'], 401);
    }

    $pat = PersonalAccessToken::where('token', hash('sha256', $plain))->first();
    if (!$pat || !$pat->expires_at || $pat->expires_at->isPast()) {
      return response()->json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }

    $type = $pat->tokenable_type === 'EventAdmin' ? 'admin' : ($pat->tokenable_type === 'EventTeller' ? 'teller' : null);
    if ($type === null || ($role !== 'any' && $role !== $type)) {
      $this->logDenied($request, $pat, "role '{$type}' used on a '{$role}' endpoint");
      return response()->json(['success' => false, 'message' => 'Not allowed for this account.'], 403);
    }

    if ($type === 'teller') {
      $owner = DB::table('event_tellers')
        ->join('events', 'events.event_id', '=', 'event_tellers.event_id')
        ->join('tellers', 'tellers.teller_id', '=', 'event_tellers.teller_id')
        ->join('accounts', 'accounts.account_id', '=', 'tellers.account_id')
        ->where('event_tellers.event_teller_id', $pat->tokenable_id)
        ->select('event_tellers.event_teller_id', 'event_tellers.teller_id', 'events.event_status', 'accounts.is_active', 'accounts.account_id')
        ->first();
      if (!$owner || $owner->event_status !== 'Active' || !$owner->is_active) {
        return response()->json(['success' => false, 'message' => 'Session is no longer valid for this event. Please log in again.'], 401);
      }
      foreach (['event_teller_id' => $owner->event_teller_id, 'teller_id' => $owner->teller_id] as $field => $value) {
        if ($request->filled($field) && (int) $request->input($field) !== (int) $value) {
          $this->logDenied($request, $pat, "teller token for {$field}={$value} tried to act as {$field}=" . $request->input($field));
          return response()->json(['success' => false, 'message' => 'Not allowed: request does not match the logged-in teller.'], 403);
        }
      }
      $request->merge(['event_teller_id' => (int) $owner->event_teller_id, 'teller_id' => (int) $owner->teller_id]);
      $request->attributes->set('account_id', (int) $owner->account_id);
    } else {
      $owner = DB::table('admins')
        ->join('accounts', 'accounts.account_id', '=', 'admins.account_id')
        ->where('admins.admin_id', $pat->tokenable_id)
        ->select('admins.admin_id', 'accounts.is_active', 'accounts.account_id')
        ->first();
      if (!$owner || !$owner->is_active) {
        return response()->json(['success' => false, 'message' => 'Account is not active. Please log in again.'], 401);
      }
      if ($request->filled('admin_id') && (int) $request->input('admin_id') !== (int) $owner->admin_id) {
        $this->logDenied($request, $pat, "admin token for admin_id={$owner->admin_id} tried to act as admin_id=" . $request->input('admin_id'));
        return response()->json(['success' => false, 'message' => 'Not allowed: request does not match the logged-in admin.'], 403);
      }
      $request->merge(['admin_id' => (int) $owner->admin_id]);
      $request->attributes->set('account_id', (int) $owner->account_id);
    }

    $request->attributes->set('token_role', $type);
    $pat->forceFill(['last_used_at' => now()])->saveQuietly();

    return $next($request);
  }

  private function logDenied(Request $request, PersonalAccessToken $pat, string $why): void
  {
    DB::table('transactions')->insert([
      'account_id' => -1,
      'transaction_type' => 'API',
      'transaction_message' => "API access DENIED on /{$request->path()}: {$why} (token #{$pat->id}, IP {$request->ip()})",
      'transaction_datetime' => date('Y-m-d H:i:s'),
    ]);
  }
}
