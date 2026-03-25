<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Blog - Amazon Review Analysis Insights | SMART SHIELD UI</title>
  <meta name="description" content="Expert insights on fake Amazon reviews, consumer protection, AI-generated reviews, and safe online shopping strategies from the SMART SHIELD UI team." />
  <meta name="keywords" content="fake review blog, amazon shopping tips, review fraud, consumer protection, ai reviews" />
  <meta name="author" content="shift8 web" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="{{ url('/blog') }}" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url('/blog') }}" />
  <meta property="og:title" content="SMART SHIELD UI Blog - Amazon Review Analysis Insights" />
  <meta property="og:description" content="Expert insights on detecting fake reviews, safe shopping, and consumer protection." />
  
  <!-- Blog Schema -->
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Blog",
    "name": "SMART SHIELD UI Blog",
    "description": "Expert insights on fake Amazon reviews, consumer protection, and safe online shopping",
    "url": "{{ url('/blog') }}",
    "publisher": {
      "@@type": "Organization",
      "name": "SMART SHIELD UI",
      "logo": {
        "@@type": "ImageObject",
        "url": "{{ url('/img/nullfake.svg') }}"
      }
    }
  }
  </script>
  
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
    
    <!-- Hero Section -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">SMART SHIELD UI Blog</h1>
      <p class="text-xl text-gray-600">
        Expert insights on fake reviews, consumer protection, and safe Amazon shopping strategies.
      </p>
    </div>

    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      
      <!-- Main Content - Blog Posts Grid -->
      <div class="lg:w-2/3">
        <div class="grid md:grid-cols-2 gap-6">
          @foreach($posts as $postData)
            <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
              <a href="{{ route('blog.show', ['slug' => $postData['slug']]) }}" class="block">
                <!-- Featured Image -->
                @if(!empty($postData['image']))
                  <img
                    src="{{ $postData['image'] }}"
                    alt="{{ $postData['title'] }}"
                    class="w-full h-40 object-cover"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                  />
                @else
                  <div class="h-40 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <svg class="w-12 h-12 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </div>
                @endif
                
                <!-- Post Content -->
                <div class="p-5">
                  <div class="flex items-center text-sm text-gray-500 mb-2">
                    <time datetime="{{ $postData['date'] }}">{{ date('M j, Y', strtotime($postData['date'])) }}</time>
                    <span class="mx-2">•</span>
                    <span>{{ $postData['author'] ?? 'SMART SHIELD UI Team' }}</span>
                  </div>
                  
                  <h2 class="text-lg font-bold text-gray-900 mb-2 hover:text-indigo-600 transition-colors line-clamp-2">
                    {{ $postData['title'] }}
                  </h2>
                  
                  <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                    {{ $postData['description'] }}
                  </p>
                  
                  <span class="text-indigo-600 font-medium text-sm hover:text-indigo-800 transition-colors">
                    Read More →
                  </span>
                </div>
              </a>
            </article>
          @endforeach
        </div>
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
