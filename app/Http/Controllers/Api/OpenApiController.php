<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'TruthShield API',
                'version' => '0.9.0',
                'description' => 'Public and authenticated APIs for weighted news credibility signals.',
            ],
            'paths' => [
                '/api/news/status' => [
                    'get' => [
                        'summary' => 'Get weighted status for a URL',
                        'parameters' => [
                            ['name' => 'url', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uri']],
                        ],
                    ],
                ],
                '/api/vision-readiness' => [
                    'get' => [
                        'summary' => 'Vision-level launch readiness checklist and operating playbooks',
                        'responses' => [
                            '200' => [
                                'description' => 'Readiness payload',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'summary' => ['type' => 'object'],
                                                'feature_points' => ['type' => 'array', 'items' => ['type' => 'object']],
                                                'local_next_points' => ['type' => 'array', 'items' => ['type' => 'object']],
                                                'local_completed_polish_points' => ['type' => 'array', 'items' => ['type' => 'object']],
                                                'production_checklist' => ['type' => 'array', 'items' => ['type' => 'object']],
                                                'security_report_flow' => ['type' => 'array', 'items' => ['type' => 'object']],
                                                'launch_dependencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/api/auth/{provider}/callback' => ['post' => ['summary' => 'OAuth callback token exchange']],
                '/api/news/evidence' => ['get' => ['summary' => 'List evidence for a URL']],
                '/api/news/official-responses' => [
                    'get' => [
                        'summary' => 'List published official or right-of-reply responses for a URL',
                        'parameters' => [
                            ['name' => 'url', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uri']],
                        ],
                    ],
                ],
                '/api/news/snapshot' => ['post' => ['summary' => 'Record article metadata snapshot and detect changes']],
                '/api/news/change-reports' => ['post' => ['summary' => 'Report deleted or modified article state']],
                '/api/news/read-session' => ['post' => ['summary' => 'Record authenticated reading time']],
                '/api/vote' => ['post' => ['summary' => 'Create or update one authenticated vote']],
                '/api/evidence/{vote}/reaction' => ['post' => ['summary' => 'Rate evidence helpfulness']],
                '/api/evidence/{vote}/report' => ['post' => ['summary' => 'Report evidence for moderation']],
                '/api/evidence-library' => [
                    'get' => [
                        'summary' => 'Search public evidence library',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'tag', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'domain', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'trusted', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                            ['name' => 'focus', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['community', 'official']]],
                            ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['helpful', 'quality', 'controversial', 'latest']]],
                        ],
                    ],
                ],
                '/api/news/search' => ['get' => ['summary' => 'Search tracked news URLs']],
                '/api/news-domain-reports' => ['post' => ['summary' => 'Report an untracked news domain']],
                '/api/leaderboard/media' => ['get' => ['summary' => 'Media leaderboard']],
                '/api/leaderboard/trust' => ['get' => ['summary' => 'Trust leaderboard']],
                '/api/exports/media.csv' => ['get' => ['summary' => 'Export media outlet CSV']],
                '/api/exports/news.csv' => ['get' => ['summary' => 'Export tracked news CSV']],
                '/api/exports/evidence.csv' => ['get' => ['summary' => 'Export evidence CSV']],
                '/api/exports/donations.csv' => ['get' => ['summary' => 'Export donation CSV']],
                '/api/exports/news-snapshots.csv' => ['get' => ['summary' => 'Export article snapshot CSV']],
                '/api/exports/news-change-reports.csv' => ['get' => ['summary' => 'Export article change report CSV']],
                '/api/exports/governance-events.csv' => ['get' => ['summary' => 'Export governance event CSV']],
                '/api/donations/ecpay' => ['post' => ['summary' => 'Create ECPay donation checkout']],
                '/api/donations/ecpay/notify' => ['post' => ['summary' => 'Receive ECPay payment notification']],
                '/api/donations/summary' => ['get' => ['summary' => 'Donation transparency summary']],
                '/api/donations/supporters' => ['get' => ['summary' => 'Recent public donation supporters']],
                '/api/donations/monthly' => ['get' => ['summary' => 'Monthly donation trend']],
                '/api/user-data-requests' => ['post' => ['summary' => 'Submit a privacy data request']],
                '/api/bug-reports' => ['post' => ['summary' => 'Submit a bug, extension, data, UX, translation, or security report']],
                '/api/exports/bug-reports.csv' => ['get' => ['summary' => 'Export bug and security reports CSV']],
                '/api/me/profile' => [
                    'get' => ['summary' => 'Authenticated profile with badges, claimant status, and contribution summary'],
                    'put' => ['summary' => 'Update public display identity and profile preferences'],
                ],
                '/api/me/claimants' => ['post' => ['summary' => 'Request verified claimant status for official responses']],
                '/api/official-responses' => ['post' => ['summary' => 'Submit official response for admin review']],
                '/api/official-responses/{officialResponse}/reaction' => ['post' => ['summary' => 'Rate a published official response helpfulness']],
                '/api/me/appeals' => ['get' => ['summary' => 'List current user appeals'], 'post' => ['summary' => 'Create current user appeal']],
                '/api/moderation-events' => ['get' => ['summary' => 'Public moderation event summaries']],
                '/api/extension/events' => ['post' => ['summary' => 'Record extension telemetry']],
                '/api/bot/config' => ['get' => ['summary' => 'Public bot protection challenge configuration']],
                '/api/extension/nonce' => ['get' => ['summary' => 'Issue a short-lived extension nonce']],
                '/api/extension/coverage' => ['get' => ['summary' => 'Domain-level extension coverage']],
                '/api/account-graph/summary' => ['get' => ['summary' => 'Authenticated account signal graph summary']],
                '/api/auth/{provider}/begin' => ['post' => ['summary' => 'Create OAuth state']],
                '/api/auth/{provider}/link' => ['post' => ['summary' => 'Link provider identity']],
                '/api/trusted-evidence-sources' => ['get' => ['summary' => 'Trusted evidence source list']],
                '/api/rate-limit-policies' => ['get' => ['summary' => 'Rate-limit policy summaries']],
                '/api/extension/selector-checks' => ['get' => ['summary' => 'Selector compatibility checks'], 'post' => ['summary' => 'Record selector check']],
                '/api/me/api-clients' => ['get' => ['summary' => 'List current user API clients'], 'post' => ['summary' => 'Create current user API client']],
                '/api/me/api-clients/{client}/revoke' => ['post' => ['summary' => 'Revoke a current user API client']],
            ],
        ]);
    }
}
