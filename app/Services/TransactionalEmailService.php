<?php

namespace App\Services;

use App\Models\BugReport;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransactionalEmailService
{
    public function sendToAddress(string $email, string $subject, string $body): array
    {
        if (! config('truthshield.email_enabled', true)) {
            return ['status' => 'disabled', 'error' => null];
        }

        if ($limited = $this->rateLimitResult($email, $subject)) {
            return $limited;
        }

        try {
            Mail::raw($body, function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });

            return ['status' => 'sent', 'error' => null];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)];
        }
    }

    public function shouldEmail(User $user, string $category): bool
    {
        if (! $user->email) {
            return false;
        }

        $defaults = config('truthshield.email_preferences', []);
        $preferences = array_replace($defaults, $user->email_preferences ?? []);

        return (bool) ($preferences[$category] ?? false);
    }

    public function sendUserNotification(User $user, string $category, string $title, ?string $body = null, ?string $actionUrl = null): array
    {
        if (! $this->shouldEmail($user, $category)) {
            return ['status' => $user->email ? 'skipped_by_preference' : 'skipped_no_email', 'error' => null];
        }

        $message = trim(implode("\n\n", array_filter([
            $body,
            $actionUrl ? "查看詳情：{$actionUrl}" : null,
            '你可以在 TruthShield 個人頁調整 email 通知偏好。',
        ])));

        return $this->sendToAddress($user->email, '[TruthShield] ' . $title, $message);
    }

    public function sendDonationReceipt(Donation $donation): array
    {
        if (! $donation->donor_email) {
            return ['status' => 'skipped_no_email', 'error' => null];
        }

        return $this->sendToAddress(
            $donation->donor_email,
            '[TruthShield] 捐款收據 / Donation receipt',
            implode("\n", [
                '感謝你支持 TruthShield。',
                '',
                "訂單編號：{$donation->merchant_trade_no}",
                "金額：TWD {$donation->amount}",
                '狀態：已付款',
                '付款時間：' . ($donation->paid_at?->timezone('Asia/Taipei')->format('Y-m-d H:i:s') ?? ''),
                '',
                '這是一封自動產生的簡易收據通知。',
            ]),
        );
    }

    public function sendBugReportResponse(BugReport $report, string $response): array
    {
        if (! $report->contact_email) {
            return ['status' => 'skipped_no_email', 'error' => null];
        }

        return $this->sendToAddress(
            $report->contact_email,
            '[TruthShield] 回覆你的回報：' . $report->title,
            implode("\n\n", [
                '感謝你的回報，TruthShield 團隊已更新處理狀態。',
                $response,
                '安全回報的細節可能不會在 email 中完整揭露，請以後台紀錄為準。',
            ]),
        );
    }

    private function rateLimitResult(string $email, string $subject): ?array
    {
        $cache = Cache::store(config('truthshield.status_cache_store'));
        $addressKey = hash('sha256', mb_strtolower(trim($email)));
        $subjectKey = hash('sha256', mb_strtolower(trim($subject)));
        $duplicateKey = "email:duplicate:{$addressKey}:{$subjectKey}";

        if ($cache->has($duplicateKey)) {
            return ['status' => 'rate_limited_duplicate', 'error' => null];
        }

        $hourKey = "email:limit:hour:{$addressKey}:" . now()->format('YmdH');
        $dayKey = "email:limit:day:{$addressKey}:" . now()->format('Ymd');
        $hourCount = (int) $cache->increment($hourKey);
        $dayCount = (int) $cache->increment($dayKey);

        if ($hourCount === 1) {
            $cache->put($hourKey, 1, now()->addHour());
        }
        if ($dayCount === 1) {
            $cache->put($dayKey, 1, now()->addDay());
        }

        $hourLimit = (int) config('truthshield.email_limits.per_address_hour', 6);
        $dayLimit = (int) config('truthshield.email_limits.per_address_day', 24);

        if ($hourCount > $hourLimit || $dayCount > $dayLimit) {
            return [
                'status' => 'rate_limited',
                'error' => "Email rate limit exceeded for recipient. hour={$hourCount}/{$hourLimit}, day={$dayCount}/{$dayLimit}",
            ];
        }

        $cache->put(
            $duplicateKey,
            true,
            now()->addSeconds((int) config('truthshield.email_limits.duplicate_ttl_seconds', 600)),
        );

        return null;
    }
}
