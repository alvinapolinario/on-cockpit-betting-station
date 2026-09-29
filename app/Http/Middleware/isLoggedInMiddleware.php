<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class isLoggedInMiddleware
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (!session()->has('account_type')) {
      return $next($request);
    } else {
      if (session()->get('account_type') == 'Admin') {
        return redirect('/dashboard');
      } else {
        return redirect('/');
      }
    }
  }
}
