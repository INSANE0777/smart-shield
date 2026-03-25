<?php

namespace App\Services;

use App\Models\AsinData;

/**
 * Central authority for managing AsinData analysis status.
 *
 * PRINCIPLE: Once status='completed', it NEVER changes back.
 * This prevents race conditions where background jobs overwrite completed analysis.
 *
 * For new features that don't block the main analysis, use separate status columns:
 * - price_analysis_status
 * - product_data_status (future)
 * - enhanced_insights_status (future)
 */
class AnalysisStatusService
{
    /**
     * Status progression order. Can only move forward, never backward.
     */
    private const STATUS_ORDER = [
        'pending'          => 0,
        'fetched'          => 1,
        'pending_analysis' => 2,
        'processing'       => 3,
        'analyzed'         => 4,
        'completed'        => 5,  // FINAL - never changes after this
    ];

    /**
     * Check if status can be changed.
     * Returns false if trying to regress from 'completed' or 'analyzed'.
     */
    public static function canChangeStatus(AsinData $asinData, string $newStatus): bool
    {
        // If already completed, status is locked
        if ($asinData->isAnalyzed()) {
            LoggingService::log('Status change blocked - analysis already complete', [
                'asin'             => $asinData->asin,
                'current_status'   => $asinData->status,
                'attempted_status' => $newStatus,
            ]);

            return false;
        }

        // Allow any change if not completed
        return true;
    }

    /**
     * Safely update status only if allowed.
     * Returns true if status was updated, false if blocked.
     */
    public static function updateStatus(AsinData $asinData, string $newStatus): bool
    {
        if (!self::canChangeStatus($asinData, $newStatus)) {
            return false;
        }

        $asinData->update(['status' => $newStatus]);

        return true;
    }

    /**
     * Mark analysis as completed. This is the ONLY way to set status='completed'.
     * Once set, status is locked and cannot be changed.
     */
    public static function markCompleted(AsinData $asinData, array $additionalData = []): bool
    {
        // Verify we have the required data for completion
        if (is_null($asinData->fake_percentage) || is_null($asinData->grade)) {
            LoggingService::log('Cannot mark completed - missing required data', [
                'asin'            => $asinData->asin,
                'fake_percentage' => $asinData->fake_percentage,
                'grade'           => $asinData->grade,
            ]);

            return false;
        }

        $updateData = array_merge($additionalData, [
            'status'           => 'completed',
            'last_analyzed_at' => now(),
        ]);

        // Set first_analyzed_at only if not already set
        if (!$asinData->first_analyzed_at) {
            $updateData['first_analyzed_at'] = now();
        }

        $asinData->update($updateData);

        LoggingService::log('Analysis marked as completed', [
            'asin'            => $asinData->asin,
            'grade'           => $asinData->grade,
            'fake_percentage' => $asinData->fake_percentage,
        ]);

        return true;
    }

    /**
     * Mark analysis as failed. Can be retried later.
     */
    public static function markFailed(AsinData $asinData, ?string $reason = null): bool
    {
        // Don't overwrite completed analysis
        if ($asinData->isAnalyzed()) {
            return false;
        }

        $asinData->update([
            'status'         => 'failed',
            'analysis_notes' => $reason,
        ]);

        LoggingService::log('Analysis marked as failed', [
            'asin'   => $asinData->asin,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Check if the product is ready to display to users.
     * This is the single source of truth for page availability.
     */
    public static function isReadyForDisplay(AsinData $asinData): bool
    {
        return $asinData->isAnalyzed();
    }

    /**
     * Get status for API responses.
     * Normalizes internal status values for external consumption.
     */
    public static function getDisplayStatus(AsinData $asinData): string
    {
        if ($asinData->isAnalyzed()) {
            return 'completed';
        }

        if ($asinData->status === 'failed') {
            return 'failed';
        }

        return 'processing';
    }
}
