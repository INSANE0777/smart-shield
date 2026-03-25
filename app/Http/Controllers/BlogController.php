<?php

namespace App\Http\Controllers;

use App\Services\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class BlogController extends Controller
{
    public function __construct(
        private BlogService $blogService
    ) {
    }

    /**
     * Display blog index page with all posts.
     */
    public function index()
    {
        $posts = $this->blogService->getAllPosts();

        // Sidebar data
        $latestPosts = $this->blogService->getLatestPosts(5);
        $categories = $this->blogService->getCategories();
        $tagCloud = $this->blogService->getTagCloud(15);

        return view('blog.index', compact('posts', 'latestPosts', 'categories', 'tagCloud'));
    }

    /**
     * Display individual blog post.
     */
    public function show($slug)
    {
        $posts = (array) config('blog.posts', []);
        $authors = (array) config('blog.authors', []);
        $post = collect($posts)->firstWhere('slug', $slug);

        // Fallback: if config is stale (or a cached blog index links to an older slug),
        // attempt to render a matching view by convention: resources/views/blog/posts/{slug}.blade.php
        if (!$post) {
            $guessedView = 'blog.posts.'.$slug;

            if (!View::exists($guessedView)) {
                return redirect()->route('blog.index');
            }

            $post = [
                'slug'        => $slug,
                'title'       => ucwords(str_replace('-', ' ', (string) $slug)),
                'description' => 'Read the latest post from the SMART SHIELD UI blog.',
                'keywords'    => 'amazon reviews, fake reviews, consumer protection',
                'author'      => 'SMART SHIELD UI Team',
                'author_key'  => 'research-team',
                'date'        => now()->toDateString(),
                'image'       => 'https://images.unsplash.com/photo-1484995342839-a9eb42974616?auto=format&fit=crop&w=1200&h=630&q=70',
                'view'        => $guessedView,
            ];

            // Sidebar data for fallback posts
            $relatedPosts = $this->blogService->getLatestPosts(5, $slug);
            $categories = $this->blogService->getCategories();
            $tagCloud = $this->blogService->getTagCloud(15);

            return view($guessedView, compact('post', 'relatedPosts', 'categories', 'tagCloud'));
        }

        if (empty($post['view']) || !View::exists($post['view'])) {
            return redirect()->route('blog.index');
        }

        // Resolve author_key to author name if not already set
        if (!isset($post['author']) && isset($post['author_key'])) {
            $authorKey = $post['author_key'];
            $post['author'] = $authors[$authorKey]['name'] ?? 'SMART SHIELD UI Team';
        }

        $post = array_merge(
            [
                'image'       => 'https://images.unsplash.com/photo-1484995342839-a9eb42974616?auto=format&fit=crop&w=1200&h=630&q=70',
                'keywords'    => 'amazon reviews, fake reviews, consumer protection',
                'description' => 'Read the latest post from the SMART SHIELD UI blog.',
                'author'      => 'SMART SHIELD UI Team',
                'author_key'  => 'research-team',
            ],
            $post
        );

        // Sidebar data
        $relatedPosts = $this->blogService->getRelatedPosts($post, 5);
        $categories = $this->blogService->getCategories();
        $tagCloud = $this->blogService->getTagCloud(15);

        return view($post['view'], compact('post', 'relatedPosts', 'categories', 'tagCloud'));
    }

    /**
     * Search blog posts.
     */
    public function search(Request $request)
    {
        $query = $request->input('q') ?? '';
        $posts = $this->blogService->search($query);

        // Sidebar data
        $latestPosts = $this->blogService->getLatestPosts(5);
        $categories = $this->blogService->getCategories();
        $tagCloud = $this->blogService->getTagCloud(15);

        return view('blog.search', compact('posts', 'query', 'latestPosts', 'categories', 'tagCloud'));
    }

    /**
     * Display posts by category.
     */
    public function category($category)
    {
        $posts = $this->blogService->getPostsByCategory($category);

        // Get category name for display
        $categoryName = collect($this->blogService->getCategories())
            ->firstWhere('slug', $category)['name'] ?? ucwords(str_replace('-', ' ', $category));

        // Sidebar data
        $latestPosts = $this->blogService->getLatestPosts(5);
        $categories = $this->blogService->getCategories();
        $tagCloud = $this->blogService->getTagCloud(15);

        return view('blog.category', compact('posts', 'category', 'categoryName', 'latestPosts', 'categories', 'tagCloud'));
    }
}

