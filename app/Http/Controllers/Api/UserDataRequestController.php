<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDataRequest;
use App\Services\BotProtectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDataRequestController extends Controller
{
    public function store(Request $request, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'data_request.create')) {
            return $response;
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'request_type' => ['required', 'in:export,deletion,correction'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $dataRequest = UserDataRequest::query()->create([
            ...$validated,
            'user_id' => $request->user()?->id,
            'status' => 'pending',
        ]);

        return response()->json(['request' => $dataRequest], 201);
    }
}
