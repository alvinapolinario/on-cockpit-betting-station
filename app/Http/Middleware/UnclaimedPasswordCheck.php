<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class UnclaimedPasswordCheck
{
    public function handle(Request $request, Closure $next)
    {
        if (!Session::get('unclaimed_authenticated')) {
            return redirect()->route('unclaimed.password');
        }

        return $next($request);
    }
}
