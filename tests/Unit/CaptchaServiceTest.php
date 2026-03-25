<?php

namespace Tests\Unit;

use App\Services\CaptchaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptchaServiceTest extends TestCase
{
    protected CaptchaService $captchaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->captchaService = new CaptchaService();
    }

    public function test_get_site_key_for_recaptcha()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.site_key', 'test-recaptcha-site-key');

        $siteKey = $this->captchaService->getSiteKey();

        $this->assertEquals('test-recaptcha-site-key', $siteKey);
    }

    public function test_get_site_key_for_hcaptcha()
    {
        Config::set('captcha.provider', 'hcaptcha');
        Config::set('captcha.hcaptcha.site_key', 'test-hcaptcha-site-key');

        $siteKey = $this->captchaService->getSiteKey();

        $this->assertEquals('test-hcaptcha-site-key', $siteKey);
    }

    public function test_get_provider_returns_recaptcha()
    {
        Config::set('captcha.provider', 'recaptcha');

        $provider = $this->captchaService->getProvider();

        $this->assertEquals('recaptcha', $provider);
    }

    public function test_get_provider_returns_hcaptcha()
    {
        Config::set('captcha.provider', 'hcaptcha');

        $provider = $this->captchaService->getProvider();

        $this->assertEquals('hcaptcha', $provider);
    }

    public function test_verify_recaptcha_success()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success'      => true,
                'challenge_ts' => '2023-01-01T00:00:00Z',
                'hostname'     => 'example.com',
            ]),
        ]);

        $result = $this->captchaService->verify('test-token', '127.0.0.1');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify' &&
                   $request['secret'] === 'test-secret' &&
                   $request['response'] === 'test-token' &&
                   $request['remoteip'] === '127.0.0.1';
        });
    }

    public function test_verify_hcaptcha_success()
    {
        Config::set('captcha.provider', 'hcaptcha');
        Config::set('captcha.hcaptcha.secret_key', 'test-hcaptcha-secret');
        Config::set('captcha.hcaptcha.verify_url', 'https://hcaptcha.com/siteverify');

        Http::fake([
            'https://hcaptcha.com/siteverify' => Http::response([
                'success'      => true,
                'challenge_ts' => '2023-01-01T00:00:00Z',
                'hostname'     => 'example.com',
            ]),
        ]);

        $result = $this->captchaService->verify('test-hcaptcha-token', '192.168.1.1');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hcaptcha.com/siteverify' &&
                   $request['secret'] === 'test-hcaptcha-secret' &&
                   $request['response'] === 'test-hcaptcha-token' &&
                   $request['remoteip'] === '192.168.1.1';
        });
    }

    public function test_verify_failure()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success'     => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $result = $this->captchaService->verify('invalid-token');

        $this->assertFalse($result);
    }

    public function test_verify_without_ip_address()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $result = $this->captchaService->verify('test-token');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify' &&
                   $request['secret'] === 'test-secret' &&
                   $request['response'] === 'test-token' &&
                   !isset($request['remoteip']);
        });
    }

    public function test_verify_with_malformed_response()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response('invalid json', 200),
        ]);

        $result = $this->captchaService->verify('test-token');

        $this->assertFalse($result);
    }

    public function test_verify_with_http_error()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([], 500),
        ]);

        $result = $this->captchaService->verify('test-token');

        $this->assertFalse($result);
    }

    public function test_verify_with_missing_success_field()
    {
        Config::set('captcha.provider', 'recaptcha');
        Config::set('captcha.recaptcha.secret_key', 'test-secret');
        Config::set('captcha.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'challenge_ts' => '2023-01-01T00:00:00Z',
                'hostname'     => 'example.com',
            ]),
        ]);

        $result = $this->captchaService->verify('test-token');

        $this->assertFalse($result);
    }
}
