<?php

namespace App\Events;

use App\Domain\Checklists\DTOs\ChecklistProjection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChecklistUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChecklistProjection $projection
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('hub.country.'.strtolower($this->projection->summary->country).'.checklist');
    }

    public function broadcastAs(): string
    {
        return 'checklist.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'country' => $this->projection->summary->country,
            'summary' => $this->projection->summary->toArray(),
            'employees' => array_map(function ($item) {
                return $item->toArray();
            }, $this->projection->employees),
            'target' => $this->projection->target?->toArray(),
        ];
    }
}
