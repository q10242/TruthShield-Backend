<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsDomain;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    public function tags(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        return response()
            ->json([
                'data' => Cache::store(config('truthshield.status_cache_store'))->remember(
                    "lookup:tags:v2:{$locale}",
                    now()->addMinutes(10),
                    fn () => Tag::query()
                        ->select('id', 'name', 'slug', 'color', 'severity', 'requires_evidence', 'description', 'translations')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (Tag $tag) => $tag->localizedPayload($locale))
                        ->values()
                        ->all(),
                ),
            ])
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }

    private function locale(Request $request): string
    {
        $requested = $request->query('locale') ?: $request->header('Accept-Language', '');

        return str_starts_with(strtolower($requested), 'en') ? 'en' : 'zh-TW';
    }

    public function evidenceReportReasons(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['value' => 'spam', 'label' => '垃圾或重複內容'],
                ['value' => 'personal_data', 'label' => '包含個資'],
                ['value' => 'malicious_link', 'label' => '惡意或危險連結'],
                ['value' => 'misleading_evidence', 'label' => '證據與主張不符'],
                ['value' => 'copyright', 'label' => '疑似著作權問題'],
                ['value' => 'needs_review', 'label' => '需要管理員檢視'],
            ],
        ]);
    }

    public function newsDomains(): JsonResponse
    {
        return response()
            ->json([
                'data' => Cache::store(config('truthshield.status_cache_store'))->remember(
                    'lookup:news-domains:v2',
                    now()->addMinutes(5),
                    function (): array {
                        $domains = NewsDomain::query()
                            ->where('is_active', true)
                            ->orderBy('priority')
                            ->orderBy('domain')
                            ->get(['domain', 'article_selector', 'title_selector', 'content_selector', 'blocked_path_pattern', 'article_url_pattern', 'list_url_pattern', 'priority']);

                        if ($domains->isEmpty()) {
                            $domains = collect(config('truthshield.news_domains'))
                                ->map(fn (string $domain) => (object) [
                                    'domain' => $domain,
                                    'article_selector' => null,
                                    'title_selector' => null,
                                    'content_selector' => null,
                                    'blocked_path_pattern' => null,
                                    'article_url_pattern' => null,
                                    'list_url_pattern' => null,
                                    'priority' => 100,
                                ]);
                        }

                        return $domains
                            ->values()
                            ->map(fn ($domain) => [
                                'domain' => $domain->domain,
                                'article_selector' => $domain->article_selector,
                                'title_selector' => $domain->title_selector,
                                'content_selector' => $domain->content_selector,
                                'blocked_path_pattern' => $domain->blocked_path_pattern,
                                'article_url_pattern' => $domain->article_url_pattern,
                                'list_url_pattern' => $domain->list_url_pattern,
                                'priority' => $domain->priority,
                            ])
                            ->all();
                    },
                ),
            ])
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
