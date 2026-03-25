@php
    $postTitle = (string) ($post['title'] ?? 'SMART SHIELD UI Blog');
    $description = (string) ($post['description'] ?? 'Practical notes on fake reviews, consumer protection, and safer Amazon shopping.');
    $keywords = $post['keywords'] ?? null;
    $slug = $post['slug'] ?? null;

    // Enhanced author handling for E-E-A-T compliance
    $authorKey = $post['author_key'] ?? null;
    $authors = config('blog.authors', []);
    $authorData = $authorKey && isset($authors[$authorKey]) ? $authors[$authorKey] : null;
    $author = $authorData ? $authorData['name'] : ($post['author'] ?? 'SMART SHIELD UI Team');
    $authorBio = $authorData['bio'] ?? '';
    $authorRole = $authorData['role'] ?? '';

    $baseUrl = rtrim((string) (config('app.url') ?: url('/')), '/');
    $canonical = $slug ? ($baseUrl.'/blog/'.$slug) : ($baseUrl.'/blog');

    $imageUrl = $post['image'] ?? null;
    if (is_string($imageUrl) && $imageUrl !== '' && !str_starts_with($imageUrl, 'http')) {
        $imageUrl = $baseUrl.'/'.ltrim($imageUrl, '/');
    }

    try {
        $publishedIso = isset($post['date'])
            ? \Illuminate\Support\Carbon::parse((string) $post['date'])->toIso8601String()
            : now()->toIso8601String();
        $modifiedIso = isset($post['last_updated'])
            ? \Illuminate\Support\Carbon::parse((string) $post['last_updated'])->toIso8601String()
            : $publishedIso;
    } catch (\Throwable) {
        $publishedIso = now()->toIso8601String();
        $modifiedIso = $publishedIso;
    }

    $readingTime = $post['reading_time'] ?? 5;
    $sources = $post['sources'] ?? [];

    // Build comprehensive JSON-LD for E-E-A-T
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $postTitle,
        'description' => $description,
        'image' => is_string($imageUrl) && $imageUrl !== '' ? [$imageUrl] : [],
        'author' => $authorData ? [
            '@type' => 'Person',
            'name' => $author,
            'jobTitle' => $authorRole,
            'description' => $authorBio,
            'url' => $authorData['links']['website'] ?? $authorData['links']['github'] ?? null,
            'sameAs' => array_values(array_filter($authorData['links'] ?? [])),
        ] : [
            '@type' => 'Organization',
            'name' => $author,
            'url' => $baseUrl,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'SMART SHIELD UI',
            'url' => $baseUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $baseUrl.'/img/nullfake.svg',
            ],
            'sameAs' => [
                'https://github.com/INSANE0777/smart-shield',
            ],
        ],
        'datePublished' => $publishedIso,
        'dateModified' => $modifiedIso,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonical,
        ],
        'wordCount' => $readingTime * 200, // Approximate words based on reading time
        'timeRequired' => 'PT'.$readingTime.'M',
        'inLanguage' => 'en-US',
        'isAccessibleForFree' => true,
        'isPartOf' => [
            '@type' => 'Blog',
            '@id' => $baseUrl.'/blog',
            'name' => 'SMART SHIELD UI Blog',
            'description' => 'Consumer protection insights, fake review detection guides, and Amazon shopping safety tips.',
        ],
    ];

    // Add citations if sources exist
    if (!empty($sources)) {
        $jsonLd['citation'] = array_map(function($source) {
            return [
                '@type' => 'CreativeWork',
                'name' => $source,
            ];
        }, $sources);
    }
@endphp

<title>{{ $postTitle }} | SMART SHIELD UI Blog</title>
<meta name="description" content="{{ $description }}" />
@if(!empty($keywords))
  <meta name="keywords" content="{{ $keywords }}" />
@endif
<meta name="author" content="{{ $author }}" />

<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
<link rel="canonical" href="{{ $canonical }}" />

{{-- Open Graph --}}
<meta property="og:type" content="article" />
<meta property="og:site_name" content="SMART SHIELD UI" />
<meta property="og:url" content="{{ $canonical }}" />
<meta property="og:title" content="{{ $postTitle }}" />
<meta property="og:description" content="{{ $description }}" />
@if(is_string($imageUrl) && $imageUrl !== '')
  <meta property="og:image" content="{{ $imageUrl }}" />
@endif
<meta property="article:published_time" content="{{ $publishedIso }}" />
<meta property="article:modified_time" content="{{ $modifiedIso }}" />
<meta property="article:author" content="{{ $author }}" />
<meta property="article:section" content="Consumer Protection" />

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $postTitle }}" />
<meta name="twitter:description" content="{{ $description }}" />
@if(is_string($imageUrl) && $imageUrl !== '')
  <meta name="twitter:image" content="{{ $imageUrl }}" />
@endif

{{-- Additional E-E-A-T signals --}}
<meta name="article:author" content="{{ $author }}" />
<meta name="date" content="{{ $post['date'] ?? date('Y-m-d') }}" />
@if(isset($post['last_updated']))
<meta name="last-modified" content="{{ $post['last_updated'] }}" />
@endif

<script type="application/ld+json">@json($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

