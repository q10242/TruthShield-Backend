<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthSessionController extends Controller
{
    public function logout(Request $request, AuditLogService $auditLog): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        $auditLog->record($request, 'auth.logout');

        return response()->json(['message' => 'Logged out.']);
    }
}
