@php
    $sources = $post['sources'] ?? [];
@endphp

@if(!empty($sources))
<div class="mt-8 pt-6 border-t border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Sources & References</h3>
    <p class="text-sm text-gray-600 mb-3">This article draws on the following sources for accuracy and verification:</p>
    <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
        @foreach($sources as $source)
        <li>{{ $source }}</li>
        @endforeach
    </ol>
    <p class="text-xs text-gray-500 mt-3 italic">
        Last updated: {{ isset($post['last_updated']) ? date('F j, Y', strtotime($post['last_updated'])) : date('F j, Y', strtotime($post['date'])) }}
    </p>
</div>
@endif
