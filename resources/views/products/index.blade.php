<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>All Analyzed Products - Amazon Review Analysis Database | SMART SHIELD UI</title>
  <meta name="description" content="Browse {{ number_format($products->total()) }}+ Amazon products analyzed for review authenticity. Each product includes fake review percentage, authenticity grade, and adjusted ratings based on our AI analysis." />
  <meta name="keywords" content="amazon reviews, fake reviews, product analysis, review authenticity, amazon products, review database, authenticity grades" />
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large" />
  <link rel="canonical" href="{{ url('/products') }}" />
  
  {{-- Open Graph --}}
  <meta property="og:type" content="website" />
  <meta property="og:title" content="All Analyzed Products - Amazon Review Analysis Database" />
  <meta property="og:description" content="Browse {{ number_format($products->total()) }}+ Amazon products analyzed for review authenticity by SMART SHIELD UI." />
  <meta property="og:url" content="{{ url('/products') }}" />
  <meta property="og:site_name" content="SMART SHIELD UI" />

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  {{-- JSON-LD for Dataset --}}
  @php
  $jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Dataset',
    'name' => 'SMART SHIELD UI Amazon Review Analysis Database',
    'description' => 'A continuously updated database of Amazon products analyzed for review authenticity, including fake review percentages, authenticity grades, and adjusted ratings.',
    'url' => url('/products'),
    'creator' => [
      '@type' => 'Organization',
      'name' => 'SMART SHIELD UI',
      'url' => url('/')
    ],
    'dateModified' => now()->toIso8601String(),
    'license' => 'https://github.com/stardothosting/nullfake/blob/main/LICENSE',
    'variableMeasured' => [
      [
        '@type' => 'PropertyValue',
        'name' => 'Authenticity Grade',
        'description' => 'Letter grade (A-F) indicating review authenticity'
      ],
      [
        '@type' => 'PropertyValue',
        'name' => 'Fake Review Percentage',
        'description' => 'Estimated percentage of reviews that are inauthentic'
      ],
      [
        '@type' => 'PropertyValue',
        'name' => 'Adjusted Rating',
        'description' => 'Product rating after removing suspected fake reviews'
      ]
    ]
  ];
  @endphp
  <script type="application/ld+json">@json($jsonLd)</script>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="min-h-screen bg-gray-100">
  @include('partials.header')

  <!-- Main Content -->
  <main class="max-w-7xl mx-auto px-6 py-8">
    
    <!-- Introductory Content Section -->
    <div class="bg-white rounded-lg shadow-sm p-8 mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-4">Amazon Review Analysis Database</h1>
      <p class="text-lg text-gray-600 mb-6">
        Browse our database of <strong>{{ number_format($products->total()) }} Amazon products</strong> analyzed for review authenticity. 
        Each product has been processed through our multi-stage AI analysis pipeline to detect fake reviews, 
        manipulation patterns, and provide adjusted ratings you can trust.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-3xl font-bold text-indigo-600 mb-1">{{ number_format($products->total()) }}+</div>
          <div class="text-sm text-gray-600">Products Analyzed</div>
          <p class="text-xs text-gray-500 mt-2">Each product includes comprehensive review analysis with fake percentage and authenticity grade.</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-3xl font-bold text-green-600 mb-1">A-F</div>
          <div class="text-sm text-gray-600">Authenticity Grades</div>
          <p class="text-xs text-gray-500 mt-2">Our grading system weighs timing patterns, language analysis, reviewer behavior, and statistical anomalies.</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-3xl font-bold text-blue-600 mb-1">Open</div>
          <div class="text-sm text-gray-600">Transparent Methodology</div>
          <p class="text-xs text-gray-500 mt-2">Our analysis code is <a href="https://github.com/stardothosting/nullfake" class="text-indigo-600 hover:underline">open source</a> for verification.</p>
        </div>
      </div>

      <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <h3 class="font-semibold text-blue-900 mb-2">How We Analyze Products</h3>
        <p class="text-sm text-blue-800">
          Our AI-powered system examines multiple signals: review timing patterns, natural language processing for AI-generated content, 
          reviewer behavior analysis, verification rates, and statistical anomaly detection. Each signal contributes to the final 
          authenticity grade. <a href="/how-it-works" class="underline">Learn more about our methodology</a>.
        </p>
      </div>

      <div class="prose prose-sm max-w-none text-gray-600">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Understanding Our Grades</h3>
        <p class="mb-3">
          Our letter grades summarize complex analysis into actionable guidance:
        </p>
        <ul class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
          <li><span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded font-medium">Grade A</span> High confidence in review authenticity (90-100% score)</li>
          <li><span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 rounded font-medium">Grade B</span> Generally authentic with minor concerns (80-89% score)</li>
          <li><span class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded font-medium">Grade C</span> Mixed signals; review with caution (70-79% score)</li>
          <li><span class="inline-block px-2 py-1 bg-orange-100 text-orange-800 rounded font-medium">Grade D</span> Significant authenticity concerns (60-69% score)</li>
          <li><span class="inline-block px-2 py-1 bg-red-100 text-red-800 rounded font-medium">Grade F</span> High probability of manipulation (below 60% score)</li>
        </ul>
        <p class="mt-4 text-xs text-gray-500">
          Note: We err toward consumer protection. Some legitimate products may receive lower grades due to unusual but genuine patterns. 
          Our <a href="/blog/review-analysis-methodology-explained" class="text-indigo-600 hover:underline">methodology documentation</a> explains our detection approach in detail.
        </p>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
      <form method="GET" action="{{ route('products.index') }}" class="space-y-4 md:space-y-0 md:flex md:items-end md:gap-4">
        <!-- Search -->
        <div class="flex-1">
          <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Products</label>
          <input type="text" 
                 name="search" 
                 id="search" 
                 value="{{ $filters['search'] ?? '' }}"
                 placeholder="Product title or ASIN..."
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        
        <!-- Grade Filter -->
        <div class="w-full md:w-40">
          <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
          <select name="grade" 
                  id="grade" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Grades</option>
            <option value="A" {{ ($filters['grade'] ?? '') === 'A' ? 'selected' : '' }}>Grade A</option>
            <option value="B" {{ ($filters['grade'] ?? '') === 'B' ? 'selected' : '' }}>Grade B</option>
            <option value="C" {{ ($filters['grade'] ?? '') === 'C' ? 'selected' : '' }}>Grade C</option>
            <option value="D" {{ ($filters['grade'] ?? '') === 'D' ? 'selected' : '' }}>Grade D</option>
            <option value="F" {{ ($filters['grade'] ?? '') === 'F' ? 'selected' : '' }}>Grade F</option>
          </select>
        </div>
        
        <!-- Country Filter -->
        <div class="w-full md:w-40">
          <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
          <select name="country" 
                  id="country" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Countries</option>
            @foreach($countries as $country)
              <option value="{{ $country }}" {{ ($filters['country'] ?? '') === $country ? 'selected' : '' }}>
                {{ strtoupper($country) }}
              </option>
            @endforeach
          </select>
        </div>
        
        <!-- Filter Button -->
        <div class="flex gap-2">
          <button type="submit" 
                  class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg font-medium">
            Filter
          </button>
          @if(!empty($filters['grade']) || !empty($filters['country']) || !empty($filters['search']))
            <a href="{{ route('products.index') }}" 
               class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg font-medium">
              Clear
            </a>
          @endif
        </div>
      </form>
      
      @if(!empty($filters['grade']) || !empty($filters['country']) || !empty($filters['search']))
        <div class="mt-4 pt-4 border-t border-gray-200">
          <p class="text-sm text-gray-600">
            Active filters:
            @if(!empty($filters['search']))
              <span class="inline-flex items-center px-2 py-1 bg-gray-100 rounded text-xs mr-2">
                Search: "{{ $filters['search'] }}"
              </span>
            @endif
            @if(!empty($filters['grade']))
              <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-xs mr-2">
                Grade {{ $filters['grade'] }}
              </span>
            @endif
            @if(!empty($filters['country']))
              <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs mr-2">
                {{ strtoupper($filters['country']) }}
              </span>
            @endif
          </p>
        </div>
      @endif
    </div>

    <!-- Products Section -->
    <div class="bg-white rounded-lg shadow-sm p-8">
      <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-900">Analyzed Products</h2>
        <p class="text-gray-600 text-sm">Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ number_format($products->total()) }}</p>
      </div>

      @if($products->count() > 0)
        <!-- Products Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
          @foreach($products as $product)
            <div class="bg-gray-50 rounded-lg p-4 hover:shadow-md transition-shadow">
              <a href="{{ route('amazon.product.show.slug', ['country' => $product->country, 'asin' => $product->asin, 'slug' => $product->slug ?? 'product']) }}" class="block">
                <!-- Product Image -->
                @if($product->product_image_url)
                  <div class="h-48 mb-3 bg-white rounded overflow-hidden flex items-center justify-center">
                    <img 
                      src="{{ $product->product_image_url }}" 
                      alt="{{ $product->product_title }}"
                      class="max-w-full max-h-full object-contain"
                      loading="lazy"
                    />
                  </div>
                @else
                  <div class="h-48 mb-3 bg-gray-200 rounded flex items-center justify-center">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                  </div>
                @endif

                <!-- Product Title -->
                <h3 class="text-sm font-medium text-gray-900 mb-2 line-clamp-2 leading-tight">
                  {{ Str::limit($product->product_title, 80) }}
                </h3>

                <!-- Grade and Stats -->
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <!-- Authenticity Grade -->
                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                      @if($product->grade === 'A') bg-green-100 text-green-800
                      @elseif($product->grade === 'B') bg-blue-100 text-blue-800
                      @elseif($product->grade === 'C') bg-yellow-100 text-yellow-800
                      @elseif($product->grade === 'D') bg-orange-100 text-orange-800
                      @else bg-red-100 text-red-800
                      @endif">
                      Grade {{ $product->grade }}
                    </span>
                  </div>

                  <!-- Fake Percentage -->
                  <div class="text-xs text-gray-500">
                    {{ number_format($product->fake_percentage, 1) }}% fake
                  </div>
                </div>

                <!-- Amazon Rating vs Adjusted -->
                @if($product->amazon_rating && $product->adjusted_rating)
                  <div class="mt-2 text-xs text-gray-500">
                    <div class="flex justify-between">
                      <span>Amazon: {{ number_format($product->amazon_rating, 1) }}/5</span>
                      <span>Adjusted: {{ number_format($product->adjusted_rating, 1) }}/5</span>
                    </div>
                  </div>
                @endif

                <!-- Analysis Date -->
                <div class="mt-2 text-xs text-gray-400">
                  @if($product->last_analyzed_at && $product->first_analyzed_at && $product->last_analyzed_at->ne($product->first_analyzed_at))
                    Re-analyzed {{ $product->last_analyzed_at->diffForHumans() }}
                  @else
                    Analyzed {{ ($product->first_analyzed_at ?? $product->updated_at)->diffForHumans() }}
                  @endif
                </div>
              </a>
            </div>
          @endforeach
        </div>

        <!-- Pagination -->
        <div class="flex justify-center">
          {{ $products->links('pagination::tailwind') }}
        </div>
      @else
        <!-- No Products Found -->
        <div class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m14 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m14 0H6m14 0l-3-3m-3 3l3-3" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
          <p class="mt-1 text-sm text-gray-500">No analyzed products are available yet.</p>
          <div class="mt-6">
            <a href="/" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:bg-brand-dark">
              Start analyzing products
            </a>
          </div>
        </div>
      @endif
    </div>

    <!-- Educational Content Section -->
    <div class="bg-white rounded-lg shadow-sm p-8 mt-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Understanding Amazon Review Authenticity</h2>
      
      <div class="prose prose-sm max-w-none text-gray-600">
        <p class="mb-4">
          Amazon reviews significantly influence purchasing decisions, but studies estimate that up to 42% of reviews on some products 
          may be fake or manipulated. Our database helps consumers make informed decisions by providing independent analysis of 
          review authenticity for thousands of products.
        </p>

        <h3 class="text-lg font-semibold text-gray-800 mt-6 mb-3">What Makes Reviews Suspicious?</h3>
        <p class="mb-3">
          Our analysis examines multiple signals that indicate potential manipulation:
        </p>
        <ul class="list-disc list-inside space-y-2 mb-4">
          <li><strong>Timing Patterns:</strong> Genuine reviews trickle in over time. Spikes of reviews within short periods often indicate coordinated campaigns.</li>
          <li><strong>Language Analysis:</strong> AI-generated reviews follow predictable patterns — perfect grammar, structured conclusions, and generic praise without specifics.</li>
          <li><strong>Reviewer Behavior:</strong> Accounts that only leave 5-star reviews or review only one brand are statistically suspicious.</li>
          <li><strong>Verification Anomalies:</strong> Unusually high verified purchase rates can indicate discount-scheme manipulation.</li>
        </ul>

        <h3 class="text-lg font-semibold text-gray-800 mt-6 mb-3">How to Use This Database</h3>
        <p class="mb-3">
          Before purchasing a product on Amazon, search for it in our database or analyze it using our 
          <a href="/" class="text-indigo-600 hover:underline">free review checker</a>. Products with Grades A or B generally have 
          authentic reviews. Products with Grade C should be reviewed carefully. Products with Grades D or F likely have significant 
          manipulation — consider alternatives or verify the product through other sources before purchasing.
        </p>

        <div class="bg-gray-50 rounded-lg p-4 mt-6">
          <h4 class="font-semibold text-gray-800 mb-2">Important Disclaimers</h4>
          <ul class="text-sm text-gray-600 space-y-1">
            <li>• Our analysis indicates review authenticity, not product quality. A product can have authentic reviews and still be poorly made.</li>
            <li>• False positives occur — some legitimate products may receive low grades due to unusual but genuine patterns.</li>
            <li>• Analysis is a snapshot in time. Products may change as reviews are added or removed.</li>
            <li>• This tool supplements, but doesn't replace, your own judgment when making purchasing decisions.</li>
          </ul>
        </div>
      </div>
    </div>

  </main>

  <!-- Back to analyzer link -->
  <div class="max-w-7xl mx-auto px-6 mt-8 mb-4">
    <a href="{{ route('home') }}" class="text-indigo-600 hover:text-indigo-800 flex items-center text-sm font-medium">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Analyze new product
    </a>
  </div>

  <!-- Footer -->
  @include('partials.footer')

</div>

<style>
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
</style>

</body>
</html>
