<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_includes_web_app_manifest_link(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
        $response->assertSee('<meta name="theme-color" content="#e07a4c">', false);
        $response->assertSee('<meta name="mobile-web-app-capable" content="yes">', false);
    }

    public function test_web_app_manifest_is_available_and_valid(): void
    {
        $path = public_path('site.webmanifest');

        $this->assertFileExists($path);

        /** @var array{name: string, short_name: string, display: string, start_url: string, theme_color: string, icons: list<array{sizes: string}>} $manifest */
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Structural', $manifest['name']);
        $this->assertSame('Structural', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('#e07a4c', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertTrue(
            collect($manifest['icons'])->contains(
                fn (array $icon): bool => $icon['sizes'] === '192x192',
            ),
        );
        $this->assertTrue(
            collect($manifest['icons'])->contains(
                fn (array $icon): bool => $icon['sizes'] === '512x512',
            ),
        );
    }

    public function test_service_worker_is_available(): void
    {
        $path = public_path('sw.js');

        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('addEventListener', $contents);
        $this->assertStringContainsString('fetch', $contents);
    }

    public function test_pwa_icons_are_available(): void
    {
        $this->assertFileExists(public_path('web-app-manifest-192x192.png'));
        $this->assertFileExists(public_path('web-app-manifest-512x512.png'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }
}
