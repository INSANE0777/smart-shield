<div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Recently Analyzed Products</h2>
            <div class="text-sm text-gray-500">
                {{ count($products) }} products analyzed
            </div>
        </div>

        @if($isLoading)
            <div class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand"></div>
                <span class="ml-2 text-gray-600">Loading products...</span>
            </div>
        @elseif(empty($products))
            <div class="text-center py-12">
                <div class="text-gray-400 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8V4a1 1 0 00-1-1H7a1 1 0 00-1 1v1m0 0V4a1 1 0 011-1h10a1 1 0 011 1v1M6 5v.01M18 5v.01"></path>
                    </svg>
                </div>
                <p class="text-gray-600">No products analyzed yet. Be the first to analyze a product!</p>
            </div>
        @else
            <div class="relative">
                <!-- Carousel Container -->
                <div class="overflow-hidden" id="carousel-container">
                    <div class="flex transition-transform duration-300 ease-in-out" id="carousel-track">
                        @foreach($products as $product)
                            <div class="flex-shrink-0 w-72 mx-2">
                                <div class="bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors duration-200 overflow-hidden group">
                                    <!-- Product Image -->
                                    <div class="relative h-48 bg-white">
                                        <img src="{{ $product['image_url'] }}" 
                                             alt="{{ $product['title'] }}"
                                             class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-200">
                                        
                                        <!-- Grade Badge -->
                                        <div class="absolute top-2 right-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border {{ $product['grade_color'] }}">
                                                Grade {{ $product['grade'] }}
                                            </span>
                                        </div>
                                        
                                        <!-- Trust Score Badge -->
                                        <div class="absolute top-2 left-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-brand bg-opacity-10 text-brand border border-brand border-opacity-20">
                                                {{ $product['trust_score'] }}% Trust
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="p-4">
                                        <h3 class="font-medium text-gray-900 text-sm mb-2 line-clamp-2 group-hover:text-brand transition-colors">
                                            {{ Str::limit($product['title'], 60) }}
                                        </h3>
                                        
                                        <!-- Metrics -->
                                        <div class="space-y-2 mb-3">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">Fake Reviews:</span>
                                                <span class="font-medium {{ $product['fake_percentage'] > 30 ? 'text-red-600' : ($product['fake_percentage'] > 10 ? 'text-yellow-600' : 'text-green-600') }}">
                                                    {{ $product['fake_percentage'] }}%
                                                </span>
                                            </div>
                                            
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">Rating:</span>
                                                <div class="flex items-center space-x-1">
                                                    <span class="text-gray-400 line-through">{{ number_format($product['amazon_rating'], 1) }}</span>
                                                    <span class="font-medium text-gray-900">{{ number_format($product['adjusted_rating'], 1) }}</span>
                                                    <span class="text-yellow-400">★</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Button -->
                                        <a href="{{ url($product['seo_url']) }}" 
                                           class="block w-full bg-brand hover:bg-brand-dark text-white text-center py-2 px-4 rounded-md text-sm font-medium transition-colors duration-200">
                                            View Analysis
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                @if(count($products) > 3)
                    <button id="prev-btn" 
                            class="absolute left-0 top-1/2 transform -translate-y-1/2 -translate-x-4 bg-white rounded-full shadow-lg p-2 hover:bg-gray-50 transition-colors duration-200 z-10">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    
                    <button id="next-btn" 
                            class="absolute right-0 top-1/2 transform -translate-y-1/2 translate-x-4 bg-white rounded-full shadow-lg p-2 hover:bg-gray-50 transition-colors duration-200 z-10">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                @endif
            </div>
            
            <!-- Carousel Indicators -->
            @if(count($products) > 3)
                <div class="flex justify-center mt-4 space-x-2" id="indicators">
                    <!-- Indicators will be generated by JavaScript -->
                </div>
            @endif
        @endif
    </div>

    @if(!empty($products) && count($products) > 3)
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.getElementById('carousel-track');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const indicators = document.getElementById('indicators');
        
        if (!track || !prevBtn || !nextBtn) return;
        
        const items = track.children;
        const itemWidth = 288; // 72 * 4 (w-72 + margins)
        const visibleItems = 3;
        const totalItems = items.length;
        const maxPosition = Math.max(0, totalItems - visibleItems);
        
        let currentPosition = 0;
        
        // Create indicators
        if (indicators && maxPosition > 0) {
            for (let i = 0; i <= maxPosition; i++) {
                const indicator = document.createElement('button');
                indicator.className = `w-2 h-2 rounded-full transition-colors duration-200 ${i === 0 ? 'bg-brand' : 'bg-gray-300'}`;
                indicator.addEventListener('click', () => goToPosition(i));
                indicators.appendChild(indicator);
            }
        }
        
        function updateCarousel() {
            const offset = -currentPosition * itemWidth;
            track.style.transform = `translateX(${offset}px)`;
            
            // Update button states
            prevBtn.disabled = currentPosition === 0;
            nextBtn.disabled = currentPosition >= maxPosition;
            
            prevBtn.style.opacity = currentPosition === 0 ? '0.5' : '1';
            nextBtn.style.opacity = currentPosition >= maxPosition ? '0.5' : '1';
            
            // Update indicators
            if (indicators) {
                const indicatorButtons = indicators.children;
                for (let i = 0; i < indicatorButtons.length; i++) {
                    indicatorButtons[i].className = `w-2 h-2 rounded-full transition-colors duration-200 ${i === currentPosition ? 'bg-brand' : 'bg-gray-300'}`;
                }
            }
        }
        
        function goToPosition(position) {
            currentPosition = Math.max(0, Math.min(maxPosition, position));
            updateCarousel();
        }
        
        prevBtn.addEventListener('click', () => {
            if (currentPosition > 0) {
                currentPosition--;
                updateCarousel();
            }
        });
        
        nextBtn.addEventListener('click', () => {
            if (currentPosition < maxPosition) {
                currentPosition++;
                updateCarousel();
            }
        });
        
        // Initialize
        updateCarousel();
        
        // Auto-scroll every 5 seconds
        setInterval(() => {
            if (currentPosition >= maxPosition) {
                currentPosition = 0;
            } else {
                currentPosition++;
            }
            updateCarousel();
        }, 5000);
    });
    </script>
    @endif

    <style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    </style>
</div> 

