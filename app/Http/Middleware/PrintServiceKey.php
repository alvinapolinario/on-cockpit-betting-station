<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-key check for the print service (FastAPI on the same server): the key in
 * PRINT_SERVICE_KEY must match its LARAVEL_PRINT_KEY, sent as the X-Print-Key header.
 */
class PrintServiceKey
{
  public function handle(Request $request, Closure $next): Response
  {
    $expected = (string) config('services.print_service.key', '');
    if ($expected === '') {
      return response()->json(['message' => 'Print service key not configured'], 503);
    }

    $provided = (string) $request->header('X-Print-Key', '');
    if (!hash_equals($expected, $provided)) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
  }
}
