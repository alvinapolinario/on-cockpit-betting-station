<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperatorMiddleware
{
  /**
   * Lets Admins and Operators through. Operators may only use the Matches routes
   * placed under this middleware; everything else stays behind isAdmin.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (in_array(session()->get('account_type'), ['Admin', 'Operator'], true)) {
      return $next($request);
    } else {
      return redirect('/');
    }
  }
}
