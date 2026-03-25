@if(config('google.adsense.enabled') && config('google.adsense.publisher_id'))
<!-- Google AdSense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('google.adsense.publisher_id') }}"
     crossorigin="anonymous"></script>
@endif

