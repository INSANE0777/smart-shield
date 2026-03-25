<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized service for site-wide statistics.
 *
 * This service provides cached, non-blocking access to site statistics
 * like products analyzed count. Cache is warmed proactively via scheduled
 * task to ensure page loads never block on cache expiry.
 *
 * @see \App\Console\Kernel - Scheduled cache warming
 */
class SiteStatisticsService
{
    public const CACHE_KEY_PRODUCTS_ANALYZED = 'site_stats.products_analyzed_count';

    public const CACHE_TTL_SECONDS = 86400; // 24 hours

    public const WARM_INTERVAL_MINUTES = 60; // Warm every hour to ensure freshness

    /**
     * Get the count of products analyzed.
     *
     * This returns a cached count that is proactively warmed via scheduled task.
     * Falls back to database query if cache is empty (e.g., first request after cache clear).
     *
     * @return int
     */
    public function getProductsAnalyzedCount(): int
    {
        // Try to get from cache first (should always be warm due to scheduled task)
        $cached = Cache::get(self::CACHE_KEY_PRODUCTS_ANALYZED);

        if ($cached !== null) {
            return (int) $cached;
        }

        // Fallback: warm the cache and return the count
        // This only happens if cache was cleared or on first-ever request
        try {
            return $this->warmProductsAnalyzedCount();
        } catch (\Exception $e) {
            return 0; // Fallback for UI-only verification without DB
        }
    }

    /**
     * Get a formatted display string for products analyzed count.
     *
     * @return string E.g., "5K+" for 5000, or "500" for 500
     */
    public function getProductsAnalyzedDisplay(): string
    {
        $count = $this->getProductsAnalyzedCount();

        if ($count >= 1000) {
            return ((int) floor($count / 1000)).'K+';
        }

        return number_format($count);
    }

    /**
     * Warm the products analyzed count cache.
     *
     * Called by scheduled task to proactively update the cache before expiry.
     * This ensures page loads never block on expensive database queries.
     *
     * @return int The freshly calculated count
     */
    public function warmProductsAnalyzedCount(): int
    {
        $policy = app(ProductAnalysisPolicy::class);
        $count = $policy->applyDisplayableConstraints(AsinData::query())->count();

        Cache::put(self::CACHE_KEY_PRODUCTS_ANALYZED, $count, self::CACHE_TTL_SECONDS);

        return $count;
    }

    /**
     * Clear the products analyzed count cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PRODUCTS_ANALYZED);
    }

    /**
     * Check if the cache is warm.
     *
     * @return bool
     */
    public function isCacheWarm(): bool
    {
        return Cache::has(self::CACHE_KEY_PRODUCTS_ANALYZED);
    }
}

