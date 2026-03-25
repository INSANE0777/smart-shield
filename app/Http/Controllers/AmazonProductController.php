<?php

namespace App\Http\Controllers;

use App\Models\AsinData;
use App\Services\BlogService;
use App\Services\LoggingService;
use App\Services\SEOService;
use Illuminate\Http\Request;

class AmazonProductController extends Controller
{
    /**
     * Display the Amazon product page (without slug).
     */
    public function show(string $country, string $asin, Request $request)
    {
        LoggingService::log('Displaying Amazon product page', [
            'country'    => $country,
            'asin'       => $asin,
            'user_agent' => $request->userAgent(),
            'ip'         => $request->ip(),
        ]);

        // Find the product data in the database for the specific country
        $asinData = AsinData::where('asin', $asin)
            ->where('country', $country)
            ->first();

        if (!$asinData) {
            LoggingService::log('Product not found in database', [
                'country' => $country,
                'asin'    => $asin,
            ]);

            // Check if there's an active analysis session for this ASIN
            $processingInfo = AsinData::checkProcessingSession($asin, $country);

            return response()
                ->view('amazon.product-not-found', [
                    'asin'              => $asin,
                    'country'           => $country,
                    'amazon_url'        => $this->buildAmazonUrl($asin, $country),
                    'has_affiliate_tag' => $this->hasAffiliateTag($country),
                    'is_processing'     => $processingInfo['is_processing'],
                    'estimated_minutes' => $processingInfo['estimated_minutes'],
                ])
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        // Check if the product has been fully analyzed
        if (!$asinData->isAnalyzed()) {
            LoggingService::log('Product not yet analyzed', [
                'asin'              => $asin,
                'status'            => $asinData->status,
                'has_reviews'       => !empty($asinData->getReviewsArray()),
                'has_openai_result' => !empty($asinData->openai_result),
            ]);

            // Product exists but is still being processed
            $isProcessing = $asinData->isProcessing();
            $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();

            return response()
                ->view('amazon.product-not-found', [
                    'asin'              => $asin,
                    'country'           => $country,
                    'amazon_url'        => $this->buildAmazonUrl($asin, $country),
                    'has_affiliate_tag' => $this->hasAffiliateTag($country),
                    'is_processing'     => $isProcessing,
                    'estimated_minutes' => $estimatedMinutes,
                    'product_title'     => $asinData->product_title,
                ])
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        // If product has a title/slug, redirect to SEO-friendly URL
        if ($asinData->have_product_data && $asinData->slug) {
            LoggingService::log('Redirecting to SEO-friendly URL', [
                'country' => $country,
                'asin'    => $asin,
                'slug'    => $asinData->slug,
            ]);

            return redirect()->route('amazon.product.show.slug', [
                'country' => $country,
                'asin'    => $asin,
                'slug'    => $asinData->slug,
            ], 301);
        }

        return $this->renderProductPage($asinData);
    }

    /**
     * Display the Amazon product page with slug (SEO-friendly URL).
     */
    public function showWithSlug(string $country, string $asin, string $slug, Request $request)
    {
        LoggingService::log('Displaying Amazon product page with slug', [
            'country'    => $country,
            'asin'       => $asin,
            'slug'       => $slug,
            'user_agent' => $request->userAgent(),
            'ip'         => $request->ip(),
        ]);

        // Find the product data in the database for the specific country
        $asinData = AsinData::where('asin', $asin)
            ->where('country', $country)
            ->first();

        if (!$asinData) {
            LoggingService::log('Product not found in database', [
                'country' => $country,
                'asin'    => $asin,
                'slug'    => $slug,
            ]);

            // Check if there's an active analysis session for this ASIN
            $processingInfo = AsinData::checkProcessingSession($asin, $country);

            return response()
                ->view('amazon.product-not-found', [
                    'asin'              => $asin,
                    'country'           => $country,
                    'amazon_url'        => $this->buildAmazonUrl($asin, $country),
                    'has_affiliate_tag' => $this->hasAffiliateTag($country),
                    'is_processing'     => $processingInfo['is_processing'],
                    'estimated_minutes' => $processingInfo['estimated_minutes'],
                ])
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        // Check if the product has been fully analyzed
        if (!$asinData->isAnalyzed()) {
            LoggingService::log('Product not yet analyzed', [
                'asin'              => $asin,
                'slug'              => $slug,
                'status'            => $asinData->status,
                'has_reviews'       => !empty($asinData->getReviewsArray()),
                'has_openai_result' => !empty($asinData->openai_result),
            ]);

            // Product exists but is still being processed
            $isProcessing = $asinData->isProcessing();
            $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();

            return response()
                ->view('amazon.product-not-found', [
                    'asin'              => $asin,
                    'country'           => $country,
                    'amazon_url'        => $this->buildAmazonUrl($asin, $country),
                    'has_affiliate_tag' => $this->hasAffiliateTag($country),
                    'is_processing'     => $isProcessing,
                    'estimated_minutes' => $estimatedMinutes,
                    'product_title'     => $asinData->product_title,
                ])
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        // Verify the slug matches the current product title
        if ($asinData->have_product_data && $asinData->slug && $asinData->slug !== $slug) {
            LoggingService::log('Slug mismatch, redirecting to correct slug', [
                'asin'          => $asin,
                'provided_slug' => $slug,
                'correct_slug'  => $asinData->slug,
            ]);

            return redirect()->route('amazon.product.show.slug', [
                'country' => $country,
                'asin'    => $asin,
                'slug'    => $asinData->slug,
            ], 301);
        }

        return $this->renderProductPage($asinData);
    }

    /**
     * Render the product page view.
     */
    private function renderProductPage(AsinData $asinData)
    {
        LoggingService::log('Rendering analyzed product page', [
            'asin'             => $asinData->asin,
            'has_product_data' => $asinData->have_product_data,
            'product_title'    => $asinData->product_title ?? 'N/A',
            'fake_percentage'  => $asinData->fake_percentage,
            'grade'            => $asinData->grade,
            'category'         => $asinData->category ?? 'N/A',
        ]);

        // Generate comprehensive SEO data using the new SEOService
        $seoService = app(SEOService::class);
        $seoData = $seoService->generateProductSEOData($asinData);

        // Get related products in the same category
        $relatedProducts = $asinData->getRelatedProducts(6);

        // Get relevant blog posts for the product category
        $blogService = app(BlogService::class);
        $relevantBlogPosts = $blogService->getPostsForProductCategory($asinData->category, 3);

        // Display the full product analysis
        $country = $asinData->country ?? 'us';

        return response()
            ->view('amazon.product-show', [
                'asinData'           => $asinData,
                'amazon_url'         => $this->buildAmazonUrl($asinData->asin, $country),
                'has_affiliate_tag'  => $this->hasAffiliateTag($country),
                'meta_title'         => $seoData['meta_title'],
                'meta_description'   => $seoData['meta_description'],
                'canonical_url'      => $seoData['canonical_url'],
                'seo_data'           => $seoData,
                'relatedProducts'    => $relatedProducts,
                'relevantBlogPosts'  => $relevantBlogPosts,
            ])
            ->header('Cache-Control', 'public, max-age=900') // 15 minutes cache
            ->header('Vary', 'Accept-Encoding'); // Handle compression variations
    }

    /**
     * Legacy method: Display Amazon product page without country (redirect to country-specific URL).
     */
    public function showLegacy(string $asin, Request $request)
    {
        LoggingService::log('Legacy URL accessed - redirecting to country-specific URL', [
            'asin'       => $asin,
            'user_agent' => $request->userAgent(),
        ]);

        // Find the product in database to determine country
        $asinData = AsinData::where('asin', $asin)->first();

        if (!$asinData) {
            // Product not found - check if there's an active analysis session
            $processingInfo = AsinData::checkProcessingSession($asin);

            return view('amazon.product-not-found', [
                'asin'              => $asin,
                'country'           => 'us',
                'amazon_url'        => $this->buildAmazonUrl($asin, 'us'),
                'has_affiliate_tag' => $this->hasAffiliateTag('us'),
                'is_processing'     => $processingInfo['is_processing'],
                'estimated_minutes' => $processingInfo['estimated_minutes'],
            ]);
        }

        // Redirect to country-specific URL
        return redirect()->route('amazon.product.show', [
            'country' => $asinData->country,
            'asin'    => $asin,
        ], 301);
    }

    /**
     * Legacy method: Display Amazon product page with slug but without country.
     */
    public function showWithSlugLegacy(string $asin, string $slug, Request $request)
    {
        LoggingService::log('Legacy slug URL accessed - redirecting to country-specific URL', [
            'asin'       => $asin,
            'slug'       => $slug,
            'user_agent' => $request->userAgent(),
        ]);

        // Find the product in database to determine country
        $asinData = AsinData::where('asin', $asin)->first();

        if (!$asinData) {
            // Product not found - check if there's an active analysis session
            $processingInfo = AsinData::checkProcessingSession($asin);

            return view('amazon.product-not-found', [
                'asin'              => $asin,
                'country'           => 'us',
                'amazon_url'        => $this->buildAmazonUrl($asin, 'us'),
                'has_affiliate_tag' => $this->hasAffiliateTag('us'),
                'is_processing'     => $processingInfo['is_processing'],
                'estimated_minutes' => $processingInfo['estimated_minutes'],
            ]);
        }

        // Redirect to country-specific URL with slug
        return redirect()->route('amazon.product.show.slug', [
            'country' => $asinData->country,
            'asin'    => $asin,
            'slug'    => $slug,
        ], 301);
    }

    /**
     * Redirect from erroneous /analysis/{asin}/{country} URLs to correct /amazon/{country}/{asin} format.
     *
     * SEO Fix: From Nov 2025 to Jan 2026, SEOService incorrectly generated canonical URLs
     * using /analysis/{asin}/{country} format, which only exists as an API route (returns JSON).
     * Google indexed these as 404 errors. This 301 redirect allows Google to:
     * 1. "Validate Fix" in Search Console
     * 2. Pass accumulated link equity to the correct URLs
     * 3. Handle any external links/bookmarks using the old format
     */
    public function redirectFromAnalysisUrl(string $asin, string $country, Request $request)
    {
        LoggingService::log('Legacy /analysis/ URL accessed - 301 redirect to correct format', [
            'asin'       => $asin,
            'country'    => $country,
            'user_agent' => $request->userAgent(),
            'referer'    => $request->header('Referer'),
        ]);

        // Redirect to the correct country-specific URL format
        // The show() method will handle further redirect to SEO-friendly slug URL if product exists
        return redirect()->route('amazon.product.show', [
            'country' => $country,
            'asin'    => $asin,
        ], 301);
    }

    /**
     * Build Amazon URL with country-specific domain and affiliate tag if configured.
     */
    private function buildAmazonUrl(string $asin, string $country = 'us'): string
    {
        // Map countries to Amazon domains
        $domains = [
            'us' => 'amazon.com',
            'gb' => 'amazon.co.uk',
            'ca' => 'amazon.ca',
            'de' => 'amazon.de',
            'fr' => 'amazon.fr',
            'it' => 'amazon.it',
            'es' => 'amazon.es',
            'ie' => 'amazon.ie',
            'jp' => 'amazon.co.jp',
            'au' => 'amazon.com.au',
            'mx' => 'amazon.com.mx',
            'in' => 'amazon.in',
            'sg' => 'amazon.sg',
            'br' => 'amazon.com.br',
            'nl' => 'amazon.nl',
            'tr' => 'amazon.com.tr',
            'ae' => 'amazon.ae',
            'sa' => 'amazon.sa',
            'se' => 'amazon.se',
            'pl' => 'amazon.pl',
            'eg' => 'amazon.eg',
            'be' => 'amazon.be',
        ];

        $domain = $domains[$country] ?? $domains['us'];
        $url = "https://www.{$domain}/dp/{$asin}";

        // Get country-specific affiliate tag
        $affiliateTag = $this->getAffiliateTagForCountry($country);
        if ($affiliateTag) {
            $url .= "?tag={$affiliateTag}";
        }

        return $url;
    }

    /**
     * Get affiliate tag for specific country.
     */
    private function getAffiliateTagForCountry(string $country): ?string
    {
        // Check if affiliate links are enabled
        if (!config('amazon.affiliate.enabled', true)) {
            return null;
        }

        // Try country-specific affiliate tag from new config system
        $countryTag = config("amazon.affiliate.tags.{$country}");
        if ($countryTag) {
            return $countryTag;
        }

        // Fall back to old config system for backward compatibility
        $legacyCountryTag = config("app.amazon_affiliate_tag_{$country}");
        if ($legacyCountryTag) {
            return $legacyCountryTag;
        }

        // Fall back to default affiliate tag (usually for US)
        $defaultTag = config('app.amazon_affiliate_tag');

        // Only use default tag for US, as it won't work on other domains
        return ($country === 'us') ? $defaultTag : null;
    }

    /**
     * Check if affiliate tag is configured for a specific country.
     * Used to conditionally display Amazon Associates disclosure.
     */
    private function hasAffiliateTag(string $country): bool
    {
        return $this->getAffiliateTagForCountry($country) !== null;
    }

    /**
     * Display a paginated list of all analyzed products.
     */
    public function index(Request $request)
    {
        LoggingService::log('Displaying products listing page', [
            'page'       => $request->get('page', 1),
            'filters'    => $request->only(['grade', 'country', 'search']),
            'user_agent' => $request->userAgent(),
            'ip'         => $request->ip(),
        ]);

        // Get analyzed products using centralized policy for consistent filtering
        $policy = app(\App\Services\ProductAnalysisPolicy::class);
        $query = AsinData::query();
        $policy->applyDisplayableConstraints($query);

        // Apply user filters
        if ($request->filled('grade')) {
            $query->where('grade', strtoupper($request->get('grade')));
        }

        if ($request->filled('country')) {
            $query->where('country', strtolower($request->get('country')));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('product_title', 'like', "%{$search}%")
                  ->orWhere('asin', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('first_analyzed_at', 'desc')
            ->paginate(50)
            ->appends($request->only(['grade', 'country', 'search']));

        // Get unique countries for filter dropdown
        $countries = AsinData::where('status', 'completed')
            ->whereNotNull('grade')
            ->select('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        // Log query results for debugging
        LoggingService::log('Products query results', [
            'total_asin_data'   => AsinData::count(),
            'analyzed_products' => $products->total(),
            'displayed_on_page' => $products->count(),
            'current_page'      => $products->currentPage(),
        ]);

        return response()
            ->view('products.index', [
                'products'  => $products,
                'countries' => $countries,
                'filters'   => [
                    'grade'   => $request->get('grade'),
                    'country' => $request->get('country'),
                    'search'  => $request->get('search'),
                ],
            ])
            ->header('Cache-Control', 'public, max-age=300') // 5 minutes cache
            ->header('Vary', 'Accept-Encoding'); // Handle compression variations
    }
}

