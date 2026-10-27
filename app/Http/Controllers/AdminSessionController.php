<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminSessionController extends Controller
{
  /**
   * Used by nginx auth_request to gate /print/* behind the admin login.
   * Returns 200 for Admin sessions, 401 otherwise (no redirects).
   */
  public function check(Request $request): Response
  {
    if ($request->session()->get('account_type') === 'Admin') {
      return response('OK', 200);
    }

    return response('Unauthorized', 401);
  }
}
