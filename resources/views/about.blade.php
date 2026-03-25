<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>About Us - SMART SHIELD UI | Free Amazon Review Analysis Tool</title>
  <meta name="description" content="Learn about SMART SHIELD UI: who we are, why we built this free Amazon fake review checker, and our mission to help consumers make informed purchasing decisions." />
  <meta name="keywords" content="about SMART SHIELD UI, fake review detection company, amazon review analysis team, consumer protection tool" />
  <meta name="author" content="SMART SHIELD UI Team" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="{{ url('/about') }}" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url('/about') }}" />
  <meta property="og:title" content="About SMART SHIELD UI - Free Amazon Review Analysis" />
  <meta property="og:description" content="Learn about our mission to help consumers identify fake Amazon reviews using AI-powered analysis." />
  
  <!-- Organization Schema -->
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Organization",
    "name": "SMART SHIELD UI",
    "url": "{{ url('/') }}",
    "logo": "{{ url('/img/nullfake.svg') }}",
    "description": "Free AI-powered Amazon fake review detection tool",
    "foundingDate": "2024",
    "founder": {
      "@@type": "Organization",
      "name": "SMART SHIELD UI Team",
      "url": "{{ url('/') }}"
    },
    "sameAs": [
      "https://github.com/INSANE0777/smart-shield"
    ],
    "contactPoint": {
      "@@type": "ContactPoint",
      "contactType": "Customer Service",
      "url": "{{ url('/contact') }}"
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

  <main class="max-w-4xl mx-auto mt-10 px-6 mb-16">
    
    <!-- Hero Section -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">About SMART SHIELD UI</h1>
      <p class="text-xl text-gray-600 mb-6">
        Free, open-source Amazon fake review detection powered by AI. Built for consumers, by developers who care about transparency.
      </p>
    </div>

    <!-- Mission -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Our Mission</h2>
      
      <p class="text-base text-gray-700 mb-4">
        Fake reviews are a massive problem on Amazon and other e-commerce platforms. Studies show that up to 42% of reviews on some products may be inauthentic, costing consumers billions in wasted purchases on low-quality products.
      </p>
      
      <p class="text-base text-gray-700 mb-4">
        SMART SHIELD UI was created to level the playing field. We believe consumers deserve free, transparent tools to identify fake reviews without hidden costs, usage limits, or privacy concerns.
      </p>
      
      <div class="bg-indigo-50 border-l-4 border-indigo-600 p-4">
        <p class="text-base text-gray-700">
          <strong>Our goal:</strong> Make fake review detection accessible to everyone, everywhere, for free.
        </p>
      </div>
    </div>

    <!-- Who We Are -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Who We Are</h2>
      
      <p class="text-base text-gray-700 mb-4">
        SMART SHIELD UI is an open-source project dedicated to AI integration, data analysis, and providing transparent tools for consumer protection.
      </p>
      
      <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="border border-gray-200 rounded-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">Our Expertise</h3>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-2">
            <li>AI and machine learning integration</li>
            <li>Natural language processing</li>
            <li>Web scraping and data analysis</li>
            <li>Laravel and PHP development</li>
            <li>Open-source software development</li>
          </ul>
        </div>
        
        <div class="border border-gray-200 rounded-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-3">Our Values</h3>
          <ul class="list-disc pl-6 text-base text-gray-700 space-y-2">
            <li>Transparency and open source</li>
            <li>Privacy and user rights</li>
            <li>Free access to essential tools</li>
            <li>Consumer protection</li>
            <li>Continuous improvement</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Why We Built This -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Why We Built This</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          <strong>The Problem:</strong> Existing fake review checkers either charge money, require accounts, have usage limits, or operate as black boxes without explaining their methodology.
        </p>
        
        <p>
          <strong>Our Solution:</strong> We built SMART SHIELD UI to be completely free, require no sign-up, have no usage limits, and be fully transparent about how it works. The entire codebase is open source on GitHub.
        </p>
        
        <p>
          <strong>The Technology:</strong> We leverage advanced AI language models (OpenAI GPT, DeepSeek, Ollama) combined with statistical analysis and pattern recognition to identify suspicious reviews with high accuracy.
        </p>
        
        <p>
          <strong>The Result:</strong> A tool that anyone can use, anywhere, anytime, to make better purchasing decisions on Amazon.
        </p>
      </div>
    </div>

    <!-- Open Source Commitment -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Open Source Commitment</h2>
      
      <p class="text-base text-gray-700 mb-4">
        SMART SHIELD UI is released under the MIT License and available on <a href="https://github.com/INSANE0777/smart-shield" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">GitHub</a>. This means:
      </p>
      
      <ul class="list-disc pl-6 text-base text-gray-700 space-y-2 mb-4">
        <li>Anyone can view the source code and verify what we do</li>
        <li>Developers can fork the project and create their own versions</li>
        <li>Security researchers can audit our code for vulnerabilities</li>
        <li>The community can contribute improvements and bug fixes</li>
        <li>You can self-host your own instance if desired</li>
      </ul>
      
      <p class="text-base text-gray-700">
        We believe transparency builds trust. If you're technically inclined, we encourage you to review our code and methodology.
      </p>
    </div>

    <!-- What We Don't Do -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">What We Don't Do</h2>
      
      <div class="space-y-4">
        <div class="bg-red-50 border-l-4 border-red-500 p-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">We Don't Sell Your Data</h3>
          <p class="text-base text-gray-700">
            We don't collect personal information, create user profiles, or sell any data to third parties. Ever.
          </p>
        </div>
        
        <div class="bg-red-50 border-l-4 border-red-500 p-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">We Don't Track Users</h3>
          <p class="text-base text-gray-700">
            No accounts mean no tracking. We use Google Analytics for aggregate traffic stats only.
          </p>
        </div>
        
        <div class="bg-red-50 border-l-4 border-red-500 p-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">We Don't Have Premium Tiers</h3>
          <p class="text-base text-gray-700">
            Everything is free. No freemium model, no upsells, no hidden paywalls.
          </p>
        </div>
        
        <div class="bg-red-50 border-l-4 border-red-500 p-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">We Don't Work With Sellers</h3>
          <p class="text-base text-gray-700">
            We don't accept payment from Amazon sellers to manipulate results or remove analyses.
          </p>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">By The Numbers</h2>
      
      <div class="grid md:grid-cols-3 gap-6">
        <div class="text-center p-6 bg-indigo-50 rounded-lg">
          <div class="text-4xl font-bold text-indigo-600 mb-2">{{ number_format($productsAnalyzedCount) }}+</div>
          <div class="text-base text-gray-700">Products Analyzed</div>
        </div>
        
        <div class="text-center p-6 bg-green-50 rounded-lg">
          <div class="text-4xl font-bold text-green-600 mb-2">14+</div>
          <div class="text-base text-gray-700">Countries Supported</div>
        </div>
        
        <div class="text-center p-6 bg-purple-50 rounded-lg">
          <div class="text-4xl font-bold text-purple-600 mb-2">100%</div>
          <div class="text-base text-gray-700">Free Forever</div>
        </div>
      </div>
    </div>

    <!-- Contact CTA -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-8 text-white text-center mb-8">
      <h2 class="text-3xl font-bold mb-4">Questions or Feedback?</h2>
      <p class="text-xl mb-6">We'd love to hear from you. Reach out through our contact page.</p>
      <a href="{{ route('contact.show') }}" class="inline-block bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
        Contact Us
      </a>
    </div>

    <!-- Additional Resources -->
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Learn More</h2>
      <div class="space-y-3">
        <a href="/how-it-works" class="block text-indigo-600 hover:text-indigo-800 text-base">How our AI analysis works →</a>
        <a href="/faq" class="block text-indigo-600 hover:text-indigo-800 text-base">Frequently asked questions →</a>
        <a href="https://github.com/INSANE0777/smart-shield" class="block text-indigo-600 hover:text-indigo-800 text-base" target="_blank" rel="noopener">View source code on GitHub →</a>
        <a href="{{ route('home') }}" class="block text-indigo-600 hover:text-indigo-800 text-base">Try the analyzer →</a>
      </div>
    </div>

  </main>

  @include('partials.footer')

  @livewireScripts
</body>
</html>


