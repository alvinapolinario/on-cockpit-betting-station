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

class EventUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $event;

  public function __construct($event)
  {
      $this->event = $event;
  }

  public function broadcastOn()
  {
      return new Channel('event-updates');
  }

  public function broadcastWith()
  {
      return ['event' => $this->event];
  }

  public function broadcastAs()
  {
      return '.EventUpdated';
  }
}
