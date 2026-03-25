<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Privacy Policy - SMART SHIELD UI | Amazon Review Analysis Tool</title>
  <meta name="description" content="Privacy Policy for SMART SHIELD UI. Learn how we handle your data, what information we collect, and how we protect your privacy when analyzing Amazon reviews." />
  <meta name="keywords" content="SMART SHIELD UI privacy policy, data protection, amazon review checker privacy, user data policy" />
  <meta name="author" content="shift8 web" />
  
  <!-- SEO and Robots Configuration -->
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="{{ url('/privacy') }}" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url('/privacy') }}" />
  <meta property="og:title" content="Privacy Policy - SMART SHIELD UI" />
  <meta property="og:description" content="How SMART SHIELD UI handles your data and protects your privacy." />
  
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
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Privacy Policy</h1>
      <p class="text-xl text-gray-600 mb-4">
        Your privacy matters. Here's exactly what we collect, why we collect it, and how we protect it.
      </p>
      <p class="text-sm text-gray-500">Last updated: January 2025</p>
    </div>

    <!-- Quick Summary -->
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
      <h2 class="text-lg font-semibold text-green-800 mb-3">The Short Version</h2>
      <ul class="space-y-2 text-base text-green-700">
        <li>• We don't require accounts or collect personal information</li>
        <li>• We don't sell, rent, or share your data with third parties</li>
        <li>• We store analyzed product data to provide shareable URLs</li>
        <li>• Our code is open source so you can verify what we do</li>
      </ul>
    </div>

    <!-- Information We Collect -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Information We Collect</h2>
      
      <div class="space-y-6">
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Product URLs You Submit</h3>
          <p class="text-base text-gray-700">
            When you paste an Amazon product URL into our analyzer, we receive that URL. We extract the product identifier (ASIN) and country code to perform our analysis.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Analysis Results</h3>
          <p class="text-base text-gray-700">
            We store the results of our AI analysis including: authenticity grade, fake review percentage, adjusted rating, explanation text, and examples of flagged reviews. This allows us to provide shareable permanent URLs and avoid re-analyzing the same product repeatedly.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Basic Usage Analytics</h3>
          <p class="text-base text-gray-700">
            We use Google Analytics to understand aggregate traffic patterns (page views, referral sources, general geographic regions). This helps us understand how the service is used but doesn't identify individual users.
          </p>
        </div>
        
        <div class="border-l-4 border-indigo-600 pl-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">IP Addresses</h3>
          <p class="text-base text-gray-700">
            Your IP address is temporarily processed for CAPTCHA verification (spam prevention) and basic security. We don't store IP addresses in our database or associate them with analysis requests.
          </p>
        </div>
      </div>
    </div>

    <!-- What We Don't Collect -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">What We Don't Collect</h2>
      
      <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-red-50 p-4 rounded-lg">
          <h3 class="font-semibold text-red-800 mb-2">No Personal Information</h3>
          <p class="text-sm text-red-700">We don't collect names, email addresses, phone numbers, or any other personal identifiers.</p>
        </div>
        
        <div class="bg-red-50 p-4 rounded-lg">
          <h3 class="font-semibold text-red-800 mb-2">No Account Data</h3>
          <p class="text-sm text-red-700">We don't require accounts, so there are no usernames, passwords, or profile data to collect.</p>
        </div>
        
        <div class="bg-red-50 p-4 rounded-lg">
          <h3 class="font-semibold text-red-800 mb-2">No Browsing History</h3>
          <p class="text-sm text-red-700">We don't track what products you analyze or create behavioral profiles.</p>
        </div>
        
        <div class="bg-red-50 p-4 rounded-lg">
          <h3 class="font-semibold text-red-800 mb-2">No Payment Information</h3>
          <p class="text-sm text-red-700">The service is free, so we never collect credit card numbers or payment details.</p>
        </div>
      </div>
    </div>

    <!-- How We Use Information -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">How We Use Your Information</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          <strong>Providing the Service:</strong> We use product URLs to fetch review data and run our AI analysis. Results are stored so you can access them via shareable URLs.
        </p>
        
        <p>
          <strong>Improving Accuracy:</strong> Aggregate analysis data helps us improve our AI models and detection algorithms. We don't use individual analyses for this - only statistical patterns across all analyses.
        </p>
        
        <p>
          <strong>Preventing Abuse:</strong> CAPTCHA verification and basic security measures help prevent automated spam and abuse of the service.
        </p>
        
        <p>
          <strong>Understanding Usage:</strong> Analytics help us understand which features are used, where traffic comes from, and how to improve the service.
        </p>
      </div>
    </div>

    <!-- Data Storage and Retention -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Data Storage and Retention</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          <strong>Analysis Results:</strong> Stored indefinitely to provide permanent shareable URLs. Analysis may be refreshed if the same product is analyzed again after 30+ days.
        </p>
        
        <p>
          <strong>Server Logs:</strong> Basic server logs (access times, error logs) are retained for 30 days for debugging and security purposes.
        </p>
        
        <p>
          <strong>Analytics Data:</strong> Google Analytics data is retained according to Google's standard retention policies (14-50 months depending on the data type).
        </p>
        
        <p>
          <strong>Location:</strong> Our servers are located in Canada. Data is processed and stored in accordance with Canadian privacy laws.
        </p>
      </div>
    </div>

    <!-- Third-Party Services -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Third-Party Services</h2>
      
      <p class="text-base text-gray-700 mb-4">We use the following third-party services to provide our analysis:</p>
      
      <div class="space-y-4">
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 mb-2">AI Analysis Providers</h3>
          <p class="text-sm text-gray-700 mb-2">
            We use OpenAI and DeepSeek for AI-powered review analysis. When we analyze a product, review text is sent to these services for processing. They are bound by their own privacy policies and data processing agreements.
          </p>
          <p class="text-xs text-gray-500">
            <a href="https://openai.com/policies/privacy-policy" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">OpenAI Privacy Policy</a>
          </p>
        </div>
        
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 mb-2">CAPTCHA Services</h3>
          <p class="text-sm text-gray-700 mb-2">
            We use reCAPTCHA or hCaptcha to prevent spam. These services may collect certain device and browser information to distinguish humans from bots.
          </p>
          <p class="text-xs text-gray-500">
            <a href="https://policies.google.com/privacy" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">Google Privacy Policy</a> |
            <a href="https://www.hcaptcha.com/privacy" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">hCaptcha Privacy Policy</a>
          </p>
        </div>
        
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 mb-2">Google Analytics</h3>
          <p class="text-sm text-gray-700 mb-2">
            We use Google Analytics for aggregate traffic analysis. This involves cookies and tracking pixels to understand usage patterns.
          </p>
          <p class="text-xs text-gray-500">
            <a href="https://policies.google.com/privacy" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">Google Privacy Policy</a>
          </p>
        </div>
        
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 mb-2">Cloudflare</h3>
          <p class="text-sm text-gray-700 mb-2">
            We use Cloudflare for content delivery and security. Cloudflare may process your IP address and request information for security and performance optimization.
          </p>
          <p class="text-xs text-gray-500">
            <a href="https://www.cloudflare.com/privacypolicy/" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">Cloudflare Privacy Policy</a>
          </p>
        </div>
      </div>
    </div>

    <!-- Data Sharing -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Data Sharing</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          <strong>We do not sell your data.</strong> We never have and never will sell, rent, or trade any information to third parties for marketing or other purposes.
        </p>
        
        <p>
          <strong>Service Providers:</strong> We share data with third-party services only as necessary to provide our analysis (AI providers, CAPTCHA, CDN). These providers are contractually obligated to protect your data.
        </p>
        
        <p>
          <strong>Legal Requirements:</strong> We may disclose information if required by law, court order, or government request. We will notify affected users when legally permitted.
        </p>
        
        <p>
          <strong>Public Analysis Results:</strong> Analysis results are publicly accessible via shareable URLs. This is a feature, not a bug - it allows users to share findings with others. No personal information is included in analysis results.
        </p>
      </div>
    </div>

    <!-- Security -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Security</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          We implement industry-standard security measures to protect data:
        </p>
        
        <ul class="list-disc pl-6 space-y-2">
          <li><strong>HTTPS encryption</strong> for all data transmission</li>
          <li><strong>Cloudflare protection</strong> against DDoS attacks and malicious traffic</li>
          <li><strong>Regular security updates</strong> to our software and infrastructure</li>
          <li><strong>Access controls</strong> limiting who can access server data</li>
          <li><strong>Open source code</strong> allowing security researchers to audit our practices</li>
        </ul>
        
        <p class="mt-4">
          No system is 100% secure. While we take reasonable precautions, we cannot guarantee absolute security of data transmitted over the internet.
        </p>
      </div>
    </div>

    <!-- Your Rights -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Your Rights</h2>
      
      <div class="space-y-4 text-base text-gray-700">
        <p>
          Since we don't collect personal information or require accounts, many traditional privacy rights (access, correction, portability) don't apply in the usual way. However:
        </p>
        
        <ul class="list-disc pl-6 space-y-2">
          <li><strong>Transparency:</strong> Our code is open source. You can see exactly what we collect and how we process it.</li>
          <li><strong>Deletion Requests:</strong> If you believe a specific analysis should be removed for a legitimate reason, contact us with the URL and explanation.</li>
          <li><strong>Questions:</strong> You can contact us anytime with privacy questions or concerns.</li>
          <li><strong>Opt-Out:</strong> You can disable cookies in your browser to limit analytics tracking. The core service works without cookies.</li>
        </ul>
      </div>
    </div>

    <!-- Children's Privacy -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Children's Privacy</h2>
      
      <p class="text-base text-gray-700">
        SMART SHIELD UI is not directed at children under 13. We do not knowingly collect information from children. Since we don't require accounts or collect personal information, this is largely a non-issue - but if you believe a child has somehow provided personal information to us, please contact us.
      </p>
    </div>

    <!-- Open Source Transparency -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Open Source Transparency</h2>
      
      <p class="text-base text-gray-700 mb-4">
        SMART SHIELD UI is open source under the MIT License. This means:
      </p>
      
      <ul class="list-disc pl-6 text-base text-gray-700 space-y-2">
        <li>You can view exactly what data we collect in our source code</li>
        <li>Security researchers can audit our data handling practices</li>
        <li>You can verify that we do what we say we do</li>
        <li>You can self-host your own instance if you want complete control</li>
      </ul>
      
      <p class="text-base text-gray-700 mt-4">
        View our code: <a href="https://github.com/stardothosting/nullfake" class="text-indigo-600 hover:underline" target="_blank" rel="noopener">github.com/stardothosting/nullfake</a>
      </p>
    </div>

    <!-- Changes to This Policy -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Changes to This Policy</h2>
      
      <p class="text-base text-gray-700">
        We may update this privacy policy from time to time. Significant changes will be noted with an updated "Last updated" date at the top of this page. Since we don't have user accounts, we can't send email notifications of changes - we recommend checking this page periodically if you're concerned about privacy.
      </p>
    </div>

    <!-- Contact -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-8 text-white text-center mb-8">
      <h2 class="text-3xl font-bold mb-4">Questions About Privacy?</h2>
      <p class="text-xl mb-6">We're happy to answer any questions about how we handle your data.</p>
      <a href="{{ route('contact.show') }}" class="inline-block bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
        Contact Us
      </a>
    </div>

    <!-- Related Links -->
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Related Policies</h2>
      <div class="space-y-3">
        <a href="/terms" class="block text-indigo-600 hover:text-indigo-800 text-base">Terms of Service →</a>
        <a href="/about" class="block text-indigo-600 hover:text-indigo-800 text-base">About Us →</a>
        <a href="https://github.com/stardothosting/nullfake" class="block text-indigo-600 hover:text-indigo-800 text-base" target="_blank" rel="noopener">View Source Code →</a>
      </div>
    </div>

  </main>

  @include('partials.footer')

  @livewireScripts
</body>
</html>
