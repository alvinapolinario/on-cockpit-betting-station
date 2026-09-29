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

class MatchesUpdated implements ShouldBroadcastNow
{
  use InteractsWithSockets, SerializesModels;

  public $matches;

  public function __construct($matches)
  {
      $this->matches = $matches;
  }

  public function broadcastOn()
  {
      return new Channel('fight-history');
  }

  public function broadcastWith()
  {
      return ['match' => $this->matches]; // Data sent to JS
  }

  public function broadcastAs()
  {
      return 'MatchesUpdated';
  }
}
