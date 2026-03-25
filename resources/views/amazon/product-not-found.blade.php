<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  {{-- No AdSense on thin content pages (product not found/not analyzed) --}}
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Product Not Found - {{ $asin }} | SMART SHIELD UI</title>
  <meta name="description" content="The Amazon product {{ $asin }} has not been analyzed yet. Analyze it now on SMART SHIELD UI." />
  <meta name="robots" content="noindex, nofollow" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  
  <!-- TailwindCSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  @vite(['resources/css/app.css'])

</head>

<body class="bg-gray-50 min-h-screen">
  @include('partials.header')

  <main class="max-w-3xl mx-auto mt-12 p-6">
    <div class="bg-white rounded-lg shadow-md p-8 text-center">
      <div class="mb-6">
        @if(isset($is_processing) && $is_processing)
          <!-- Processing state - show spinner icon -->
          <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-blue-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mb-2">Analysis In Progress</h1>
          <p class="text-gray-600 mb-4">
            @if(isset($product_title) && $product_title)
              We're currently analyzing <strong>{{ $product_title }}</strong>.
            @else
              This product is currently being analyzed.
            @endif
          </p>
        @else
          <!-- Not found state - show document icon -->
          <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mb-2">Product Not Found</h1>
          <p class="text-gray-600 mb-4">
            We haven't analyzed this Amazon product yet.
          </p>
        @endif

        <div class="bg-gray-50 p-3 rounded-lg mb-6">
          <span class="text-sm text-gray-600">ASIN:</span>
          <span class="font-mono text-sm font-medium">{{ $asin }}</span>
        </div>

        @if(isset($is_processing) && $is_processing)
          <!-- Show processing information with ETA -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="text-blue-900 font-semibold">Estimated completion time</span>
            </div>
            <p class="text-blue-700 text-lg font-bold">
              {{ $estimated_minutes ?? 3 }} {{ ($estimated_minutes ?? 3) === 1 ? 'minute' : 'minutes' }}
            </p>
            <p class="text-sm text-blue-600 mt-2">
              Please check back on this page in approximately {{ $estimated_minutes ?? 3 }} {{ ($estimated_minutes ?? 3) === 1 ? 'minute' : 'minutes' }}.
              The analysis will be complete and ready to view.
            </p>
          </div>

          <!-- Auto-refresh hint -->
          <div class="bg-gray-50 p-3 rounded-lg mb-6">
            <p class="text-sm text-gray-600">
              You can bookmark this page or keep it open - refresh to see the completed analysis.
            </p>
          </div>
        @endif
      </div>

      <div class="space-y-4">
        <a href="{{ $amazon_url }}" 
           target="_blank" 
           rel="noopener noreferrer"
           class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium">
          View on Amazon
        </a>
        @include('partials.affiliate-disclosure')

        @if(isset($is_processing) && $is_processing)
          <!-- Add a refresh button for processing state -->
          <button onclick="window.location.reload()" 
                  class="block w-full sm:inline-block sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium mt-3">
            Check Status Now
          </button>
        @endif
      </div>

      @if(!isset($is_processing) || !$is_processing)
        <!-- Only show the "Want to analyze" message if not currently processing -->
        <div class="mt-8 p-4 bg-brand bg-opacity-5 rounded-lg">
          <p class="text-sm text-white">
            <strong>Want to analyze this product?</strong> 
            Copy the Amazon URL and paste it on our home page to get started.
          </p>
        </div>
      @endif
    </div>
  </main>

  @include('partials.footer')
</body>
</html> 

