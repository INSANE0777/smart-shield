<?php

namespace Tests\Feature;

use App\Services\BlogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    private BlogService $blogService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blogService = app(BlogService::class);
    }

    #[Test]
    public function blog_index_page_loads_successfully(): void
    {
        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertSee('Blog');
    }

    #[Test]
    public function blog_index_shows_posts(): void
    {
        $response = $this->get('/blog');

        $response->assertStatus(200);
        // Should show at least one post title
        $posts = $this->blogService->getAllPosts();
        if ($posts->isNotEmpty()) {
            $response->assertSee($posts->first()['title']);
        }
    }

    #[Test]
    public function blog_post_page_loads_successfully(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No blog posts configured');
        }

        $slug = $posts->first()['slug'];
        $response = $this->get("/blog/{$slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function blog_search_returns_results_for_valid_query(): void
    {
        $response = $this->get('/blog/search?q=fake+reviews');

        $response->assertStatus(200);
        $response->assertSee('Search Results');
    }

    #[Test]
    public function blog_search_handles_empty_query(): void
    {
        $response = $this->get('/blog/search?q=');

        $response->assertStatus(200);
        $response->assertSee('0 articles');
    }

    #[Test]
    public function blog_search_escapes_html_in_query(): void
    {
        $xssAttempt = '<script>alert("xss")</script>';
        $response = $this->get('/blog/search?q='.urlencode($xssAttempt));

        $response->assertStatus(200);
        // The script tag should be escaped, not executed
        $response->assertDontSee('<script>alert("xss")</script>', false);
        // Should see the escaped version in the page
        $response->assertSee('&lt;script&gt;', false);
    }

    #[Test]
    public function blog_category_page_loads_successfully(): void
    {
        $categories = $this->blogService->getCategories();

        if ($categories->isEmpty()) {
            $this->markTestSkipped('No categories available');
        }

        $categorySlug = $categories->first()['slug'];
        $response = $this->get("/blog/category/{$categorySlug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function blog_category_shows_correct_posts(): void
    {
        $categories = $this->blogService->getCategories();

        if ($categories->isEmpty()) {
            $this->markTestSkipped('No categories available');
        }

        $category = $categories->first();
        $response = $this->get("/blog/category/{$category['slug']}");

        $response->assertStatus(200);
        $response->assertSee($category['name']);
    }

    #[Test]
    public function blog_service_returns_all_posts(): void
    {
        $posts = $this->blogService->getAllPosts();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $posts);
        // Should have at least some posts based on config
        $this->assertGreaterThan(0, $posts->count());
    }

    #[Test]
    public function blog_service_returns_latest_posts(): void
    {
        $latestPosts = $this->blogService->getLatestPosts(5);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $latestPosts);
        $this->assertLessThanOrEqual(5, $latestPosts->count());
    }

    #[Test]
    public function blog_service_excludes_current_post_from_latest(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->count() < 2) {
            $this->markTestSkipped('Need at least 2 posts for this test');
        }

        $firstPost = $posts->first();
        $latestPosts = $this->blogService->getLatestPosts(10, $firstPost['slug']);

        $slugs = $latestPosts->pluck('slug')->toArray();
        $this->assertNotContains($firstPost['slug'], $slugs);
    }

    #[Test]
    public function blog_service_returns_categories(): void
    {
        $categories = $this->blogService->getCategories();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $categories);

        if ($categories->isNotEmpty()) {
            $firstCategory = $categories->first();
            $this->assertArrayHasKey('name', $firstCategory);
            $this->assertArrayHasKey('slug', $firstCategory);
            $this->assertArrayHasKey('count', $firstCategory);
        }
    }

    #[Test]
    public function blog_service_returns_tag_cloud(): void
    {
        $tagCloud = $this->blogService->getTagCloud(15);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $tagCloud);

        if ($tagCloud->isNotEmpty()) {
            $firstTag = $tagCloud->first();
            $this->assertArrayHasKey('name', $firstTag);
            $this->assertArrayHasKey('slug', $firstTag);
            $this->assertArrayHasKey('count', $firstTag);
            $this->assertArrayHasKey('size', $firstTag);
        }
    }

    #[Test]
    public function blog_service_search_finds_matching_posts(): void
    {
        $results = $this->blogService->search('fake reviews');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        // 'fake reviews' should match many posts
        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function blog_service_search_returns_empty_for_no_matches(): void
    {
        $results = $this->blogService->search('xyznonexistenttermxyz');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(0, $results->count());
    }

    #[Test]
    public function blog_service_search_returns_empty_for_empty_query(): void
    {
        $results = $this->blogService->search('');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(0, $results->count());
    }

    #[Test]
    public function blog_service_gets_related_posts(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No posts available');
        }

        $currentPost = $posts->first();
        $relatedPosts = $this->blogService->getRelatedPosts($currentPost, 5);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $relatedPosts);

        // Related posts should not include the current post
        $slugs = $relatedPosts->pluck('slug')->toArray();
        $this->assertNotContains($currentPost['slug'], $slugs);
    }

    #[Test]
    public function blog_service_gets_posts_by_category(): void
    {
        $categories = $this->blogService->getCategories();

        if ($categories->isEmpty()) {
            $this->markTestSkipped('No categories available');
        }

        $categorySlug = $categories->first()['slug'];
        $posts = $this->blogService->getPostsByCategory($categorySlug);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $posts);
        $this->assertGreaterThan(0, $posts->count());
    }

    #[Test]
    public function blog_service_returns_empty_for_invalid_category(): void
    {
        $posts = $this->blogService->getPostsByCategory('nonexistent-category-slug');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $posts);
        $this->assertEquals(0, $posts->count());
    }

    #[Test]
    public function blog_service_gets_posts_for_product_category(): void
    {
        $posts = $this->blogService->getPostsForProductCategory('Electronics', 3);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $posts);
        $this->assertLessThanOrEqual(3, $posts->count());
    }

    #[Test]
    public function blog_service_returns_latest_for_null_product_category(): void
    {
        $posts = $this->blogService->getPostsForProductCategory(null, 3);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $posts);
        $this->assertLessThanOrEqual(3, $posts->count());
    }

    #[Test]
    public function blog_sidebar_appears_on_index(): void
    {
        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertSee('Search Blog');
        $response->assertSee('Categories');
    }

    #[Test]
    public function blog_sidebar_appears_on_posts(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No blog posts configured');
        }

        $slug = $posts->first()['slug'];
        $response = $this->get("/blog/{$slug}");

        $response->assertStatus(200);
        $response->assertSee('Search Blog');
    }

    #[Test]
    public function blog_posts_have_seo_meta_tags(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No blog posts configured');
        }

        $post = $posts->first();
        $response = $this->get("/blog/{$post['slug']}");

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:type" content="article"', false);
        $response->assertSee('application/ld+json', false);
    }

    #[Test]
    public function new_blog_posts_are_accessible(): void
    {
        // Test the 4 new posts created today
        $newSlugs = [
            'amazon-choice-badge-explained',
            'browser-extensions-fake-review-detection',
            'amazon-listing-hijacking-explained',
            'international-amazon-fake-review-patterns',
        ];

        foreach ($newSlugs as $slug) {
            $response = $this->get("/blog/{$slug}");
            $response->assertStatus(200, "Blog post {$slug} should be accessible");
        }
    }

    #[Test]
    public function blog_search_form_has_csrf_protection(): void
    {
        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertSee('csrf-token', false);
    }

    #[Test]
    public function blog_posts_include_author_bio(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No blog posts configured');
        }

        $slug = $posts->first()['slug'];
        $response = $this->get("/blog/{$slug}");

        $response->assertStatus(200);
        $response->assertSee('About the Author');
    }

    #[Test]
    public function blog_posts_include_sources(): void
    {
        $posts = $this->blogService->getAllPosts();

        if ($posts->isEmpty()) {
            $this->markTestSkipped('No blog posts configured');
        }

        // Check a post that should have sources
        $response = $this->get('/blog/how-to-spot-fake-amazon-reviews');

        $response->assertStatus(200);
        $response->assertSee('Sources');
    }
}
