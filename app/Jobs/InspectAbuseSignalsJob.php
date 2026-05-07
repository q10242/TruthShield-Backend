<?php

namespace App\Jobs;

use App\Models\NewsUrl;
use App\Models\User;
use App\Models\Vote;
use App\Services\AbuseDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InspectAbuseSignalsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $newsUrlId,
        public ?int $voteId = null,
        public string $action = 'vote',
    ) {}

    public function handle(AbuseDetectionService $abuseDetection): void
    {
        $user = User::query()->find($this->userId);
        $newsUrl = NewsUrl::query()->find($this->newsUrlId);

        if (! $user || ! $newsUrl) {
            return;
        }

        if ($this->action === 'reaction' && $this->voteId) {
            $vote = Vote::query()->with('newsUrl')->find($this->voteId);
            if ($vote) {
                $abuseDetection->inspectReactionSignals($user, $vote);
            }

            return;
        }

        $vote = $this->voteId ? Vote::query()->find($this->voteId) : null;
        if ($vote) {
            $abuseDetection->inspectVoteSignals($user, $newsUrl, $vote);
        }
    }
}
