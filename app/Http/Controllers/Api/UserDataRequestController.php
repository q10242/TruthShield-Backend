<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDataRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'request_type' => ['required', 'in:export,deletion,correction'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $dataRequest = UserDataRequest::query()->create([
            ...$validated,
            'user_id' => $request->user()?->id,
            'status' => 'pending',
        ]);

        return response()->json(['request' => $dataRequest], 201);
    }
}
