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
                ['method' => 'GET', 'path' => '/api/vision-readiness', 'description' => 'Vision-level launch readiness checklist, journalism taxonomy, evidence rubric, participation loops, and operational playbooks.'],
                ['method' => 'GET', 'path' => '/api/news/status', 'description' => 'Weighted credibility status for a URL.'],
                ['method' => 'POST', 'path' => '/api/news/snapshot', 'description' => 'Record article metadata snapshot and detect title/content/availability changes without storing full copyrighted text.'],
                ['method' => 'POST', 'path' => '/api/news/change-reports', 'description' => 'Report deleted, edited, redirected, paywalled, or archive-needed article states.'],
                ['method' => 'POST', 'path' => '/api/news/read-session', 'description' => 'Record authenticated article reading seconds before voting.'],
                ['method' => 'POST', 'path' => '/api/vote', 'description' => 'Create or update one user vote for one URL.'],
                ['method' => 'GET', 'path' => '/api/news/evidence', 'description' => 'Evidence list with weighted helpfulness and preview metadata.'],
                ['method' => 'GET', 'path' => '/api/news/official-responses', 'description' => 'Published official, subject, author, or right-of-reply responses for one URL.'],
                ['method' => 'GET', 'path' => '/api/me/profile', 'description' => 'Authenticated user profile, badges, claimant requests, official responses, and contribution summary.'],
                ['method' => 'PUT', 'path' => '/api/me/profile', 'description' => 'Update public display identity, profile bio, and real-name visibility preference.'],
                ['method' => 'POST', 'path' => '/api/me/claimants', 'description' => 'Request verified author, media, subject, or organization claimant status.'],
                ['method' => 'POST', 'path' => '/api/official-responses', 'description' => 'Submit an official or right-of-reply response for admin review.'],
                ['method' => 'POST', 'path' => '/api/official-responses/{officialResponse}/reaction', 'description' => 'Rate a published official response as helpful or not helpful with trust weighting.'],
                ['method' => 'GET', 'path' => '/api/evidence-library', 'description' => 'Public evidence and official response library with tag/domain/source/focus filters and helpful, quality, controversy, or latest sorting.'],
                ['method' => 'GET', 'path' => '/api/news-domain-reports/status', 'description' => 'Check whether an untracked news domain has already been reported.'],
                ['method' => 'GET', 'path' => '/api/me/notifications', 'description' => 'Authenticated user notifications.'],
                ['method' => 'POST', 'path' => '/api/me/notifications/read-all', 'description' => 'Mark authenticated user notifications as read.'],
                ['method' => 'GET', 'path' => '/api/me/export', 'description' => 'Export authenticated user data as JSON.'],
                ['method' => 'GET', 'path' => '/api/me/appeals', 'description' => 'List authenticated user appeals.'],
                ['method' => 'POST', 'path' => '/api/me/appeals', 'description' => 'Create an appeal for evidence, trust, or account restriction decisions.'],
                ['method' => 'GET', 'path' => '/api/moderation-events', 'description' => 'Public transparent moderation event summaries.'],
                ['method' => 'POST', 'path' => '/api/extension/events', 'description' => 'Record extension compatibility telemetry.'],
                ['method' => 'GET', 'path' => '/api/extension/coverage', 'description' => 'Domain-level extension compatibility coverage summary.'],
                ['method' => 'POST', 'path' => '/api/traffic/events', 'description' => 'Record privacy-first web or extension usage events without storing full browsing history.'],
                ['method' => 'POST', 'path' => '/api/traffic/events/batch', 'description' => 'Record a small batch of privacy-first usage events.'],
                ['method' => 'GET', 'path' => '/api/traffic/summary', 'description' => 'Public aggregate traffic summary for operations and transparency.'],
                ['method' => 'GET', 'path' => '/api/account-graph/summary', 'description' => 'Authenticated anti-abuse account graph summary.'],
                ['method' => 'POST', 'path' => '/api/auth/{provider}/begin', 'description' => 'Create short-lived OAuth state for formal provider login.'],
                ['method' => 'POST', 'path' => '/api/auth/{provider}/link', 'description' => 'Link a provider identity to the authenticated account.'],
                ['method' => 'GET', 'path' => '/api/trusted-evidence-sources', 'description' => 'List active trusted evidence hosts.'],
                ['method' => 'GET', 'path' => '/api/rate-limit-policies', 'description' => 'List active public rate-limit policy summaries.'],
                ['method' => 'GET', 'path' => '/api/extension/selector-checks', 'description' => 'List extension selector compatibility checks.'],
                ['method' => 'GET', 'path' => '/api/bot/config', 'description' => 'Public bot-protection configuration for frontend challenge rendering.'],
                ['method' => 'GET', 'path' => '/api/extension/nonce', 'description' => 'Short-lived extension request nonce for signed local extension calls.'],
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
                ['method' => 'GET', 'path' => '/api/exports/news-snapshots.csv', 'description' => 'Article metadata snapshot CSV export.'],
                ['method' => 'GET', 'path' => '/api/exports/news-change-reports.csv', 'description' => 'Article change report CSV export.'],
                ['method' => 'GET', 'path' => '/api/exports/governance-events.csv', 'description' => 'Governance and moderation event CSV export.'],
                ['method' => 'POST', 'path' => '/api/donations/ecpay', 'description' => 'Create an ECPay donation checkout payload.'],
                ['method' => 'POST', 'path' => '/api/donations/ecpay/notify', 'description' => 'ECPay server notification callback with CheckMacValue verification.'],
                ['method' => 'GET', 'path' => '/api/donations/summary', 'description' => 'Public donation totals for transparency pages.'],
                ['method' => 'GET', 'path' => '/api/exports/donations.csv', 'description' => 'Donation CSV export for operations.'],
                ['method' => 'POST', 'path' => '/api/user-data-requests', 'description' => 'Submit privacy data export, deletion, or correction requests.'],
                ['method' => 'POST', 'path' => '/api/bug-reports', 'description' => 'Submit general bug, extension, data, UX, translation, or security reports for admin triage.'],
                ['method' => 'GET', 'path' => '/api/exports/bug-reports.csv', 'description' => 'Bug and security report CSV export for operations triage.'],
                ['method' => 'GET', 'path' => '/api/algorithm', 'description' => 'Public algorithm summary.'],
            ],
        ]);
    }
}
