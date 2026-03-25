{{-- Blog Search Widget --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Search Blog</h3>
    <form action="{{ route('blog.search') }}" method="GET">
        <div class="relative">
            <input 
                type="text" 
                name="q" 
                value="{{ request('q', '') }}"
                placeholder="Search articles..." 
                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
            <button 
                type="submit" 
                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-indigo-600"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </div>
    </form>
</div>
