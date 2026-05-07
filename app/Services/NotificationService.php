<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public function __construct(private readonly TransactionalEmailService $emails)
    {
    }

    public function send(User $user, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $metadata = [], ?string $emailCategory = null): UserNotification
    {
        $emailCategory ??= $this->categoryForType($type);
        $emailResult = $emailCategory
            ? $this->emails->sendUserNotification($user, $emailCategory, $title, $body, $actionUrl)
            : ['status' => 'not_requested', 'error' => null];

        return UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
            'email_category' => $emailCategory,
            'email_status' => $emailResult['status'],
            'email_sent_at' => $emailResult['status'] === 'sent' ? now() : null,
            'email_error' => $emailResult['error'],
        ]);
    }

    private function categoryForType(string $type): ?string
    {
        return match (true) {
            str_starts_with($type, 'account.') => 'account',
            str_starts_with($type, 'official_response.'), str_starts_with($type, 'claimant.') => 'official_response',
            str_starts_with($type, 'evidence.'), str_starts_with($type, 'appeal.'), str_starts_with($type, 'abuse.') => 'moderation',
            str_starts_with($type, 'donation.') => 'donation',
            str_starts_with($type, 'bug_report.') => 'bug_report',
            default => null,
        };
    }
}
