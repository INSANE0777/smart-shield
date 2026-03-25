@php
    $authorKey = $post['author_key'] ?? null;
    $authors = config('blog.authors', []);
    $authorData = $authorKey && isset($authors[$authorKey]) ? $authors[$authorKey] : null;
    $author = $authorData ? $authorData['name'] : ($post['author'] ?? 'SMART SHIELD UI Team');
    $authorBio = $authorData['bio'] ?? 'The SMART SHIELD UI team specializes in AI-powered review analysis and consumer protection.';
    $authorRole = $authorData['role'] ?? 'Consumer Protection Researchers';
    $credentials = $authorData['credentials'] ?? [];
    $links = $authorData['links'] ?? [];
@endphp

<div class="mt-12 pt-8 border-t border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">About the Author</h3>
    <div class="flex items-start space-x-4">
        <div class="flex-shrink-0 w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
            {{ collect(explode(' ', $author))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
        </div>
        <div class="flex-1">
            <h4 class="text-lg font-semibold text-gray-900">{{ $author }}</h4>
            <p class="text-sm text-indigo-600 mb-2">{{ $authorRole }}</p>
            <p class="text-base text-gray-600 mb-3">{{ $authorBio }}</p>
            
            @if(!empty($credentials))
            <div class="mb-3">
                <p class="text-sm font-medium text-gray-700 mb-1">Credentials:</p>
                <ul class="text-sm text-gray-600 list-disc list-inside">
                    @foreach($credentials as $credential)
                    <li>{{ $credential }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(!empty($links))
            <div class="flex space-x-3">
                @if(isset($links['website']))
                <a href="{{ $links['website'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 text-sm">Website</a>
                @endif
                @if(isset($links['github']))
                <a href="{{ $links['github'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 text-sm">GitHub</a>
                @endif
                @if(isset($links['linkedin']))
                <a href="{{ $links['linkedin'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 text-sm">LinkedIn</a>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

