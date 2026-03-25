<header class="bg-white shadow p-4">
  <div class="max-w-6xl mx-auto flex items-center justify-between">
    <!-- Logo -->
    <div class="flex items-center space-x-3">
      <a href="{{ route('home') }}">
        <img src="/img/nullfake.svg" alt="SMART SHIELD UI Logo" class="h-16 w-auto object-contain" />
      </a>
    </div>

    <!-- Desktop Navigation -->
    <nav class="hidden md:flex items-center space-x-4 text-sm" aria-label="Desktop Navigation">
      <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('home') ? 'text-indigo-600 font-medium' : '' }}">Home</a>
      <a href="/products" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('products.*') ? 'text-indigo-600 font-medium' : '' }}">All Products</a>
      <a href="/blog" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->is('blog*') ? 'text-indigo-600 font-medium' : '' }}">Blog</a>
      <a href="/how-it-works" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('how-it-works') ? 'text-indigo-600 font-medium' : '' }}">How It Works</a>
      <a href="https://www.reddit.com/r/null_fake/" class="text-gray-600 hover:text-gray-900 transition-colors" target="_blank" rel="noopener noreferrer">Community</a>
      <a href="/about" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('about') ? 'text-indigo-600 font-medium' : '' }}">About</a>
      <a href="{{ route('contact.show') }}" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('contact.*') ? 'text-indigo-600 font-medium' : '' }}">Contact</a>
      <div class="flex items-center gap-2">
        <a href="https://chromewebstore.google.com/detail/null-fake-product-review/fgngdgdpfcfkddgipaafgpnadiikfkaa"
           target="_blank"
           rel="noopener noreferrer"
           class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 px-3 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
           title="Install Chrome Extension">
          <img src="/img/chrome.svg" alt="Chrome logo" class="h-4 w-4" />
          <span>Chrome Extension</span>
        </a>
        <a href="https://addons.mozilla.org/en-GB/firefox/addon/null-fake-amazon-reviews/"
           target="_blank"
           rel="noopener noreferrer"
           class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 px-3 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
           title="Install Firefox Add-on">
          <img src="/img/firefox.svg" alt="Firefox logo" class="h-4 w-4" />
          <span>Firefox Add-on</span>
        </a>
      </div>
    </nav>

    <!-- Mobile Hamburger Button -->
    <button id="mobile-menu-button" class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
      <svg id="hamburger-icon" class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
      </svg>
      <svg id="close-icon" class="w-6 h-6 text-gray-600 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
    </button>
  </div>

  <!-- Mobile Navigation Menu -->
  <div id="mobile-menu" class="md:hidden mobile-menu mt-4 pb-4 border-t border-gray-200">
    <nav class="flex flex-col space-y-4 pt-4" aria-label="Mobile Navigation">
      <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->routeIs('home') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">Home</a>
      <a href="/products" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->routeIs('products.*') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">All Products</a>
      <a href="/blog" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->is('blog*') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">Blog</a>
      <a href="/how-it-works" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->routeIs('how-it-works') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">How It Works</a>
      <a href="https://www.reddit.com/r/null_fake/" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2" target="_blank" rel="noopener noreferrer">Community</a>
      <a href="/about" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->routeIs('about') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">About</a>
      <a href="{{ route('contact.show') }}" class="text-gray-600 hover:text-gray-900 transition-colors px-4 py-2 {{ request()->routeIs('contact.*') ? 'text-indigo-600 font-medium bg-indigo-50' : '' }}">Contact</a>
      <div class="flex flex-col gap-2 mx-4">
        <a href="https://chromewebstore.google.com/detail/null-fake-product-review/fgngdgdpfcfkddgipaafgpnadiikfkaa"
           target="_blank"
           rel="noopener noreferrer"
           class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
           title="Install Chrome Extension">
          <img src="/img/chrome.svg" alt="Chrome logo" class="h-4 w-4" />
          <span>Chrome Extension</span>
        </a>
        <a href="https://addons.mozilla.org/en-GB/firefox/addon/null-fake-amazon-reviews/"
           target="_blank"
           rel="noopener noreferrer"
           class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
           title="Install Firefox Add-on">
          <img src="/img/firefox.svg" alt="Firefox logo" class="h-4 w-4" />
          <span>Firefox Add-on</span>
        </a>
      </div>
    </nav>
  </div>
</header>
