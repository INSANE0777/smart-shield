@inject('captcha', 'App\Services\CaptchaService')

<div>
    <form wire:submit.prevent="analyze" class="space-y-6" id="review-form">
        <div>
            <label for="productUrl" class="block text-sm font-medium">Amazon Product URL</label>
            <input type="url" id="productUrl" wire:model="productUrl" required
                   class="mt-1 w-full px-4 py-2 border rounded focus:outline-none focus:ring focus:border-indigo-300"
                   onpaste="setTimeout(validateAmazonUrl, 100)" />
            @error('productUrl')
                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
            @enderror
            <div id="url-validation-status" class="mt-2 text-sm" style="display: none;"></div>
        </div>

        @if(!app()->environment(['local', 'testing']))
            <div id="captcha-container">
                @if($captcha->getProvider() === 'recaptcha')
                    @if(!$captcha_passed)
                        <div id="recaptcha-container" class="g-recaptcha" data-sitekey="{{ $captcha->getSiteKey() }}" data-callback="onRecaptchaSuccess"></div>
                        <input type="hidden" wire:model="g_recaptcha_response">
                        @error('g_recaptcha_response')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                        <script>
                            let recaptchaWidgetId = null;
                            function onRecaptchaSuccess(token) {
                                window.Livewire.find('{{ $this->getId() }}').set('g_recaptcha_response', token);
                            }

                            function renderRecaptcha() {
                                if (typeof grecaptcha !== 'undefined') {
                                    if (recaptchaWidgetId !== null) {
                                        grecaptcha.reset(recaptchaWidgetId);
                                    } else {
                                        recaptchaWidgetId = grecaptcha.render('recaptcha-container', {
                                            'sitekey': '{{ $captcha->getSiteKey() }}',
                                            'callback': onRecaptchaSuccess
                                        });
                                    }
                                }
                            }

                            document.addEventListener("livewire:load", function () {
                                renderRecaptcha();
                                Livewire.hook('message.processed', (message, component) => {
                                    renderRecaptcha();
                                });
                            });
                        </script>
                        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                    @endif
                @elseif($captcha->getProvider() === 'hcaptcha')
                    @if(!$captcha_passed)
                        <div
                            wire:key="hcaptcha-{{ $hcaptchaKey }}"
                            id="hcaptcha-container-{{ $hcaptchaKey }}"
                            class="h-captcha"
                            data-sitekey="{{ $captcha->getSiteKey() }}"
                            data-callback="onHcaptchaSuccess"
                        ></div>
                        <input type="hidden" wire:model="h_captcha_response">
                        @error('h_captcha_response')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                        <script>
                            function onHcaptchaSuccess(token) {
                                window.Livewire.find('{{ $this->getId() }}').set('h_captcha_response', token);
                            }
                            function renderHcaptcha() {
                                if (typeof hcaptcha !== 'undefined') {
                                    const container = document.getElementById('hcaptcha-container-{{ $hcaptchaKey }}');
                                    if (container) {
                                        container.innerHTML = '';
                                    }
                                    hcaptcha.render('hcaptcha-container-{{ $hcaptchaKey }}', {
                                        'sitekey': '{{ $captcha->getSiteKey() }}',
                                        'callback': onHcaptchaSuccess
                                    });
                                }
                            }
                            document.addEventListener("livewire:load", function () {
                                renderHcaptcha();
                                Livewire.hook('message.processed', (message, component) => {
                                    renderHcaptcha();
                                });
                            });
                        </script>
                        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                    @endif
                @endif
            </div>
        @else
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <strong>Local Environment:</strong> Captcha validation is disabled for development.
            </div>
        @endif

        <button type="submit" class="w-full bg-brand text-white py-2 px-4 rounded hover:bg-brand-dark disabled:opacity-50"
                wire:loading.attr="disabled" 
                onclick="showAnalysisProgress()"
                id="analyze-button">
                            <span wire:loading.remove wire:target="analyze">Analyze Reviews</span>
                <span wire:loading wire:target="analyze">
                Analyzing...
            </span>
        </button>
    </form>



    {{-- Simple Loading indicator that actually works --}}
    <div wire:loading wire:target="analyze" class="mt-6 bg-white rounded-lg shadow-md p-6 w-full"></div>

    {{-- Progress bar for both sync and async modes --}}
    @if($loading)
    <div class="mt-6 bg-white rounded-lg shadow-md p-6 w-full progress-bar-container">
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Analyzing Reviews</h3>
            
                         <!-- Animated progress bar -->
             <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                 <div id="progress-bar" class="bg-brand h-3 rounded-full transition-all duration-1000 ease-out" 
                      style="width: {{ $progressPercentage }}%"></div>
             </div>
             
             <!-- Progress Status -->
             <p id="progress-status" class="text-xs text-gray-500 mb-2">{{ $currentlyProcessing ?? 'Initializing analysis...' }}</p>
            
                         <p class="text-base text-gray-600 mb-4">
                 Gathering review information and performing AI analysis...
             </p>
            
            <div class="flex justify-center">
                <svg id="loading-spinner" class="animate-spin h-8 w-8 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            
            <p class="text-xs text-gray-500 mt-4">
                This may take 30-60 seconds...
            </p>
        </div>
    </div>
    @endif

    {{-- No longer show results on homepage - redirect to dedicated product page instead --}}

    {{-- Error display --}}
    @if($error)
        <div class="mt-6 text-red-600 bg-red-100 p-4 rounded">
            <strong>Error:</strong> {{ $error }}
        </div>
    @endif
</div>

{{-- Analysis progress tracking JavaScript --}}
<script>
// Global variables for async analysis
let currentSessionId = null;
let currentPoller = null;
let isAsyncMode = {{ config('analysis.async_enabled') ? 'true' : 'false' }};
let lastProgressSignature = null;

function showAnalysisProgress() {
    const progressBar = document.getElementById('progress-bar');
    const progressStatus = document.getElementById('progress-status');
    
    if (!progressBar || !progressStatus) return;
    
    // Clear previous results immediately for better UX
    window.Livewire.find('{{ $this->getId() }}').call('clearPreviousResults');
    
    // Also hide the results section immediately via JavaScript
    const resultsSection = document.querySelector('[data-results-section]');
    if (resultsSection) {
        resultsSection.style.display = 'none';
    }
    
    if (isAsyncMode) {
        // In async mode, Livewire handles all progress updates
        // No need to manipulate DOM directly - just ensure we're in async mode
        console.log('Async mode: Progress will be handled by Livewire polling');
    } else {
        // Use Livewire-controlled simulated progress for sync mode
        console.log('Sync mode: Starting simulated progress via Livewire');
        const livewireComponent = window.Livewire.find('{{ $this->getId() }}');
        if (livewireComponent) {
            livewireComponent.call('startSyncProgress');
        }
    }
}

// Sync progress simulation via Livewire
window.addEventListener('startSyncProgressSimulation', function() {
    console.log('Starting sync progress simulation via Livewire');
    
    const steps = [
        { percent: 12, message: 'Validating product URL...', delay: 2000 },
        { percent: 25, message: 'Authenticating request...', delay: 3000 },
        { percent: 38, message: 'Accessing product database...', delay: 4000 },
        { percent: 52, message: 'Gathering review information...', delay: 18000 },
        { percent: 70, message: 'Processing reviews with AI...', delay: 25000 },
        { percent: 85, message: 'Computing authenticity metrics...', delay: 6000 },
        { percent: 95, message: 'Generating final report...', delay: 4000 },
        { percent: 100, message: 'Analysis complete!', delay: 2000 }
    ];
    
    let currentStep = 0;
    
    function updateSyncProgress() {
        if (currentStep < steps.length) {
            const step = steps[currentStep];
            
            // Update via Livewire instead of direct DOM manipulation
            const livewireComponent = window.Livewire.find('{{ $this->getId() }}');
            if (livewireComponent) {
                livewireComponent.call('updateProgress', {
                    progress: step.percent,
                    message: step.message,
                    step: currentStep + 1
                });
            }
            
            currentStep++;
            if (currentStep < steps.length) {
                setTimeout(updateSyncProgress, step.delay);
            }
        }
    }
    
    setTimeout(updateSyncProgress, 500);
});

// Async analysis functions
async function startAsyncAnalysis(productUrl, captchaData) {
    console.log('=== STARTING ASYNC ANALYSIS ===');
    console.log('Product URL:', productUrl);
    console.log('Captcha data:', captchaData);
    
    try {
        // Notify Livewire that we're starting async polling (keeps progress bar visible)
        window.Livewire.find('{{ $this->getId() }}').call('startAsyncPolling');
        
        console.log('Making API call to /api/analysis/start...');
        // Check CSRF token exists before making request
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('CSRF token not found in page meta tags');
        }
        console.log('CSRF token found, making API request...');

        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            credentials: 'same-origin', // Include session cookies for CSRF validation
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                productUrl: productUrl,
                g_recaptcha_response: captchaData.g_recaptcha_response || '',
                h_captcha_response: captchaData.h_captcha_response || ''
            })
        });

        console.log('API response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('API request failed:', response.status, errorText);
            throw new Error(`API request failed: ${response.status} - ${errorText}`);
        }

        const data = await response.json();
        
        if (data.success) {
            currentSessionId = data.session_id;
            console.log('Analysis started, session ID:', currentSessionId);
            
            // Start polling for progress
            startProgressPolling();
        } else {
            throw new Error(data.error || 'Failed to start analysis');
        }
        
    } catch (error) {
        console.error('Error starting async analysis:', error);
        handleAnalysisError('Failed to start analysis: ' + error.message);
    }
}

// Improved progress polling class with better error handling and timing
class ProgressPoller {
    constructor(sessionId, options = {}) {
        this.sessionId = sessionId;
        this.interval = options.interval || {{ config('analysis.polling_interval', 2000) }};
        this.maxRetries = options.maxRetries || 3;
        this.backoffMultiplier = options.backoffMultiplier || 1.5;
        this.maxInterval = options.maxInterval || 30000; // 30s ceiling to reduce load during long waits
        this.maxDurationMs = options.maxDurationMs || 45 * 60 * 1000; // 45 minutes
        this.isPolling = false;
        this.retryCount = 0;
        this.noChangeCount = 0;
        this.lastServerSignature = null;
        this.startedAtMs = null;
        this.onProgress = options.onProgress || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});
    }

    start() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.retryCount = 0;
        this.noChangeCount = 0;
        this.lastServerSignature = null;
        this.startedAtMs = Date.now();
                    console.log('Starting progress polling for session:', this.sessionId);
        this._poll();
    }

    stop() {
        this.isPolling = false;
        console.log(`[${new Date().toISOString()}] Stopped progress polling`);
    }

    async _poll() {
        if (!this.isPolling) return;

        const startTime = Date.now();
                        console.log(`Poll #${this.retryCount + 1} for session:`, this.sessionId);

        try {
            if (this.startedAtMs && (Date.now() - this.startedAtMs) > this.maxDurationMs) {
                this.stop();
                this.onError('Analysis is taking longer than expected. Please try again in a few minutes.');

                return;
            }

            const response = await fetch(`/api/analysis/progress/${this.sessionId}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: AbortSignal.timeout(10000) // 10 second timeout
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            const elapsed = Date.now() - startTime;
            
                                console.log(`Poll response (${elapsed}ms): ${data.progress_percentage}% - ${data.current_message}`);

            // Reset retry count on successful response
            this.retryCount = 0;

            // Handle response
            if (data.success) {
                const signature = `${data.status}|${data.progress_percentage}|${data.current_message}`;
                if (signature === this.lastServerSignature) {
                    this.noChangeCount++;
                } else {
                    this.noChangeCount = 0;
                    this.lastServerSignature = signature;
                }

                this.onProgress(data);

                if (data.status === 'completed') {
                                            console.log('Analysis completed - calling onComplete');
                        this.stop();
                        this.onComplete(data);
                    return;
                } else if (data.status === 'failed') {
                    console.log(`[${new Date().toISOString()}] Analysis failed: ${data.error}`);
                    this.stop();
                    this.onError(data.error);
                    return;
                }
            }

            // Schedule next poll
            this._scheduleNextPoll();

        } catch (error) {
            console.error(`[${new Date().toISOString()}] Polling error:`, error);
            
            this.retryCount++;
            
            if (this.retryCount >= this.maxRetries) {
                console.error(`[${new Date().toISOString()}] Max retries exceeded, stopping polling`);
                this.stop();
                this.onError(`Polling failed after ${this.maxRetries} attempts: ${error.message}`);
                return;
            }

            // Exponential backoff on retry
            this._scheduleNextPoll(true);
        }
    }

    _scheduleNextPoll(isRetry = false) {
        if (!this.isPolling) return;

        let delay = this.interval;
        
        if (isRetry) {
            // Exponential backoff: 2s, 3s, 4.5s, etc.
            delay = this.interval * Math.pow(this.backoffMultiplier, this.retryCount - 1);
            console.log(`[${new Date().toISOString()}] Retrying in ${delay}ms (attempt ${this.retryCount})`);
        } else if (this.noChangeCount >= 3) {
            // If the server response isn't changing, back off polling to reduce load.
            // 2s, 2s, 2s, then 6s, 8s, 10s... capped at maxInterval.
            delay = Math.min(this.maxInterval, this.interval * (this.noChangeCount + 1));
        }

        setTimeout(() => this._poll(), delay);
    }
}

function startProgressPolling() {
    if (!currentSessionId) return;
    
    // Stop any existing poller
    if (currentPoller) {
        currentPoller.stop();
    }
    
    // Create new poller with handlers
    currentPoller = new ProgressPoller(currentSessionId, {
        interval: {{ config('analysis.polling_interval', 2000) }},
        maxRetries: 5,
        onProgress: updateProgressFromServer,
        onComplete: handleAnalysisComplete,
        onError: handleAnalysisError
    });

    currentPoller.start();
}

function updateProgressFromServer(data) {
    const signature = `${data.progress_percentage}|${data.current_message}|${data.current_step || 0}`;
    if (signature === lastProgressSignature) {
        return;
    }
    lastProgressSignature = signature;

    // Update Livewire component state instead of direct DOM manipulation
    const livewireComponent = window.Livewire.find('{{ $this->getId() }}');
    if (livewireComponent) {
        livewireComponent.call('updateAsyncProgress', {
            progress: data.progress_percentage,
            message: data.current_message,
            step: data.current_step || 0
        });
        
        console.log(`Progress: ${data.progress_percentage}% - ${data.current_message}`);
    }
}

function handleAnalysisComplete(data) {
    console.log('Analysis completed:', data);
    
    // Update final progress through Livewire
    updateProgressFromServer({
        progress_percentage: 100,
        current_message: 'Analysis complete!',
        current_step: 8
    });
    
    // Let Livewire handle completion (results or redirection)
    const livewireComponent = window.Livewire.find('{{ $this->getId() }}');
    if (livewireComponent) {
        livewireComponent.call('handleAsyncCompletion', data);
    } else {
        console.log('Livewire component not found');
    }
    
    currentSessionId = null;
    currentPoller = null;
}

function handleAnalysisError(errorMessage) {
    console.error('Analysis failed:', errorMessage);
    
    // Update Livewire component with error
    const livewireComponent = window.Livewire.find('{{ $this->getId() }}');
    if (livewireComponent) {
        livewireComponent.call('handleAsyncError', errorMessage);
    }
    
    currentSessionId = null;
    currentPoller = null;
}

// updateProgressError function removed - now handled by handleAsyncError Livewire method

// Reset progress when page loads
document.addEventListener('DOMContentLoaded', function() {
    const progressBar = document.getElementById('progress-bar');
    const progressStatus = document.getElementById('progress-status');
    
    if (progressBar) progressBar.style.width = '0%';
    if (progressStatus) progressStatus.textContent = 'Ready to analyze...';
    
    // Reset results section display
    const resultsSection = document.querySelector('[data-results-section]');
    if (resultsSection) {
        resultsSection.style.display = '';
    }
});

// Client-side Amazon URL validation
let validationTimeout;
let lastValidatedUrl = '';
let isValidating = false;

async function validateAmazonUrl() {
    const urlInput = document.getElementById('productUrl');
    const statusDiv = document.getElementById('url-validation-status');
    const analyzeButton = document.getElementById('analyze-button');
    
    if (!urlInput || !statusDiv) return;
    
    const url = urlInput.value.trim();
    console.log('validateAmazonUrl called with URL:', url, 'lastValidatedUrl:', lastValidatedUrl);
    
    // Don't validate if URL is empty or same as last validated
    if (!url || url === lastValidatedUrl) {
        console.log('Skipping validation - URL empty or already validated');
        statusDiv.style.display = 'none';
        if (analyzeButton) analyzeButton.disabled = false;
        return;
    }
    
    // Clear previous timeout
    if (validationTimeout) {
        clearTimeout(validationTimeout);
    }
    
    // Handle Amazon short URLs (a.co) first
    if (url.includes('a.co/') || url.includes('amzn.to/')) {
        console.log('Short URL detected, processing:', url);
        showValidationStatus('🔗 Amazon short URL detected - validating...', 'checking');
        if (analyzeButton) analyzeButton.disabled = true;
        
        // Try to expand the URL client-side first
        try {
            const expandedUrl = await expandShortUrl(url);
            if (expandedUrl && expandedUrl !== url) {
                console.log('Successfully expanded short URL:', expandedUrl);
                
                // Update the input field with expanded URL
                urlInput.value = expandedUrl;
                
                // Try to sync with Livewire (but don't let this block validation)
                try {
                    if (typeof window.Livewire !== 'undefined' && window.Livewire.emit) {
                        window.Livewire.emit('updateProductUrl', expandedUrl);
                    } else if (typeof Livewire !== 'undefined' && Livewire.emit) {
                        Livewire.emit('updateProductUrl', expandedUrl);
                    }
                } catch (e) {
                    console.warn('Could not sync with Livewire (continuing anyway):', e);
                }
                
                // Reset validation state to ensure the expanded URL gets validated
                lastValidatedUrl = '';
                console.log('About to validate expanded URL:', expandedUrl);
                
                // Continue validation with expanded URL immediately
                setTimeout(() => {
                    console.log('Recursive call to validateAmazonUrl with expanded URL');
                    validateAmazonUrl();
                }, 100);
                return;
            }
        } catch (error) {
            console.warn('Short URL expansion failed:', error);
        }
        
        // If client-side expansion failed, try to validate the short URL directly
        // by attempting to extract any ASIN that might be in the short URL path
        // Support multiple short URL patterns: /d/ASIN, /d/shortcode, /shortcode
        let shortUrlAsin = null;
        
        // Try to extract ASIN from different patterns
        const asinPatterns = [
            /\/d\/([A-Z0-9]{10})/, // Direct ASIN in /d/ path
            /\/([A-Z0-9]{10})$/,   // ASIN at end of URL
        ];
        
        for (const pattern of asinPatterns) {
            const match = url.match(pattern);
            if (match) {
                shortUrlAsin = match[1];
                console.log('Found potential ASIN in short URL:', shortUrlAsin);
                break;
            }
        }
        
        if (shortUrlAsin) {
            try {
                // Try to validate using the extracted ASIN
                let validationResult = await tryMultipleValidationMethods(shortUrlAsin);
                
                if (validationResult.success) {
                    showValidationStatus('✅ Short URL validated successfully', 'success');
                    if (analyzeButton) analyzeButton.disabled = false;
                } else {
                    showValidationStatus('🔗 Short URL detected - will process server-side', 'info');
                    if (analyzeButton) analyzeButton.disabled = false;
                }
                return;
            } catch (error) {
                console.warn('Short URL validation failed:', error);
            }
        } else {
            console.log('No ASIN pattern found in short URL, will rely on server-side expansion');
        }
        
        // If all else fails, still allow submission - server will handle expansion
        showValidationStatus('🔗 Short URL detected - will process server-side', 'info');
        if (analyzeButton) analyzeButton.disabled = false;
        return;
    }
    
    // Validate Amazon domain first - support international domains
    const amazonDomainPattern = /^https?:\/\/(?:www\.)?amazon\.(com|co\.uk|ca|de|fr|it|es|ie|in|co\.jp|com\.mx|com\.br|sg|com\.au|nl|com\.tr|ae|sa|se|pl|eg|be)\//i;
    if (!amazonDomainPattern.test(url)) {
        showValidationStatus('❌ Please use a valid Amazon product URL from a supported country.', 'error');
        if (analyzeButton) analyzeButton.disabled = true;
        return;
    }
    
    // Extract ASIN from URL - support multiple Amazon URL formats
    console.log('Processing Amazon URL for ASIN extraction:', url);
    let asinMatch = url.match(/\/dp\/([A-Z0-9]{10})/);
    if (!asinMatch) {
        asinMatch = url.match(/\/gp\/product\/([A-Z0-9]{10})/);
    }
    if (!asinMatch) {
        asinMatch = url.match(/\/exec\/obidos\/ASIN\/([A-Z0-9]{10})/);
    }
    if (!asinMatch) {
        asinMatch = url.match(/\/product\/([A-Z0-9]{10})/);
    }
    if (!asinMatch) {
        asinMatch = url.match(/\/product-reviews\/([A-Z0-9]{10})/);
    }
    
    if (!asinMatch) {
        showValidationStatus('❌ Invalid Amazon URL format. Please use a direct product page URL.', 'error');
        if (analyzeButton) analyzeButton.disabled = true;
        return;
    }
    
    const asin = asinMatch[1];
    lastValidatedUrl = url;
    
    // Show checking status
    showValidationStatus('🔍 Checking if product exists...', 'checking');
    if (analyzeButton) analyzeButton.disabled = true;
    
        // Validate immediately - no artificial delays needed
    isValidating = true;
    
    try {
        // Try multiple validation approaches
        console.log('Starting validation methods for ASIN:', asin);
        const validationStartTime = Date.now();
        let validationResult = await tryMultipleValidationMethods(asin);
        const validationDuration = Date.now() - validationStartTime;
        console.log('Validation result:', validationResult, 'Duration:', validationDuration + 'ms');
        
        // Ensure minimum display time for validation messages (especially for cached responses)
        const minDisplayTime = 1500; // 1.5 seconds minimum
        const remainingTime = Math.max(0, minDisplayTime - validationDuration);
        
        if (validationResult.success) {
            const message = validationResult.cached ? 
                '✅ Product verified on Amazon (cached)' : 
                '✅ Product verified on Amazon';
            showValidationStatus(message, 'success');
            // Add delay for cached responses that complete too quickly
            setTimeout(() => {
                if (analyzeButton) analyzeButton.disabled = false;
            }, remainingTime);
        } else if (validationResult.uncertain) {
            showValidationStatus('⚠️ Unable to verify - will check during analysis', 'warning');
            setTimeout(() => {
                if (analyzeButton) analyzeButton.disabled = false;
            }, remainingTime);
        } else {
            // Even if validation fails, allow submission since server might still work
            showValidationStatus('⚠️ Could not verify product - will check during analysis', 'warning');
            setTimeout(() => {
                if (analyzeButton) analyzeButton.disabled = false;
            }, remainingTime);
        }
        
    } catch (error) {
        console.warn('Validation error:', error);
        // Always allow submission - let server handle validation
        showValidationStatus('⚠️ Verification unavailable - will check during analysis', 'warning');
        if (analyzeButton) analyzeButton.disabled = false;
    }
    
    isValidating = false;
}

async function tryMultipleValidationMethods(asin) {
    const methods = [
        { name: 'Image Validation', fn: () => validateViaImageRequest(asin) },
        { name: 'Web Validation', fn: () => validateViaWebRequest(asin) }
    ];
    
    let hasAnyResponse = false;
    
    for (const method of methods) {
        try {
            console.log(`🔍 Trying ${method.name} for ASIN: ${asin}`);
            const result = await method.fn();
            hasAnyResponse = true;
            if (result.success) {
                const cacheInfo = result.cached ? ' (cached response)' : '';
                console.log(`✅ ${method.name} succeeded for ASIN: ${asin}${cacheInfo}`);
                return { ...result, method: method.name };
            }
        } catch (error) {
            console.log(`❌ ${method.name} failed for ASIN ${asin}:`, error.message);
            // If we get any response (even an error), it means network is working
            if (error.message.includes('404') || error.message.includes('405')) {
                hasAnyResponse = true;
            }
        }
    }
    
    // If we had network responses but no success, product might not exist
    // If we had no responses at all, it's uncertain (network/CORS issues)
    console.log(`🤷 All validation methods failed for ASIN: ${asin}, hasAnyResponse: ${hasAnyResponse}`);
    return { success: false, uncertain: !hasAnyResponse };
}



async function expandShortUrl(shortUrl) {
    console.log('Attempting to expand short URL via backend:', shortUrl);
    
    try {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('CSRF token not found');
        }
        
        const response = await fetch('/api/expand-url', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ url: shortUrl })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.expanded_url) {
            console.log('Backend successfully expanded URL:', data.expanded_url);
            return data.expanded_url;
        } else {
            throw new Error(data.error || 'Backend expansion failed');
        }
    } catch (error) {
        console.warn('Backend URL expansion failed:', error.message);
        throw error;
    }
}



async function validateViaImageRequest(asin) {
    // Try to load an Amazon product image - if it loads, product likely exists
    return new Promise((resolve, reject) => {
        const img = new Image();
        const startTime = Date.now();
        const minValidationTime = 500; // Minimum 500ms for better UX
        
        const timeout = setTimeout(() => {
            img.onload = null;
            img.onerror = null;
            reject(new Error('Image validation timeout'));
        }, 3000);
        
        img.onload = () => {
            clearTimeout(timeout);
            const elapsed = Date.now() - startTime;
            const remainingTime = Math.max(0, minValidationTime - elapsed);
            
            // Add minimum delay for cached responses
            setTimeout(() => {
                resolve({ success: true, cached: elapsed < 50 });
            }, remainingTime);
        };
        
        img.onerror = () => {
            clearTimeout(timeout);
            const elapsed = Date.now() - startTime;
            const remainingTime = Math.max(0, minValidationTime - elapsed);
            
            setTimeout(() => {
                reject(new Error('Image not found'));
            }, remainingTime);
        };
        
        // Try standard Amazon image URL
        img.src = `https://images-na.ssl-images-amazon.com/images/P/${asin}.01.L.jpg`;
        
        // Set crossorigin to avoid sending credentials
        img.crossOrigin = 'anonymous';
    });
}

async function validateViaWebRequest(asin) {
    // Try multiple approaches for web validation
    const approaches = [
        { name: 'Iframe Loading', fn: () => validateViaIframe(asin) },
        { name: 'Search Endpoint', fn: () => validateViaAlternateEndpoint(asin) }
    ];
    
    for (const approach of approaches) {
        try {
            console.log(`  🌐 Trying ${approach.name} for ASIN: ${asin}`);
            const result = await approach.fn();
            if (result.success) {
                console.log(`  ✅ ${approach.name} succeeded for ASIN: ${asin}`);
                return result;
            }
        } catch (error) {
            console.log(`  ❌ ${approach.name} failed for ASIN ${asin}:`, error.message);
        }
    }
    
    throw new Error('All web validation approaches failed');
}

async function validateViaIframe(asin) {
    return new Promise((resolve, reject) => {
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.style.width = '0';
        iframe.style.height = '0';
        
        const timeout = setTimeout(() => {
            document.body.removeChild(iframe);
            reject(new Error('Iframe validation timeout'));
        }, 4000);
        
        iframe.onload = () => {
            clearTimeout(timeout);
            document.body.removeChild(iframe);
            resolve({ success: true });
        };
        
        iframe.onerror = () => {
            clearTimeout(timeout);
            document.body.removeChild(iframe);
            reject(new Error('Iframe validation failed'));
        };
        
        // Use Amazon search results page which is more permissive
        iframe.src = `https://www.amazon.com/s?k=${asin}&ref=nb_sb_noss`;
        document.body.appendChild(iframe);
    });
}

async function validateViaAlternateEndpoint(asin) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 3000);
    
    try {
        // Try the search endpoint instead of direct product page
        const response = await fetch(`https://www.amazon.com/s?k=${asin}`, {
            method: 'GET',
            mode: 'no-cors',
            signal: controller.signal,
            credentials: 'omit',
            referrerPolicy: 'no-referrer',
            cache: 'no-store'
        });
        
        clearTimeout(timeoutId);
        return { success: true };
        
    } catch (error) {
        clearTimeout(timeoutId);
        throw error;
    }
}

function showValidationStatus(message, type) {
    const statusDiv = document.getElementById('url-validation-status');
    if (!statusDiv) return;
    
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;
    
    // Remove previous classes
    statusDiv.className = 'mt-2 text-sm';
    
    // Add appropriate styling
    switch (type) {
        case 'success':
            statusDiv.className += ' text-green-600';
            break;
        case 'error':
            statusDiv.className += ' text-red-600';
            break;
        case 'warning':
            statusDiv.className += ' text-yellow-600';
            break;
        case 'checking':
            statusDiv.className += ' text-brand';
            break;
    }
}

// Also validate on input change (with debouncing)
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('productUrl');
    const analyzeButton = document.getElementById('analyze-button');
    
    if (urlInput) {
        urlInput.addEventListener('input', function() {
            // Clear status immediately on input change
            const statusDiv = document.getElementById('url-validation-status');
            if (statusDiv) {
                statusDiv.style.display = 'none';
            }
            lastValidatedUrl = '';
            
            // Enable button while typing (will be disabled during validation if needed)
            if (analyzeButton && !isValidating) {
                analyzeButton.disabled = false;
            }
            
            // Debounce validation to avoid excessive requests while typing
            if (validationTimeout) {
                clearTimeout(validationTimeout);
            }
            validationTimeout = setTimeout(validateAmazonUrl, 1000);
        });
        
        // Also validate on paste
        urlInput.addEventListener('paste', function() {
            setTimeout(validateAmazonUrl, 200);
        });
    }
    
    // Listen for Livewire events
    if (typeof Livewire !== 'undefined') {
        Livewire.on('syncInputs', () => {
            const urlInput = document.getElementById('productUrl');
            if (urlInput && urlInput.value) {
                // Manually sync the input value to Livewire
                window.Livewire.find('{{ $this->getId() }}').call('setProductUrl', urlInput.value);
                console.log('Synced URL to Livewire:', urlInput.value);
            }
        });

        // Listen for async analysis start event from Livewire
        Livewire.on('startAsyncAnalysis', (data) => {
            console.log('Received startAsyncAnalysis event:', data);
            console.log('About to call startAsyncAnalysis function...');
            startAsyncAnalysis(data[0].productUrl, data[0].captchaData);
        });
    }
});
</script>

