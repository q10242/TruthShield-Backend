<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\NewsUrl;
use App\Services\BotProtectionService;
use App\Services\TrustScoreService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CommentController extends Controller
{
    private const MAX_BODY = 500;
    private const REPORT_HIDE_THRESHOLD = 3;

    public function index(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'news_url' => ['required', 'url', 'max:4096'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $newsUrl = $this->newsUrlFor($fingerprints, $validated['news_url']);
        if (! $newsUrl) {
            return response()->json(['data' => [], 'meta' => ['total' => 0, 'next_cursor' => null]]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $cursor = isset($validated['cursor']) ? (int) $validated['cursor'] : PHP_INT_MAX;

        $query = Comment::query()
            ->with(['user:id,display_name,trust_score,selected_badge_id,public_identity_label', 'user.selectedBadge:id,name,color', 'replies.user:id,display_name,trust_score,public_identity_label'])
            ->where('subject_type', Comment::SUBJECT_NEWS_URL)
            ->where('subject_id', $newsUrl->id)
            ->whereNull('parent_id')
            ->whereNull('hidden_at')
            ->where('id', '<', $cursor)
            ->orderByDesc('id')
            ->limit($perPage);

        $comments = $query->get();
        $total = Comment::query()
            ->where('subject_type', Comment::SUBJECT_NEWS_URL)
            ->where('subject_id', $newsUrl->id)
            ->whereNull('parent_id')
            ->whereNull('hidden_at')
            ->count();

        $myReactions = [];
        if ($request->user()) {
            $commentIds = $comments->pluck('id')->merge($comments->flatMap(fn ($c) => $c->replies->pluck('id')))->unique()->values();
            $myReactions = CommentReaction::query()
                ->whereIn('comment_id', $commentIds)
                ->where('user_id', $request->user()->id)
                ->pluck('helpful', 'comment_id')
                ->all();
        }

        return response()->json([
            'data' => $comments->map(fn ($c) => $this->commentPayload($c, $myReactions)),
            'meta' => [
                'total' => $total,
                'next_cursor' => $comments->count() === $perPage ? $comments->last()?->id : null,
            ],
        ]);
    }

    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        TrustScoreService $trustScores,
        BotProtectionService $botProtection,
    ): JsonResponse {
        if ($response = $botProtection->enforce($request, 'comment.create')) {
            return $response;
        }

        $validated = $request->validate([
            'news_url' => ['required', 'url', 'max:4096'],
            'body' => ['required', 'string', 'min:2', 'max:' . self::MAX_BODY],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
            'challenge_retry' => ['nullable', 'boolean'],
        ]);

        $newsUrl = $this->newsUrlFor($fingerprints, $validated['news_url'], true);

        if (isset($validated['parent_id'])) {
            $parent = Comment::query()->find($validated['parent_id']);
            if (! $parent || $parent->subject_id !== $newsUrl->id || $parent->parent_id !== null) {
                return response()->json(['message' => 'Invalid parent comment.'], 422);
            }
        }

        $comment = Comment::query()->create([
            'user_id' => $request->user()->id,
            'subject_type' => Comment::SUBJECT_NEWS_URL,
            'subject_id' => $newsUrl->id,
            'source_news_url_id' => $newsUrl->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
            'weight_score' => $trustScores->voteWeightFor($request->user()),
        ]);

        $comment->load(['user:id,display_name,trust_score,selected_badge_id,public_identity_label', 'user.selectedBadge:id,name,color']);

        return response()->json(['data' => $this->commentPayload($comment, [])], 201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comment->update(['hidden_at' => now()]);

        return response()->json(['message' => 'Comment removed.']);
    }

    public function react(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        $existing = CommentReaction::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            if ($existing->helpful === $validated['helpful']) {
                // Toggle off
                $existing->delete();
                $column = $validated['helpful'] ? 'helpful_count' : 'unhelpful_count';
                $comment->decrement($column);
            } else {
                // Switch reaction
                $old = $existing->helpful ? 'helpful_count' : 'unhelpful_count';
                $new = $validated['helpful'] ? 'helpful_count' : 'unhelpful_count';
                $existing->update(['helpful' => $validated['helpful']]);
                $comment->decrement($old);
                $comment->increment($new);
            }
        } else {
            CommentReaction::query()->create([
                'comment_id' => $comment->id,
                'user_id' => $request->user()->id,
                'helpful' => $validated['helpful'],
            ]);
            $column = $validated['helpful'] ? 'helpful_count' : 'unhelpful_count';
            $comment->increment($column);
        }

        $comment->refresh();

        return response()->json([
            'helpful_count' => $comment->helpful_count,
            'unhelpful_count' => $comment->unhelpful_count,
            'my_reaction' => $existing && !$existing->exists ? null : ($validated['helpful'] ? 'helpful' : 'unhelpful'),
        ]);
    }

    public function report(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        // Simple report: track via a flag on the comment (no separate table)
        // Auto-hide when report threshold reached
        $reportKey = "comment_report_{$comment->id}_{$request->user()->id}";
        if (cache()->has($reportKey)) {
            return response()->json(['message' => 'Already reported.'], 422);
        }

        cache()->put($reportKey, true, now()->addDays(30));

        $reportCountKey = "comment_report_count_{$comment->id}";
        $reportCount = (int) cache()->increment($reportCountKey);
        cache()->put($reportCountKey, $reportCount, now()->addDays(30));

        if ($reportCount >= self::REPORT_HIDE_THRESHOLD) {
            $comment->update(['hidden_at' => now()]);
        }

        return response()->json(['message' => 'Report submitted.']);
    }

    private function newsUrlFor(UrlFingerprintService $fingerprints, string $url, bool $create = false): ?NewsUrl
    {
        try {
            $fingerprint = $fingerprints->fingerprint($url);
        } catch (InvalidArgumentException $exception) {
            throw new HttpResponseException(response()->json(['message' => $exception->getMessage()], 422));
        }

        return $create
            ? NewsUrl::query()->firstOrCreate(
                ['hash' => $fingerprint['hash']],
                [
                    'original_url' => $fingerprint['original_url'],
                    'normalized_url' => $fingerprint['normalized_url'],
                    'voting_closes_at' => now()->addHours(72),
                ],
            )
            : NewsUrl::query()->where('hash', $fingerprint['hash'])->first();
    }

    private function commentPayload(Comment $comment, array $myReactions): array
    {
        $user = $comment->user;
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'weight_score' => round($comment->weight_score, 2),
            'helpful_count' => $comment->helpful_count,
            'unhelpful_count' => $comment->unhelpful_count,
            'created_at' => $comment->created_at?->toISOString(),
            'author' => $user ? [
                'display_name' => $user->display_name ?: 'TruthShield 讀者',
                'trust_score' => round((float) $user->trust_score, 2),
                'identity_label' => $user->public_identity_label,
                'badge' => $user->selectedBadge ? [
                    'name' => $user->selectedBadge->name,
                    'color' => $user->selectedBadge->color,
                ] : null,
            ] : null,
            'my_reaction' => array_key_exists($comment->id, $myReactions)
                ? ($myReactions[$comment->id] ? 'helpful' : 'unhelpful')
                : null,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn ($r) => $this->commentPayload($r, $myReactions))->values()
                : [],
        ];
    }
}
