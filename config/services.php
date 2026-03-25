<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'rapidapi' => [
        'key'  => env('RAPIDAPI_KEY'),
        'host' => env('RAPIDAPI_HOST', 'amazon23.p.rapidapi.com'),
    ],

    'openai' => [
        'api_key'            => env('OPENAI_API_KEY'),
        'base_url'           => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model'              => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout'            => env('OPENAI_TIMEOUT', 120),
        'parallel_threshold' => env('OPENAI_PARALLEL_THRESHOLD', 50),
        'chunk_size'         => env('OPENAI_CHUNK_SIZE', 25),
    ],

    'deepseek' => [
        'api_key'  => env('DEEPSEEK_API_KEY', ''),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
        'model'    => env('DEEPSEEK_MODEL', 'deepseek-v3'),
        'timeout'  => env('DEEPSEEK_TIMEOUT', 120),
    ],

    'groq' => [
        'api_key'  => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout'  => env('GROQ_TIMEOUT', 60),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model'    => env('OLLAMA_MODEL', 'phi4:14b'),
        'timeout'  => env('OLLAMA_TIMEOUT', 120),
        // Adaptive chunking for large review sets (transparent to API consumers)
        'chunking_threshold' => env('OLLAMA_CHUNKING_THRESHOLD', 80), // Reviews count that triggers chunking
        'chunk_size'         => env('OLLAMA_CHUNK_SIZE', 25), // Reviews per chunk
    ],

    'llm' => [
        'primary_provider' => env('LLM_PRIMARY_PROVIDER', 'openai'),
        'fallback_order'   => ['groq', 'ollama', 'deepseek', 'openai'],
        'auto_fallback'    => env('LLM_AUTO_FALLBACK', true),
        'cost_tracking'    => env('LLM_COST_TRACKING', true),
    ],

    'mailtrain' => [
        'base_url'  => env('MAILTRAIN_BASE_URL'),
        'api_token' => env('MAILTRAIN_API_TOKEN'),
        'list_id'   => env('MAILTRAIN_LIST_ID'),
        'timeout'   => env('MAILTRAIN_TIMEOUT', 30),
    ],

    'extension' => [
        'api_key'         => env('EXTENSION_API_KEY'),
        'require_api_key' => env('EXTENSION_REQUIRE_API_KEY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Note: Configuration Consolidation
    |--------------------------------------------------------------------------
    |
    | Amazon-related settings moved to config/amazon.php
    | Monitoring/alerting settings moved to config/monitoring.php
    | Analysis settings remain in config/analysis.php
    |
    */

];
