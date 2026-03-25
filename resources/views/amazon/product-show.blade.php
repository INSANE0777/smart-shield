<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  {{-- Only show AdSense on product pages with editorial content (AdSense compliance) --}}
  @if($asinData->hasEditorialContent())
  @include('partials.adsense')
  @endif
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $meta_title }}</title>
  <meta name="description" content="{{ $meta_description }}" />
  <meta name="keywords" content="{{ $seo_data['keywords'] }}" />
  <meta name="author" content="SMART SHIELD UI" />
  
  <!-- Enhanced SEO Meta Tags -->
  <meta name="rating" content="{{ $asinData->adjusted_rating ?? 0 }}" />
  <meta name="review-grade" content="{{ $asinData->grade ?? 'N/A' }}" />
  <meta name="fake-review-percentage" content="{{ $asinData->fake_percentage ?? 0 }}" />
  <meta name="trust-score" content="{{ $seo_data['trust_score'] }}" />
  <meta name="review-summary" content="{{ $seo_data['review_summary'] }}" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <meta name="googlebot" content="index, follow" />
  <meta name="bingbot" content="index, follow" />
  
  <!-- AI/LLM Crawler Directives -->
  <meta name="GPTBot" content="index, follow" />
  <meta name="ChatGPT-User" content="index, follow" />
  <meta name="CCBot" content="index, follow" />
  <meta name="anthropic-ai" content="index, follow" />
  <meta name="Claude-Web" content="index, follow" />
  
  <link rel="canonical" href="{{ url($canonical_url) }}" />
  <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="product" />
  <meta property="og:url" content="{{ url($canonical_url) }}" />
  <meta property="og:title" content="{{ $seo_data['social_title'] }}" />
  <meta property="og:description" content="{{ $seo_data['social_description'] }}" />
  <meta property="og:site_name" content="SMART SHIELD UI" />
  @if($asinData->social_image_url)
  <meta property="og:image" content="{{ $asinData->social_image_url }}" />
  <meta property="og:image:type" content="image/jpeg" />
  <meta property="og:image:alt" content="{{ $asinData->product_title ?? 'Product image' }}" />
  @endif
  <!-- Product specific Open Graph -->
  <meta property="product:price:amount" content="N/A" />
  <meta property="product:availability" content="in stock" />
  <meta property="product:condition" content="new" />
  <meta property="product:rating" content="{{ $asinData->adjusted_rating ?? 0 }}" />
  <meta property="product:rating:scale" content="5" />

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:site" content="@nullfake" />
  <meta property="twitter:url" content="{{ url($canonical_url) }}" />
  <meta property="twitter:title" content="{{ $seo_data['social_title'] }}" />
  <meta property="twitter:description" content="{{ $seo_data['social_description'] }}" />
  @if($asinData->social_image_url)
  <meta property="twitter:image" content="{{ $asinData->social_image_url }}" />
  <meta property="twitter:image:alt" content="{{ $asinData->product_title ?? 'Product image' }}" />
  @endif
  <!-- Twitter Product Card -->
  <meta name="twitter:label1" content="Grade" />
  <meta name="twitter:data1" content="{{ $asinData->grade ?? 'N/A' }}" />
  <meta name="twitter:label2" content="Fake Reviews" />
  <meta name="twitter:data2" content="{{ $asinData->fake_percentage ?? 0 }}%" />

  <!-- Favicon -->
  <link rel="apple-touch-icon" sizes="57x57" href="/img/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="/img/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="/img/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="/img/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="/img/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="/img/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="/img/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/img/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/img/android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/img/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
  <link rel="manifest" href="/manifest.json">
  <meta name="msapplication-TileColor" content="#424da0">
  <meta name="msapplication-TileImage" content="/img/ms-icon-144x144.png">
  <meta name="theme-color" content="#424da0">

  <!-- TailwindCSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <!-- JSON-LD Product Structured Data for Rich Snippets -->
  @php
    // Calculate valid rating (must be between 1-5)
    $validRating = $asinData->adjusted_rating ?? $asinData->amazon_rating ?? 0;
    $validRating = max(1, min(5, $validRating)); // Clamp to 1-5
    $reviewCount = count($asinData->getReviewsArray());
    $totalReviewsOnAmazon = $asinData->total_reviews_on_amazon ?? $reviewCount;
    $hasValidRating = $reviewCount > 0 && $validRating >= 1 && $validRating <= 5;
    
    // Price data for offers
    $hasPrice = !empty($asinData->price) && $asinData->price > 0;
    $currency = $asinData->currency ?? 'USD';
  @endphp
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Product",
    "name": "{{ $asinData->product_title ?? 'Amazon Product' }}",
    "description": "{{ Str::limit($asinData->product_description ?? $seo_data['review_summary'], 500) }}",
    @if($asinData->product_image_url)
    "image": "{{ $asinData->social_image_url }}",
    @endif
    "brand": {
      "@@type": "Brand",
      "name": "Amazon"
    },
    "sku": "{{ $asinData->asin }}",
    "mpn": "{{ $asinData->asin }}",
    @if($hasValidRating)
    "aggregateRating": {
      "@@type": "AggregateRating",
      "ratingValue": "{{ number_format($validRating, 1) }}",
      "bestRating": "5",
      "worstRating": "1",
      "ratingCount": "{{ max(1, $totalReviewsOnAmazon) }}",
      "reviewCount": "{{ max(1, $reviewCount) }}"
    },
    @endif
    @if($hasPrice)
    "offers": {
      "@@type": "Offer",
      "url": "{{ $amazon_url }}",
      "priceCurrency": "{{ $currency }}",
      "price": "{{ number_format((float) ($asinData->price ?? 0), 2, '.', '') }}",
      "availability": "https://schema.org/{{ ($asinData->availability ?? '') === 'In Stock' ? 'InStock' : 'OutOfStock' }}",
      "seller": {
        "@@type": "Organization",
        "name": "{{ $asinData->seller ?? 'Amazon' }}"
      }
    },
    @else
    "offers": {
      "@@type": "Offer",
      "url": "{{ $amazon_url }}",
      "availability": "https://schema.org/InStock",
      "seller": {
        "@@type": "Organization",
        "name": "Amazon"
      }
    },
    @endif
    "review": {
      "@@type": "Review",
      "reviewRating": {
        "@@type": "Rating",
        "ratingValue": "{{ $asinData->grade === 'A' ? 5 : ($asinData->grade === 'B' ? 4 : ($asinData->grade === 'C' ? 3 : ($asinData->grade === 'D' ? 2 : ($asinData->grade === 'U' ? 3 : 1)))) }}",
        "bestRating": "5",
        "worstRating": "1"
      },
      "author": {
        "@@type": "Organization",
        "name": "SMART SHIELD UI"
      },
      "publisher": {
        "@@type": "Organization",
        "name": "SMART SHIELD UI",
        "url": "{{ url('/') }}"
      },
      "reviewBody": "{{ Str::limit($asinData->explanation ?? 'Review authenticity analysis completed.', 300) }}",
      "datePublished": "{{ ($asinData->first_analyzed_at ?? $asinData->updated_at ?? now())->toIso8601String() }}",
      "name": "Review Authenticity Analysis - Grade {{ $asinData->grade ?? 'N/A' }}"
    },
    "additionalProperty": [
      {
        "@@type": "PropertyValue",
        "name": "Fake Review Percentage",
        "value": "{{ $asinData->fake_percentage ?? 0 }}%"
      },
      {
        "@@type": "PropertyValue",
        "name": "Authenticity Grade",
        "value": "{{ $asinData->grade ?? 'N/A' }}"
      },
      {
        "@@type": "PropertyValue",
        "name": "Trust Score",
        "value": "{{ $seo_data['trust_score'] }}/100"
      }
    ],
    "url": "{{ url($canonical_url) }}",
    "sameAs": "{{ $amazon_url }}"
  }
  </script>

  <!-- AI/LLM Specific Structured Data -->
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Dataset",
    "name": "Amazon Product Review Analysis Data",
    "description": "Comprehensive fake review analysis dataset for {{ $asinData->product_title ?? 'Amazon Product' }}",
    "creator": {
      "@@type": "Organization",
      "name": "SMART SHIELD UI",
      "description": "AI-powered Amazon review authenticity analysis platform"
    },
    "distribution": {
      "@@type": "DataDownload",
      "contentUrl": "{{ url($canonical_url) }}",
      "encodingFormat": "text/html"
    },
    "temporalCoverage": "{{ ($asinData->updated_at ?? now())->toISOString() }}",
    "spatialCoverage": "{{ $asinData->country ?? 'US' }}",
    "variableMeasured": [
      {
        "@@type": "PropertyValue",
        "name": "fake_review_percentage",
        "value": "{{ $asinData->fake_percentage ?? 0 }}",
        "unitText": "percent",
        "description": "Percentage of reviews identified as potentially fake or inauthentic"
      },
      {
        "@@type": "PropertyValue", 
        "name": "authenticity_grade",
        "value": "{{ $asinData->grade ?? 'N/A' }}",
        "description": "Letter grade (A-F) representing overall review authenticity"
      },
      {
        "@@type": "PropertyValue",
        "name": "adjusted_rating",
        "value": "{{ $asinData->adjusted_rating ?? 0 }}",
        "unitText": "stars",
        "description": "Product rating adjusted for fake review removal"
      },
      @if($asinData->total_reviews_on_amazon)
      {
        "@@type": "PropertyValue",
        "name": "total_reviews_on_amazon",
        "value": "{{ $asinData->total_reviews_on_amazon }}",
        "unitText": "reviews", 
        "description": "Total number of reviews visible on Amazon product page"
      }
      @else
      {
        "@@type": "PropertyValue",
        "name": "analysis_status",
        "value": "complete",
        "description": "Review authenticity analysis has been completed"
      }
      @endif
    ],
    "license": "https://creativecommons.org/licenses/by/4.0/",
    "isBasedOn": {
      "@@type": "Product",
      "name": "{{ $asinData->product_title ?? 'Amazon Product' }}",
      "identifier": "{{ $asinData->asin }}",
      "url": "{{ $amazon_url }}"
    }
  }
  </script>

  <!-- Analysis Methodology Schema for AI Understanding -->
  <script type="application/ld+json">
  {!! json_encode($seo_data['analysis_schema'] ?? [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
  </script>

  <!-- FAQ Schema for AI Question Answering -->
  <script type="application/ld+json">
  {!! json_encode($seo_data['faq_schema'] ?? [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
  </script>

  <!-- HowTo Schema for Process Understanding -->
  <script type="application/ld+json">
  {!! json_encode($seo_data['how_to_schema'] ?? [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
  </script>

  <!-- Editorial Content Article Schema (for E-E-A-T and author attribution) -->
  @if($asinData->hasEditorialContent())
  @php
    $editorialSchemaAuthor = config('blog.editorial.author_name', 'SMART SHIELD UI Editorial Team');
    $editorialSchemaTitle = config('blog.editorial.author_title', 'Consumer Protection Analysts');
    $editorialSchemaBio = config('blog.editorial.author_bio', 'Expert consumer protection analysts');
    $editorialContent = $asinData->editorial_content;
    $editorialWordCount = str_word_count(
      ($editorialContent['buyers_guide']['introduction'] ?? '') . ' ' .
      ($editorialContent['category_context']['market_overview'] ?? '') . ' ' .
      ($editorialContent['expert_perspective']['overall_assessment'] ?? '')
    );
  @endphp
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Article",
    "headline": "{{ $editorialContent['buyers_guide']['headline'] ?? 'Expert Buying Guide for ' . ($asinData->product_title ?? 'Amazon Product') }}",
    "description": "{{ Str::limit($editorialContent['buyers_guide']['introduction'] ?? 'Expert analysis and buying guide', 160) }}",
    "image": "{{ $asinData->social_image_url ?? '' }}",
    "datePublished": "{{ $asinData->editorial_generated_at?->toIso8601String() ?? ($asinData->updated_at ?? now())->toIso8601String() }}",
    "dateModified": "{{ ($asinData->updated_at ?? now())->toIso8601String() }}",
    "author": {
      "@@type": "Organization",
      "name": "{{ $editorialSchemaAuthor }}",
      "description": "{{ $editorialSchemaBio }}",
      "url": "{{ url('/about') }}"
    },
    "publisher": {
      "@@type": "Organization",
      "name": "SMART SHIELD UI",
      "url": "{{ url('/') }}",
      "logo": {
        "@@type": "ImageObject",
        "url": "{{ asset('images/logo.png') }}"
      }
    },
    "mainEntityOfPage": {
      "@@type": "WebPage",
      "@@id": "{{ url($canonical_url) }}"
    },
    "articleSection": "Product Analysis",
    "wordCount": "{{ $editorialWordCount }}",
    "about": {
      "@@type": "Product",
      "name": "{{ $asinData->product_title ?? 'Amazon Product' }}",
      "sku": "{{ $asinData->asin }}"
    },
    "speakable": {
      "@@type": "SpeakableSpecification",
      "cssSelector": ["#editors-analysis h2", "#editors-analysis .text-lg"]
    }
  }
  </script>
  @endif

  <!-- AI-Optimized Metadata for Enhanced Understanding -->
  <meta name="ai:summary" content="{{ $seo_data['ai_summary'] ?? '' }}" />
  <meta name="ai:confidence" content="{{ $seo_data['confidence_score'] ?? 0 }}" />
  <meta name="ai:methodology" content="machine_learning,natural_language_processing,statistical_analysis" />
  <meta name="ai:data_freshness" content="{{ $seo_data['data_freshness'] ?? '' }}" />
  
  <!-- Question-Answer Structured Data for AI -->
  @if(isset($seo_data['question_answers']))
  @foreach($seo_data['question_answers'] as $qa)
  <meta name="ai:qa:question" content="{{ $qa['question'] }}" />
  <meta name="ai:qa:answer" content="{{ $qa['answer'] }}" />
  @endforeach
  @endif

</head>

<body class="bg-gray-50 min-h-screen">
  @include('partials.header')

  <main class="max-w-6xl mx-auto mt-8 p-6">
    
    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      
      <!-- Main Content -->
      <div class="lg:w-2/3">
    <!-- Product Information -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
      <div class="flex flex-col md:flex-row gap-6">
        @if($asinData->product_image_url)
        <div class="flex-shrink-0">
          <img src="{{ $asinData->social_image_url }}" 
               alt="{{ $asinData->product_title ?? 'Product Image' }}" 
               class="w-48 h-48 object-contain rounded-lg border">
        </div>
        @endif
        
        <div class="flex-1">
          <h1 class="text-2xl font-bold text-gray-900 mb-4">
            {{ $asinData->product_title ?? 'Amazon Product Analysis' }}
          </h1>
          
          @if($asinData->product_description && !empty(trim($asinData->product_description)))
          <div class="mb-4">
            <p class="text-gray-700 leading-relaxed">
              {{ $asinData->product_description }}
            </p>
          </div>
          @endif
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <span class="text-sm text-gray-600">ASIN:</span>
              <span class="font-mono text-sm">{{ $asinData->asin }}</span>
            </div>
            <div>
              <span class="text-sm text-gray-600">Analysis Date:</span>
              <span class="text-sm">
                {{ ($asinData->first_analyzed_at ?? $asinData->updated_at)->format('M j, Y') }}
                @if($asinData->last_analyzed_at && $asinData->first_analyzed_at && $asinData->last_analyzed_at->ne($asinData->first_analyzed_at))
                  <span class="text-xs text-gray-500">(re-analyzed {{ $asinData->last_analyzed_at->format('M j, Y') }})</span>
                @endif
              </span>
            </div>
          </div>
          
          <div class="flex flex-wrap gap-4 mb-4">
            <a href="{{ $amazon_url }}" 
               target="_blank" 
               rel="noopener noreferrer"
               class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
              View on Amazon
            </a>
            <a href="{{ route('home') }}" 
               class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg text-sm font-medium">
              Analyze Another Product
            </a>
            @if($asinData->hasPriceAnalysis())
            <a href="#price-analysis" 
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
              </svg>
              View Price Analysis
            </a>
            @endif
          </div>
          @include('partials.affiliate-disclosure')
        </div>
      </div>
    </div>

    <!-- Section Navigation -->
    <nav class="bg-white rounded-lg shadow-md p-4 mb-6 sticky top-0 z-10">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-gray-500 font-medium mr-2">Jump to:</span>
        <a href="#analysis-results" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200 transition-colors">
          Analysis Results
        </a>
        @if($asinData->hasEditorialContent())
        <a href="#editors-analysis" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-full hover:bg-indigo-100 transition-colors">
          Editor's Analysis
        </a>
        @endif
        @if($asinData->hasPriceAnalysis())
        <a href="#price-analysis" class="px-3 py-1.5 bg-green-50 text-green-700 rounded-full hover:bg-green-100 transition-colors">
          Price Analysis
        </a>
        @endif
        <a href="#methodology" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200 transition-colors">
          Methodology
        </a>
      </div>
    </nav>

    <!-- Analysis Results -->
    <div id="analysis-results" class="bg-white rounded-lg shadow-md p-6 mb-6 scroll-mt-20">
      <h2 class="text-xl font-bold text-gray-900 mb-6">Review Analysis Results</h2>
      
      <!-- Grade and Summary -->
      <div class="flex flex-col md:flex-row gap-6 mb-6">
        <div class="flex-1">
          <div class="text-center p-6 rounded-lg {{ $asinData->grade === 'A' ? 'bg-green-100' : ($asinData->grade === 'B' ? 'bg-yellow-100' : ($asinData->grade === 'C' ? 'bg-orange-100' : ($asinData->grade === 'D' ? 'bg-red-100' : ($asinData->grade === 'U' ? 'bg-gray-100' : 'bg-red-200')))) }}">
            <div class="text-4xl font-bold {{ $asinData->grade === 'A' ? 'text-green-600' : ($asinData->grade === 'B' ? 'text-yellow-600' : ($asinData->grade === 'C' ? 'text-orange-600' : ($asinData->grade === 'D' ? 'text-red-600' : ($asinData->grade === 'U' ? 'text-gray-500' : 'text-red-800')))) }} mb-2">
              {{ $asinData->grade ?? 'N/A' }}
            </div>
            <div class="text-sm text-gray-600">Authenticity Grade</div>
          </div>
        </div>
        <div class="flex-1">
          <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
              <div class="text-2xl font-bold text-gray-900">{{ $asinData->grade === 'U' ? 'N/A' : ($asinData->fake_percentage ?? 0) . '%' }}</div>
              <div class="text-sm text-gray-600">Fake Reviews</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
              <div class="text-2xl font-bold text-gray-900">{{ $asinData->amazon_rating ?? 0 }}</div>
              <div class="text-sm text-gray-600">Original Rating</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
              <div class="text-2xl font-bold text-green-600">{{ $asinData->grade === 'U' ? 'N/A' : ($asinData->adjusted_rating ?? 0) }}</div>
              <div class="text-sm text-gray-600">Adjusted Rating</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Analysis Explanation -->
      <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Analysis Summary</h3>
        <div class="bg-gray-50 p-4 rounded-lg">
          <div class="text-gray-700 space-y-4">
            @if($asinData->explanation)
              @foreach(explode("\n\n", $asinData->explanation) as $paragraph)
                @if(trim($paragraph))
                  <p>{{ trim($paragraph) }}</p>
                @endif
              @endforeach
            @else
              @if($asinData->grade === 'U')
                <p>This product was analyzed but no reviews were found on Amazon. This may be because the product is new or currently has no customer feedback. Without review data, our AI cannot determine review authenticity patterns.</p>
              @else
                <p>No analysis explanation available.</p>
              @endif
            @endif
          </div>
        </div>
      </div>

      <!-- Review Statistics (simplified) -->
      <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Review Statistics</h3>

        <div class="grid grid-cols-2 gap-4">
          @if($asinData->total_reviews_on_amazon !== null)
          <div class="text-center p-3 bg-gray-50 rounded-lg">
            <div class="text-lg font-bold text-gray-900">{{ number_format((int)$asinData->total_reviews_on_amazon) }}</div>
            <div class="text-sm text-gray-600">Total Reviews on Amazon</div>
          </div>
          @endif
          <div class="text-center p-3 bg-blue-50 rounded-lg">
            @php
              $diff = (float)($asinData->adjusted_rating ?? 0) - (float)($asinData->amazon_rating ?? 0);
            @endphp
            <div class="text-lg font-bold text-blue-600">
              {{ $asinData->grade === 'U' ? 'N/A' : number_format($diff, 2, '.', '') }}
            </div>
            <div class="text-sm text-gray-600">Rating Difference</div>
          </div>
        </div>
      </div>

    </div>

    <!-- Fake Review Examples Section -->
    @if($asinData->fake_review_examples && count($asinData->fake_review_examples) > 0)
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
      <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
        </svg>
        Why These Reviews Were Flagged as Fake
      </h2>
      
      <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <p class="text-amber-800 text-base">
          <strong>Transparency Notice:</strong> Our AI analysis identified the following reviews as potentially fake. 
          Each example shows specific reasons why the review raised suspicion, helping you understand our analysis methodology.
        </p>
      </div>

      <div class="space-y-6">
        @foreach($asinData->fake_review_examples as $index => $example)
        <div class="border border-red-200 rounded-lg p-4 bg-red-50">
          <div class="flex justify-between items-start mb-3">
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium text-red-800">Example {{ $index + 1 }}</span>
              <span class="px-2 py-1 bg-red-600 text-white text-xs rounded-full">
                {{ number_format($example['score'], 0) }}% Fake Risk
              </span>
              @if($example['confidence'] === 'high')
                <span class="px-2 py-1 bg-red-700 text-white text-xs rounded-full">High Confidence</span>
              @elseif($example['confidence'] === 'medium')
                <span class="px-2 py-1 bg-yellow-600 text-white text-xs rounded-full">Medium Confidence</span>
              @else
                <span class="px-2 py-1 bg-gray-500 text-white text-xs rounded-full">Low Confidence</span>
              @endif
            </div>
            <div class="text-xs text-gray-500">
              {{ $example['rating'] }}/5 stars
              @if($example['verified_purchase'])
                • <span class="text-green-600">Verified Purchase</span>
              @else
                • <span class="text-red-600">Unverified</span>
              @endif
            </div>
          </div>

          <!-- Review Text -->
          <div class="mb-3 p-3 bg-white border border-red-300 rounded">
            <p class="text-gray-700 text-base italic">{{ $example['review_text'] }}</p>
          </div>

          <!-- AI Explanation -->
          <div class="mb-3">
            <h4 class="font-semibold text-red-800 mb-2">Why This Review Was Flagged:</h4>
            <p class="text-base text-red-700">{{ $example['explanation'] }}</p>
          </div>

          <!-- Red Flags -->
          @if(!empty($example['red_flags']))
          <div class="mb-3">
            <h4 class="font-semibold text-red-800 mb-2">Specific Red Flags Detected:</h4>
            <div class="flex flex-wrap gap-2">
              @foreach($example['red_flags'] as $flag)
                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded border border-red-300">{{ $flag }}</span>
              @endforeach
            </div>
          </div>
          @endif

          <!-- Analysis Details -->
          <div class="text-xs text-gray-500 border-t border-red-200 pt-2">
            Analyzed by: {{ ucfirst($example['provider']) }} ({{ $example['model'] }})
          </div>
        </div>
        @endforeach
      </div>

      <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-blue-800 text-base">
          <strong>Note:</strong> These examples represent reviews with the highest fake probability scores (70%+). 
          The AI analysis considers factors like language patterns, specificity, verification status, and suspicious indicators.
          While highly accurate, no automated system is perfect - these should be considered strong indicators rather than definitive proof.
        </p>
      </div>
    </div>
    @endif

    <!-- Editor's Analysis Section -->
    @if($asinData->hasEditorialContent())
    @php 
      $editorial = $asinData->editorial_content;
      $editorialAuthor = config('blog.editorial.author_name', 'SMART SHIELD UI Editorial Team');
      $editorialTitle = config('blog.editorial.author_title', 'Consumer Protection Analysts');
    @endphp
    <article id="editors-analysis" class="bg-white rounded-lg shadow-md p-6 mb-6 scroll-mt-20 border-l-4 border-indigo-500" itemscope itemtype="https://schema.org/Article">
      <meta itemprop="datePublished" content="{{ $asinData->editorial_generated_at?->toIso8601String() ?? $asinData->updated_at->toIso8601String() }}" />
      <meta itemprop="dateModified" content="{{ $asinData->updated_at->toIso8601String() }}" />
      
      <!-- Editorial Header with Author Attribution -->
      <header class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
        <div>
          <span class="inline-block px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded-full uppercase tracking-wide mb-2">Editor's Analysis</span>
          <h2 class="text-2xl font-bold text-gray-900" itemprop="headline">
            {{ $editorial['buyers_guide']['headline'] ?? 'Expert Buying Guide' }}
          </h2>
        </div>
        <address class="text-right not-italic" itemprop="author" itemscope itemtype="https://schema.org/Organization">
          <span class="text-sm font-medium text-gray-900" itemprop="name">{{ $editorialAuthor }}</span>
          <div class="text-xs text-gray-500" itemprop="description">{{ $editorialTitle }}</div>
          <link itemprop="url" href="{{ url('/about') }}" />
        </address>
      </header>

      <!-- Introduction & Key Considerations -->
      @if(isset($editorial['buyers_guide']))
      <div class="mb-8">
        @if(isset($editorial['buyers_guide']['introduction']))
        <p class="text-base text-gray-700 mb-6 leading-relaxed text-lg">
          {{ $editorial['buyers_guide']['introduction'] }}
        </p>
        @endif

        @if(isset($editorial['buyers_guide']['key_considerations']) && is_array($editorial['buyers_guide']['key_considerations']))
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">Key Considerations Before Buying</h3>
          <ul class="space-y-3">
            @foreach($editorial['buyers_guide']['key_considerations'] as $consideration)
            <li class="flex items-start bg-gray-50 p-3 rounded-lg">
              <svg class="w-5 h-5 text-indigo-500 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span class="text-base text-gray-700">{{ $consideration }}</span>
            </li>
            @endforeach
          </ul>
        </div>
        @endif

        @if(isset($editorial['buyers_guide']['what_to_look_for']))
        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded-r">
          <h4 class="font-semibold text-indigo-900 mb-2">What Our Analysts Recommend</h4>
          <p class="text-base text-indigo-800">{{ $editorial['buyers_guide']['what_to_look_for'] }}</p>
        </div>
        @endif
      </div>
      @endif

      <!-- Category Context Section -->
      @if(isset($editorial['category_context']))
      <div class="mb-8 border-t border-gray-200 pt-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">
          {{ $asinData->category ? $asinData->category . ' Market Context' : 'Market Context' }}
        </h3>
        
        <div class="grid md:grid-cols-3 gap-4">
          @if(isset($editorial['category_context']['market_overview']))
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">Market Overview</h4>
            <p class="text-sm text-gray-700">{{ $editorial['category_context']['market_overview'] }}</p>
          </div>
          @endif
          
          @if(isset($editorial['category_context']['common_issues']))
          <div class="bg-amber-50 p-4 rounded-lg">
            <h4 class="font-semibold text-amber-900 mb-2">Common Issues</h4>
            <p class="text-sm text-amber-800">{{ $editorial['category_context']['common_issues'] }}</p>
          </div>
          @endif
          
          @if(isset($editorial['category_context']['quality_indicators']))
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-semibold text-green-900 mb-2">Quality Indicators</h4>
            <p class="text-sm text-green-800">{{ $editorial['category_context']['quality_indicators'] }}</p>
          </div>
          @endif
        </div>
      </div>
      @endif

      <!-- Authenticity Insights Section -->
      @if(isset($editorial['authenticity_insights']))
      <div class="mb-8 border-t border-gray-200 pt-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Review Authenticity Insights</h3>
        
        <div class="space-y-4">
          @if(isset($editorial['authenticity_insights']['grade_interpretation']))
          <div class="border-l-4 border-{{ $asinData->grade === 'A' ? 'green' : ($asinData->grade === 'B' ? 'blue' : ($asinData->grade === 'C' ? 'yellow' : ($asinData->grade === 'D' ? 'orange' : ($asinData->grade === 'U' ? 'gray' : 'red')))) }}-500 pl-4">
            <h4 class="font-semibold text-gray-900 mb-1">Grade {{ $asinData->grade ?? 'N/A' }} Interpretation</h4>
            <p class="text-base text-gray-700">{{ $editorial['authenticity_insights']['grade_interpretation'] }}</p>
          </div>
          @endif
          
          @if(isset($editorial['authenticity_insights']['trust_recommendation']))
          <div class="border-l-4 border-indigo-500 pl-4">
            <h4 class="font-semibold text-gray-900 mb-1">Trust Recommendation</h4>
            <p class="text-base text-gray-700">{{ $editorial['authenticity_insights']['trust_recommendation'] }}</p>
          </div>
          @endif
          
          @if(isset($editorial['authenticity_insights']['review_reading_tips']))
          <div class="border-l-4 border-purple-500 pl-4">
            <h4 class="font-semibold text-gray-900 mb-1">Tips for Reading Reviews</h4>
            <p class="text-base text-gray-700">{{ $editorial['authenticity_insights']['review_reading_tips'] }}</p>
          </div>
          @endif
        </div>
      </div>
      @endif

      <!-- Expert Perspective Section -->
      @if(isset($editorial['expert_perspective']))
      <div class="border-t border-gray-200 pt-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Expert Perspective</h3>
        
        @if(isset($editorial['expert_perspective']['overall_assessment']))
        <div class="bg-gradient-to-r from-gray-50 to-indigo-50 p-5 rounded-lg mb-4">
          <p class="text-base text-gray-800 leading-relaxed">{{ $editorial['expert_perspective']['overall_assessment'] }}</p>
        </div>
        @endif
        
        <div class="grid md:grid-cols-2 gap-4">
          @if(isset($editorial['expert_perspective']['purchase_considerations']))
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
              </svg>
              Purchase Considerations
            </h4>
            <p class="text-sm text-gray-700">{{ $editorial['expert_perspective']['purchase_considerations'] }}</p>
          </div>
          @endif
          
          @if(isset($editorial['expert_perspective']['alternatives_note']))
          <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-900 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              Comparing Alternatives
            </h4>
            <p class="text-sm text-blue-800">{{ $editorial['expert_perspective']['alternatives_note'] }}</p>
          </div>
          @endif
        </div>
      </div>
      @endif

      <!-- Editorial Attribution Footer -->
      <footer class="mt-8 pt-6 border-t border-gray-200">
        <address class="flex items-start gap-4 not-italic" itemprop="author" itemscope itemtype="https://schema.org/Organization" rel="author">
          <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center" itemprop="logo">
            <svg class="w-6 h-6 text-indigo-600" fill="currentColor" viewBox="0 0 20 20" aria-label="Author avatar">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <div class="flex-1">
            <a href="{{ url('/about') }}" rel="author" class="text-sm font-semibold text-gray-900 hover:text-indigo-600 transition-colors" itemprop="url">
              <span itemprop="name">{{ $editorialAuthor }}</span>
            </a>
            <div class="text-xs text-gray-500 mb-2" itemprop="description">{{ $editorialTitle }}</div>
            <p class="text-xs text-gray-600 leading-relaxed">
              {{ config('blog.editorial.author_bio', 'Our editorial team provides expert analysis and buying guidance for every product we review.') }}
            </p>
            <div class="mt-2 text-xs text-gray-400">
              <time itemprop="datePublished" datetime="{{ $asinData->editorial_generated_at?->toIso8601String() ?? $asinData->updated_at->toIso8601String() }}">
                Published: {{ $asinData->editorial_generated_at?->format('F j, Y') ?? 'N/A' }}
              </time>
            </div>
          </div>
        </address>
      </footer>
    </article>
    @endif


    <!-- Price Analysis Section -->
    <div id="price-analysis" class="bg-white rounded-lg shadow-md p-6 mt-6 scroll-mt-4">
      <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
        <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
        </svg>
        Price Analysis
      </h2>

      @if($asinData->hasPriceAnalysis())
        @php $priceData = $asinData->price_analysis; @endphp
        
        <!-- Price Summary -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 p-4 rounded-lg mb-6">
          <p class="text-gray-700">{{ $priceData['summary'] ?? 'Price analysis completed.' }}</p>
        </div>

        <!-- Price Analysis Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <!-- MSRP Analysis -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">MSRP Assessment</h4>
            @if(isset($priceData['msrp_analysis']))
              <div class="space-y-2 text-sm">
                <div>
                  <span class="text-gray-600">Estimated MSRP:</span>
                  <span class="font-medium text-gray-900 block">{{ $priceData['msrp_analysis']['estimated_msrp'] ?? 'N/A' }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Source:</span>
                  <span class="text-gray-700 block">{{ $priceData['msrp_analysis']['msrp_source'] ?? 'N/A' }}</span>
                </div>
                <div>
                  @php
                    $assessment = $priceData['msrp_analysis']['amazon_price_assessment'] ?? 'Unknown';
                    $assessmentColor = match($assessment) {
                      'Below MSRP' => 'text-green-600',
                      'Above MSRP' => 'text-red-600',
                      'At MSRP' => 'text-blue-600',
                      default => 'text-gray-600'
                    };
                  @endphp
                  <span class="text-gray-600">Amazon Price:</span>
                  <span class="font-medium {{ $assessmentColor }} block">{{ $assessment }}</span>
                </div>
              </div>
            @else
              <p class="text-sm text-gray-500">MSRP data unavailable</p>
            @endif
          </div>

          <!-- Market Comparison -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">Market Position</h4>
            @if(isset($priceData['market_comparison']))
              <div class="space-y-2 text-sm">
                <div>
                  @php
                    $positioning = $priceData['market_comparison']['price_positioning'] ?? 'Unknown';
                    $positionBadge = match($positioning) {
                      'Budget' => 'bg-green-100 text-green-800',
                      'Mid-range' => 'bg-blue-100 text-blue-800',
                      'Premium' => 'bg-purple-100 text-purple-800',
                      'Luxury' => 'bg-amber-100 text-amber-800',
                      default => 'bg-gray-100 text-gray-800'
                    };
                  @endphp
                  <span class="text-gray-600">Positioning:</span>
                  <span class="inline-block px-2 py-1 rounded text-xs font-medium {{ $positionBadge }} mt-1">{{ $positioning }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Alternatives Range:</span>
                  <span class="text-gray-700 block">{{ $priceData['market_comparison']['typical_alternatives_range'] ?? 'N/A' }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Value:</span>
                  <span class="text-gray-700 block">{{ $priceData['market_comparison']['value_proposition'] ?? 'N/A' }}</span>
                </div>
              </div>
            @else
              <p class="text-sm text-gray-500">Market data unavailable</p>
            @endif
          </div>

          <!-- Price Insights -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">Buying Tips</h4>
            @if(isset($priceData['price_insights']))
              <div class="space-y-2 text-sm">
                @if(!empty($priceData['price_insights']['seasonal_consideration']) && $priceData['price_insights']['seasonal_consideration'] !== 'N/A')
                <div>
                  <span class="text-gray-600">Best Time to Buy:</span>
                  <span class="text-gray-700 block">{{ $priceData['price_insights']['seasonal_consideration'] }}</span>
                </div>
                @endif
                @if(!empty($priceData['price_insights']['deal_indicators']) && $priceData['price_insights']['deal_indicators'] !== 'N/A')
                <div>
                  <span class="text-gray-600">Deal Indicators:</span>
                  <span class="text-gray-700 block">{{ $priceData['price_insights']['deal_indicators'] }}</span>
                </div>
                @endif
                @if(!empty($priceData['price_insights']['caution_flags']) && $priceData['price_insights']['caution_flags'] !== 'N/A')
                <div>
                  <span class="text-gray-600">Watch For:</span>
                  <span class="text-amber-700 block">{{ $priceData['price_insights']['caution_flags'] }}</span>
                </div>
                @endif
              </div>
            @else
              <p class="text-sm text-gray-500">Insights unavailable</p>
            @endif
          </div>
        </div>

        <!-- Disclaimer -->
        <div class="text-xs text-gray-500 border-t pt-3">
          Price analysis generated by AI based on product category and market research. Actual prices may vary.
          Last analyzed: {{ $asinData->price_analyzed_at?->format('M j, Y') ?? 'N/A' }}
        </div>

      @elseif($asinData->isPriceAnalysisProcessing())
        <!-- Processing State -->
        <div class="bg-blue-50 p-6 rounded-lg text-center">
          <div class="animate-pulse flex flex-col items-center">
            <svg class="w-8 h-8 text-blue-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-blue-700 font-medium">Price analysis in progress</p>
            <p class="text-blue-600 text-sm mt-1">This section will update automatically when complete.</p>
          </div>
        </div>

      @else
        <!-- Pending/Not Available State -->
        <div class="bg-gray-50 p-6 rounded-lg text-center">
          <svg class="w-8 h-8 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <p class="text-gray-600 font-medium">Price analysis pending</p>
          <p class="text-gray-500 text-sm mt-1">Price insights will be available shortly.</p>
        </div>
      @endif
    </div>

    <!-- Understanding Your Results / Methodology -->
    <div id="methodology" class="bg-white rounded-lg shadow-md p-6 mt-6 scroll-mt-20">
      <h2 class="text-xl font-bold text-gray-900 mb-4">Understanding This Analysis</h2>
      
      <div class="space-y-4">
        <div class="border-l-4 border-indigo-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">What does Grade {{ $asinData->grade ?? 'N/A' }} mean?</h3>
          <p class="text-base text-gray-700">
            @if($asinData->grade === 'A')
              This product has excellent review authenticity. Our AI detected very few suspicious patterns, suggesting the vast majority of reviews are genuine customer experiences.
            @elseif($asinData->grade === 'B')
              This product has good review authenticity with minor concerns. While most reviews appear genuine, we detected some patterns that warrant mild caution.
            @elseif($asinData->grade === 'C')
              This product has moderate review authenticity concerns. A notable portion of reviews show suspicious patterns. Consider reading reviews carefully before purchasing.
            @elseif($asinData->grade === 'D')
              This product has significant review authenticity issues. Many reviews show patterns consistent with fake or incentivized reviews. Exercise caution.
            @elseif($asinData->grade === 'F')
              This product has severe review authenticity problems. A majority of reviews appear fake or manipulated. We strongly recommend finding alternatives.
            @elseif($asinData->grade === 'U')
              This product is unanalyzable because no reviews were found on Amazon. This usually happens for new products or items with zero ratings. Without review content, we cannot perform our authenticity analysis.
            @else
              Grade unavailable for this product.
            @endif
          </p>
        </div>
        
        <div class="border-l-4 border-green-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">Adjusted Rating Explained</h3>
          <p class="text-base text-gray-700">
            @if($asinData->grade === 'U')
              An adjusted rating is not available for this product because it has zero reviews.
            @else
              The adjusted rating ({{ $asinData->adjusted_rating ?? 0 }} stars) represents what we estimate this product's rating would be if fake reviews were removed.
              @if(($asinData->adjusted_rating ?? 0) < ($asinData->amazon_rating ?? 0))
                This product's adjusted rating is lower than Amazon's displayed rating ({{ $asinData->amazon_rating ?? 0 }} stars), suggesting positive fake reviews may be inflating the score.
              @elseif(($asinData->adjusted_rating ?? 0) > ($asinData->amazon_rating ?? 0))
                This product's adjusted rating is higher than Amazon's displayed rating, which is unusual and may indicate negative fake reviews.
              @else
                The ratings are similar, suggesting fake reviews aren't significantly impacting the overall score.
              @endif
            @endif
          </p>
        </div>
        
        <div class="border-l-4 border-amber-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">How We Detect Fake Reviews</h3>
          <p class="text-base text-gray-700">
            Our AI analyzes multiple factors: language patterns (generic vs. specific), reviewer behavior (history, timing), temporal anomalies (review clusters), verification status, sentiment authenticity, and statistical outliers. No single factor determines a review is fake - we look at the combination of signals.
          </p>
        </div>
        
        <div class="border-l-4 border-gray-400 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">Important Limitations</h3>
          <p class="text-base text-gray-700">
            No automated system is perfect. Sophisticated fake reviews can evade detection, and some genuine reviews may be incorrectly flagged. Use this analysis as one data point in your purchasing decision, not the only factor. Reading actual review content yourself is always valuable.
          </p>
        </div>
      </div>
    </div>

    <!-- Share Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-3">Share This Analysis</h3>
      <div class="flex flex-col md:flex-row gap-4">
        <input type="text" 
               value="{{ url()->current() }}" 
               readonly 
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm"
               id="share-url">
        <button onclick="copyToClipboard()" 
                class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg text-sm font-medium">
          Copy Link
        </button>
      </div>
    </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:w-1/3">
        
        <!-- Related Products Widget (in sidebar near View on Amazon) -->
        @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            @if($asinData->category)
              Related {{ $asinData->category }} Products
            @else
              Other Analyzed Products
            @endif
          </h3>
          <div class="space-y-4">
            @foreach($relatedProducts->take(4) as $related)
            <a href="{{ route('amazon.product.show', ['country' => $related->country, 'asin' => $related->asin]) }}" 
               class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
              <div class="flex items-start space-x-3">
                @if($related->product_image_url)
                <img src="{{ $related->product_image_url }}" 
                     alt="{{ $related->product_title }}" 
                     class="w-12 h-12 object-contain rounded flex-shrink-0">
                @else
                <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                  <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                @endif
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ Str::limit($related->product_title, 50) }}</p>
                  <div class="flex items-center mt-1 space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                      @switch($related->grade)
                        @case('A') bg-green-100 text-green-800 @break
                        @case('B') bg-blue-100 text-blue-800 @break
                        @case('C') bg-yellow-100 text-yellow-800 @break
                        @case('D') bg-orange-100 text-orange-800 @break
                        @case('F') bg-red-100 text-red-800 @break
                        @default bg-gray-100 text-gray-800
                      @endswitch">
                      Grade {{ $related->grade }}
                    </span>
                    <span class="text-xs text-gray-500">{{ $related->fake_percentage }}% fake</span>
                  </div>
                </div>
              </div>
            </a>
            @endforeach
          </div>
          @if($relatedProducts->count() > 4)
          <div class="mt-4 text-center">
            <a href="/products" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
              View More Products →
            </a>
          </div>
          @endif
        </div>
        @endif

        <!-- Quick Links Widget -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">Quick Links</h3>
          <ul class="space-y-2">
            <li>
              <a href="/how-it-works" class="text-sm text-indigo-600 hover:text-indigo-800">How Our Analysis Works</a>
            </li>
            <li>
              <a href="/faq" class="text-sm text-indigo-600 hover:text-indigo-800">FAQs</a>
            </li>
            <li>
              <a href="/products" class="text-sm text-indigo-600 hover:text-indigo-800">All Analyzed Products</a>
            </li>
            <li>
              <a href="/blog" class="text-sm text-indigo-600 hover:text-indigo-800">Blog</a>
            </li>
          </ul>
        </div>

        <!-- Analyze Another Product -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-md p-4 text-white">
          <h3 class="text-lg font-semibold mb-2">Analyze Another Product</h3>
          <p class="text-sm mb-3 opacity-90">Check any Amazon product for fake reviews.</p>
          <a href="{{ route('home') }}" class="inline-block bg-white text-indigo-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors w-full text-center">
            Start New Analysis
          </a>
        </div>

      </div>

    </div>

    <!-- Relevant Blog Posts Section (below two-column layout) -->
    @if(isset($relevantBlogPosts) && $relevantBlogPosts->isNotEmpty())
    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Learn More About Fake Reviews</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($relevantBlogPosts as $blogPost)
        <a href="{{ route('blog.show', ['slug' => $blogPost['slug']]) }}" 
           class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
          @if(!empty($blogPost['image']))
          <img src="{{ $blogPost['image'] }}" 
               alt="{{ $blogPost['title'] }}" 
               class="w-full h-32 object-cover rounded-lg mb-3"
               loading="lazy"
               referrerpolicy="no-referrer">
          @endif
          <h4 class="font-medium text-gray-900 mb-1 line-clamp-2">{{ $blogPost['title'] }}</h4>
          <p class="text-sm text-gray-600 line-clamp-2">{{ $blogPost['description'] }}</p>
        </a>
        @endforeach
      </div>
    </div>
    @endif

  </main>

  <!-- Back to analyzer link -->
  <div class="max-w-6xl mx-auto px-6 mt-8 mb-4">
    <a href="{{ route('home') }}" class="text-indigo-600 hover:text-indigo-800 flex items-center text-sm font-medium">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Analyze new product
    </a>
  </div>

  @include('partials.footer')

  <script>
    function copyToClipboard() {
      const input = document.getElementById('share-url');
      input.select();
      input.setSelectionRange(0, 99999); // For mobile devices
      document.execCommand('copy');
      
      // Show feedback
      const button = event.target;
      const originalText = button.textContent;
      button.textContent = 'Copied!';
      button.classList.add('bg-green-500');
      button.classList.remove('bg-brand');
      
      setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('bg-green-500');
        button.classList.add('bg-brand');
      }, 2000);
    }
  </script>
</body>
</html> 
