<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public static function createLog($account_id, $transaction_type,$transaction_message)
    {
      $log = new Transaction;
      $log->account_id = $account_id;
      $log->transaction_type = $transaction_type;
      $log->transaction_message = $transaction_message;
      $log->transaction_datetime = date('Y-m-d H:i:s');
      $log->save();
    }

    public static function getDevice($r)
    {
      $userAgent = $r->header('User-Agent');


      $browser = 'Unknown Browser';
      if (strpos($userAgent, 'Chrome') !== false) {
          $browser = 'Chrome';
      } elseif (strpos($userAgent, 'Firefox') !== false) {
          $browser = 'Firefox';
      } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
          $browser = 'Safari';
      } elseif (strpos($userAgent, 'Edge') !== false) {
          $browser = 'Edge';
      }

      $platform = 'Unknown Platform';
      if (strpos($userAgent, 'Windows') !== false) {
          $platform = 'Windows';
      } elseif (strpos($userAgent, 'Macintosh') !== false || strpos($userAgent, 'Mac OS') !== false) {
          $platform = 'Mac OS';
      } elseif (strpos($userAgent, 'Linux') !== false) {
          $platform = 'Linux';
      } elseif (strpos($userAgent, 'Android') !== false) {
          $platform = 'Android';
      } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
          $platform = 'iOS';
      }


      $device = 'Desktop';
      if (strpos($userAgent, 'Mobi') !== false) {
          $device = 'Mobile';
      }


      $log_message = " IP: " . $r->ip() .
                          " | Browser: $browser" .
                          " | Platform: $platform" .
                          " | Device: $device";

                          return $log_message;
    }
}
