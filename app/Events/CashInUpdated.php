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

class CashInUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $cash_ins;

  public function __construct($cash_ins)
  {
      $this->cash_ins = $cash_ins;
  }

  public function broadcastOn()
  {
      return new Channel('cash-ins');
  }

  public function broadcastWith()
  {
      return ['cash_ins' => $this->cash_ins];
  }

  public function broadcastAs()
  {
      return 'CashInUpdated';
  }

}
