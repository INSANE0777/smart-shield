<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsyncPollingStopAndBackoffTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function review_analyzer_js_avoids_endless_high_frequency_updates_when_progress_is_unchanged(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = $response->getContent();

        // Guard against regressions that reintroduce the old undefined function call.
        $this->assertStringNotContainsString('updateProgressError(', $html);

        // Ensure polling has load-shedding/backoff logic and a max duration cap.
        $this->assertStringContainsString('maxInterval', $html);
        $this->assertStringContainsString('maxDurationMs', $html);
        $this->assertStringContainsString('noChangeCount', $html);

        // Ensure we dedupe unchanged progress updates to reduce /livewire/update traffic.
        $this->assertStringContainsString('lastProgressSignature', $html);
    }
}
