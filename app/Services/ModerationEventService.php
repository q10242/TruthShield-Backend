<?php

namespace App\Services;

use App\Models\ModerationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ModerationEventService
{
    public function record(Request $request, string $eventType, ?Model $subject, string $publicReason, array $metadata = []): ModerationEvent
    {
        return ModerationEvent::query()->create([
            'user_id' => $request->user()?->id,
            'event_type' => $eventType,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'public_reason' => $publicReason,
            'metadata' => $metadata,
        ]);
    }
}
