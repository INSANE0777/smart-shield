@if(!empty($post['image']))
  <div class="mb-8">
    <img
      src="{{ $post['image'] }}"
      alt="{{ $post['title'] }}"
      class="w-full h-64 md:h-80 object-cover rounded-lg border border-gray-200"
      loading="lazy"
      referrerpolicy="no-referrer"
    />
  </div>
@endif



