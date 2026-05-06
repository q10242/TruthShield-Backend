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
}
