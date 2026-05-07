<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\EcpayDonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class DonationController extends Controller
{
    public function store(Request $request, EcpayDonationService $ecpay): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'in:100,300,500,1000,2000,5000'],
            'donor_name' => ['nullable', 'string', 'max:80'],
            'donor_email' => ['nullable', 'email', 'max:160'],
            'message' => ['nullable', 'string', 'max:120'],
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

        $payload = $ecpay->createPayload($donation);
        $donation->forceFill(['request_payload' => $payload])->save();

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
        $paid = Donation::query()->where('status', Donation::STATUS_PAID);

        return response()->json([
            'total_amount' => (int) (clone $paid)->sum('amount'),
            'paid_count' => (clone $paid)->count(),
            'month_amount' => (int) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'month_count' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->count(),
            'pending_count' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
        ]);
    }

    public function supporters(): JsonResponse
    {
        $supporters = Donation::query()
            ->where('status', Donation::STATUS_PAID)
            ->latest('paid_at')
            ->limit(24)
            ->get()
            ->map(fn (Donation $donation) => [
                'name' => $donation->donor_name ?: '匿名支持者',
                'amount' => $donation->amount,
                'message' => $donation->message,
                'paid_at' => $donation->paid_at?->toISOString(),
            ]);

        return response()->json(['data' => $supporters]);
    }

    public function monthly(): JsonResponse
    {
        $rows = collect(range(5, 0))
            ->map(function (int $monthsAgo) {
                $month = now()->startOfMonth()->subMonths($monthsAgo);
                $query = Donation::query()
                    ->where('status', Donation::STATUS_PAID)
                    ->whereBetween('paid_at', [$month, $month->copy()->endOfMonth()]);

                return [
                    'month' => $month->format('Y-m'),
                    'amount' => (int) (clone $query)->sum('amount'),
                    'count' => (clone $query)->count(),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function notify(Request $request, EcpayDonationService $ecpay)
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
}
