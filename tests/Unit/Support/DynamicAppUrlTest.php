<?php

namespace Tests\Unit\Support;

use App\Support\DynamicAppUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DynamicAppUrlTest extends TestCase
{
    public function test_uses_configured_app_url_when_set(): void
    {
        putenv('APP_URL=http://configured.example');
        $_ENV['APP_URL'] = 'http://configured.example';
        $_SERVER['APP_URL'] = 'http://configured.example';

        $this->assertSame('http://configured.example', DynamicAppUrl::configured());
    }

    public function test_fallback_uses_app_port_when_app_url_is_empty(): void
    {
        putenv('APP_URL=');
        $_ENV['APP_URL'] = '';
        $_SERVER['APP_URL'] = '';

        config(['app.url' => '']);

        $this->assertNull(DynamicAppUrl::configured());

        putenv('APP_PORT=44432');
        $_ENV['APP_PORT'] = '44432';
        $_SERVER['APP_PORT'] = '44432';

        $url = DynamicAppUrl::fallback();

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringEndsWith(':44432', $url);
    }

    public function test_from_request_uses_browser_host(): void
    {
        $request = Request::create('http://10.0.0.55:44432/admin', 'GET');

        $this->assertSame('http://10.0.0.55:44432', DynamicAppUrl::fromRequest($request));
    }

    public function test_apply_updates_config_and_url_generator(): void
    {
        $request = Request::create('http://172.16.1.20:44432/login', 'GET');

        $url = DynamicAppUrl::apply($request);

        $this->assertSame('http://172.16.1.20:44432', $url);
        $this->assertSame('http://172.16.1.20:44432', config('app.url'));
        $this->assertSame('http://172.16.1.20:44432/storage', config('filesystems.disks.public.url'));
        $this->assertSame('http://172.16.1.20:44432/login', URL::route('login', [], true));
    }
}
