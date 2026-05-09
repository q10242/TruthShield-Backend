<?php

namespace App\Services;

use App\Models\Donation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class EcpayDonationService
{
    public function createPayload(Donation $donation, ?string $locale = null): array
    {
        $backPath = '/donate/return?trade_no='.urlencode($donation->merchant_trade_no);
        if ($locale && $this->ecpayLanguage($locale)) {
            $backPath .= '&locale='.urlencode($locale);
        }

        $payload = [
            'MerchantID' => $this->merchantId(),
            'MerchantTradeNo' => $donation->merchant_trade_no,
            'MerchantTradeDate' => $donation->created_at?->format('Y/m/d H:i:s') ?? now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => (string) $donation->amount,
            'TradeDesc' => 'TruthShield donation',
            'ItemName' => 'TruthShield 真相護盾公益捐款',
            'ReturnURL' => $this->apiUrl('/api/donations/ecpay/notify'),
            'ChoosePayment' => 'ALL',
            'ClientBackURL' => $this->webUrl($backPath),
            'EncryptType' => '1',
        ];

        if ($language = $this->ecpayLanguage($locale)) {
            $payload['Language'] = $language;
        }

        $payload['CheckMacValue'] = $this->checkMacValue($payload);

        return $payload;
    }

    public function checkMacValue(array $parameters): string
    {
        $filtered = Arr::except($parameters, ['CheckMacValue']);
        ksort($filtered, SORT_STRING | SORT_FLAG_CASE);

        $encoded = 'HashKey='.$this->hashKey();
        foreach ($filtered as $key => $value) {
            $encoded .= '&'.$key.'='.$value;
        }
        $encoded .= '&HashIV='.$this->hashIv();

        $encoded = strtolower(urlencode($encoded));
        $encoded = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29', '%20'],
            ['-', '_', '.', '!', '*', '(', ')', '+'],
            $encoded,
        );

        return strtoupper(hash('sha256', $encoded));
    }

    public function isValidCallback(array $parameters): bool
    {
        $received = strtoupper((string) ($parameters['CheckMacValue'] ?? ''));

        return $received !== '' && hash_equals($received, $this->checkMacValue($parameters));
    }

    public function nextTradeNo(): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) config('services.ecpay.trade_prefix', 'TSD'));
        $prefix = Str::upper(Str::limit($prefix ?: 'TSD', 6, ''));

        return $prefix.Carbon::now()->format('ymdHis').random_int(1000, 9999);
    }

    public function checkoutUrl(): string
    {
        return $this->requiredConfig('checkout_url');
    }

    private function merchantId(): string
    {
        return $this->requiredConfig('merchant_id');
    }

    private function hashKey(): string
    {
        return $this->requiredConfig('hash_key');
    }

    private function hashIv(): string
    {
        return $this->requiredConfig('hash_iv');
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.ecpay.api_base_url', config('app.url')), '/').$path;
    }

    private function webUrl(string $path): string
    {
        return rtrim((string) config('services.ecpay.web_base_url', env('FRONTEND_URL', 'http://127.0.0.1:15173')), '/').$path;
    }

    private function ecpayLanguage(?string $locale): ?string
    {
        return match ($locale) {
            'en' => 'ENG',
            'ja' => 'JPN',
            'ko' => 'KOR',
            'zh-CN' => 'CHI',
            default => null,
        };
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config("services.ecpay.{$key}", ''));

        if ($value === '') {
            throw new RuntimeException("ECPay config [services.ecpay.{$key}] is not configured.");
        }

        return $value;
    }
}
