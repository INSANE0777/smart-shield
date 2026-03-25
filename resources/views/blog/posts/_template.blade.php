<!DOCTYPE html>
<html lang="en">
<head>
  @include('partials.ezoic')
  @include('partials.adsense')
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @include('blog.posts._seo-meta')
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
  @include('partials.header')
  <main class="max-w-4xl mx-auto mt-10 px-6 mb-16">
    <article class="bg-white rounded-lg shadow-lg p-8">
      <a href="/blog" class="text-indigo-600 text-sm mb-4 inline-block">← Back to Blog</a>
      <h1 class="text-4xl font-bold mb-4">{{ $post['title'] }}</h1>
      <p class="text-gray-600 mb-8">{{ date('F j, Y', strtotime($post['date'])) }} • {{ $post['author'] }}</p>
      @include('blog.posts._featured-image')
      <div class="prose max-w-none">
        <p class="text-xl mb-6">{{ $post['description'] }}</p>
        <p>{!! $content ?? 'Article content coming soon. Check back later for the full article.' !!}</p>
      </div>
    </article>
  </main>
  @include('partials.footer')
</body>
</html>


