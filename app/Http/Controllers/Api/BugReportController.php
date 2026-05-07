<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Services\BotProtectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BugReportController extends Controller
{
    public function store(Request $request, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'bug_report.create')) {
            return $response;
        }

        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:bug,security,extension,data,translation,ux'],
            'severity' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:4000'],
            'page_url' => ['nullable', 'url', 'max:4096'],
            'attachment_url' => ['nullable', 'url', 'max:4096'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'browser' => ['nullable', 'string', 'max:160'],
            'extension_version' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:80'],
            'diagnostics' => ['nullable', 'array'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $severity = $validated['severity'] ?? ($validated['report_type'] === 'security' ? 'high' : 'medium');

        $bugReport = BugReport::query()->create([
            ...Arr::except($validated, ['challenge_token']),
            'severity' => $severity,
            'status' => 'new',
            'source' => $validated['source'] ?? 'website',
            'diagnostics' => array_slice($validated['diagnostics'] ?? [], 0, 40, true),
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Bug report received.',
            'report' => $bugReport,
        ], 201);
    }
}
