<?php

namespace Tests\Unit;

use App\Services\UrlFingerprintService;
use PHPUnit\Framework\TestCase;

class UrlFingerprintServiceTest extends TestCase
{
    public function test_query_order_does_not_change_url_hash(): void
    {
        $service = new UrlFingerprintService;

        $first = $service->fingerprint('https://Example.com/story?b=2&a=1');
        $second = $service->fingerprint('https://example.com/story?a=1&b=2');

        $this->assertSame($first['hash'], $second['hash']);
        $this->assertSame('https://example.com/story?a=1&b=2', $first['normalized_url']);
    }

    public function test_tracking_query_and_amp_paths_are_removed(): void
    {
        $service = new UrlFingerprintService;

        $first = $service->fingerprint('https://example.com/story/amp?utm_source=fb&a=1&fbclid=abc');
        $second = $service->fingerprint('https://example.com/story?a=1');

        $this->assertSame($second['hash'], $first['hash']);
        $this->assertSame('https://example.com/story?a=1', $first['normalized_url']);
    }

    public function test_youtube_video_urls_share_one_fingerprint(): void
    {
        $service = new UrlFingerprintService;

        $watch = $service->fingerprint('https://www.youtube.com/watch?v=abc123&utm_source=share&t=315s');
        $short = $service->fingerprint('https://youtu.be/abc123?t=315');
        $shorts = $service->fingerprint('https://m.youtube.com/shorts/abc123?feature=share');
        $live = $service->fingerprint('https://youtube.com/live/abc123?si=test');

        $this->assertSame('https://www.youtube.com/watch?v=abc123', $watch['normalized_url']);
        $this->assertSame($watch['hash'], $short['hash']);
        $this->assertSame($watch['hash'], $shorts['hash']);
        $this->assertSame($watch['hash'], $live['hash']);
    }
}
