<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BlogService
{
    /**
     * Category mappings derived from keyword analysis.
     * Each category has associated keywords that will match posts.
     */
    private const CATEGORY_MAPPINGS = [
        'Detection Guides' => [
            'fake reviews',
            'spot fake',
            'ai detection',
            'ai generated',
            'fake review detection',
            'suspicious reviews',
            'pattern analysis',
            'timing patterns',
        ],
        'Consumer Protection' => [
            'consumer protection',
            'safe shopping',
            'avoid scams',
            'online safety',
            'buyer protection',
            'consumer rights',
            'ftc',
        ],
        'Industry Insights' => [
            'fake review industry',
            'review fraud economics',
            'review farms',
            'review manipulation',
            'seller tactics',
            'amazon fraud',
        ],
        'Tools & Technology' => [
            'review analysis tools',
            'nlp',
            'machine learning',
            'statistical analysis',
            'methodology',
            'open source',
            'laravel',
        ],
        'Amazon Programs' => [
            'amazon vine',
            'verified purchase',
            'amazon badge',
            'amazon program',
        ],
        'Research & Data' => [
            'research findings',
            'statistics',
            'data',
            'analysis',
            'counterfeit',
        ],
    ];

    /**
     * Get all posts from config with author names resolved.
     */
    public function getAllPosts(): Collection
    {
        $posts = collect(config('blog.posts', []));
        $authors = config('blog.authors', []);

        return $posts
            ->filter(fn ($post) => !empty($post['view']) && View::exists($post['view']))
            ->map(function ($post) use ($authors) {
                if (!isset($post['author']) && isset($post['author_key'])) {
                    $post['author'] = $authors[$post['author_key']]['name'] ?? 'SMART SHIELD UI Team';
                }

                return $post;
            })
            ->sortByDesc('date')
            ->values();
    }

    /**
     * Get latest N posts.
     */
    public function getLatestPosts(int $limit = 5, ?string $excludeSlug = null): Collection
    {
        return $this->getAllPosts()
            ->when($excludeSlug, fn ($posts) => $posts->filter(fn ($p) => $p['slug'] !== $excludeSlug))
            ->take($limit);
    }

    /**
     * Get related posts based on shared keywords.
     */
    public function getRelatedPosts(array $currentPost, int $limit = 5): Collection
    {
        $currentKeywords = $this->parseKeywords($currentPost['keywords'] ?? '');

        if (empty($currentKeywords)) {
            return $this->getLatestPosts($limit, $currentPost['slug'] ?? null);
        }

        return $this->getAllPosts()
            ->filter(fn ($post) => ($post['slug'] ?? '') !== ($currentPost['slug'] ?? ''))
            ->map(function ($post) use ($currentKeywords) {
                $postKeywords = $this->parseKeywords($post['keywords'] ?? '');
                $sharedKeywords = array_intersect($currentKeywords, $postKeywords);
                $post['relevance_score'] = count($sharedKeywords);

                return $post;
            })
            ->filter(fn ($post) => $post['relevance_score'] > 0)
            ->sortByDesc('relevance_score')
            ->take($limit)
            ->values();
    }

    /**
     * Get all categories with post counts.
     */
    public function getCategories(): Collection
    {
        $posts = $this->getAllPosts();
        $categories = collect();

        foreach (self::CATEGORY_MAPPINGS as $categoryName => $categoryKeywords) {
            $matchingPosts = $posts->filter(function ($post) use ($categoryKeywords) {
                $postKeywords = strtolower($post['keywords'] ?? '');
                foreach ($categoryKeywords as $keyword) {
                    if (Str::contains($postKeywords, strtolower($keyword))) {
                        return true;
                    }
                }

                return false;
            });

            if ($matchingPosts->isNotEmpty()) {
                $categories->push([
                    'name'  => $categoryName,
                    'slug'  => Str::slug($categoryName),
                    'count' => $matchingPosts->count(),
                ]);
            }
        }

        return $categories->sortByDesc('count')->values();
    }

    /**
     * Get posts by category.
     */
    public function getPostsByCategory(string $categorySlug): Collection
    {
        $categoryName = null;
        foreach (self::CATEGORY_MAPPINGS as $name => $keywords) {
            if (Str::slug($name) === $categorySlug) {
                $categoryName = $name;
                break;
            }
        }

        if (!$categoryName) {
            return collect();
        }

        $categoryKeywords = self::CATEGORY_MAPPINGS[$categoryName];

        return $this->getAllPosts()->filter(function ($post) use ($categoryKeywords) {
            $postKeywords = strtolower($post['keywords'] ?? '');
            foreach ($categoryKeywords as $keyword) {
                if (Str::contains($postKeywords, strtolower($keyword))) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Get tag cloud with frequencies.
     */
    public function getTagCloud(int $limit = 20): Collection
    {
        $posts = $this->getAllPosts();
        $tagCounts = [];

        foreach ($posts as $post) {
            $keywords = $this->parseKeywords($post['keywords'] ?? '');
            foreach ($keywords as $keyword) {
                $normalized = $this->normalizeTag($keyword);
                if (strlen($normalized) >= 3) {
                    $tagCounts[$normalized] = ($tagCounts[$normalized] ?? 0) + 1;
                }
            }
        }

        arsort($tagCounts);
        $tagCounts = array_slice($tagCounts, 0, $limit, true);

        // Calculate tag sizes (1-5 scale based on frequency)
        $maxCount = max($tagCounts) ?: 1;
        $minCount = min($tagCounts) ?: 1;
        $range = max(1, $maxCount - $minCount);

        return collect($tagCounts)->map(function ($count, $tag) use ($minCount, $range) {
            $normalized = ($count - $minCount) / $range;
            $size = round(1 + ($normalized * 4)); // Scale 1-5

            return [
                'name'  => $tag,
                'slug'  => Str::slug($tag),
                'count' => $count,
                'size'  => $size,
            ];
        })->sortBy('name')->values();
    }

    /**
     * Search posts by query.
     */
    public function search(string $query): Collection
    {
        $query = strtolower(trim($query));

        if (empty($query)) {
            return collect();
        }

        return $this->getAllPosts()
            ->filter(function ($post) use ($query) {
                $searchable = strtolower(
                    ($post['title'] ?? '').' '.
                    ($post['description'] ?? '').' '.
                    ($post['keywords'] ?? '')
                );

                return Str::contains($searchable, $query);
            })
            ->values();
    }

    /**
     * Get posts relevant to a product category.
     * Used for showing blog posts on product pages.
     */
    public function getPostsForProductCategory(?string $productCategory, int $limit = 3): Collection
    {
        if (empty($productCategory)) {
            return $this->getLatestPosts($limit);
        }

        $productCategory = strtolower($productCategory);

        // Map product categories to relevant blog keywords
        $relevantKeywords = $this->getRelevantKeywordsForProduct($productCategory);

        $matchingPosts = $this->getAllPosts()
            ->map(function ($post) use ($relevantKeywords) {
                $postKeywords = strtolower($post['keywords'] ?? '');
                $score = 0;
                foreach ($relevantKeywords as $keyword) {
                    if (Str::contains($postKeywords, $keyword)) {
                        $score++;
                    }
                }
                $post['relevance_score'] = $score;

                return $post;
            })
            ->sortByDesc('relevance_score')
            ->take($limit)
            ->values();

        // If no relevant posts found, return latest posts about fake reviews in general
        if ($matchingPosts->isEmpty() || $matchingPosts->first()['relevance_score'] === 0) {
            return $this->getLatestPosts($limit);
        }

        return $matchingPosts;
    }

    /**
     * Get category name for a post based on its keywords.
     */
    public function getCategoryForPost(array $post): ?string
    {
        $postKeywords = strtolower($post['keywords'] ?? '');

        foreach (self::CATEGORY_MAPPINGS as $categoryName => $categoryKeywords) {
            foreach ($categoryKeywords as $keyword) {
                if (Str::contains($postKeywords, strtolower($keyword))) {
                    return $categoryName;
                }
            }
        }

        return null;
    }

    /**
     * Parse comma-separated keywords into array.
     */
    private function parseKeywords(string $keywords): array
    {
        return array_filter(
            array_map('trim', explode(',', strtolower($keywords)))
        );
    }

    /**
     * Normalize a tag for display.
     */
    private function normalizeTag(string $tag): string
    {
        return ucwords(trim($tag));
    }

    /**
     * Get relevant blog keywords for a product category.
     */
    private function getRelevantKeywordsForProduct(string $productCategory): array
    {
        // Always include general fake review detection keywords
        $keywords = ['fake reviews', 'consumer protection', 'safe shopping'];

        // Add category-specific keywords
        $categoryMappings = [
            'electronics' => ['ai detection', 'review manipulation'],
            'health'      => ['counterfeit', 'consumer protection'],
            'beauty'      => ['fake reviews', 'consumer protection'],
            'home'        => ['safe shopping', 'review analysis'],
            'sports'      => ['fake reviews', 'consumer protection'],
            'toys'        => ['safe shopping', 'consumer protection'],
        ];

        foreach ($categoryMappings as $category => $extraKeywords) {
            if (Str::contains($productCategory, $category)) {
                $keywords = array_merge($keywords, $extraKeywords);
                break;
            }
        }

        return array_unique($keywords);
    }
}
