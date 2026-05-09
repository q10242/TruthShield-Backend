<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\EcpayDonationService;
use App\Services\NotificationService;
use App\Services\TransactionalEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DonationController extends Controller
{
    public function store(Request $request, EcpayDonationService $ecpay): JsonResponse
    {
        $allowedAmounts = implode(',', config('truthshield.donation_amounts', [100, 300, 500, 1000, 2000, 5000]));
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'in:'.$allowedAmounts],
            'donor_name' => ['nullable', 'string', 'max:80'],
            'donor_email' => ['nullable', 'email', 'max:160'],
            'message' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'in:zh-TW,en,ja,ko,zh-CN'],
        ]);

        $user = $request->user() ?? $this->userFromBearerToken($request);

        $donation = Donation::query()->create([
            'user_id' => $user?->id,
            'provider' => 'ecpay',
            'merchant_trade_no' => $ecpay->nextTradeNo(),
            'amount' => $validated['amount'],
            'donor_name' => $validated['donor_name'] ?? null,
            'donor_email' => $validated['donor_email'] ?? null,
            'message' => $validated['message'] ?? null,
        ]);

        $payload = $ecpay->createPayload($donation, $validated['locale'] ?? null);
        $donation->forceFill(['request_payload' => $payload])->save();
        $this->forgetDonationCaches();

        return response()->json([
            'donation' => [
                'id' => $donation->id,
                'merchant_trade_no' => $donation->merchant_trade_no,
                'amount' => $donation->amount,
                'status' => $donation->status,
            ],
            'checkout' => [
                'method' => 'POST',
                'url' => $ecpay->checkoutUrl(),
                'params' => $payload,
            ],
        ], 201);
    }

    public function config(): JsonResponse
    {
        return response()->json(Cache::store(config('truthshield.status_cache_store'))->remember('donations:config:v1', now()->addMinutes(10), fn () => [
            'amounts' => config('truthshield.donation_amounts', [100, 300, 500, 1000, 2000, 5000]),
            'currency' => 'TWD',
            'provider' => 'ecpay',
            'monthly_goal' => (int) config('truthshield.donation_monthly_goal', 15000),
        ]));
    }

    public function show(string $tradeNo): JsonResponse
    {
        $donation = Donation::query()
            ->where('merchant_trade_no', $tradeNo)
            ->firstOrFail();

        return response()->json([
            'donation' => [
                'merchant_trade_no' => $donation->merchant_trade_no,
                'amount' => $donation->amount,
                'status' => $donation->status,
                'paid_at' => $donation->paid_at?->toISOString(),
            ],
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json(Cache::store(config('truthshield.status_cache_store'))->remember('donations:summary:v1', now()->addSeconds(30), function (): array {
            $monthStart = now()->startOfMonth();
            $row = Donation::query()
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as total_amount,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid_count,
                    COALESCE(SUM(CASE WHEN status = ? AND paid_at >= ? THEN amount ELSE 0 END), 0) as month_amount,
                    SUM(CASE WHEN status = ? AND paid_at >= ? THEN 1 ELSE 0 END) as month_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count
                ', [
                    Donation::STATUS_PAID,
                    Donation::STATUS_PAID,
                    Donation::STATUS_PAID,
                    $monthStart,
                    Donation::STATUS_PAID,
                    $monthStart,
                    Donation::STATUS_PENDING,
                ])
                ->first();

            return [
                'total_amount' => (int) $row->total_amount,
                'paid_count' => (int) $row->paid_count,
                'month_amount' => (int) $row->month_amount,
                'month_count' => (int) $row->month_count,
                'pending_count' => (int) $row->pending_count,
            ];
        }));
    }

    public function supporters(): JsonResponse
    {
        $supporters = Cache::store(config('truthshield.status_cache_store'))->remember('donations:supporters:v1', now()->addSeconds(30), fn () => Donation::query()
            ->where('status', Donation::STATUS_PAID)
            ->latest('paid_at')
            ->limit(24)
            ->get()
            ->map(fn (Donation $donation) => [
                'name' => $donation->donor_name ?: '匿名支持者',
                'amount' => $donation->amount,
                'message' => $donation->message,
                'paid_at' => $donation->paid_at?->toISOString(),
            ]));

        return response()->json(['data' => $supporters]);
    }

    public function monthly(): JsonResponse
    {
        $rows = Cache::store(config('truthshield.status_cache_store'))->remember('donations:monthly:v1', now()->addMinutes(5), function () {
            $start = now()->startOfMonth()->subMonths(5);
            $raw = Donation::query()
                ->where('status', Donation::STATUS_PAID)
                ->where('paid_at', '>=', $start)
                ->selectRaw("to_char(paid_at, 'YYYY-MM') as month, COALESCE(SUM(amount), 0) as amount, COUNT(*) as count")
                ->groupBy(DB::raw("to_char(paid_at, 'YYYY-MM')"))
                ->get()
                ->keyBy('month');

            return collect(range(5, 0))
                ->map(function (int $monthsAgo) use ($raw) {
                    $month = now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m');

                    return [
                        'month' => $month,
                        'amount' => (int) ($raw[$month]->amount ?? 0),
                        'count' => (int) ($raw[$month]->count ?? 0),
                    ];
                })
                ->values();
        })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function notify(Request $request, EcpayDonationService $ecpay, TransactionalEmailService $emails, NotificationService $notifications)
    {
        $payload = $request->all();
        $tradeNo = (string) ($payload['MerchantTradeNo'] ?? '');
        $donation = Donation::query()->where('merchant_trade_no', $tradeNo)->first();

        if (! $donation || ! $ecpay->isValidCallback($payload)) {
            return response('0|Error', 400);
        }

        $isPaid = (string) ($payload['RtnCode'] ?? '') === '1';
        $donation->forceFill([
            'status' => $isPaid ? Donation::STATUS_PAID : Donation::STATUS_FAILED,
            'provider_payload' => $payload,
            'paid_at' => $isPaid ? now() : $donation->paid_at,
        ])->save();

        if ($isPaid && ! in_array($donation->receipt_email_status, ['sent', 'rate_limited_duplicate'], true)) {
            $emailResult = $emails->sendDonationReceipt($donation->refresh());
            $donation->forceFill([
                'receipt_email_status' => $emailResult['status'],
                'receipt_email_sent_at' => $emailResult['status'] === 'sent' ? now() : null,
                'receipt_email_error' => $emailResult['error'],
            ])->save();

            if ($donation->user) {
                $notifications->send(
                    $donation->user,
                    'donation.paid',
                    '捐款付款已完成',
                    '感謝你支持 TruthShield。訂單編號：'.$donation->merchant_trade_no,
                    config('services.ecpay.web_base_url').'/donate?trade_no='.urlencode($donation->merchant_trade_no),
                    ['donation_id' => $donation->id],
                    'donation',
                );
            }
        }

        $this->forgetDonationCaches();

        return response('1|OK');
    }

    private function userFromBearerToken(Request $request)
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        return PersonalAccessToken::findToken($token)?->tokenable;
    }

    private function forgetDonationCaches(): void
    {
        $cache = Cache::store(config('truthshield.status_cache_store'));
        foreach (['donations:summary:v1', 'donations:supporters:v1', 'donations:monthly:v1', 'transparency:summary:v1', 'system:health:metrics:v1'] as $key) {
            $cache->forget($key);
        }
    }
}
