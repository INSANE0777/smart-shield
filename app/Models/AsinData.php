<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for Amazon product review analysis data.
 */
class AsinData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'asin_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'asin',
        'country',
        'product_description',
        'product_title',
        'product_image_url',
        'have_product_data',
        'total_reviews_on_amazon',
        'product_data_scraped_at',
        'reviews',
        'openai_result',
        'detailed_analysis',
        'fake_review_examples',
        'fake_percentage',
        'amazon_rating',
        'adjusted_rating',
        'grade',
        'explanation',
        'product_insights',
        'status',
        'analysis_notes',
        'first_analyzed_at',
        'last_analyzed_at',
        'source',
        'extension_version',
        'extraction_timestamp',
        'price',
        'currency',
        'availability',
        'condition',
        'seller',
        'price_updated_at',
        'price_analysis',
        'price_analysis_status',
        'price_analyzed_at',
        'editorial_content',
        'editorial_status',
        'editorial_generated_at',
        'category_source',
        'category_tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reviews'                  => 'array',
        'openai_result'            => 'array',
        'detailed_analysis'        => 'array',
        'fake_review_examples'     => 'array',
        'price_analysis'           => 'array',
        'editorial_content'        => 'array',
        'category_tags'            => 'array',
        'have_product_data'        => 'boolean',
        'product_data_scraped_at'  => 'datetime',
        'first_analyzed_at'        => 'datetime',
        'last_analyzed_at'         => 'datetime',
        'extraction_timestamp'     => 'datetime',
        'price_updated_at'         => 'datetime',
        'price_analyzed_at'        => 'datetime',
        'editorial_generated_at'   => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Query Scopes - Better Organization
    |--------------------------------------------------------------------------
    |
    | These scopes help organize queries by logical groups rather than
    | requiring complex where clauses throughout the application.
    |
    */

    /**
     * Scope for completed analysis.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed')
                    ->whereNotNull('fake_percentage')
                    ->whereNotNull('grade');
    }

    /**
     * Scope for products with specific grades.
     */
    public function scopeWithGrades(Builder $query, array $grades): Builder
    {
        return $query->whereIn('grade', $grades);
    }

    /**
     * Scope for products with product data.
     */
    public function scopeWithProductData(Builder $query): Builder
    {
        return $query->where('have_product_data', true)
                    ->whereNotNull('product_title');
    }

    /**
     * Scope for products needing reanalysis.
     */
    public function scopeNeedsReanalysis(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'failed')
              ->orWhere('status', 'pending')
              ->orWhereNull('openai_result');
        });
    }

    /**
     * Scope for recent analysis.
     */
    public function scopeRecentlyAnalyzed(Builder $query, int $days = 30): Builder
    {
        return $query->where('first_analyzed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for products by country.
     */
    public function scopeForCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Scope for products with minimum review count.
     */
    public function scopeWithMinimumReviews(Builder $query, int $minReviews = 1): Builder
    {
        return $query->whereRaw('JSON_LENGTH(reviews) >= ?', [$minReviews]);
    }

    /**
     * Scope for products containing a specific category tag.
     */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->whereJsonContains('category_tags', $category);
    }

    /**
     * Scope for products with category data.
     */
    public function scopeWithCategory(Builder $query): Builder
    {
        return $query->whereNotNull('category_tags')
            ->whereRaw('JSON_LENGTH(category_tags) > 0');
    }

    /**
     * Scope for products missing category data.
     */
    public function scopeWithoutCategory(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('category_tags')
                ->orWhereRaw('JSON_LENGTH(category_tags) = 0');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Computed Properties - Reduce God Object Complexity
    |--------------------------------------------------------------------------
    |
    | These computed properties encapsulate complex logic and make the model
    | easier to work with by providing meaningful derived data.
    |
    */

    /**
     * Get the grade color for UI display.
     */
    public function getGradeColorAttribute(): string
    {
        return match ($this->grade) {
            'A'     => 'green',
            'B'     => 'blue',
            'C'     => 'yellow',
            'D'     => 'orange',
            'F'     => 'red',
            'U'     => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get the grade description.
     */
    public function getGradeDescriptionAttribute(): string
    {
        return app(\App\Services\GradeCalculationService::class)->getGradeDescription($this->grade ?? 'N/A');
    }

    /**
     * Get review statistics.
     */
    public function getReviewStatsAttribute(): array
    {
        $reviews = $this->getReviewsArray();
        $totalReviews = count($reviews);

        if ($totalReviews === 0) {
            return [
                'total'               => 0,
                'verified_count'      => 0,
                'verified_percentage' => 0,
                'average_rating'      => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }

        $verifiedCount = 0;
        $ratingSum = 0;
        $ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($reviews as $review) {
            if (isset($review['meta_data']['verified_purchase']) && $review['meta_data']['verified_purchase']) {
                $verifiedCount++;
            }

            if (isset($review['rating']) && is_numeric($review['rating'])) {
                $rating = (int) $review['rating'];
                $ratingSum += $rating;
                if (isset($ratingDistribution[$rating])) {
                    $ratingDistribution[$rating]++;
                }
            }
        }

        return [
            'total'               => $totalReviews,
            'verified_count'      => $verifiedCount,
            'verified_percentage' => round(($verifiedCount / $totalReviews) * 100, 1),
            'average_rating'      => round($ratingSum / $totalReviews, 2),
            'rating_distribution' => $ratingDistribution,
        ];
    }

    /**
     * Check if analysis is stale and needs refresh.
     */
    public function getIsStaleAttribute(): bool
    {
        if (!$this->first_analyzed_at) {
            return true;
        }

        // Consider analysis stale after 30 days
        return $this->first_analyzed_at->diffInDays() > 30;
    }

    /**
     * Get analysis age in human readable format.
     */
    public function getAnalysisAgeAttribute(): ?string
    {
        if (!$this->first_analyzed_at) {
            return null;
        }

        return $this->first_analyzed_at->diffForHumans();
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods - Encapsulate Complex Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Helper method to safely get reviews as array.
     *
     * @return array<int, array> Array of review data
     */
    public function getReviewsArray(): array
    {
        $reviews = $this->reviews;

        // If it's already an array, return it
        if (is_array($reviews)) {
            return $reviews;
        }

        // If it's a string, decode it
        if (is_string($reviews)) {
            $decoded = json_decode($reviews, true);

            return is_array($decoded) ? $decoded : [];
        }

        // Default to empty array
        return [];
    }

    /**
     * Check if the product has been fully analyzed.
     *
     * @return bool True if analysis is complete, false otherwise
     */
    public function isAnalyzed(): bool
    {
        // A product is considered analyzed if it has:
        // 1. Status is completed AND
        // 2. Has fake_percentage and grade (key analysis results)
        //
        // Note: Grade U products (no reviews) ARE considered analyzed - they went through
        // the complete analysis process and received a valid result (Grade U).
        // The analysis is complete, just with no reviews to analyze.
        return $this->status === 'completed' &&
               !is_null($this->fake_percentage) &&
               !is_null($this->grade);
    }

    /**
     * Check if the product is currently being processed (queued or in-progress analysis).
     *
     * @return bool True if analysis is in progress
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['fetched', 'pending', 'pending_analysis', 'processing']);
    }

    /**
     * Get estimated time remaining for processing in minutes.
     * Based on typical analysis times and current review count.
     *
     * @return int Estimated minutes remaining
     */
    public function getEstimatedProcessingTimeMinutes(): int
    {
        $reviewCount = count($this->getReviewsArray());
        $baseTime = 2; // Base time for small products (2 minutes)

        // Add time based on review count - roughly 30 seconds per 50 reviews
        $additionalTime = ceil($reviewCount / 50);

        // Cap at reasonable maximum
        return min($baseTime + $additionalTime, 10);
    }

    /**
     * Check if there's an active processing session for a given ASIN.
     * Used when no AsinData record exists yet to check if analysis is queued.
     *
     * @param string      $asin    The ASIN to check
     * @param string|null $country Optional country filter
     *
     * @return array{is_processing: bool, estimated_minutes: int, session: \App\Models\AnalysisSession|null}
     */
    public static function checkProcessingSession(string $asin, ?string $country = null): array
    {
        $query = AnalysisSession::where('asin', $asin)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc');

        $session = $query->first();

        if (!$session) {
            return [
                'is_processing'     => false,
                'estimated_minutes' => 0,
                'session'           => null,
            ];
        }

        // Calculate estimated time based on session progress
        $elapsedMinutes = $session->started_at
            ? $session->started_at->diffInMinutes(now())
            : 0;

        // Base estimate of 3 minutes total, minus elapsed time
        $estimatedMinutes = max(1, 3 - $elapsedMinutes);

        // If it's been processing for a while, give a conservative estimate
        if ($elapsedMinutes > 3) {
            $estimatedMinutes = 2; // Still processing after expected time
        }

        return [
            'is_processing'     => true,
            'estimated_minutes' => $estimatedMinutes,
            'session'           => $session,
        ];
    }

    /**
     * Generate a URL-friendly slug from the product title.
     *
     * @return string|null The slug or null if no title
     */
    public function getSlugAttribute(): ?string
    {
        if (empty($this->product_title)) {
            return null;
        }

        // Convert to lowercase and replace spaces/special chars with hyphens
        $slug = strtolower($this->product_title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Limit length to 60 characters for SEO
        if (strlen($slug) > 60) {
            $slug = substr($slug, 0, 60);
            $slug = preg_replace('/-[^-]*$/', '', $slug); // Remove partial words
        }

        return $slug ?: null;
    }

    /**
     * Generate the SEO-friendly URL for this product.
     *
     * @return string The SEO-friendly URL path
     */
    public function getSeoUrlAttribute(): string
    {
        $slug = $this->slug;

        if ($slug) {
            return "/amazon/{$this->asin}/{$slug}";
        }

        // Fallback to basic URL if no slug available
        return "/amazon/{$this->asin}";
    }

    /*
    |--------------------------------------------------------------------------
    | Price Analysis Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if price analysis has been completed.
     *
     * @return bool True if price analysis is complete
     */
    public function hasPriceAnalysis(): bool
    {
        return $this->price_analysis_status === 'completed'
            && !is_null($this->price_analysis)
            && !empty($this->price_analysis);
    }

    /**
     * Check if price analysis is currently in progress.
     *
     * @return bool True if price analysis is processing
     */
    public function isPriceAnalysisProcessing(): bool
    {
        return $this->price_analysis_status === 'processing';
    }

    /**
     * Check if price analysis needs to be run.
     *
     * @return bool True if price analysis should be triggered
     */
    public function needsPriceAnalysis(): bool
    {
        // Don't analyze if already completed or in progress
        if ($this->hasPriceAnalysis() || $this->isPriceAnalysisProcessing()) {
            return false;
        }

        // Only analyze products with completed review analysis
        return $this->isAnalyzed() && $this->have_product_data;
    }

    /**
     * Scope for products pending price analysis.
     */
    public function scopePendingPriceAnalysis(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->where(function ($q) {
                $q->whereNull('price_analysis_status')
                    ->orWhere('price_analysis_status', 'pending')
                    ->orWhere('price_analysis_status', 'failed');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Editorial Content Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if editorial content has been generated.
     *
     * @return bool True if editorial content is complete
     */
    public function hasEditorialContent(): bool
    {
        return $this->editorial_status === 'completed'
            && !is_null($this->editorial_content)
            && !empty($this->editorial_content);
    }

    /**
     * Check if editorial content generation is currently in progress.
     *
     * @return bool True if editorial content is processing
     */
    public function isEditorialContentProcessing(): bool
    {
        return $this->editorial_status === 'processing';
    }

    /**
     * Check if editorial content needs to be generated.
     *
     * @return bool True if editorial content should be generated
     */
    public function needsEditorialContent(): bool
    {
        // Don't generate if already completed or in progress
        if ($this->hasEditorialContent() || $this->isEditorialContentProcessing()) {
            return false;
        }

        // Only generate for products with completed review analysis
        return $this->isAnalyzed() && $this->have_product_data;
    }

    /**
     * Scope for products that need editorial content generation.
     * Includes 'pending' (DB default for unprocessed), 'failed' (retry), and NULL.
     * Duplicate processing is prevented by hasEditorialContent() check in service.
     */
    public function scopePendingEditorialContent(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->whereNotNull('grade')
            ->whereNotNull('fake_percentage')
            ->where(function ($q) {
                $q->whereNull('editorial_status')
                    ->orWhere('editorial_status', 'pending')
                    ->orWhere('editorial_status', 'failed');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Image URL Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Transform Amazon image URL to high resolution version for social sharing.
     *
     * Amazon image URLs contain transformation parameters like:
     * - _SY300_SX300_ = dimensions
     * - _QL70_ = quality (70%)
     * - _FMwebp_ = webp format
     *
     * For social sharing we need:
     * - Larger images (1200px+ recommended for og:image)
     * - JPEG format (better compatibility than webp)
     * - Higher quality
     *
     * @param string|null $imageUrl The original Amazon image URL
     *
     * @return string|null The transformed high-resolution URL
     */
    public static function transformToHighResImage(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // Only transform Amazon media URLs
        if (!str_contains($imageUrl, 'media-amazon.com/images/')) {
            return $imageUrl;
        }

        // Pattern to match Amazon image transformation parameters
        // Examples: ._AC_SY300_SX300_QL70_FMwebp_.jpg or ._AC_SL1500_.jpg
        $pattern = '/\._[A-Z0-9_]+_\.(jpg|jpeg|png|gif|webp)$/i';

        // Replace with high-resolution parameters optimized for social sharing
        // _AC_SL1200_ gives us a 1200px image (optimal for og:image)
        // Using .jpg for maximum compatibility
        $highResUrl = preg_replace($pattern, '._AC_SL1200_.jpg', $imageUrl);

        // If the pattern didn't match, try to handle URLs without the trailing format
        if ($highResUrl === $imageUrl && preg_match('/\._[A-Z0-9_]+_$/i', $imageUrl)) {
            $highResUrl = preg_replace('/\._[A-Z0-9_]+_$/i', '._AC_SL1200_.jpg', $imageUrl);
        }

        return $highResUrl;
    }

    /**
     * Get high-resolution image URL for social media sharing (og:image, twitter:image).
     *
     * This accessor provides a properly sized and formatted image URL for
     * social media previews. Social platforms like Facebook, Twitter/X, and
     * Slack have specific requirements:
     * - Minimum 200x200px (Facebook)
     * - Recommended 1200x630px for og:image
     * - JPEG or PNG format (webp not universally supported)
     *
     * @return string|null
     */
    public function getSocialImageUrlAttribute(): ?string
    {
        return self::transformToHighResImage($this->product_image_url);
    }

    /**
     * Get the display image URL (for product pages, same as stored URL).
     *
     * This is the original image URL, which may be smaller/optimized for
     * fast page loads. Use social_image_url for sharing previews.
     *
     * @return string|null
     */
    public function getDisplayImageUrlAttribute(): ?string
    {
        return $this->product_image_url;
    }

    /*
    |--------------------------------------------------------------------------
    | Category & Related Products Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the leaf category (last element of category_tags).
     *
     * Derived from category_tags - no redundant storage needed.
     * Example: ["Electronics", "Computers", "Networking", "Routers"] => "Routers"
     */
    public function getCategoryAttribute(): ?string
    {
        $tags = $this->category_tags;

        if (empty($tags) || !is_array($tags)) {
            return null;
        }

        return end($tags) ?: null;
    }

    /**
     * Get the full category path as a string.
     *
     * Derived from category_tags - no redundant storage needed.
     * Example: ["Electronics", "Computers", "Networking", "Routers"] => "Electronics > Computers > Networking > Routers"
     */
    public function getCategoryPathAttribute(): ?string
    {
        $tags = $this->category_tags;

        if (empty($tags) || !is_array($tags)) {
            return null;
        }

        return implode(' > ', $tags);
    }

    /**
     * Get related products ranked by shared category tag count.
     *
     * Products are ranked by how many category tags they share with this product.
     * A product with tags ["Electronics", "Computers", "Networking", "Routers"]
     * will rank highest products that share all 4 tags, then 3, then 2, etc.
     *
     * This ensures "Routers" ranks other "Routers" highest, while still showing
     * related "Networking" products if not enough exact matches exist.
     *
     * @param int $limit         Maximum number of related products to return
     * @param int $minSharedTags Minimum shared tags required (default: 2)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRelatedProducts(int $limit = 6, int $minSharedTags = 2): \Illuminate\Database\Eloquent\Collection
    {
        $tags = $this->category_tags;

        // No tags = no related products
        if (empty($tags) || !is_array($tags)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Build the shared tag count expression
        // Each JSON_CONTAINS returns 1 if tag exists, 0 if not
        // Sum of all = number of shared tags
        $tagChecks = [];
        $bindings = [];
        foreach ($tags as $tag) {
            $tagChecks[] = 'JSON_CONTAINS(category_tags, ?)';
            $bindings[] = json_encode($tag);
        }

        $sharedCountExpression = '('.implode(' + ', $tagChecks).')';

        return static::where('id', '!=', $this->id)
            ->where('status', 'completed')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->whereNotNull('category_tags')
            ->whereRaw('JSON_LENGTH(category_tags) > 0')
            // Exclude unanalyzable products (Grade U) from recommendations
            ->whereNotNull('grade')
            ->where('grade', '!=', 'U')
            ->whereNotNull('fake_percentage')
            // Require minimum shared tags to filter out unrelated products
            ->whereRaw("{$sharedCountExpression} >= ?", array_merge($bindings, [$minSharedTags]))
            // Order by shared tag count descending (most related first)
            ->orderByRaw("{$sharedCountExpression} DESC", $bindings)
            // Prefer higher authenticity grades first (A best)
            ->orderByRaw("CASE grade
                WHEN 'A' THEN 1
                WHEN 'B' THEN 2
                WHEN 'C' THEN 3
                WHEN 'D' THEN 4
                WHEN 'F' THEN 5
                ELSE 99
            END ASC")
            // Prefer lower fake percentage when grades are similar
            ->orderBy('fake_percentage', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if product has category data.
     *
     * @return bool
     */
    public function hasCategory(): bool
    {
        return !empty($this->category_tags) && is_array($this->category_tags);
    }

    /**
     * Get category tags from category_path string.
     *
     * Parses "Electronics > Computers > Networking > Routers" into
     * ["Electronics", "Computers", "Networking", "Routers"]
     *
     * @param string|null $categoryPath The category path string
     *
     * @return array The parsed category tags
     */
    public static function parseCategoryPath(?string $categoryPath): array
    {
        if (empty($categoryPath)) {
            return [];
        }

        // Split by " > " separator and trim each part
        $tags = array_map('trim', explode('>', $categoryPath));

        // Filter empty values and re-index
        return array_values(array_filter($tags, fn ($tag) => !empty($tag)));
    }

    /**
     * Scope for products with category tags.
     */
    public function scopeWithCategoryTags(Builder $query): Builder
    {
        return $query->whereNotNull('category_tags')
            ->whereRaw('JSON_LENGTH(category_tags) > 0');
    }

    /**
     * Scope for products missing category tags.
     */
    public function scopeWithoutCategoryTags(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('category_tags')
                ->orWhereRaw('JSON_LENGTH(category_tags) = 0');
        });
    }
}

