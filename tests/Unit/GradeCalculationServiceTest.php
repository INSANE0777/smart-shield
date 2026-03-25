<?php

namespace Tests\Unit;

use App\Services\GradeCalculationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeCalculationServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_grade_a_for_low_fake_percentages()
    {
        $this->assertEquals('A', GradeCalculationService::calculateGrade(0));
        $this->assertEquals('A', GradeCalculationService::calculateGrade(5));
        $this->assertEquals('A', GradeCalculationService::calculateGrade(8));
    }

    #[Test]
    public function it_calculates_grade_b_for_moderate_fake_percentages()
    {
        $this->assertEquals('B', GradeCalculationService::calculateGrade(9));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(15));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(20));
    }

    #[Test]
    public function it_calculates_grade_c_for_medium_fake_percentages()
    {
        $this->assertEquals('C', GradeCalculationService::calculateGrade(21));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(30));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(40));
    }

    #[Test]
    public function it_calculates_grade_d_for_high_fake_percentages()
    {
        $this->assertEquals('D', GradeCalculationService::calculateGrade(41));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(55));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(65));
    }

    #[Test]
    public function it_calculates_grade_f_for_very_high_fake_percentages()
    {
        $this->assertEquals('F', GradeCalculationService::calculateGrade(66));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(85));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(100));
    }

    #[Test]
    public function it_handles_boundary_values_correctly()
    {
        // Test exact boundary values
        $this->assertEquals('A', GradeCalculationService::calculateGrade(8.0));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(8.1));

        $this->assertEquals('B', GradeCalculationService::calculateGrade(20.0));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(20.1));

        $this->assertEquals('C', GradeCalculationService::calculateGrade(40.0));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(40.1));

        $this->assertEquals('D', GradeCalculationService::calculateGrade(65.0));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(65.1));
    }

    #[Test]
    public function it_handles_decimal_values()
    {
        $this->assertEquals('A', GradeCalculationService::calculateGrade(7.9));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(19.5));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(39.99));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(64.1));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(85.7));
    }

    #[Test]
    public function it_handles_edge_cases()
    {
        // Test negative values (shouldn't happen in practice, but handle gracefully)
        $this->assertEquals('A', GradeCalculationService::calculateGrade(-1));
        $this->assertEquals('A', GradeCalculationService::calculateGrade(-10.5));

        // Test very high values
        $this->assertEquals('F', GradeCalculationService::calculateGrade(150));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(999.99));
    }

    #[Test]
    public function it_returns_correct_grade_thresholds()
    {
        $thresholds = GradeCalculationService::getGradeThresholds();

        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('A', $thresholds);
        $this->assertArrayHasKey('B', $thresholds);
        $this->assertArrayHasKey('C', $thresholds);
        $this->assertArrayHasKey('D', $thresholds);
        $this->assertArrayHasKey('F', $thresholds);

        // Verify threshold values
        $this->assertEquals(['min' => 0, 'max' => 8], $thresholds['A']);
        $this->assertEquals(['min' => 9, 'max' => 20], $thresholds['B']);
        $this->assertEquals(['min' => 21, 'max' => 40], $thresholds['C']);
        $this->assertEquals(['min' => 41, 'max' => 65], $thresholds['D']);
        $this->assertEquals(['min' => 66, 'max' => 100], $thresholds['F']);
    }

    #[Test]
    public function it_returns_correct_grade_descriptions()
    {
        $this->assertEquals(
            'Excellent - Highly authentic reviews with strong genuine signals',
            GradeCalculationService::getGradeDescription('A')
        );
        $this->assertEquals(
            'Good - Predominantly genuine reviews with reliable feedback',
            GradeCalculationService::getGradeDescription('B')
        );
        $this->assertEquals(
            'Fair - Mixed authenticity, focus on verified purchase reviews',
            GradeCalculationService::getGradeDescription('C')
        );
        $this->assertEquals(
            'Caution - Significant authenticity concerns present',
            GradeCalculationService::getGradeDescription('D')
        );
        $this->assertEquals(
            'Warning - Many reviews lack authenticity indicators',
            GradeCalculationService::getGradeDescription('F')
        );
        $this->assertEquals(
            'Unknown grade',
            GradeCalculationService::getGradeDescription('X')
        );
    }

    #[Test]
    public function it_maintains_consistency_with_new_stricter_thresholds()
    {
        // Test that the new stricter thresholds work correctly to reduce A-grade inflation
        // New thresholds: A≤8%, B≤20%, C≤40%, D≤65%, F>65%

        // Grade A: 0-8% (only exceptional products)
        $this->assertEquals('A', GradeCalculationService::calculateGrade(0));
        $this->assertEquals('A', GradeCalculationService::calculateGrade(5));
        $this->assertEquals('A', GradeCalculationService::calculateGrade(8));

        // Grade B: 9-20% (good products)
        $this->assertEquals('B', GradeCalculationService::calculateGrade(9));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(15));
        $this->assertEquals('B', GradeCalculationService::calculateGrade(20));

        // Grade C: 21-40% (average products)
        $this->assertEquals('C', GradeCalculationService::calculateGrade(21));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(30));
        $this->assertEquals('C', GradeCalculationService::calculateGrade(40));

        // Grade D: 41-65% (poor products)
        $this->assertEquals('D', GradeCalculationService::calculateGrade(41));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(55));
        $this->assertEquals('D', GradeCalculationService::calculateGrade(65));

        // Grade F: 66-100% (terrible products)
        $this->assertEquals('F', GradeCalculationService::calculateGrade(66));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(85));
        $this->assertEquals('F', GradeCalculationService::calculateGrade(100));
    }
}
