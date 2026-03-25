<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Docker Deployment Integration Tests.
 *
 * These tests validate that the Docker setup works correctly:
 * - Environment configuration
 * - Service connectivity
 * - Container health
 * - Application functionality in Docker context
 *
 * Note: These tests require Docker to be running and are marked as integration tests.
 * Run with: php artisan test --group=docker
 */
class DockerDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip Docker tests if Docker is not available
        if (!$this->isDockerAvailable()) {
            $this->markTestSkipped('Docker is not available');
        }
    }

    #[Test]
    public function docker_compose_configuration_is_valid()
    {
        $result = Process::run('docker-compose -f docker/docker-compose.yml config');

        $this->assertTrue($result->successful(), 'Docker Compose configuration should be valid');
        $this->assertStringContains('services:', $result->output());
        $this->assertStringContains('nullfake-app', $result->output());
        $this->assertStringContains('nullfake-db', $result->output());
        $this->assertStringContains('nullfake-nginx', $result->output());
    }

    #[Test]
    public function env_example_contains_required_docker_variables()
    {
        $envExample = file_get_contents(base_path('.env.example'));

        // Critical Docker-related variables
        $requiredVars = [
            'DB_HOST=',
            'DB_DATABASE=',
            'QUEUE_CONNECTION=',
            'ANALYSIS_ASYNC_ENABLED=',
            'LLM_PRIMARY_PROVIDER=',
            'OLLAMA_BASE_URL=',
            'OPENAI_API_KEY=',
            'AMAZON_REVIEW_SERVICE=',
        ];

        foreach ($requiredVars as $var) {
            $this->assertStringContains($var, $envExample, "Missing required variable: {$var}");
        }
    }

    #[Test]
    public function dockerfile_builds_successfully()
    {
        // Test that Dockerfile can build without errors
        $result = Process::timeout(300)->run('docker build -f docker/Dockerfile -t nullfake-test .');

        $this->assertTrue($result->successful(), 'Dockerfile should build successfully');

        // Cleanup test image
        Process::run('docker rmi nullfake-test');
    }

    #[Test]
    public function docker_entrypoint_script_is_executable()
    {
        $entrypointPath = base_path('docker/entrypoint.sh');

        $this->assertFileExists($entrypointPath);
        $this->assertTrue(is_executable($entrypointPath), 'Entrypoint script should be executable');
    }

    #[Test]
    public function nginx_configuration_is_valid()
    {
        $nginxConfig = file_get_contents(base_path('docker/nginx/default.conf'));

        // Check for essential nginx directives
        $this->assertStringContains('server {', $nginxConfig);
        $this->assertStringContains('listen 80;', $nginxConfig);
        $this->assertStringContains('root /var/www/html/public;', $nginxConfig);
        $this->assertStringContains('fastcgi_pass app:9000;', $nginxConfig);
        $this->assertStringContains('index.php', $nginxConfig);
    }

    #[Test]
    public function php_configuration_has_required_settings()
    {
        $phpConfig = file_get_contents(base_path('docker/php/local.ini'));

        // Check for essential PHP settings
        $this->assertStringContains('memory_limit=512M', $phpConfig);
        $this->assertStringContains('upload_max_filesize=100M', $phpConfig);
        $this->assertStringContains('post_max_size=100M', $phpConfig);
        $this->assertStringContains('max_execution_time=300', $phpConfig);
    }

    /**
     * Integration test that starts containers and validates functionality
     * This is a comprehensive test that requires Docker to be running.
     *
     * @group docker
     * @group slow
     */
    #[Test]
    public function full_docker_stack_starts_and_responds()
    {
        if (!$this->isDockerAvailable()) {
            $this->markTestSkipped('Docker integration test requires Docker to be running');
        }

        // Ensure we have a .env file for testing
        if (!file_exists(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }

        try {
            // Start containers
            $startResult = Process::timeout(120)->run('docker-compose -f docker/docker-compose.yml up -d');
            $this->assertTrue($startResult->successful(), 'Docker containers should start successfully');

            // Wait for services to be ready
            sleep(30);

            // Test database connectivity
            $dbTest = Process::run('docker-compose -f docker/docker-compose.yml exec -T db mysql -u faker -ppassword -e "SELECT 1;"');
            $this->assertTrue($dbTest->successful(), 'Database should be accessible');

            // Test web server response
            $webTest = Process::run('curl -s -o /dev/null -w "%{http_code}" http://localhost:8080');
            $this->assertEquals('200', trim($webTest->output()), 'Web server should respond with HTTP 200');

            // Test Ollama service
            $ollamaTest = Process::run('curl -s http://localhost:11434/api/tags');
            $this->assertTrue($ollamaTest->successful(), 'Ollama service should be accessible');
        } finally {
            // Cleanup: Stop containers
            Process::run('docker-compose -f docker/docker-compose.yml down');
        }
    }

    /**
     * Test that validates environment variable loading in Docker context.
     *
     * @group docker
     */
    #[Test]
    public function docker_environment_variables_are_loaded_correctly()
    {
        if (!$this->isDockerAvailable()) {
            $this->markTestSkipped('Docker integration test requires Docker to be running');
        }

        // Create test .env file
        $testEnv = base_path('.env.docker.test');
        file_put_contents($testEnv, '
APP_NAME=DockerTest
DB_HOST=db
OLLAMA_BASE_URL=http://ollama:11434
LLM_PRIMARY_PROVIDER=ollama
QUEUE_CONNECTION=database
ANALYSIS_ASYNC_ENABLED=true
');

        try {
            // Start containers with test env
            $result = Process::run("docker-compose -f docker/docker-compose.yml --env-file {$testEnv} config");

            $this->assertTrue($result->successful());
            $this->assertStringContains('DB_HOST: db', $result->output());
            $this->assertStringContains('OLLAMA_BASE_URL: http://ollama:11434', $result->output());
        } finally {
            // Cleanup
            if (file_exists($testEnv)) {
                unlink($testEnv);
            }
        }
    }

    #[Test]
    public function docker_volumes_are_properly_configured()
    {
        $result = Process::run('docker-compose -f docker/docker-compose.yml config');

        $this->assertTrue($result->successful());

        // Check that essential volumes are configured
        $output = $result->output();
        $this->assertStringContains('vendor_data:', $output);
        $this->assertStringContains('node_modules_data:', $output);
        $this->assertStringContains('db_data:', $output);
        $this->assertStringContains('ollama_data:', $output);
    }

    #[Test]
    public function docker_networks_are_properly_configured()
    {
        $result = Process::run('docker-compose -f docker/docker-compose.yml config');

        $this->assertTrue($result->successful());

        $output = $result->output();
        $this->assertStringContains('networks:', $output);
        $this->assertStringContains('nullfake:', $output);
    }

    /**
     * Test the Docker test script itself.
     */
    #[Test]
    public function docker_test_script_is_executable_and_valid()
    {
        $testScript = base_path('docker/test-docker.sh');

        $this->assertFileExists($testScript);
        $this->assertTrue(is_executable($testScript), 'Docker test script should be executable');

        // Check script contains essential tests
        $scriptContent = file_get_contents($testScript);
        $this->assertStringContains('docker info', $scriptContent);
        $this->assertStringContains('docker-compose', $scriptContent);
        $this->assertStringContains('curl', $scriptContent);
    }

    /**
     * Check if Docker is available for testing.
     */
    private function isDockerAvailable(): bool
    {
        $result = Process::run('docker info');

        return $result->successful();
    }

    /**
     * Test that required Docker images are available or can be pulled.
     *
     * @group docker
     * @group slow
     */
    #[Test]
    public function required_docker_images_are_available()
    {
        if (!$this->isDockerAvailable()) {
            $this->markTestSkipped('Docker is not available');
        }

        $requiredImages = [
            'php:8.3-fpm',
            'nginx:alpine',
            'mariadb:10.11',
            'ollama/ollama:latest',
        ];

        foreach ($requiredImages as $image) {
            $result = Process::timeout(60)->run("docker pull {$image}");
            $this->assertTrue($result->successful(), "Should be able to pull required image: {$image}");
        }
    }
}
