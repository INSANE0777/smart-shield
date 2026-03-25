<?php

namespace App\Http\Controllers;

use App\Models\AsinData;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Generate main sitemap index.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.index', self::CACHE_TTL, function () {
            $sitemaps = [
                [
                    'loc'     => url('/sitemap-static.xml'),
                    'lastmod' => Carbon::now()->toISOString(),
                ],
                [
                    'loc'     => url('/sitemap-products.xml'),
                    'lastmod' => $this->getLatestProductUpdate(),
                ],
                [
                    'loc'     => url('/sitemap-analysis.xml'),
                    'lastmod' => $this->getLatestAnalysisUpdate(),
                ],
            ];

            return $this->generateSitemapIndex($sitemaps);
        });

        return response($xml, 200, [
            'Content-Type'  => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate static pages sitemap.
     */
    public function static(): Response
    {
        $xml = Cache::remember('sitemap.static', self::CACHE_TTL, function () {
            $urls = [
                [
                    'loc'        => url('/'),
                    'lastmod'    => Carbon::now()->toISOString(),
                    'changefreq' => 'daily',
                    'priority'   => '1.0',
                ],
                [
                    'loc'        => url('/how-it-works'),
                    'lastmod'    => Carbon::now()->toISOString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.9',
                ],
                [
                    'loc'        => url('/fakespot-alternative'),
                    'lastmod'    => Carbon::now()->toISOString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ],
                [
                    'loc'        => url('/faq'),
                    'lastmod'    => Carbon::now()->toISOString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.7',
                ],
                [
                    'loc'        => url('/contact'),
                    'lastmod'    => Carbon::now()->subDays(7)->toISOString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.5',
                ],
                [
                    'loc'        => url('/privacy'),
                    'lastmod'    => Carbon::now()->subDays(30)->toISOString(),
                    'changefreq' => 'monthly',
                    'priority'   => '0.3',
                ],
            ];

            return $this->generateUrlSet($urls);
        });

        return response($xml, 200, [
            'Content-Type'  => 'application/xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Generate products sitemap with AI-optimized metadata.
     */
    public function products(): Response
    {
        $xml = Cache::remember('sitemap.products', self::CACHE_TTL, function () {
            $products = AsinData::where('status', 'completed')
                ->where('have_product_data', true)
                ->whereNotNull('product_title')
                ->orderBy('updated_at', 'desc')
                ->limit(50000) // Sitemap limit
                ->get();

            $urls = $products->map(function ($product) {
                return [
                    'loc'        => url("/analysis/{$product->asin}/{$product->country}"),
                    'lastmod'    => $product->updated_at->toISOString(),
                    'changefreq' => $this->getChangeFrequency($product),
                    'priority'   => $this->calculatePriority($product),
                    'image'      => $product->product_image_url ? [
                        'loc'     => $product->product_image_url,
                        'title'   => $product->product_title,
                        'caption' => "Review analysis for {$product->product_title}",
                    ] : null,
                    // AI-specific metadata
                    'news' => [
                        'publication_date' => $product->updated_at->toISOString(),
                        'title'            => "Review Analysis: {$product->product_title}",
                        'keywords'         => $this->generateProductKeywords($product),
                    ],
                ];
            })->toArray();

            return $this->generateUrlSet($urls, true);
        });

        return response($xml, 200, [
            'Content-Type'  => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate analysis-focused sitemap for AI crawlers.
     */
    public function analysis(): Response
    {
        $xml = Cache::remember('sitemap.analysis', self::CACHE_TTL, function () {
            $analyses = AsinData::where('status', 'completed')
                ->whereNotNull('explanation')
                ->orderBy('updated_at', 'desc')
                ->limit(50000)
                ->get();

            $urls = $analyses->map(function ($analysis) {
                return [
                    'loc'        => url("/analysis/{$analysis->asin}/{$analysis->country}"),
                    'lastmod'    => $analysis->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority'   => $this->calculateAnalysisPriority($analysis),
                    // AI-optimized metadata
                    'ai_metadata' => [
                        'fake_percentage' => $analysis->fake_percentage,
                        'grade'           => $analysis->grade,
                        'trust_score'     => $this->calculateTrustScore($analysis),
                        'review_count'    => count($analysis->getReviewsArray()),
                        'analysis_type'   => 'fake_review_detection',
                        'methodology'     => 'ai_machine_learning',
                    ],
                ];
            })->toArray();

            return $this->generateAnalysisSitemap($urls);
        });

        return response($xml, 200, [
            'Content-Type'  => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate sitemap index XML.
     */
    private function generateSitemapIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($sitemaps as $sitemap) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>'.htmlspecialchars($sitemap['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.$sitemap['lastmod']."</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Generate URL set XML with enhanced metadata.
     */
    private function generateUrlSet(array $urls, bool $includeImages = false): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        if ($includeImages) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
            $xml .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
        }

        $xml .= '>'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";

            // Add image information if available
            if ($includeImages && isset($url['image']) && $url['image']) {
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>'.htmlspecialchars($url['image']['loc'])."</image:loc>\n";
                $xml .= '      <image:title>'.htmlspecialchars($url['image']['title'])."</image:title>\n";
                $xml .= '      <image:caption>'.htmlspecialchars($url['image']['caption'])."</image:caption>\n";
                $xml .= "    </image:image>\n";
            }

            // Add news information for AI crawlers
            if ($includeImages && isset($url['news'])) {
                $xml .= "    <news:news>\n";
                $xml .= "      <news:publication>\n";
                $xml .= "        <news:name>SMART SHIELD UI</news:name>\n";
                $xml .= "        <news:language>en</news:language>\n";
                $xml .= "      </news:publication>\n";
                $xml .= '      <news:publication_date>'.$url['news']['publication_date']."</news:publication_date>\n";
                $xml .= '      <news:title>'.htmlspecialchars($url['news']['title'])."</news:title>\n";
                $xml .= '      <news:keywords>'.htmlspecialchars($url['news']['keywords'])."</news:keywords>\n";
                $xml .= "    </news:news>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Generate AI-optimized analysis sitemap.
     */
    private function generateAnalysisSitemap(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:ai="https://nullfake.com/schemas/ai-analysis/1.0"';
        $xml .= '>'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";

            // Add AI-specific metadata
            if (isset($url['ai_metadata'])) {
                $meta = $url['ai_metadata'];
                $xml .= "    <ai:analysis>\n";
                $xml .= '      <ai:fake_percentage>'.$meta['fake_percentage']."</ai:fake_percentage>\n";
                $xml .= '      <ai:grade>'.htmlspecialchars($meta['grade'])."</ai:grade>\n";
                $xml .= '      <ai:trust_score>'.$meta['trust_score']."</ai:trust_score>\n";
                $xml .= '      <ai:review_count>'.$meta['review_count']."</ai:review_count>\n";
                $xml .= '      <ai:analysis_type>'.$meta['analysis_type']."</ai:analysis_type>\n";
                $xml .= '      <ai:methodology>'.$meta['methodology']."</ai:methodology>\n";
                $xml .= "    </ai:analysis>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get latest product update timestamp.
     */
    private function getLatestProductUpdate(): string
    {
        $latest = AsinData::where('have_product_data', true)
            ->latest('updated_at')
            ->first();

        return $latest ? $latest->updated_at->toISOString() : Carbon::now()->toISOString();
    }

    /**
     * Get latest analysis update timestamp.
     */
    private function getLatestAnalysisUpdate(): string
    {
        $latest = AsinData::where('status', 'completed')
            ->latest('updated_at')
            ->first();

        return $latest ? $latest->updated_at->toISOString() : Carbon::now()->toISOString();
    }

    /**
     * Calculate change frequency based on product data.
     */
    private function getChangeFrequency(AsinData $product): string
    {
        $daysSinceUpdate = Carbon::now()->diffInDays($product->updated_at);

        if ($daysSinceUpdate < 1) {
            return 'hourly';
        }
        if ($daysSinceUpdate < 7) {
            return 'daily';
        }
        if ($daysSinceUpdate < 30) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Calculate priority based on product metrics.
     */
    private function calculatePriority(AsinData $product): string
    {
        $priority = 0.5; // Base priority

        // Higher priority for products with more reviews
        $reviewCount = count($product->getReviewsArray());
        if ($reviewCount > 100) {
            $priority += 0.3;
        } elseif ($reviewCount > 50) {
            $priority += 0.2;
        } elseif ($reviewCount > 20) {
            $priority += 0.1;
        }

        // Higher priority for recent analyses
        $daysSinceUpdate = Carbon::now()->diffInDays($product->updated_at);
        if ($daysSinceUpdate < 7) {
            $priority += 0.2;
        } elseif ($daysSinceUpdate < 30) {
            $priority += 0.1;
        }

        // Higher priority for products with high fake percentages (more newsworthy)
        if ($product->fake_percentage > 50) {
            $priority += 0.2;
        } elseif ($product->fake_percentage > 30) {
            $priority += 0.1;
        }

        return number_format(min(1.0, $priority), 1);
    }

    /**
     * Calculate priority for analysis pages.
     */
    private function calculateAnalysisPriority(AsinData $analysis): string
    {
        $priority = 0.6; // Base priority for analysis pages

        // Higher priority for comprehensive analyses
        if ($analysis->explanation && strlen($analysis->explanation) > 200) {
            $priority += 0.2;
        }

        // Higher priority for extreme cases
        $fakePercentage = $analysis->fake_percentage ?? 0;
        if ($fakePercentage > 70 || $fakePercentage < 5) {
            $priority += 0.2;
        }

        return number_format(min(1.0, $priority), 1);
    }

    /**
     * Generate keywords for product sitemap.
     */
    private function generateProductKeywords(AsinData $product): string
    {
        $keywords = [
            'fake reviews',
            'amazon analysis',
            'product authenticity',
            'review verification',
        ];

        if ($product->grade) {
            $keywords[] = "grade {$product->grade}";
        }

        if ($product->fake_percentage > 30) {
            $keywords[] = 'high fake percentage';
        }

        return implode(', ', $keywords);
    }

    /**
     * Calculate trust score for sitemap metadata.
     */
    private function calculateTrustScore(AsinData $analysis): int
    {
        $fakePercentage = $analysis->fake_percentage ?? 0;
        $grade = $analysis->grade ?? 'F';

        $gradeScores = ['A' => 95, 'B' => 85, 'C' => 75, 'D' => 65, 'F' => 45, 'U' => 0];
        $gradeScore = $gradeScores[$grade] ?? 50;

        $trustScore = max(0, $gradeScore - ($fakePercentage * 0.5));

        return (int) round($trustScore);
    }

    /**
     * Clear sitemap cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('sitemap.index');
        Cache::forget('sitemap.static');
        Cache::forget('sitemap.products');
        Cache::forget('sitemap.analysis');
    }

    /**
     * Warm sitemap cache by generating all sitemaps.
     */
    public static function warmCache(): void
    {
        $controller = new self();

        // Generate each sitemap to populate cache
        $controller->index();
        $controller->static();
        $controller->products();
        $controller->analysis();
    }
}

