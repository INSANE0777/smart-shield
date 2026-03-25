<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EzoicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_SCRIPT_1 = 'https://cmp.gatekeeperconsent.com/min.js';
    private const PRIVACY_SCRIPT_2 = 'https://the.gatekeeperconsent.com/cmp.min.js';
    private const HEADER_SCRIPT = '//www.ezojs.com/ezoic/sa.min.js';

    #[Test]
    public function ezoic_scripts_render_when_enabled()
    {
        config(['ads.ezoic.enabled' => true]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(self::PRIVACY_SCRIPT_1, false);
        $response->assertSee(self::PRIVACY_SCRIPT_2, false);
        $response->assertSee(self::HEADER_SCRIPT, false);
        $response->assertSee('window.ezstandalone', false);
        $response->assertSee('ezstandalone.cmd', false);
    }

    #[Test]
    public function ezoic_scripts_do_not_render_when_disabled()
    {
        config(['ads.ezoic.enabled' => false]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee(self::PRIVACY_SCRIPT_1, false);
        $response->assertDontSee(self::PRIVACY_SCRIPT_2, false);
        $response->assertDontSee(self::HEADER_SCRIPT, false);
        $response->assertDontSee('window.ezstandalone', false);
    }

    #[Test]
    public function ezoic_scripts_render_on_product_pages()
    {
        config(['ads.ezoic.enabled' => true]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B0EZOIC123',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Ezoic Test Product',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertSee(self::PRIVACY_SCRIPT_1, false);
        $response->assertSee(self::PRIVACY_SCRIPT_2, false);
        $response->assertSee(self::HEADER_SCRIPT, false);
    }
}
