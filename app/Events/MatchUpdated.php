<?php

namespace App\Events;

use App\Models\Fight;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MatchUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $match;

  public function __construct($match)
  {
      $this->match = $match;
  }

  public function broadcastOn()
  {
      return new Channel('match-updates'); // Must match the JS listener
  }

  public function broadcastWith()
  {
      return ['match' => $this->match]; // Data sent to JS
  }

  public function broadcastAs()
  {
      return 'MatchUpdated';
  }

}
