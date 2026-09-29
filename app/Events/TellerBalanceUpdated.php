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

class TellerBalanceUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $event_tellers;

  public function __construct($event_tellers)
  {
      $this->event_tellers = $event_tellers;
  }

  public function broadcastOn()
  {
      return new Channel('event-tellers');
  }

  public function broadcastWith()
  {
      return ['event_tellers' => $this->event_tellers];
  }

  public function broadcastAs()
  {
      return 'TellerBalanceUpdated';
  }
}
