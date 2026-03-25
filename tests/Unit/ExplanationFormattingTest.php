<?php

namespace Tests\Unit;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplanationFormattingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_single_paragraph_explanations()
    {
        $asinData = AsinData::factory()->create([
            'explanation'     => 'This is a single paragraph explanation without line breaks.',
            'status'          => 'completed',
            'fake_percentage' => 25,
            'grade'           => 'B',
            'country'         => 'us',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);
        $response->assertSee('This is a single paragraph explanation without line breaks.');
        // Should render as single paragraph
        $response->assertSee('<p>This is a single paragraph explanation without line breaks.</p>', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_formats_multi_paragraph_explanations_correctly()
    {
        $explanation = "This is the first paragraph with detailed analysis.\n\nThis is the second paragraph with specific examples.\n\nThis is the third paragraph with conclusions.";

        $asinData = AsinData::factory()->create([
            'explanation'     => $explanation,
            'status'          => 'completed',
            'fake_percentage' => 30,
            'grade'           => 'C',
            'country'         => 'us',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);

        // Should render each paragraph separately
        $response->assertSee('<p>This is the first paragraph with detailed analysis.</p>', false);
        $response->assertSee('<p>This is the second paragraph with specific examples.</p>', false);
        $response->assertSee('<p>This is the third paragraph with conclusions.</p>', false);

        // Should have proper spacing between paragraphs
        $response->assertSee('space-y-4');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_explanations_with_extra_whitespace()
    {
        $explanation = "  First paragraph with leading spaces.  \n\n  \n\nSecond paragraph after empty line.  \n\n   ";

        $asinData = AsinData::factory()->create([
            'explanation'     => $explanation,
            'status'          => 'completed',
            'fake_percentage' => 35,
            'grade'           => 'C',
            'country'         => 'us',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);

        // Should trim whitespace and skip empty paragraphs
        $response->assertSee('<p>First paragraph with leading spaces.</p>', false);
        $response->assertSee('<p>Second paragraph after empty line.</p>', false);

        // Should not render empty paragraphs
        $response->assertDontSee('<p></p>', false);
        $response->assertDontSee('<p>   </p>', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_or_empty_explanations_gracefully()
    {
        $asinData = AsinData::factory()->create([
            'explanation'     => null,
            'status'          => 'completed',
            'fake_percentage' => 20,
            'grade'           => 'B',
            'country'         => 'us',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);
        // Should not crash and should show the analysis summary section
        $response->assertSee('Analysis Summary');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_html_entities_in_explanations()
    {
        $explanation = "This explanation contains special characters: <script> & \"quotes\" & 'apostrophes'.\n\nSecond paragraph with more & symbols.";

        $asinData = AsinData::factory()->create([
            'explanation'     => $explanation,
            'status'          => 'completed',
            'fake_percentage' => 40,
            'grade'           => 'D',
            'country'         => 'us',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);

        // Should properly escape HTML entities
        $response->assertSee('<script> & "quotes" & \'apostrophes\'');
        $response->assertSee('more & symbols');
    }
}
