<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsConfigTest extends TestCase
{
    public function test_chrome_extension_origins_are_allowed_without_credentials(): void
    {
        $patterns = config('cors.allowed_origins_patterns');
        $origins = config('cors.allowed_origins');

        $this->assertContains('#^chrome-extension://[a-p]{32}$#', $patterns);
        $this->assertContains('http://127.0.0.1:5173', $origins);
        $this->assertContains('http://localhost:5173', $origins);
        $this->assertSame(false, config('cors.supports_credentials'));

        $pattern = $patterns[0];
        $this->assertSame(1, preg_match($pattern, 'chrome-extension://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertSame(0, preg_match($pattern, 'moz-extension://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertSame(0, preg_match($pattern, 'https://evil.example'));
    }
}
