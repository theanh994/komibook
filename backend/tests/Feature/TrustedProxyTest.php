<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-proxy-probe', function (Request $request) {
            return response()->json([
                'is_secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'ip' => $request->ip(),
            ]);
        });
    }

    public function test_trusted_loopback_proxy_headers_are_honored(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'komibook.id.vn',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ])->get('http://127.0.0.1/test-proxy-probe');

        $response->assertStatus(200);
        $response->assertJson([
            'is_secure' => true,
            'scheme' => 'https',
            'host' => 'komibook.id.vn',
            'ip' => '203.0.113.195',
        ]);
    }

    public function test_untrusted_remote_proxy_headers_are_ignored(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.55',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'komibook.id.vn',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ])->get('http://untrusted-attacker.com/test-proxy-probe');

        $response->assertStatus(200);
        $response->assertJson([
            'is_secure' => false,
            'scheme' => 'http',
            'host' => 'untrusted-attacker.com',
            'ip' => '198.51.100.55',
        ]);
    }
}
