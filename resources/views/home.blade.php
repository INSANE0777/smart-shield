<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $seoData['meta_title'] ?? 'SMART SHIELD UI - Amazon Review Analysis' }}</title>
  <meta name="description" content="{{ $seoData['meta_description'] ?? 'AI-powered Amazon review analysis across 14+ countries. Detect fake reviews instantly.' }}" />
  <meta name="keywords" content="{{ $seoData['keywords'] ?? 'amazon review analysis, fake review detector, ai review analysis' }}" />
  <meta name="author" content="shift8 web" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <meta name="googlebot" content="index, follow" />
  <meta name="bingbot" content="index, follow" />
  
  <link rel="canonical" href="{{ url('/') }}" />
  <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}" />

  <!-- Favicon and App Icons -->
  <link rel="apple-touch-icon" sizes="57x57" href="/img/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="/img/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="/img/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="/img/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="/img/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="/img/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="/img/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/img/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192"  href="/img/android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/img/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
  <link rel="manifest" href="/manifest.json">
  <meta name="msapplication-TileColor" content="#424da0">
  <meta name="msapplication-TileImage" content="/img/ms-icon-144x144.png">
  <meta name="theme-color" content="#424da0">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url('/') }}" />
  <meta property="og:title" content="SMART SHIELD UI - Amazon Review Analysis" />
  <meta property="og:description" content="AI-powered Amazon review analyzer. Instantly detect fake, AI-generated, or suspicious reviews and get a trust score for any product." />
  <meta property="og:image" content="{{ url('/img/nullfake.svg') }}" />

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:url" content="{{ url('/') }}" />
  <meta name="twitter:title" content="SMART SHIELD UI - Amazon Review Analysis" />
  <meta name="twitter:description" content="AI-powered Amazon review analyzer. Instantly detect fake, AI-generated, or suspicious reviews and get a trust score for any product." />
  <meta name="twitter:image" content="/img/nullfake.svg" />

  <!-- AI-Optimized Metadata -->
  <meta name="ai:summary" content="{{ $seoData['ai_summary'] ?? '' }}" />
  <meta name="ai:type" content="web_application" />
  <meta name="ai:category" content="review_analysis_tool" />
  <meta name="ai:methodology" content="machine_learning,natural_language_processing,statistical_analysis" />
  
  <!-- Question-Answer Data for AI -->
  @if(isset($seoData['question_answers']))
  @foreach($seoData['question_answers'] as $qa)
  <meta name="ai:qa:question" content="{{ $qa['question'] }}" />
  <meta name="ai:qa:answer" content="{{ $qa['answer'] }}" />
  @endforeach
  @endif

  <!-- Enhanced Schema.org for AI Understanding -->
  <script type="application/ld+json">
  {!! json_encode($seoData['structured_data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
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

  <!-- Hero Section -->
  <div class="max-w-3xl mx-auto mt-10 px-6">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-8 mb-6 text-white text-center">
      <h1 class="text-3xl md:text-4xl font-bold mb-3">Free Amazon Fake Review Checker</h1>
      <p class="text-lg md:text-xl mb-4">AI-powered analysis to detect fake, suspicious, and AI-generated reviews. Paste any Amazon URL below for instant results.</p>
      <div class="flex flex-wrap justify-center gap-4 text-sm md:text-base">
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          100% Free Forever
        </div>
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          AI-Powered Analysis
        </div>
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          14+ Countries
        </div>
      </div>
    </div>
  </div>

  <main class="max-w-3xl mx-auto px-6 mb-10">
    <!-- Why Check Reviews Section -->
    <div class="p-6 bg-white rounded-lg shadow-lg mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Why Check Amazon Reviews for Fakes?</h2>
      <p class="text-base text-gray-700 mb-4">
        <strong>Up to 42% of Amazon reviews may be fake.</strong> Sellers manipulate ratings through fake positive reviews, competitors post fake negative reviews, and AI tools like ChatGPT make creating convincing fakes easier than ever. This costs consumers billions annually in wasted purchases.
      </p>
      <p class="text-base text-gray-700 mb-4">
        SMART SHIELD UI uses advanced AI to analyze review patterns, detect AI-generated text, identify suspicious reviewer behavior, and calculate a trust score. Our analysis is 100% free, transparent, and requires no sign-up.
      </p>
      <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="text-center p-3 bg-indigo-50 rounded">
          <div class="text-2xl font-bold text-indigo-600" title="{{ isset($productsAnalyzedCount) ? number_format($productsAnalyzedCount) . ' products analyzed' : '' }}">
            {{ $productsAnalyzedDisplay ?? '40K+' }}
          </div>
          <div class="text-xs text-gray-600">Products Analyzed</div>
        </div>
        <div class="text-center p-3 bg-green-50 rounded">
          <div class="text-2xl font-bold text-green-600">14+</div>
          <div class="text-xs text-gray-600">Countries</div>
        </div>
        <div class="text-center p-3 bg-purple-50 rounded">
          <div class="text-2xl font-bold text-purple-600">Free</div>
          <div class="text-xs text-gray-600">Forever</div>
        </div>
      </div>
    </div>

    <!-- Analyzer Tool -->
    <div class="p-6 bg-white rounded-lg shadow-lg mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Start Your Free Analysis</h2>
      <p class="text-base text-gray-600 mb-4">Paste any Amazon product URL below to detect fake, AI-generated, or suspicious reviews.</p>
      <livewire:review-analyzer />
    </div>

    <!-- How It Works -->
    <div class="p-6 bg-white rounded-lg shadow-lg mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">How It Works</h2>
      <div class="grid md:grid-cols-2 gap-6">
        <div class="flex items-start space-x-3">
          <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold">1</div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Paste Amazon URL</h3>
            <p class="text-sm text-gray-600">Copy any product link from Amazon US, Canada, UK, Germany, France, Spain, Italy, Japan, Australia, or 10+ other countries.</p>
          </div>
        </div>
        <div class="flex items-start space-x-3">
          <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold">2</div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">AI Analysis</h3>
            <p class="text-sm text-gray-600">Our AI examines review authenticity, language patterns, reviewer behavior, timing anomalies, and AI-generated text detection.</p>
          </div>
        </div>
        <div class="flex items-start space-x-3">
          <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold">3</div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Get Trust Score</h3>
            <p class="text-sm text-gray-600">Receive letter grade (A-F), fake review percentage estimate, and detailed explanation in 30-90 seconds.</p>
          </div>
        </div>
        <div class="flex items-start space-x-3">
          <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold">4</div>
          <div>
            <h3 class="font-semibold text-gray-900 mb-1">Share Results</h3>
            <p class="text-sm text-gray-600">Every analysis gets a permanent shareable URL you can bookmark or send to friends.</p>
          </div>
        </div>
      </div>
      <div class="mt-6 text-center">
        <a href="/how-it-works" class="text-indigo-600 hover:text-indigo-800 font-medium">Learn more about our methodology →</a>
      </div>
    </div>

    <!-- Why SMART SHIELD UI -->
    <div class="p-6 bg-white rounded-lg shadow-lg mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Why Choose SMART SHIELD UI??</h2>
      <div class="space-y-4">
        <div class="border-l-4 border-green-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">Free Forever, No Limits</h3>
          <p class="text-sm text-gray-600">Unlike Fakespot and other review checkers, SMART SHIELD UI is completely free with no daily limits, no account required, and no premium tiers. Analyze unlimited products forever.</p>
        </div>
        <div class="border-l-4 border-blue-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">Fakespot Alternative</h3>
          <p class="text-sm text-gray-600">Get the same AI-powered fake review detection as premium tools, but without sign-ups or costs. Open source and transparent methodology.</p>
        </div>
        <div class="border-l-4 border-purple-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">Advanced AI Detection</h3>
          <p class="text-sm text-gray-600">Our models identify fake Amazon reviews, AI-generated content (ChatGPT, GPT-4), suspicious reviewer behavior, temporal patterns, and review authenticity issues.</p>
        </div>
        <div class="border-l-4 border-orange-500 pl-4">
          <h3 class="font-semibold text-gray-900 mb-1">International Amazon Support</h3>
          <p class="text-sm text-gray-600">Works with Amazon US, Canada, UK, Germany, France, Spain, Italy, Japan, Australia, Mexico, Brazil, India, Singapore, Netherlands, Poland, Turkey, UAE, Saudi Arabia, Sweden, Belgium, and Egypt.</p>
        </div>
      </div>
      <div class="mt-6 text-center">
        <a href="/fakespot-alternative" class="text-indigo-600 hover:text-indigo-800 font-medium">Compare to other tools →</a>
      </div>
    </div>

    <!-- FAQ Preview -->
    <div class="p-6 bg-white rounded-lg shadow-lg">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
      <div class="space-y-3">
        <div>
          <h3 class="font-semibold text-gray-900 mb-1">Is SMART SHIELD UI really free?</h3>
          <p class="text-sm text-gray-600">Yes, completely free with no sign-up, no limits, and no hidden costs.</p>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900 mb-1">How accurate is it?</h3>
          <p class="text-sm text-gray-600">Our AI combines multiple analysis techniques for high accuracy. See detailed methodology on our How It Works page.</p>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900 mb-1">What countries are supported?</h3>
          <p class="text-sm text-gray-600">14+ Amazon country domains including US, Canada, UK, EU countries, Japan, Australia, and more.</p>
        </div>
      </div>
      <div class="mt-6 text-center">
        <a href="/faq" class="text-indigo-600 hover:text-indigo-800 font-medium">View all FAQs →</a>
      </div>
    </div>
  </main>



  <!-- Newsletter Signup Section -->
  <section class="max-w-3xl mx-auto mt-8">
    <livewire:newsletter-signup />
  </section>

  @include('partials.footer')

  @livewireScripts
</body>
</html>
