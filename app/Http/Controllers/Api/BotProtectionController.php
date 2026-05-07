<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BotProtectionService;
use App\Services\ExtensionNonceService;
use Illuminate\Http\JsonResponse;

class BotProtectionController extends Controller
{
    public function config(BotProtectionService $botProtection): JsonResponse
    {
        return response()->json($botProtection->publicConfig());
    }

    public function extensionNonce(ExtensionNonceService $nonces): JsonResponse
    {
        return response()->json($nonces->issue());
    }
}
