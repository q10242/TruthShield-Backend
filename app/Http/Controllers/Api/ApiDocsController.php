<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiDocsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'name' => 'TruthShield API',
            'version' => '0.9.0',
            'endpoints' => [
                ['method' => 'POST', 'path' => '/api/auth/{provider}/callback', 'description' => 'OAuth callback token exchange for Facebook, Google, or GitHub.'],
                ['method' => 'GET', 'path' => '/api/openapi.json', 'description' => 'Machine-readable OpenAPI specification.'],
                ['method' => 'GET', 'path' => '/api/news/status', 'description' => 'Weighted credibility status for a URL.'],
                ['method' => 'POST', 'path' => '/api/news/snapshot', 'description' => 'Record article metadata snapshot and detect title/content/availability changes without storing full copyrighted text.'],
                ['method' => 'POST', 'path' => '/api/news/change-reports', 'description' => 'Report deleted, edited, redirected, paywalled, or archive-needed article states.'],
                ['method' => 'POST', 'path' => '/api/news/read-session', 'description' => 'Record authenticated article reading seconds before voting.'],
                ['method' => 'POST', 'path' => '/api/vote', 'description' => 'Create or update one user vote for one URL.'],
                ['method' => 'GET', 'path' => '/api/news/evidence', 'description' => 'Evidence list with weighted helpfulness and preview metadata.'],
                ['method' => 'GET', 'path' => '/api/evidence-library', 'description' => 'Public evidence library.'],
                ['method' => 'GET', 'path' => '/api/news-domain-reports/status', 'description' => 'Check whether an untracked news domain has already been reported.'],
                ['method' => 'GET', 'path' => '/api/me/notifications', 'description' => 'Authenticated user notifications.'],
                ['method' => 'POST', 'path' => '/api/me/notifications/read-all', 'description' => 'Mark authenticated user notifications as read.'],
                ['method' => 'GET', 'path' => '/api/me/export', 'description' => 'Export authenticated user data as JSON.'],
                ['method' => 'GET', 'path' => '/api/me/appeals', 'description' => 'List authenticated user appeals.'],
                ['method' => 'POST', 'path' => '/api/me/appeals', 'description' => 'Create an appeal for evidence, trust, or account restriction decisions.'],
                ['method' => 'GET', 'path' => '/api/moderation-events', 'description' => 'Public transparent moderation event summaries.'],
                ['method' => 'POST', 'path' => '/api/extension/events', 'description' => 'Record extension compatibility telemetry.'],
                ['method' => 'GET', 'path' => '/api/extension/coverage', 'description' => 'Domain-level extension compatibility coverage summary.'],
                ['method' => 'GET', 'path' => '/api/account-graph/summary', 'description' => 'Authenticated anti-abuse account graph summary.'],
                ['method' => 'POST', 'path' => '/api/auth/{provider}/begin', 'description' => 'Create short-lived OAuth state for formal provider login.'],
                ['method' => 'POST', 'path' => '/api/auth/{provider}/link', 'description' => 'Link a provider identity to the authenticated account.'],
                ['method' => 'GET', 'path' => '/api/trusted-evidence-sources', 'description' => 'List active trusted evidence hosts.'],
                ['method' => 'GET', 'path' => '/api/rate-limit-policies', 'description' => 'List active public rate-limit policy summaries.'],
                ['method' => 'GET', 'path' => '/api/extension/selector-checks', 'description' => 'List extension selector compatibility checks.'],
                ['method' => 'POST', 'path' => '/api/extension/selector-checks', 'description' => 'Record extension selector runtime check.'],
                ['method' => 'GET', 'path' => '/api/me/api-clients', 'description' => 'List authenticated user API clients.'],
                ['method' => 'POST', 'path' => '/api/me/api-clients', 'description' => 'Create an API key for controlled data integrations.'],
                ['method' => 'POST', 'path' => '/api/me/api-clients/{client}/revoke', 'description' => 'Revoke an API client owned by the authenticated user.'],
                ['method' => 'POST', 'path' => '/api/admin/evidences/{evidence}/hide', 'description' => 'Admin hide evidence and publish moderation summary.'],
                ['method' => 'POST', 'path' => '/api/admin/abuse-events/{event}/review', 'description' => 'Admin review abuse event and optionally adjust risk.'],
                ['method' => 'POST', 'path' => '/api/admin/users/{user}/trust-adjustment', 'description' => 'Admin trust score adjustment with required reason.'],
                ['method' => 'GET', 'path' => '/api/leaderboard/media', 'description' => 'Media leaderboard.'],
                ['method' => 'GET', 'path' => '/api/exports/news.csv', 'description' => 'Tracked news CSV export.'],
                ['method' => 'GET', 'path' => '/api/exports/evidence.csv', 'description' => 'Evidence CSV export.'],
                ['method' => 'POST', 'path' => '/api/donations/ecpay', 'description' => 'Create an ECPay donation checkout payload.'],
                ['method' => 'POST', 'path' => '/api/donations/ecpay/notify', 'description' => 'ECPay server notification callback with CheckMacValue verification.'],
                ['method' => 'GET', 'path' => '/api/donations/summary', 'description' => 'Public donation totals for transparency pages.'],
                ['method' => 'GET', 'path' => '/api/exports/donations.csv', 'description' => 'Donation CSV export for operations.'],
                ['method' => 'POST', 'path' => '/api/user-data-requests', 'description' => 'Submit privacy data export, deletion, or correction requests.'],
                ['method' => 'GET', 'path' => '/api/algorithm', 'description' => 'Public algorithm summary.'],
            ],
        ]);
    }
}
