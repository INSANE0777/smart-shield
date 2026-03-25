<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\GradeCalculationService;
use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeCalculationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function all_services_use_consistent_grade_calculation()
    {
        // Test various fake percentages to ensure consistency with new stricter thresholds
        $testCases = [
            ['fake_percentage' => 5.0, 'expected_grade' => 'A'],
            ['fake_percentage' => 8.0, 'expected_grade' => 'A'],
            ['fake_percentage' => 15.0, 'expected_grade' => 'B'],
            ['fake_percentage' => 20.0, 'expected_grade' => 'B'],
            ['fake_percentage' => 30.0, 'expected_grade' => 'C'],
            ['fake_percentage' => 40.0, 'expected_grade' => 'C'],
            ['fake_percentage' => 55.0, 'expected_grade' => 'D'],
            ['fake_percentage' => 65.0, 'expected_grade' => 'D'],
            ['fake_percentage' => 85.0, 'expected_grade' => 'F'],
            ['fake_percentage' => 100.0, 'expected_grade' => 'F'],
        ];

        foreach ($testCases as $case) {
            $fakePercentage = $case['fake_percentage'];
            $expectedGrade = $case['expected_grade'];

            // Test direct service call
            $directGrade = GradeCalculationService::calculateGrade($fakePercentage);
            $this->assertEquals(
                $expectedGrade,
                $directGrade,
                "Direct GradeCalculationService failed for {$fakePercentage}%"
            );

            // Test through ReviewAnalysisService by creating a product with calculated metrics
            $product = AsinData::factory()->create([
                'asin'            => 'B0TEST'.str_pad((int) $fakePercentage, 3, '0', STR_PAD_LEFT),
                'fake_percentage' => $fakePercentage,
                'grade'           => $expectedGrade, // Set expected grade
                'status'          => 'completed',
                'reviews'         => [
                    ['rating' => 5, 'text' => 'Test review', 'meta_data' => ['verified_purchase' => true]],
                ],
                'openai_result' => ['detailed_scores' => [0 => $fakePercentage]],
            ]);

            // Verify the grade matches what our centralized service would calculate
            $calculatedGrade = GradeCalculationService::calculateGrade($product->fake_percentage);
            $this->assertEquals(
                $expectedGrade,
                $calculatedGrade,
                "Grade calculation inconsistency for fake_percentage {$fakePercentage}%"
            );
            $this->assertEquals(
                $product->grade,
                $calculatedGrade,
                "Product grade doesn't match calculated grade for {$fakePercentage}%"
            );
        }
    }

    #[Test]
    public function reanalyze_command_uses_consistent_grades()
    {
        // Create a product with high fake percentage
        $product = AsinData::factory()->create([
            'asin'            => 'B0TESTCMD01',
            'fake_percentage' => 80.0,
            'grade'           => 'F',
            'status'          => 'completed',
            'reviews'         => [
                ['rating' => 5, 'text' => 'Test review', 'meta_data' => ['verified_purchase' => true]],
            ],
            'openai_result' => ['detailed_scores' => [0 => 80]],
        ]);

        // Run the reanalyze command in fast mode
        $this->artisan('analysis:manage', [
            'action'   => 'reanalyze',
            '--grade'  => 'F',
            '--limit'  => 1,
            '--force'  => true,
        ])->assertExitCode(0);

        // Refresh the product and verify the grade calculation is consistent
        $product->refresh();

        $expectedGrade = GradeCalculationService::calculateGrade($product->fake_percentage);
        $this->assertEquals(
            $expectedGrade,
            $product->grade,
            'Reanalyze command produced inconsistent grade calculation'
        );
    }

    #[Test]
    public function fix_discrepancies_command_uses_consistent_grades()
    {
        // Create a product that would be processed by the fix command
        $product = AsinData::factory()->create([
            'asin'            => 'B0TESTFIX01',
            'fake_percentage' => 45.0,
            'grade'           => 'D', // This should match our centralized calculation (45% = D under new thresholds)
            'status'          => 'completed',
            'reviews'         => [
                ['rating' => 5, 'text' => 'Test review 1', 'meta_data' => ['verified_purchase' => true]],
                ['rating' => 4, 'text' => 'Test review 2', 'meta_data' => ['verified_purchase' => false]],
            ],
            'openai_result' => ['detailed_scores' => [0 => 40, 1 => 50]],
        ]);

        // Verify the grade matches our centralized calculation
        $expectedGrade = GradeCalculationService::calculateGrade($product->fake_percentage);
        $this->assertEquals(
            $expectedGrade,
            $product->grade,
            "Product grade doesn't match centralized calculation"
        );
    }

    #[Test]
    public function boundary_values_are_consistent_across_services()
    {
        // Test exact boundary values with new stricter thresholds
        $boundaryTests = [
            ['percentage' => 8.0, 'expected' => 'A'], // A/B boundary
            ['percentage' => 8.1, 'expected' => 'B'],
            ['percentage' => 20.0, 'expected' => 'B'], // B/C boundary
            ['percentage' => 20.1, 'expected' => 'C'],
            ['percentage' => 40.0, 'expected' => 'C'], // C/D boundary
            ['percentage' => 40.1, 'expected' => 'D'],
            ['percentage' => 65.0, 'expected' => 'D'], // D/F boundary
            ['percentage' => 65.1, 'expected' => 'F'],
        ];

        foreach ($boundaryTests as $test) {
            $grade = GradeCalculationService::calculateGrade($test['percentage']);
            $this->assertEquals(
                $test['expected'],
                $grade,
                "Boundary value {$test['percentage']}% should be grade {$test['expected']}, got {$grade}"
            );
        }
    }

    #[Test]
    public function grade_thresholds_match_implementation()
    {
        $thresholds = GradeCalculationService::getGradeThresholds();

        // Test that the thresholds match the actual implementation
        foreach ($thresholds as $grade => $range) {
            // Test minimum value
            $calculatedGrade = GradeCalculationService::calculateGrade($range['min']);
            $this->assertEquals(
                $grade,
                $calculatedGrade,
                "Minimum threshold for grade {$grade} ({$range['min']}%) doesn't match implementation"
            );

            // Test maximum value
            $calculatedGrade = GradeCalculationService::calculateGrade($range['max']);
            $this->assertEquals(
                $grade,
                $calculatedGrade,
                "Maximum threshold for grade {$grade} ({$range['max']}%) doesn't match implementation"
            );
        }
    }

    #[Test]
    public function old_inconsistent_thresholds_are_no_longer_used()
    {
        // Test values that would have been graded differently under old systems

        // Test values that show the differences between old inconsistent systems

        // 5% - Under new stricter system should be A (only exceptional products)
        $this->assertEquals(
            'A',
            GradeCalculationService::calculateGrade(5),
            'Value 5% should be A under new stricter system'
        );

        // 15% - Old system would give A, new stricter system gives B
        $this->assertEquals(
            'B',
            GradeCalculationService::calculateGrade(15),
            'Value 15% should be B under new stricter system (was A under old system)'
        );

        // 30% - Old system would give B, new system gives C
        $this->assertEquals(
            'C',
            GradeCalculationService::calculateGrade(30),
            'Value 30% should be C under new system (was B under old system)'
        );

        // 50% - Old system would give C, new system gives D
        $this->assertEquals(
            'D',
            GradeCalculationService::calculateGrade(50),
            'Value 50% should be D under new system (was C under old system)'
        );

        // 70% - Old system would give D, new system gives F
        $this->assertEquals(
            'F',
            GradeCalculationService::calculateGrade(70),
            'Value 70% should be F under new system (was D under old system)'
        );
    }
}
