<?php

namespace App\Services;

class GradeCalculationService
{
    /**
     * Calculate grade from fake percentage using consistent thresholds.
     *
     * @param float $fakePercentage
     *
     * @return string
     */
    public static function calculateGrade(float $fakePercentage): string
    {
        // Stricter grade thresholds to reduce A-grade inflation and improve affiliate click-through rates
        // Analysis showed 49% A-grades was unrealistic and reduced user engagement
        if ($fakePercentage <= 8) {
            return 'A';  // Only exceptional products (was 15%)
        }
        if ($fakePercentage <= 20) {
            return 'B';  // Good products (was 30%)
        }
        if ($fakePercentage <= 40) {
            return 'C';  // Average products (was 50%)
        }
        if ($fakePercentage <= 65) {
            return 'D';  // Poor products (was 70%)
        }

        return 'F';  // Terrible products (71-100%)
    }

    /**
     * Get grade thresholds for reference.
     *
     * @return array
     */
    public static function getGradeThresholds(): array
    {
        return [
            'A' => ['min' => 0, 'max' => 8],
            'B' => ['min' => 9, 'max' => 20],
            'C' => ['min' => 21, 'max' => 40],
            'D' => ['min' => 41, 'max' => 65],
            'F' => ['min' => 66, 'max' => 100],
        ];
    }

    /**
     * Get grade description.
     *
     * Descriptions are framed positively, focusing on authenticity rate.
     *
     * @param string $grade
     *
     * @return string
     */
    public static function getGradeDescription(string $grade): string
    {
        return match ($grade) {
            'A'     => 'Excellent - Highly authentic reviews with strong genuine signals',
            'B'     => 'Good - Predominantly genuine reviews with reliable feedback',
            'C'     => 'Fair - Mixed authenticity, focus on verified purchase reviews',
            'D'     => 'Caution - Significant authenticity concerns present',
            'F'     => 'Warning - Many reviews lack authenticity indicators',
            'U'     => 'Unanalyzable - No reviews available for analysis',
            default => 'Unknown grade'
        };
    }
}
