{{-- Related Posts Widget --}}
@if(isset($relatedPosts) && $relatedPosts->isNotEmpty())
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Related Posts</h3>
    <ul class="space-y-3">
        @foreach($relatedPosts as $relatedPost)
        <li>
            <a href="{{ route('blog.show', ['slug' => $relatedPost['slug']]) }}" 
               class="block group">
                <span class="text-sm text-gray-800 group-hover:text-indigo-600 transition-colors line-clamp-2">
                    {{ $relatedPost['title'] }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ date('M j, Y', strtotime($relatedPost['date'])) }}
                </span>
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif

