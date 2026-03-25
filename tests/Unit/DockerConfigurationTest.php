<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Docker Configuration Validation Tests.
 *
 * These tests validate Docker configuration files without requiring Docker to be running.
 * They ensure that all configuration files are properly structured and contain required settings.
 */
class DockerConfigurationTest extends TestCase
{
    #[Test]
    public function docker_compose_file_exists_and_contains_required_sections()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');

        $this->assertFileExists($dockerComposePath);

        $content = file_get_contents($dockerComposePath);

        // Check for essential YAML structure without parsing
        $this->assertStringContainsString('services:', $content);
        $this->assertStringContainsString('networks:', $content);
        $this->assertStringContainsString('volumes:', $content);
        $this->assertStringContainsString('version:', $content);
    }

    #[Test]
    public function docker_compose_contains_required_services()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        $requiredServices = ['app:', 'nginx:', 'db:', 'queue:', 'ollama:'];

        foreach ($requiredServices as $service) {
            $this->assertStringContainsString($service, $content, "Missing required service: {$service}");
        }
    }

    #[Test]
    public function docker_compose_services_have_required_configuration()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        // App service validation
        $this->assertStringContainsString('build:', $content);
        $this->assertStringContainsString('volumes:', $content);
        $this->assertStringContainsString('networks:', $content);
        $this->assertStringContainsString('depends_on:', $content);

        // Database service validation
        $this->assertStringContainsString('mariadb:10.11', $content);
        $this->assertStringContainsString('MARIADB_DATABASE:', $content);

        // Nginx service validation
        $this->assertStringContainsString('nginx:alpine', $content);
        $this->assertStringContainsString('ports:', $content);
    }

    #[Test]
    public function dockerfile_exists_and_contains_required_instructions()
    {
        $dockerfilePath = base_path('docker/Dockerfile');

        $this->assertFileExists($dockerfilePath);

        $content = file_get_contents($dockerfilePath);

        // Check for essential Dockerfile instructions
        $this->assertStringContainsString('FROM php:8.3-fpm', $content);
        $this->assertStringContainsString('WORKDIR /var/www/html', $content);
        $this->assertStringContainsString('RUN apt-get update', $content);
        $this->assertStringContainsString('docker-php-ext-install', $content);
        $this->assertStringContainsString('COPY --from=composer', $content);
        $this->assertStringContainsString('composer install', $content);
        $this->assertStringContainsString('npm ci && npm run build', $content);
        $this->assertStringContainsString('ENTRYPOINT', $content);
    }

    #[Test]
    public function entrypoint_script_exists_and_is_properly_structured()
    {
        $entrypointPath = base_path('docker/entrypoint.sh');

        $this->assertFileExists($entrypointPath);

        $content = file_get_contents($entrypointPath);

        // Check for essential entrypoint functionality
        $this->assertStringContainsString('#!/bin/bash', $content);
        $this->assertStringContainsString('set -e', $content);
        $this->assertStringContainsString('mkdir -p storage', $content);
        $this->assertStringContainsString('chown -R www-data:www-data', $content);
        $this->assertStringContainsString('php artisan key:generate', $content);
        $this->assertStringContainsString('php artisan migrate', $content);
        $this->assertStringContainsString('exec "$@"', $content);
    }

    #[Test]
    public function nginx_configuration_is_properly_structured()
    {
        $nginxConfigPath = base_path('docker/nginx/default.conf');

        $this->assertFileExists($nginxConfigPath);

        $content = file_get_contents($nginxConfigPath);

        // Check for essential nginx configuration
        $this->assertStringContainsString('server {', $content);
        $this->assertStringContainsString('listen 80;', $content);
        $this->assertStringContainsString('root /var/www/html/public;', $content);
        $this->assertStringContainsString('index index.php', $content);
        $this->assertStringContainsString('location ~ \.php$', $content);
        $this->assertStringContainsString('fastcgi_pass app:9000;', $content);
        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $content);
    }

    #[Test]
    public function php_configuration_has_appropriate_settings()
    {
        $phpConfigPath = base_path('docker/php/local.ini');

        $this->assertFileExists($phpConfigPath);

        $content = file_get_contents($phpConfigPath);

        // Check for essential PHP settings for Laravel
        $this->assertStringContainsString('memory_limit=512M', $content);
        $this->assertStringContainsString('upload_max_filesize=100M', $content);
        $this->assertStringContainsString('post_max_size=100M', $content);
        $this->assertStringContainsString('max_execution_time=300', $content);
        $this->assertStringContainsString('opcache.enable=1', $content);
    }

    #[Test]
    public function env_example_contains_all_required_variables_for_docker()
    {
        $envExamplePath = base_path('.env.example');

        $this->assertFileExists($envExamplePath);

        $content = file_get_contents($envExamplePath);

        // Essential variables for Docker deployment
        $requiredVars = [
            'APP_NAME=',
            'APP_ENV=',
            'APP_KEY=',
            'APP_DEBUG=',
            'APP_URL=',
            'DB_CONNECTION=',
            'DB_HOST=',
            'DB_PORT=',
            'DB_DATABASE=',
            'DB_USERNAME=',
            'DB_PASSWORD=',
            'QUEUE_CONNECTION=',
            'ANALYSIS_ASYNC_ENABLED=',
            'LLM_PRIMARY_PROVIDER=',
            'OPENAI_API_KEY=',
            'OLLAMA_BASE_URL=',
            'AMAZON_REVIEW_SERVICE=',
        ];

        foreach ($requiredVars as $var) {
            $this->assertStringContainsString($var, $content, "Missing required environment variable: {$var}");
        }
    }

    #[Test]
    public function docker_test_script_exists_and_has_proper_structure()
    {
        $testScriptPath = base_path('docker/test-docker.sh');

        $this->assertFileExists($testScriptPath);

        $content = file_get_contents($testScriptPath);

        // Check for essential test script functionality
        $this->assertStringContainsString('#!/bin/bash', $content);
        $this->assertStringContainsString('set -e', $content);
        $this->assertStringContainsString('docker info', $content);
        $this->assertStringContainsString('docker-compose', $content);
        $this->assertStringContainsString('curl', $content);
    }

    // Docker README test removed - documentation files deleted per .cursorrules policy

    #[Test]
    public function docker_volumes_are_properly_defined()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        $requiredVolumes = ['db_data:', 'ollama_data:', 'vendor_data:', 'node_modules_data:'];

        $this->assertStringContainsString('volumes:', $content);

        foreach ($requiredVolumes as $volume) {
            $this->assertStringContainsString($volume, $content, "Missing required volume: {$volume}");
        }
    }

    #[Test]
    public function docker_networks_are_properly_configured()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        $this->assertStringContainsString('networks:', $content);
        $this->assertStringContainsString('nullfake:', $content);
        $this->assertStringContainsString('driver: bridge', $content);
    }

    #[Test]
    public function docker_ports_are_properly_exposed()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        // Check nginx ports
        $this->assertStringContainsString('8080:80', $content);

        // Check database ports
        $this->assertStringContainsString('3307:3306', $content);

        // Check Ollama ports
        $this->assertStringContainsString('11434:11434', $content);
    }

    #[Test]
    public function docker_environment_variables_are_properly_set()
    {
        $dockerComposePath = base_path('docker/docker-compose.yml');
        $content = file_get_contents($dockerComposePath);

        // Check app environment variables
        $this->assertStringContainsString('DB_HOST=db', $content);
        $this->assertStringContainsString('OLLAMA_BASE_URL=http://ollama:11434', $content);

        // Check database environment variables
        $this->assertStringContainsString('MARIADB_DATABASE: faker', $content);
        $this->assertStringContainsString('MARIADB_USER: faker', $content);
        $this->assertStringContainsString('MARIADB_PASSWORD: password', $content);
    }
}
