<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $categoryName }} - SMART SHIELD UI Blog</title>
  <meta name="description" content="Browse {{ $categoryName }} articles on the SMART SHIELD UI blog. Expert insights on fake Amazon reviews and consumer protection." />
  <meta name="keywords" content="{{ strtolower($categoryName) }}, fake reviews, amazon, consumer protection" />
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="{{ route('blog.category', ['category' => $category]) }}" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ route('blog.category', ['category' => $category]) }}" />
  <meta property="og:title" content="{{ $categoryName }} - SMART SHIELD UI Blog" />
  <meta property="og:description" content="Browse {{ $categoryName }} articles on the SMART SHIELD UI blog." />
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
  @livewireStyles

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-BYWNNLXEYV"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-BYWNNLXEYV');
  </script>

</head>
<body class="bg-gray-100 text-gray-800">

  @include('partials.header')

  <main class="max-w-6xl mx-auto mt-10 px-6 mb-16">
    
    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <div class="mb-4">
        <a href="/blog" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Back to Blog</a>
      </div>
      <h1 class="text-3xl font-bold text-gray-900 mb-2">
        {{ $categoryName }}
      </h1>
      <p class="text-gray-600">
        {{ $posts->count() }} {{ Str::plural('article', $posts->count()) }} in this category
      </p>
    </div>

    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      
      <!-- Main Content -->
      <div class="lg:w-2/3">
        @if($posts->isEmpty())
          <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <h2 class="text-xl font-semibold text-gray-900 mb-2">No articles yet</h2>
            <p class="text-gray-600 mb-4">
              We haven't published any articles in this category yet. Check back soon!
            </p>
            <a href="/blog" class="inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
              Browse All Articles
            </a>
          </div>
        @else
          <div class="space-y-6">
            @foreach($posts as $postData)
              <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <a href="{{ route('blog.show', ['slug' => $postData['slug']]) }}" class="flex flex-col md:flex-row">
                  @if(!empty($postData['image']))
                    <img
                      src="{{ $postData['image'] }}"
                      alt="{{ $postData['title'] }}"
                      class="w-full md:w-48 h-48 md:h-auto object-cover"
                      loading="lazy"
                      referrerpolicy="no-referrer"
                    />
                  @endif
                  
                  <div class="p-6 flex-1">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                      <time datetime="{{ $postData['date'] }}">{{ date('F j, Y', strtotime($postData['date'])) }}</time>
                      <span class="mx-2">•</span>
                      <span>{{ $postData['author'] ?? 'SMART SHIELD UI Team' }}</span>
                    </div>
                    
                    <h2 class="text-xl font-bold text-gray-900 mb-2 hover:text-indigo-600 transition-colors">
                      {{ $postData['title'] }}
                    </h2>
                    
                    <p class="text-gray-600 mb-3 line-clamp-2">
                      {{ $postData['description'] }}
                    </p>
                    
                    <span class="text-indigo-600 font-medium hover:text-indigo-800 transition-colors">
                      Read More →
                    </span>
                  </div>
                </a>
              </article>
            @endforeach
          </div>
        @endif
      </div>

      <!-- Sidebar -->
      <div class="lg:w-1/3">
        @include('partials.blog-sidebar')
      </div>

    </div>

  </main>

  @include('partials.footer')

  @livewireScripts
</body>
</html>
