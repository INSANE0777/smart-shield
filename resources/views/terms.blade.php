<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Terms of Service | SMART SHIELD UI</title>
  <meta name="description" content="Terms of Service for SMART SHIELD UI - Free Amazon Review Analysis Tool" />
  <meta name="robots" content="index, follow" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
  @include('partials.header')

  <main class="max-w-4xl mx-auto mt-10 px-6 mb-16">
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h1 class="text-4xl font-bold text-gray-900 mb-6">Terms of Service</h1>
      <p class="text-sm text-gray-500 mb-8">Last Updated: January 2, 2025</p>

      <div class="prose max-w-none text-base text-gray-700 space-y-6">
        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Acceptance of Terms</h2>
          <p>By accessing and using SMART SHIELD UI ("the Service"), you accept and agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Service.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Description of Service</h2>
          <p>SMART SHIELD UI provides free AI-powered analysis of Amazon product reviews to help identify potentially fake or suspicious reviews. The Service is provided "as is" without warranties of any kind.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">3. No Account Required</h2>
          <p>The Service does not require user accounts or personal information. We do not collect, store, or process personal data about individual users.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Accuracy Disclaimer</h2>
          <p>While we strive for accuracy, our analysis provides probability estimates, not certainties. No fake review detection system is 100% accurate. Users should use our analysis as one factor in purchasing decisions, not the sole determinant.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Fair Use</h2>
          <p>The Service is free for personal, non-commercial use. Automated scraping, bulk analysis, or commercial use without permission is prohibited. Contact us for commercial licensing.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">6. No Liability</h2>
          <p>We are not liable for any damages resulting from use of the Service, including but not limited to purchasing decisions based on our analysis. Use at your own risk.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Open Source License</h2>
          <p>The SMART SHIELD UI codebase is licensed under the MIT License and available on GitHub. See our repository for full license terms.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Changes to Terms</h2>
          <p>We reserve the right to modify these terms at any time. Continued use of the Service after changes constitutes acceptance of new terms.</p>
        </section>

        <section>
          <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Contact</h2>
          <p>Questions about these terms? <a href="{{ route('contact.show') }}" class="text-indigo-600 hover:underline">Contact us</a>.</p>
        </section>
      </div>
    </div>
  </main>

  @include('partials.footer')
</body>
</html>


