{{-- Tag Cloud Widget --}}
@if(isset($tagCloud) && $tagCloud->isNotEmpty())
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Tag Cloud</h3>
    <div class="flex flex-wrap gap-2">
        @foreach($tagCloud as $tag)
        <a href="{{ route('blog.search', ['q' => $tag['name']]) }}" 
           class="inline-block px-2 py-1 rounded border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition-colors
                  @if($tag['size'] >= 4) text-sm font-medium text-indigo-700
                  @elseif($tag['size'] >= 3) text-sm text-indigo-600
                  @elseif($tag['size'] >= 2) text-xs text-gray-700
                  @else text-xs text-gray-500
                  @endif"
           title="{{ $tag['count'] }} posts">
            {{ $tag['name'] }}
        </a>
        @endforeach
    </div>
</div>
@endif
