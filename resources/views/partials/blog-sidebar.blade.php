{{-- Blog Sidebar --}}
{{-- Contains: Search, Related/Latest Posts, Categories, Tag Cloud --}}
<aside class="space-y-0">
    {{-- Search Box --}}
    @include('partials.widgets.search-box')
    
    {{-- Related Posts (on single post pages) or Latest Posts (on index/search) --}}
    @if(isset($relatedPosts) && $relatedPosts->isNotEmpty())
        @include('partials.widgets.related-posts')
    @elseif(isset($latestPosts) && $latestPosts->isNotEmpty())
        @include('partials.widgets.latest-posts')
    @endif
    
    {{-- Categories --}}
    @include('partials.widgets.categories')
    
    {{-- Tag Cloud --}}
    @include('partials.widgets.tag-cloud')
</aside>
