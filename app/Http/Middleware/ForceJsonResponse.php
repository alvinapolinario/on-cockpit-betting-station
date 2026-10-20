<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The mobile / desktop apps do not send "Accept: application/json", so Laravel
 * would answer validation failures with a 302 redirect to an HTML page that the
 * apps cannot parse. Forcing the header makes API errors come back as JSON.
 */
class ForceJsonResponse
{
  public function handle(Request $request, Closure $next)
  {
    $request->headers->set('Accept', 'application/json');

    return $next($request);
  }
}
