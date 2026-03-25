@inject('captcha', 'App\Services\CaptchaService')

<div class="newsletter-signup-container">
    @if(!$subscribed)
        <div class="bg-gradient-to-r from-gray-50 to-slate-50 border rounded-lg p-6 shadow-sm border-brand">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 mr-3 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">Stay Updated</h3>
            </div>
            
            <p class="text-gray-600 mb-4 text-sm">
                Get notified when we add new features or discover interesting insights about online reviews.
            </p>

            <form wire:submit.prevent="subscribe" class="space-y-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input 
                            type="email" 
                            wire:model="email" 
                            placeholder="Enter your email address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent focus:ring-brand transition-colors text-sm"
                            :disabled="loading"
                        />
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <button 
                        type="submit" 
                        class="px-6 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand transition-colors font-medium text-sm whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center bg-brand hover:bg-brand-dark"
                        :disabled="loading"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Subscribe</span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Subscribing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Success/Error Messages -->
    @if($message)
        <div class="mt-4 p-4 rounded-lg {{ $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            <div class="flex items-center">
                @if($messageType === 'success')
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                @else
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                @endif
                <span class="text-sm font-medium">{{ $message }}</span>
            </div>
            
            @if($subscribed)
                <button 
                    wire:click="resetForm" 
                    class="mt-3 text-sm underline hover:no-underline text-brand hover:text-brand-dark"
                >
                    Subscribe another email
                </button>
            @endif
        </div>
    @endif
</div> 

