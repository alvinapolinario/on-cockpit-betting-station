<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CashOutUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $cash_outs;

  public function __construct($cash_outs)
  {
      $this->cash_outs = $cash_outs;
  }

  public function broadcastOn()
  {
      return new Channel('cash-outs');
  }

  public function broadcastWith()
  {
      return ['cash_outs' => $this->cash_outs];
  }

  public function broadcastAs()
  {
      return 'CashOutUpdated';
  }


}
