<header class="bg-white shadow p-4">
  <div class="max-w-6xl mx-auto flex items-center justify-between">
    <!-- Logo -->
    <div class="flex items-center space-x-3">
      <a href="{{ route('home') }}" class="flex items-center space-x-2 group">
        <div class="bg-indigo-600 p-2 rounded-lg group-hover:bg-indigo-700 transition-colors">
          <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
        </div>
        <span class="text-2xl font-bold text-gray-900 tracking-tight group-hover:text-indigo-600 transition-colors">SMART SHIELD</span>
      </a>
    </div>

    <!-- Desktop Navigation -->
    <nav class="hidden md:flex items-center space-x-6 text-sm font-medium" aria-label="Desktop Navigation">
      <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600 transition-colors {{ request()->routeIs('home') ? 'text-indigo-600' : '' }}">Home</a>
      <a href="/how-it-works" class="text-gray-600 hover:text-indigo-600 transition-colors {{ request()->routeIs('how-it-works') ? 'text-indigo-600' : '' }}">How It Works</a>
      <a href="/about" class="text-gray-600 hover:text-indigo-600 transition-colors {{ request()->routeIs('about') ? 'text-indigo-600' : '' }}">About</a>
      <a href="{{ route('contact.show') }}" class="text-gray-600 hover:text-indigo-600 transition-colors {{ request()->routeIs('contact.*') ? 'text-indigo-600' : '' }}">Contact</a>
      <a href="https://github.com/INSANE0777/smart-shield" target="_blank" rel="noopener noreferrer" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors flex items-center gap-2">
        <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
        <span>GitHub</span>
      </a>
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
    <nav class="flex flex-col space-y-2 pt-4" aria-label="Mobile Navigation">
      <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600 transition-colors px-4 py-3 rounded-lg {{ request()->routeIs('home') ? 'text-indigo-600 bg-indigo-50' : '' }}">Home</a>
      <a href="/how-it-works" class="text-gray-600 hover:text-indigo-600 transition-colors px-4 py-3 rounded-lg {{ request()->routeIs('how-it-works') ? 'text-indigo-600 bg-indigo-50' : '' }}">How It Works</a>
      <a href="/about" class="text-gray-600 hover:text-indigo-600 transition-colors px-4 py-3 rounded-lg {{ request()->routeIs('about') ? 'text-indigo-600 bg-indigo-50' : '' }}">About</a>
      <a href="{{ route('contact.show') }}" class="text-gray-600 hover:text-indigo-600 transition-colors px-4 py-3 rounded-lg {{ request()->routeIs('contact.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">Contact</a>
      <a href="https://github.com/INSANE0777/smart-shield" target="_blank" rel="noopener noreferrer" class="mx-4 mt-4 bg-gray-900 text-white px-4 py-3 rounded-lg text-center font-medium flex items-center justify-center gap-2">
        <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
        <span>GitHub Repository</span>
      </a>
    </nav>
  </div>
</header>

