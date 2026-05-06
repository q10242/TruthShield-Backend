<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public function send(User $user, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $metadata = []): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }
}
