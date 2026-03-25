<?php

namespace App\Services\Amazon;

use App\Services\LoggingService;

/**
 * Factory for creating Amazon review service instances.
 *
 * This factory allows switching between different Amazon review fetching
 * implementations based on environment configuration.
 */
class AmazonReviewServiceFactory
{
    /**
     * Create an Amazon review service instance based on configuration.
     */
    public static function create(): AmazonReviewServiceInterface
    {
        $serviceType = env('AMAZON_REVIEW_SERVICE', 'brightdata');

        switch (strtolower($serviceType)) {
            case 'brightdata':
            case 'bright-data':
            case 'bd':
            default:
                // Use container resolution if available (for testing), otherwise create new instance
                return app()->bound(BrightDataScraperService::class)
                    ? app(BrightDataScraperService::class)
                    : new BrightDataScraperService();

            case 'scraping':
            case 'direct':
            case 'scrape':
                LoggingService::log('Using direct Amazon scraping service');

                return new AmazonScrapingService();

            case 'unwrangle':
            case 'api':
                LoggingService::log('Using Unwrangle API service');

                return new AmazonFetchService();
        }
    }

    /**
     * Get the currently configured service type.
     */
    public static function getCurrentServiceType(): string
    {
        return env('AMAZON_REVIEW_SERVICE', 'brightdata');
    }

    /**
     * Check if BrightData scraper is enabled.
     */
    public static function isBrightDataEnabled(): bool
    {
        $serviceType = strtolower(env('AMAZON_REVIEW_SERVICE', 'brightdata'));

        return in_array($serviceType, ['brightdata', 'bright-data', 'bd']);
    }

    /**
     * Check if direct scraping is enabled.
     */
    public static function isScrapingEnabled(): bool
    {
        $serviceType = strtolower(env('AMAZON_REVIEW_SERVICE', 'brightdata'));

        return in_array($serviceType, ['scraping', 'direct', 'scrape']);
    }

    /**
     * Check if Unwrangle API is enabled.
     */
    public static function isUnwrangleEnabled(): bool
    {
        $serviceType = strtolower(env('AMAZON_REVIEW_SERVICE', 'brightdata'));

        return in_array($serviceType, ['unwrangle', 'api']);
    }

    /**
     * Get available service types.
     */
    public static function getAvailableServices(): array
    {
        return [
            'brightdata' => [
                'name'        => 'BrightData Scraper',
                'description' => 'Uses BrightData managed scraper API (~$500/month)',
                'class'       => BrightDataScraperService::class,
                'env_values'  => ['brightdata', 'bright-data', 'bd'],
            ],
            'scraping' => [
                'name'        => 'Direct Scraping',
                'description' => 'Direct Amazon scraping (free, requires cookies)',
                'class'       => AmazonScrapingService::class,
                'env_values'  => ['scraping', 'direct', 'scrape'],
            ],
            'unwrangle' => [
                'name'        => 'Unwrangle API',
                'description' => 'Uses Unwrangle API service ($90/month)',
                'class'       => AmazonFetchService::class,
                'env_values'  => ['unwrangle', 'api'],
            ],
        ];
    }
}

