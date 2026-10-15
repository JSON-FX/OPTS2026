<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ReverbRuntimeConfigTest extends TestCase
{
    public function test_public_reverb_settings_follow_runtime_host_and_never_expose_secret(): void
    {
        $this->withoutVite();
        foreach (['staging.opts.lguquezon.local', 'opts.lguquezon.local'] as $host) {
            config(['app.url' => 'https://'.$host, 'broadcasting.connections.reverb.key' => 'public-key', 'broadcasting.connections.reverb.secret' => 'must-stay-private', 'broadcasting.connections.reverb.public' => ['host' => $host, 'port' => 443, 'scheme' => 'https']]);
            $html = $this->get('/login')->assertOk()->assertDontSee('must-stay-private')->getContent();
            preg_match('/name="opts-reverb" content="([^"]+)"/', $html, $matches);
            $settings = json_decode(html_entity_decode($matches[1]), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['key' => 'public-key', 'host' => $host, 'port' => 443, 'scheme' => 'https'], $settings);
        }
    }

    public function test_local_build_fallback_is_preserved_without_public_runtime_settings(): void
    {
        $this->withoutVite();
        config(['broadcasting.connections.reverb.public.host' => null]);
        $this->get('/login')->assertOk()->assertDontSee('name="opts-reverb"', false);
    }
}
