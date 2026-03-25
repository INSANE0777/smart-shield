<?php

namespace Tests\Unit;

use App\Services\LoggingService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any previous log expectations
        Log::spy();
    }

    public function test_log_with_default_level()
    {
        LoggingService::log('Test message');

        Log::shouldHaveReceived('info')
           ->once()
           ->with('Test message', []);
    }

    public function test_log_with_custom_level()
    {
        LoggingService::log('Debug message', ['key' => 'value'], LoggingService::DEBUG);

        Log::shouldHaveReceived('debug')
           ->once()
           ->with('Debug message', ['key' => 'value']);
    }

    public function test_log_with_error_level()
    {
        LoggingService::log('Error message', ['error' => 'details'], LoggingService::ERROR);

        Log::shouldHaveReceived('error')
           ->once()
           ->with('Error message', ['error' => 'details']);
    }

    public function test_log_with_context()
    {
        $context = ['user_id' => 123, 'action' => 'test'];
        LoggingService::log('Action performed', $context);

        Log::shouldHaveReceived('info')
           ->once()
           ->with('Action performed', $context);
    }

    public function test_handle_exception_timeout_error()
    {
        $exception = new \Exception('cURL error 28: Operation timed out after 30 seconds');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('The review service took too long to respond. This can happen when Amazon is blocking requests or the service is overloaded. Please try again in a few minutes.', $result);

        Log::shouldHaveReceived('error')
           ->once()
           ->with('cURL error 28: Operation timed out after 30 seconds', \Mockery::type('array'));
    }

    public function test_handle_exception_product_not_found()
    {
        $exception = new \Exception('Product does not exist on Amazon.com (US) site');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('Product does not exist on Amazon.com (US) site. Please check the URL and try again.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_data_type_error()
    {
        $exception = new \Exception('count(): Argument #1 ($value) must be of type Countable|array');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('Data processing error occurred. Please try again.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_fetching_failed()
    {
        $exception = new \Exception('Failed to fetch reviews from Amazon');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('An unexpected error occurred. Please try again later.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_openai_error()
    {
        $exception = new \Exception('OpenAI API request failed with status 500');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('Analysis service is temporarily unavailable. Please try again later.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_invalid_url()
    {
        $exception = new \Exception('Could not extract ASIN from URL: invalid-url');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('Please provide a valid Amazon product URL.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_redirect_failed()
    {
        $exception = new \Exception('Failed to follow redirect: connection timeout');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('Unable to resolve the shortened URL. Please try using the full Amazon product URL instead.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_generic_error()
    {
        $exception = new \Exception('Some unknown error occurred');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('An unexpected error occurred. Please try again later.', $result);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_handle_exception_logs_trace()
    {
        $exception = new \Exception('Test exception');

        LoggingService::handleException($exception);

        Log::shouldHaveReceived('error')
           ->once()
           ->with('Test exception', \Mockery::on(function ($context) {
               return isset($context['trace']) && is_string($context['trace']);
           }));
    }

    public function test_log_progress()
    {
        LoggingService::logProgress('Step 1', 'Initializing process');

        Log::shouldHaveReceived('info')
           ->once()
           ->with('Progress: Step 1 - Initializing process', []);
    }

    public function test_log_progress_with_complex_message()
    {
        LoggingService::logProgress('Analysis Phase', 'Processing 50 reviews with OpenAI');

        Log::shouldHaveReceived('info')
           ->once()
           ->with('Progress: Analysis Phase - Processing 50 reviews with OpenAI', []);
    }

    public function test_error_types_constants()
    {
        $this->assertEquals('debug', LoggingService::DEBUG);
        $this->assertEquals('info', LoggingService::INFO);
        $this->assertEquals('error', LoggingService::ERROR);
    }

    public function test_error_types_array_structure()
    {
        $errorTypes = LoggingService::ERROR_TYPES;

        $this->assertIsArray($errorTypes);
        $this->assertArrayHasKey('API_TIMEOUT', $errorTypes);
        $this->assertArrayHasKey('UNWRANGLE_TIMEOUT', $errorTypes);
        $this->assertArrayHasKey('PRODUCT_NOT_FOUND', $errorTypes);
        $this->assertArrayHasKey('DATA_TYPE_ERROR', $errorTypes);
        $this->assertArrayHasKey('FETCHING_FAILED', $errorTypes);
        $this->assertArrayHasKey('OPENAI_ERROR', $errorTypes);
        $this->assertArrayHasKey('INVALID_URL', $errorTypes);
        $this->assertArrayHasKey('REDIRECT_FAILED', $errorTypes);

        // Check structure of each error type
        foreach ($errorTypes as $type) {
            $this->assertArrayHasKey('patterns', $type);
            $this->assertArrayHasKey('message', $type);
            $this->assertIsArray($type['patterns']);
            $this->assertTrue(
                is_string($type['message']) || is_null($type['message']),
                'Message should be string or null for validation/captcha errors'
            );
        }
    }

    public function test_multiple_pattern_matching()
    {
        // Test that the first matching pattern is used
        $exception = new \Exception('Operation timed out and cURL error 28 occurred');

        $result = LoggingService::handleException($exception);

        $this->assertEquals('The review service took too long to respond. This can happen when Amazon is blocking requests or the service is overloaded. Please try again in a few minutes.', $result);
    }

    public function test_case_sensitive_pattern_matching()
    {
        $exception = new \Exception('cURL error 28: timed out after 30000 milliseconds');
        $result = LoggingService::handleException($exception);

        $this->assertStringContainsString('took too long to respond', $result);
    }

    public function test_handle_exception_proxy_auth_error()
    {
        $exception = new \Exception('cURL error 56: Received HTTP code 407 from proxy after CONNECT (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://www.amazon.com/dp/B00076SPZO');
        $result = LoggingService::handleException($exception);

        $this->assertEquals('The review service is temporarily unavailable due to network configuration issues. Please try again in a few minutes.', $result);
    }

    public function test_handle_exception_proxy_auth_error_generic()
    {
        $exception = new \Exception('proxy authentication failed');
        $result = LoggingService::handleException($exception);

        $this->assertEquals('The review service is temporarily unavailable due to network configuration issues. Please try again in a few minutes.', $result);
    }
}
