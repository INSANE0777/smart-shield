{{-- Categories Widget --}}
@if(isset($categories) && $categories->isNotEmpty())
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Categories</h3>
    <ul class="space-y-2">
        @foreach($categories as $category)
        <li>
            <a href="{{ route('blog.category', ['category' => $category['slug']]) }}" 
               class="flex items-center justify-between text-sm text-gray-700 hover:text-indigo-600 transition-colors">
                <span>{{ $category['name'] }}</span>
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">
                    {{ $category['count'] }}
                </span>
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif

