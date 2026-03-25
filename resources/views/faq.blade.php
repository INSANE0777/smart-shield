<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>FAQ - Frequently Asked Questions | SMART SHIELD UI</title>
  <meta name="description" content="Frequently asked questions about SMART SHIELD UI: pricing, accuracy, supported countries, privacy, data storage, and how to use our free Amazon review checker." />
  <meta name="keywords" content="SMART SHIELD UI faq, amazon review checker questions, fake review detector help, review analysis accuracy" />
  <meta name="author" content="shift8 web" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="{{ url('/faq') }}" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url('/faq') }}" />
  <meta property="og:title" content="SMART SHIELD UI FAQ - Everything You Need to Know" />
  <meta property="og:description" content="Common questions about our free Amazon fake review checker: pricing, accuracy, privacy, and usage." />
  
  <!-- AI-Optimized Q&A Metadata -->
  <meta name="ai:qa:question" content="Is SMART SHIELD UI free to use?" />
  <meta name="ai:qa:answer" content="Yes, SMART SHIELD UI is completely free with no sign-up required, no usage limits, and no hidden costs. The web tool and Chrome extension are both free forever." />
  
  <meta name="ai:qa:question" content="What countries does SMART SHIELD UI support?" />
  <meta name="ai:qa:answer" content="SMART SHIELD UI supports Amazon products from 14+ countries: US, Canada, UK, Germany, France, Spain, Italy, Japan, Australia, Mexico, Brazil, India, Singapore, Netherlands, Poland, Turkey, UAE, Saudi Arabia, Sweden, Belgium, and Egypt." />
  
  <meta name="ai:qa:question" content="How accurate is SMART SHIELD UI?" />
  <meta name="ai:qa:answer" content="SMART SHIELD UI uses advanced AI and statistical analysis to provide high-accuracy fake review detection, but no tool is 100% perfect. Results should be used as one factor in purchasing decisions, not the only factor." />
  
  <meta name="ai:qa:question" content="Does SMART SHIELD UI store my data?" />
  <meta name="ai:qa:answer" content="SMART SHIELD UI stores analyzed product information (ASIN, country, results) to provide shareable URLs. It does NOT store personal information about users, require accounts, or track browsing behavior." />
  
  <!-- JSON-LD Structured Data for FAQ Page -->
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "FAQPage",
    "mainEntity": [
      {
        "@@type": "Question",
        "name": "Is SMART SHIELD UI really free?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "Yes, completely free. No trial period, no credit card, no hidden costs, no premium tier. The web tool is 100% free with unlimited usage. Our Chrome extension is also free. SMART SHIELD UI is open source (MIT license) and will remain free forever."
        }
      },
      {
        "@@type": "Question",
        "name": "Are there any usage limits?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "No daily limits, no monthly quotas, no throttling. Analyze as many products as you need, whenever you need."
        }
      },
      {
        "@@type": "Question",
        "name": "How accurate is SMART SHIELD UI?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "Our AI analysis combines multiple detection methods for high accuracy, but no tool is 100% perfect. We provide probability estimates (fake percentage and letter grade) rather than absolute certainties. Use our analysis as one factor in your purchasing decision."
        }
      },
      {
        "@@type": "Question",
        "name": "Which Amazon countries are supported?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "SMART SHIELD UI supports 14+ Amazon country domains including United States, Canada, United Kingdom, Germany, France, Spain, Italy, Japan, Australia, Mexico, Brazil, India, Singapore, Netherlands, Poland, Turkey, UAE, Saudi Arabia, Sweden, Belgium, and Egypt."
        }
      },
      {
        "@@type": "Question",
        "name": "What data do you store?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "We store analyzed product information: ASIN (product ID), country code, analysis results, and timestamps. We do NOT track users, require accounts, or store personal information."
        }
      },
      {
        "@@type": "Question",
        "name": "How does SMART SHIELD UI compare to Fakespot?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "SMART SHIELD UI is completely free with no limits or sign-up required, while Fakespot has usage restrictions. We're open source, support more international Amazon sites, and provide shareable permanent URLs for all analyses."
        }
      }
    ]
  }
  </script>

  <!-- WebSite Schema -->
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "WebSite",
    "name": "SMART SHIELD UI",
    "alternateName": "SMART SHIELD UI Amazon Review Checker",
    "url": "{{ url('/') }}",
    "description": "Free AI-powered Amazon fake review detector with no sign-up required",
    "potentialAction": {
      "@@type": "SearchAction",
      "target": "{{ url('/') }}?url={search_term_string}",
      "query-input": "required name=search_term_string"
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
    .faq-item { scroll-margin-top: 100px; }
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

  <main class="max-w-4xl mx-auto mt-10 px-6 mb-16">
    
    <!-- Hero Section -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h1>
      <p class="text-xl text-gray-600">
        Everything you need to know about SMART SHIELD UI, our free Amazon fake review checker.
      </p>
    </div>

    <!-- Table of Contents -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Quick Navigation</h2>
      <div class="grid md:grid-cols-2 gap-4 text-base">
        <a href="#pricing" class="text-indigo-600 hover:text-indigo-800">Pricing & Free Tier</a>
        <a href="#accuracy" class="text-indigo-600 hover:text-indigo-800">Accuracy & Reliability</a>
        <a href="#countries" class="text-indigo-600 hover:text-indigo-800">Supported Countries</a>
        <a href="#privacy" class="text-indigo-600 hover:text-indigo-800">Privacy & Data Storage</a>
        <a href="#usage" class="text-indigo-600 hover:text-indigo-800">How to Use</a>
        <a href="#technical" class="text-indigo-600 hover:text-indigo-800">Technical Questions</a>
        <a href="#comparison" class="text-indigo-600 hover:text-indigo-800">vs Other Tools</a>
        <a href="#api" class="text-indigo-600 hover:text-indigo-800">API & Integration</a>
      </div>
    </div>

    <!-- Pricing & Free Tier -->
    <div id="pricing" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Pricing & Free Tier</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Is SMART SHIELD UI really free?</h3>
          <p class="text-base text-gray-700">
            Yes, completely free. No trial period, no credit card, no hidden costs, no premium tier. The web tool is 100% free with unlimited usage. Our Chrome extension is also free. SMART SHIELD UI is open source (MIT license) and will remain free forever.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Are there any usage limits?</h3>
          <p class="text-base text-gray-700">
            No daily limits, no monthly quotas, no throttling. Analyze as many products as you need, whenever you need.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Do I need an account?</h3>
          <p class="text-base text-gray-700">
            No account required. Just paste an Amazon URL and get instant results. No email, no password, no personal information needed.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">How do you make money if it's free?</h3>
          <p class="text-base text-gray-700">
            SMART SHIELD UI is an open-source project built by <a href="https://shift8web.ca" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">Shift8 Web</a> to demonstrate AI capabilities and contribute to consumer protection. The tool is free as a service to the community. We don't sell data, show intrusive ads, or have premium tiers.
          </p>
        </div>
      </div>
    </div>

    <!-- Accuracy & Reliability -->
    <div id="accuracy" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Accuracy & Reliability</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-purple-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">How accurate is SMART SHIELD UI?</h3>
          <p class="text-base text-gray-700">
            Our AI analysis combines multiple detection methods for high accuracy, but no tool is 100% perfect. Sophisticated fake reviews can occasionally pass undetected, and authentic reviews may rarely be flagged. We provide probability estimates (fake percentage and letter grade) rather than absolute certainties. Use our analysis as one factor in your purchasing decision.
          </p>
        </div>
        
        <div class="border-l-4 border-purple-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What makes SMART SHIELD UI reliable?</h3>
          <p class="text-base text-gray-700 mb-2">
            Our methodology combines:
          </p>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-1">
            <li>Advanced AI language models (OpenAI, DeepSeek, Ollama)</li>
            <li>Natural language processing and sentiment analysis</li>
            <li>Reviewer behavior pattern analysis</li>
            <li>Temporal pattern detection</li>
            <li>Statistical anomaly detection</li>
          </ul>
          <p class="text-base text-gray-700 mt-2">
            Learn more on our <a href="/how-it-works" class="text-indigo-600 hover:underline">How It Works</a> page.
          </p>
        </div>
        
        <div class="border-l-4 border-purple-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can fake reviews fool SMART SHIELD UI?</h3>
          <p class="text-base text-gray-700">
            Sophisticated fake review campaigns using varied language, realistic timing, and verified purchases can be harder to detect. However, most fake review operations use patterns and shortcuts that our AI identifies. We continuously update our models as fake review techniques evolve.
          </p>
        </div>
        
        <div class="border-l-4 border-purple-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What if I disagree with a grade?</h3>
          <p class="text-base text-gray-700">
            Our grades represent statistical likelihood based on multiple factors. If you believe a specific product was misgraded, please <a href="/contact" class="text-indigo-600 hover:underline">contact us</a> with the product URL and explanation. Feedback helps us improve our models. Remember that our analysis is probabilistic, not definitive.
          </p>
        </div>
      </div>
    </div>

    <!-- Supported Countries -->
    <div id="countries" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Supported Countries</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-blue-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Which Amazon countries are supported?</h3>
          <p class="text-base text-gray-700 mb-2">
            SMART SHIELD UI supports 14+ Amazon country domains:
          </p>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-1">
            <li><strong>North America:</strong> United States (amazon.com), Canada (amazon.ca), Mexico (amazon.com.mx)</li>
            <li><strong>Europe:</strong> United Kingdom (amazon.co.uk), Germany (amazon.de), France (amazon.fr), Spain (amazon.es), Italy (amazon.it), Netherlands (amazon.nl), Poland (amazon.pl), Sweden (amazon.se), Belgium (amazon.com.be)</li>
            <li><strong>Asia-Pacific:</strong> Japan (amazon.co.jp), Australia (amazon.com.au), India (amazon.in), Singapore (amazon.sg)</li>
            <li><strong>Middle East & Latin America:</strong> Brazil (amazon.com.br), Turkey (amazon.com.tr), UAE (amazon.ae), Saudi Arabia (amazon.sa), Egypt (amazon.eg)</li>
          </ul>
        </div>
        
        <div class="border-l-4 border-blue-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Does accuracy vary by country?</h3>
          <p class="text-base text-gray-700">
            Our core detection methods work across all countries. However, review writing styles and cultural norms vary, which can create subtle accuracy differences. English-language markets (US, UK, Canada, Australia) have the most training data. International markets are well-supported but may have minor variance in nuanced language detection.
          </p>
        </div>
        
        <div class="border-l-4 border-blue-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Will you add more countries?</h3>
          <p class="text-base text-gray-700">
            Yes. As Amazon expands to new regions and as we refine our international language models, we'll add support for additional countries. Our architecture is designed for easy expansion.
          </p>
        </div>
      </div>
    </div>

    <!-- Privacy & Data Storage -->
    <div id="privacy" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Privacy & Data Storage</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-green-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What data do you store?</h3>
          <p class="text-base text-gray-700">
            We store analyzed product information: ASIN (product ID), country code, analysis results, and timestamps. This allows us to provide shareable permanent URLs and avoid re-analyzing the same product repeatedly.
          </p>
        </div>
        
        <div class="border-l-4 border-green-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Do you track users?</h3>
          <p class="text-base text-gray-700">
            No. We don't require accounts, so we have no email addresses, passwords, or user profiles. We don't track what products individual users analyze or create behavioral profiles. We use Google Analytics for aggregate traffic statistics (page views, referrers) but no personal identification.
          </p>
        </div>
        
        <div class="border-l-4 border-green-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Is my analysis history private?</h3>
          <p class="text-base text-gray-700">
            Yes. Since we don't have accounts, we can't associate analyses with individual users. Each analysis gets a unique shareable URL, but there's no "your history" or "your dashboard" that could leak what you've been researching.
          </p>
        </div>
        
        <div class="border-l-4 border-green-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I delete my analysis data?</h3>
          <p class="text-base text-gray-700">
            Analysis results are public by design (via shareable URLs) and aren't associated with individual users. If you want a specific analysis removed for a legitimate reason (e.g., it contains sensitive information), <a href="/contact" class="text-indigo-600 hover:underline">contact us</a> with the URL and explanation.
          </p>
        </div>
        
        <div class="border-l-4 border-green-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Do you sell data?</h3>
          <p class="text-base text-gray-700">
            Absolutely not. We don't sell, rent, or share any data with third parties. Our open-source code is transparent - you can verify exactly what we do with data on <a href="https://github.com/stardothosting/nullfake" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">GitHub</a>.
          </p>
        </div>
      </div>
    </div>

    <!-- How to Use -->
    <div id="usage" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">How to Use</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-orange-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">How do I analyze a product?</h3>
          <p class="text-base text-gray-700 mb-2">
            Three easy ways:
          </p>
          <ol class="list-decimal pl-6 text-base text-gray-700 space-y-1">
            <li><strong>Web Tool:</strong> Paste any Amazon product URL into the analyzer on our <a href="{{ route('home') }}" class="text-indigo-600 hover:underline">homepage</a></li>
            <li><strong>Chrome Extension:</strong> Install our <a href="https://chromewebstore.google.com/detail/null-fake-product-review/fgngdgdpfcfkddgipaafgpnadiikfkaa" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">free Chrome extension</a> and click the button on any Amazon product page</li>
            <li><strong>Direct URL:</strong> Type nullfake.com, paste URL, press enter</li>
          </ol>
        </div>
        
        <div class="border-l-4 border-orange-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What URLs are accepted?</h3>
          <p class="text-base text-gray-700">
            Any Amazon product URL from supported countries. Examples: <code class="bg-gray-100 px-2 py-1 rounded">amazon.com/dp/B0ABCD1234</code>, <code class="bg-gray-100 px-2 py-1 rounded">amazon.co.uk/product/B0ABCD1234</code>. You can also enter just the ASIN (10-character product code like B0ABCD1234).
          </p>
        </div>
        
        <div class="border-l-4 border-orange-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">How long does analysis take?</h3>
          <p class="text-base text-gray-700">
            First-time analysis: 30-90 seconds depending on review count and server load. Previously analyzed products load instantly from our database. We show real-time progress updates during analysis.
          </p>
        </div>
        
        <div class="border-l-4 border-orange-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I re-analyze products?</h3>
          <p class="text-base text-gray-700">
            Yes. We automatically refresh analysis for products that were last analyzed more than 30 days ago. For recently analyzed products, we show the existing results to save time. If you want to force a refresh, <a href="/contact" class="text-indigo-600 hover:underline">contact us</a>.
          </p>
        </div>
        
        <div class="border-l-4 border-orange-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I share results?</h3>
          <p class="text-base text-gray-700">
            Yes. Every analysis gets a permanent shareable URL like <code class="bg-gray-100 px-2 py-1 rounded">nullfake.com/amazon/us/B0ABCD1234</code>. Share it with friends, post it on social media, or reference it in discussions. No account needed to view results.
          </p>
        </div>
      </div>
    </div>

    <!-- Technical Questions -->
    <div id="technical" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Technical Questions</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-red-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Is SMART SHIELD UI open source?</h3>
          <p class="text-base text-gray-700">
            Yes. MIT licensed and available on <a href="https://github.com/stardothosting/nullfake" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">GitHub</a>. You can view the code, fork it, modify it, and even host your own instance.
          </p>
        </div>
        
        <div class="border-l-4 border-red-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What technology powers SMART SHIELD UI?</h3>
          <p class="text-base text-gray-700 mb-2">
            Our stack includes:
          </p>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-1">
            <li><strong>Backend:</strong> Laravel (PHP framework)</li>
            <li><strong>AI Models:</strong> OpenAI GPT, DeepSeek, Ollama (local LLMs)</li>
            <li><strong>Frontend:</strong> Livewire, Tailwind CSS</li>
            <li><strong>Database:</strong> MySQL</li>
            <li><strong>Chrome Extension:</strong> Vanilla JavaScript</li>
          </ul>
        </div>
        
        <div class="border-l-4 border-red-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I self-host SMART SHIELD UI?</h3>
          <p class="text-base text-gray-700">
            Yes. The GitHub repository includes installation instructions. You'll need a web server, PHP, MySQL, and API keys for AI services (or use local Ollama). Self-hosting is great for privacy, customization, or running your own analysis service.
          </p>
        </div>
        
        <div class="border-l-4 border-red-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Does SMART SHIELD UI have a mobile app?</h3>
          <p class="text-base text-gray-700">
            Not yet. The web interface works on mobile browsers, but there's no native iOS or Android app. The Chrome extension works on desktop Chrome, Edge, Brave, and other Chromium browsers.
          </p>
        </div>
      </div>
    </div>

    <!-- Comparison to Other Tools -->
    <div id="comparison" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">vs Other Tools</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-yellow-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">How does SMART SHIELD UI compare to Fakespot?</h3>
          <p class="text-base text-gray-700 mb-2">
            Key differences:
          </p>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-1">
            <li><strong>Free:</strong> SMART SHIELD UI is completely free with no limits; Fakespot has usage restrictions</li>
            <li><strong>No Account:</strong> We don't require sign-up; Fakespot does</li>
            <li><strong>Open Source:</strong> Our code is public; Fakespot is proprietary</li>
            <li><strong>International:</strong> We support 14+ countries; Fakespot has limited international support</li>
          </ul>
          <p class="text-base text-gray-700 mt-2">
            See detailed comparison: <a href="/fakespot-alternative" class="text-indigo-600 hover:underline">Fakespot Alternative</a>
          </p>
        </div>
        
        <div class="border-l-4 border-yellow-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">What about ReviewMeta?</h3>
          <p class="text-base text-gray-700">
            ReviewMeta is also free and doesn't require accounts. Main difference: SMART SHIELD UI uses advanced AI language models for deeper text analysis, while ReviewMeta focuses on statistical patterns. Both approaches have merit. You can use both tools for comprehensive analysis.
          </p>
        </div>
        
        <div class="border-l-4 border-yellow-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Should I use multiple tools?</h3>
          <p class="text-base text-gray-700">
            Yes, cross-referencing is smart. Different tools use different methodologies and may catch different patterns. If multiple tools agree a product has fake reviews, that's a strong signal. If they disagree, dig deeper into the actual reviews yourself.
          </p>
        </div>
      </div>
    </div>

    <!-- API & Integration -->
    <div id="api" class="bg-white rounded-lg shadow-lg p-8 mb-8 faq-item">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">API & Integration</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-gray-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Does SMART SHIELD UI have an API?</h3>
          <p class="text-base text-gray-700">
            Not yet. We're considering a public API for developers who want to integrate fake review detection into their own applications. If you're interested, <a href="/contact" class="text-indigo-600 hover:underline">contact us</a> to express interest and share your use case.
          </p>
        </div>
        
        <div class="border-l-4 border-gray-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I bulk-analyze products?</h3>
          <p class="text-base text-gray-700">
            Currently, the web tool analyzes one product at a time. Bulk analysis isn't available yet but may come in the future, possibly as an API feature. For now, use the Chrome extension to quickly analyze multiple products as you browse.
          </p>
        </div>
        
        <div class="border-l-4 border-gray-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I embed SMART SHIELD UI on my website?</h3>
          <p class="text-base text-gray-700">
            Not currently. We don't offer embeddable widgets. However, you can link to our tool or to specific analysis results. If you want to build something similar, our code is open source and can be forked.
          </p>
        </div>
      </div>
    </div>

    <!-- Still Have Questions? -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-8 text-white text-center mb-8">
      <h2 class="text-3xl font-bold mb-4">Still Have Questions?</h2>
      <p class="text-xl mb-6">We're here to help. Reach out through our contact page.</p>
      <a href="{{ route('contact.show') }}" class="inline-block bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
        Contact Us
      </a>
    </div>

    <!-- Learn More -->
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Learn More</h2>
      <div class="space-y-3">
        <a href="/how-it-works" class="block text-indigo-600 hover:text-indigo-800 text-base">How our analysis methodology works →</a>
        <a href="/methodology" class="block text-indigo-600 hover:text-indigo-800 text-base">Our detailed methodology →</a>
        <a href="/fakespot-alternative" class="block text-indigo-600 hover:text-indigo-800 text-base">Compare to other review checkers →</a>
        <a href="{{ route('home') }}" class="block text-indigo-600 hover:text-indigo-800 text-base">Try the analyzer →</a>
      </div>
    </div>

  </main>

  @include('partials.footer')

  @livewireScripts
</body>
</html>

