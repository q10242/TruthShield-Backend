<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ApiClient::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(['id', 'name', 'status', 'abilities', 'last_used_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
        ]);

        $plain = 'ts_' . Str::random(48);
        $client = ApiClient::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'key_hash' => hash('sha256', $plain),
            'status' => 'active',
            'abilities' => $validated['abilities'] ?? ['read'],
        ]);

        return response()->json([
            'client' => $client,
            'plain_key' => $plain,
        ], 201);
    }

    public function revoke(Request $request, ApiClient $client): JsonResponse
    {
        abort_unless($client->user_id === $request->user()->id, 404);

        $client->forceFill(['status' => 'revoked'])->save();

        return response()->json(['client' => $client]);
    }
}
