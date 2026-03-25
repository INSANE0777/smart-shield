@inject('captcha', 'App\Services\CaptchaService')

<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Contact Us - SMART SHIELD UI</title>
  <meta name="description" content="Contact SMART SHIELD UI for support, feedback, or questions about our Amazon review analysis tool. We're here to help with any questions about fake review detection." />
  <meta name="robots" content="index, follow" />
  
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
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="msapplication-TileImage" content="/img/ms-icon-144x144.png">
  <meta name="theme-color" content="#ffffff">

  <!-- Fonts and Styling -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>

</head>
<body class="bg-gray-50 min-h-screen">
  @include('partials.header')

  <!-- Main Content -->
  <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-white rounded-lg shadow-lg p-8">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Contact Us</h1>
        <p class="text-lg text-gray-600">
          Have a question, suggestion, or need help? We'd love to hear from you.
        </p>
      </div>

      @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800">
                {{ session('success') }}
              </p>
            </div>
          </div>
        </div>
      @endif

      @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">
                There were some errors with your submission:
              </h3>
              <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                  @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            </div>
          </div>
        </div>
      @endif

      <form action="{{ route('contact.submit') }}" method="POST" class="space-y-6">
        @csrf
        
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
          <input type="email" 
                 id="email" 
                 name="email" 
                 value="{{ old('email') }}"
                 required
                 class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-300 @enderror">
          @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
          <input type="text" 
                 id="subject" 
                 name="subject" 
                 value="{{ old('subject') }}"
                 required
                 maxlength="255"
                 class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('subject') border-red-300 @enderror">
          @error('subject')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
          <textarea id="message" 
                    name="message" 
                    rows="6" 
                    required
                    maxlength="5000"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('message') border-red-300 @enderror">{{ old('message') }}</textarea>
          @error('message')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
          <p class="mt-1 text-sm text-gray-500">Maximum 5,000 characters</p>
        </div>

        @if(!app()->environment(['local', 'testing']))
          <div id="captcha-container">
            @if($captcha->getProvider() === 'recaptcha')
              <div id="recaptcha-container" class="g-recaptcha" data-sitekey="{{ $captcha->getSiteKey() }}" data-callback="onRecaptchaSuccess"></div>
              <input type="hidden" name="g_recaptcha_response" id="g_recaptcha_response">
              @error('captcha')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
              @enderror
              <script>
                function onRecaptchaSuccess(token) {
                  document.getElementById('g_recaptcha_response').value = token;
                }

                function renderRecaptcha() {
                  if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.render('recaptcha-container', {
                      'sitekey': '{{ $captcha->getSiteKey() }}',
                      'callback': onRecaptchaSuccess
                    });
                  }
                }

                document.addEventListener("DOMContentLoaded", function() {
                  renderRecaptcha();
                });
              </script>
              <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @elseif($captcha->getProvider() === 'hcaptcha')
              <div id="hcaptcha-container" 
                   class="h-captcha" 
                   data-sitekey="{{ $captcha->getSiteKey() }}" 
                   data-callback="onHcaptchaSuccess"></div>
              <input type="hidden" name="h_captcha_response" id="h_captcha_response">
              @error('captcha')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
              @enderror
              <script>
                function onHcaptchaSuccess(token) {
                  document.getElementById('h_captcha_response').value = token;
                }
              </script>
              <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
            @endif
          </div>
        @endif

        <div>
          <button type="submit" 
                  class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
            Send Message
          </button>
        </div>
      </form>
    </div>

  </main>

  <!-- Footer -->
  @include('partials.footer')
</body>
</html>
