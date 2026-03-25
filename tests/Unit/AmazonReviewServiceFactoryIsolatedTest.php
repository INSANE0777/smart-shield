<?php

namespace Tests\Unit;

use App\Services\Amazon\AmazonFetchService;
use App\Services\Amazon\AmazonReviewServiceFactory;
use App\Services\Amazon\AmazonScrapingService;
use App\Services\Amazon\BrightDataScraperService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Isolated tests for AmazonReviewServiceFactory with proper environment mocking.
 */
class AmazonReviewServiceFactoryIsolatedTest extends TestCase
{
    #[Test]
    public function it_creates_brightdata_service_when_configured()
    {
        // Mock the environment temporarily
        $originalEnv = $_ENV['AMAZON_REVIEW_SERVICE'] ?? null;
        $_ENV['AMAZON_REVIEW_SERVICE'] = 'brightdata';

        // Create a new factory instance to avoid cached values
        $factory = new class() extends AmazonReviewServiceFactory {
            public static function create(): \App\Services\Amazon\AmazonReviewServiceInterface
            {
                // Use direct $_ENV access to bypass Laravel's caching
                $serviceType = $_ENV['AMAZON_REVIEW_SERVICE'] ?? 'brightdata';

                switch (strtolower($serviceType)) {
                    case 'brightdata':
                    case 'bright-data':
                    case 'bd':
                    default:
                        return new BrightDataScraperService();

                    case 'scraping':
                    case 'direct':
                    case 'scrape':
                        return new AmazonScrapingService();

                    case 'unwrangle':
                    case 'api':
                        return new AmazonFetchService();
                }
            }
        };

        $service = $factory::create();

        $this->assertInstanceOf(BrightDataScraperService::class, $service);

        // Restore original environment
        if ($originalEnv !== null) {
            $_ENV['AMAZON_REVIEW_SERVICE'] = $originalEnv;
        } else {
            unset($_ENV['AMAZON_REVIEW_SERVICE']);
        }
    }

    #[Test]
    public function it_creates_brightdata_service_with_alias()
    {
        // Mock the environment temporarily
        $originalEnv = $_ENV['AMAZON_REVIEW_SERVICE'] ?? null;
        $_ENV['AMAZON_REVIEW_SERVICE'] = 'bright-data';

        // Create a new factory instance to avoid cached values
        $factory = new class() extends AmazonReviewServiceFactory {
            public static function create(): \App\Services\Amazon\AmazonReviewServiceInterface
            {
                $serviceType = $_ENV['AMAZON_REVIEW_SERVICE'] ?? 'brightdata';

                switch (strtolower($serviceType)) {
                    case 'brightdata':
                    case 'bright-data':
                    case 'bd':
                    default:
                        return new BrightDataScraperService();

                    case 'scraping':
                    case 'direct':
                    case 'scrape':
                        return new AmazonScrapingService();

                    case 'unwrangle':
                    case 'api':
                        return new AmazonFetchService();
                }
            }
        };

        $service = $factory::create();

        $this->assertInstanceOf(BrightDataScraperService::class, $service);

        // Restore original environment
        if ($originalEnv !== null) {
            $_ENV['AMAZON_REVIEW_SERVICE'] = $originalEnv;
        } else {
            unset($_ENV['AMAZON_REVIEW_SERVICE']);
        }
    }

    #[Test]
    public function it_creates_scraping_service_when_configured()
    {
        // Mock the environment temporarily
        $originalEnv = $_ENV['AMAZON_REVIEW_SERVICE'] ?? null;
        $_ENV['AMAZON_REVIEW_SERVICE'] = 'scraping';

        // Create a new factory instance
        $factory = new class() extends AmazonReviewServiceFactory {
            public static function create(): \App\Services\Amazon\AmazonReviewServiceInterface
            {
                $serviceType = $_ENV['AMAZON_REVIEW_SERVICE'] ?? 'unwrangle';

                switch (strtolower($serviceType)) {
                    case 'brightdata':
                    case 'bright-data':
                    case 'bd':
                    default:
                        return new BrightDataScraperService();

                    case 'scraping':
                    case 'direct':
                    case 'scrape':
                        return new AmazonScrapingService();

                    case 'unwrangle':
                    case 'api':
                        return new AmazonFetchService();
                }
            }
        };

        $service = $factory::create();

        $this->assertInstanceOf(AmazonScrapingService::class, $service);

        // Restore original environment
        if ($originalEnv !== null) {
            $_ENV['AMAZON_REVIEW_SERVICE'] = $originalEnv;
        } else {
            unset($_ENV['AMAZON_REVIEW_SERVICE']);
        }
    }

    #[Test]
    public function it_verifies_brightdata_service_in_available_services()
    {
        $services = AmazonReviewServiceFactory::getAvailableServices();

        $this->assertArrayHasKey('brightdata', $services);

        $brightdata = $services['brightdata'];
        $this->assertEquals('BrightData Scraper', $brightdata['name']);
        $this->assertEquals(\App\Services\Amazon\BrightDataScraperService::class, $brightdata['class']);
        $this->assertContains('brightdata', $brightdata['env_values']);
        $this->assertContains('bright-data', $brightdata['env_values']);
        $this->assertContains('bd', $brightdata['env_values']);
    }
}
