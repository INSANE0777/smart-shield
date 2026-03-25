<div align="center">
  <img src="public/img/nullfake.svg" alt="Null Fake Logo" width="200">
</div>

# SMART SHIELD UI

A Laravel application that analyzes Amazon product reviews to detect fake reviews using AI. Supports **Amazon products from 14+ countries** including US, Canada, Germany, France, UK, Japan, Mexico, Brazil, India, Singapore, and more. The service includes multiple data collection methods with BrightData's managed web scraping, direct Amazon scraping, and comprehensive AI analysis with multi-provider support.

Visit [nullfake.com](https://nullfake.com) to try it out.

Read our [blog post about how nullfake works](https://shift8web.ca/from-fakespot-to-null-fake-navigating-the-evolving-landscape-of-fake-reviews/)

## Table of Contents

- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Environment Setup](#environment-setup)
  - [Database Setup](#database-setup)
  - [Running the Application](#running-the-application)
    - [Traditional Setup](#option-1-traditional-setup-recommended-for-development)
    - [Docker Setup](#option-2-docker-setup-optional)
- [How It Works](#how-it-works)
- [Supported Countries](#supported-countries)
- [Features](#features)
  - [Review Collection](#review-collection)
  - [AI Analysis](#ai-analysis)
  - [User Experience](#user-experience)
  - [Infrastructure](#infrastructure)
- [Data Collection Methods](#data-collection-methods)
  - [BrightData Web Scraper](#brightdata-web-scraper-recommended)
  - [Direct Amazon Scraping](#direct-amazon-scraping)
  - [AJAX Bypass](#ajax-bypass-experimental)
- [Chrome Extension API](#chrome-extension-api)
  - [API Endpoints](#api-endpoints)
  - [Configuration](#configuration)
  - [Benefits of Extension Integration](#benefits-of-extension-integration)
- [Database Schema](#database-schema)
- [Technology Stack](#technology-stack)
- [Docker Deployment](#docker-deployment)
  - [Docker vs Traditional Setup](#docker-vs-traditional-setup)
  - [Docker Quick Reference](#docker-quick-reference)
  - [Docker Environment Configuration](#docker-environment-configuration)
  - [Docker Services Architecture](#docker-services-architecture)
- [Configuration](#configuration)
  - [LLM Provider Setup](#llm-provider-setup)
  - [Asynchronous Processing](#asynchronous-processing)
- [Management Commands](#management-commands)
  - [LLM Management](#llm-management)
  - [Data Processing](#data-processing)
  - [Price Analysis](#price-analysis)
  - [Session Management](#session-management)
  - [Queue Processing](#queue-processing)
- [Usage](#usage)
- [Development](#development)
  - [Testing](#testing)
  - [Code Style](#code-style)
- [License](#license)
- [Shift8](#shift8)

## Getting Started

### Prerequisites

Before setting up Null Fake, ensure you have the following installed:

- **PHP 8.2 or higher** with the following extensions:
  - BCMath
  - Ctype
  - cURL
  - DOM
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PCRE
  - PDO
  - Tokenizer
  - XML
- **Composer** (for PHP dependency management)
- **Node.js 18+ and npm** (for frontend asset compilation)
- **MySQL 8.0+** or **PostgreSQL 13+**
- **Redis** (optional, for caching and queue management)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/stardothosting/nullfake.git
   cd nullfake
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

4. **Build frontend assets:**
   ```bash
   npm run build
   ```

### Environment Setup

1. **Copy the environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

3. **Configure your `.env` file with the following essential settings:**

   ```bash
   # Application
   APP_NAME="Null Fake"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   # Database
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nullfake
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password

   # Queue Configuration
   QUEUE_CONNECTION=database
   ANALYSIS_ASYNC_ENABLED=true

   # Choose your LLM provider (required for analysis)
   # Option 1: OpenAI
   LLM_PRIMARY_PROVIDER=openai
   OPENAI_API_KEY=sk-proj-your-openai-key-here
   OPENAI_MODEL=gpt-4o-mini

   # Option 2: DeepSeek (cost-effective)
   # LLM_PRIMARY_PROVIDER=deepseek
   # DEEPSEEK_API_KEY=sk-your-deepseek-key-here
   # DEEPSEEK_MODEL=deepseek-v3

   # Option 3: Self-hosted Ollama (free)
   # LLM_PRIMARY_PROVIDER=ollama
   # OLLAMA_BASE_URL=http://localhost:11434
   # OLLAMA_MODEL=qwen2.5:7b

   # Amazon Review Service (choose one)
   AMAZON_REVIEW_SERVICE=brightdata  # or 'scraping' or 'ajax'
   
   # If using BrightData
   BRIGHTDATA_SCRAPER_API=your-brightdata-api-key

   # If using direct scraping (add multiple sessions for rotation)
   # AMAZON_COOKIES_1=your-session-cookies-here
   # AMAZON_COOKIES_2=your-session-cookies-here

   # Captcha (for production)
   CAPTCHA_ENABLED=false  # Set to true in production
   # RECAPTCHA_SITE_KEY=your-recaptcha-site-key
   # RECAPTCHA_SECRET_KEY=your-recaptcha-secret-key

   # Amazon Affiliate Links (optional)
   AMAZON_AFFILIATE_ENABLED=true  # Set to false to disable affiliate links
   # AMAZON_AFFILIATE_TAG=your-affiliate-tag

   # Chrome Extension API (optional)
   # EXTENSION_API_KEY=your-extension-api-key  # Not required for local/testing
   ```

### Database Setup

1. **Create your database:**
   ```bash
   # For MySQL
   mysql -u root -p -e "CREATE DATABASE nullfake CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # For PostgreSQL
   createdb nullfake
   ```

2. **Run migrations:**
   ```bash
   php artisan migrate
   ```

3. **Seed the database (optional):**
   ```bash
   php artisan db:seed
   ```

### Running the Application

#### Option 1: Traditional Setup (Recommended for Development)

1. **Start the Laravel development server:**
   ```bash
   php artisan serve
   ```
   The application will be available at `http://localhost:8000`

2. **Start the queue worker (in a separate terminal):**
   ```bash
   php artisan queue:work --queue=analysis
   ```

3. **For development with hot reloading (optional, in a separate terminal):**
   ```bash
   npm run dev
   ```

#### Option 2: Docker Setup (Optional)

Docker provides an easy way to run NullFake with all dependencies included. This is perfect if you're not familiar with Laravel or want a quick setup.

**Prerequisites:**
- Docker and Docker Compose installed
- No other services running on ports 8080, 3307, or 11434

**Quick Start:**
```bash
# 1. Copy and configure environment
cp docker.env.example .env

# 2. Edit .env and configure at least one LLM provider:
# For OpenAI: Add your OPENAI_API_KEY
# For local AI: Use OLLAMA (no API key needed)

# 3. Start all services (automatic initialization included)
docker-compose -f docker/docker-compose.yml up -d

# 4. Optional: Install local AI model
docker-compose -f docker/docker-compose.yml exec ollama ollama pull phi4:14b
```

**Automatic Setup:**
The Docker containers now automatically handle:
- Laravel app key generation
- Database migrations  
- Permissions setup
- Directory creation

**Access the Application:**
- **Web Interface**: http://localhost:8080
- **Database**: localhost:3307 (user: faker, password: password)
- **Ollama API**: http://localhost:11434

**Docker Services Included:**
- **PHP 8.3-FPM**: Laravel application
- **Nginx**: Web server
- **MariaDB**: Database
- **Queue Worker**: Background job processing
- **Ollama**: Local AI model server (optional)

For detailed Docker documentation, troubleshooting, and advanced configuration, see the [Docker Setup Guide](docker/README.md).

### Quick Test

**Traditional Setup:**
1. Visit `http://localhost:8000`
2. Enter an Amazon product URL (e.g., `https://amazon.com/dp/B08N5WRWNW`)
3. Complete the captcha if enabled
4. Watch the analysis process in real-time

**Docker Setup:**
1. Visit `http://localhost:8080`
2. Enter an Amazon product URL (e.g., `https://amazon.com/dp/B08N5WRWNW`)
3. Complete the captcha if enabled
4. Watch the analysis process in real-time

### LLM Provider Setup

Choose one of the following AI providers for review analysis:

#### Ollama (Free, Self-hosted)
Perfect for development and cost-conscious deployments:

1. **Install Ollama:**
   ```bash
   curl -fsSL https://ollama.com/install.sh | sh
   ```

2. **Pull a model:**
   ```bash
   # Lightweight model (3B parameters)
   ollama pull llama3.2:3b
   
   # Better quality model (7B parameters) 
   ollama pull qwen2.5:7b
   ```

3. **Configure in `.env`:**
   ```bash
   LLM_PRIMARY_PROVIDER=ollama
   OLLAMA_BASE_URL=http://localhost:11434
   OLLAMA_MODEL=qwen2.5:7b
   ```

#### OpenAI (Default)
Most reliable, requires API key:

```bash
LLM_PRIMARY_PROVIDER=openai
OPENAI_API_KEY=sk-proj-your-openai-key-here
OPENAI_MODEL=gpt-4o-mini
```

#### DeepSeek (Cost-effective)
94% cheaper than OpenAI with comparable quality:

```bash
LLM_PRIMARY_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-deepseek-key-here
DEEPSEEK_MODEL=deepseek-v3
```

## Docker Deployment

For users who prefer containerized deployment or want to avoid setting up PHP/MySQL locally, NullFake includes a complete Docker setup.

### Docker vs Traditional Setup

| Aspect | Traditional Setup | Docker Setup |
|--------|------------------|--------------|
| **Setup Time** | 15-30 minutes | 5-10 minutes |
| **Prerequisites** | PHP 8.2+, MySQL, Node.js, Composer | Docker only |
| **Best For** | Development, customization | Quick testing, production |
| **Performance** | Native performance | Near-native (containerized) |
| **Isolation** | Uses system resources | Fully isolated environment |

### Docker Quick Reference

```bash
# Start services
docker-compose -f docker/docker-compose.yml up -d

# View logs
docker-compose -f docker/docker-compose.yml logs -f

# Access application container
docker-compose -f docker/docker-compose.yml exec app bash

# Run Laravel commands
docker-compose -f docker/docker-compose.yml exec app php artisan [command]

# Stop services
docker-compose -f docker/docker-compose.yml down

# Stop and remove data (WARNING: destroys database)
docker-compose -f docker/docker-compose.yml down -v
```

### Docker Environment Configuration

The Docker setup uses a template-based configuration approach:

1. **Safe defaults** in `docker.env.example` (committed to git)
2. **User customization** in `.env` (not committed to git)
3. **External secrets** provided by users (API keys, etc.)

**Required Configuration:**
```bash
# Copy template
cp docker.env.example .env

# Edit .env and add at least one LLM provider:
OPENAI_API_KEY=sk-proj-your-key-here  # OR
LLM_PRIMARY_PROVIDER=ollama            # (free, local AI)
```

### Docker Services Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     Nginx       │    │   Laravel App   │    │    MariaDB      │
│   (Port 8080)   │───▶│  (PHP 8.3-FPM) │───▶│  (Port 3307)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                              ▼
┌─────────────────┐    ┌─────────────────┐
│  Queue Worker   │    │     Ollama      │
│ (Background)    │    │  (Port 11434)   │
└─────────────────┘    └─────────────────┘
```

### Troubleshooting

**Common Issues:**

1. **"Class not found" errors:** Run `composer dump-autoload`
2. **Permission errors:** Ensure `storage/` and `bootstrap/cache/` are writable
3. **Queue jobs not processing:** Make sure `php artisan queue:work` is running
4. **LLM provider errors:** Test your provider with `php artisan llm:manage test`
5. **Amazon scraping fails:** Check your cookies/API keys and test with `php artisan test:amazon-scraping`

**Docker-Specific Issues:**

6. **Port conflicts:** Change ports in `docker/docker-compose.yml` if 8080, 3307, or 11434 are in use
7. **500 errors:** Check that you've configured at least one LLM provider in `.env`
8. **Database connection fails:** Ensure `DB_HOST=db` in your `.env` file
9. **Containers won't start:** Run `docker-compose -f docker/docker-compose.yml logs` to see error details

**Logs and Debugging:**
- Application logs: `storage/logs/laravel.log`
- Queue jobs: `php artisan queue:failed` to see failed jobs
- Debug mode: Set `APP_DEBUG=true` in `.env`
- Docker logs: `docker-compose -f docker/docker-compose.yml logs [service]`

## How It Works

1. User submits an Amazon product URL from any supported country and completes a captcha
2. Null Fake automatically detects the country and retrieves the ASIN from the URL
3. Database check: If a review analysis for the given ASIN and country exists in the database (and is less than 30 days old), the cached analysis is returned instantly
4. If fresh data is needed, the service fetches reviews using the configured data collection method (BrightData, direct scraping, or AJAX bypass)
5. Reviews are analyzed using AI with configurable thresholds for fake review detection
6. Results are displayed including fake review percentage, grade, explanation, and ratings

## Supported Countries

Null Fake supports Amazon product analysis from the following countries:

| Country | Amazon Domain | Status |
|---------|---------------|--------|
| 🇺🇸 United States | amazon.com | ✅ Full Support |
| 🇨🇦 Canada | amazon.ca | ✅ Full Support |
| 🇩🇪 Germany | amazon.de | ✅ Full Support |
| 🇫🇷 France | amazon.fr | ✅ Full Support |
| 🇬🇧 United Kingdom | amazon.co.uk | ✅ Full Support |
| 🇮🇹 Italy | amazon.it | ✅ Full Support |
| 🇪🇸 Spain | amazon.es | ✅ Full Support |
| 🇯🇵 Japan | amazon.co.jp | ✅ Full Support |
| 🇦🇺 Australia | amazon.com.au | ✅ Full Support |
| 🇮🇳 India | amazon.in | ✅ Full Support |
| 🇲🇽 Mexico | amazon.com.mx | ✅ Full Support |
| 🇧🇷 Brazil | amazon.com.br | ✅ Full Support |
| 🇸🇬 Singapore | amazon.sg | ✅ Full Support |
| 🇳🇱 Netherlands | amazon.nl | ✅ Full Support |

**Additional domains supported**: Turkey, UAE, Saudi Arabia, Sweden, Poland, Egypt, Belgium

> **Note**: All countries use the same sophisticated AI analysis and data collection methods. Product metadata, review extraction, and fake review detection work consistently across all supported Amazon domains.

## Features

### Review Collection
- **BrightData Integration**: Professional web scraping with managed infrastructure and anti-bot protection
- **Direct Amazon Scraping**: Custom scraping with proxy support and session management
- **AJAX Bypass**: Alternative method using Amazon's internal endpoints
- **Multi-session Management**: Cookie rotation across multiple Amazon sessions for reliability
- **Rate Limiting**: Configurable delays and request throttling

### AI Analysis
- **Multi-provider Support**: OpenAI, DeepSeek, or self-hosted Ollama
- **Configurable Thresholds**: Adjustable fake review detection sensitivity (default: 85+ score)
- **Comprehensive Scoring**: Heuristic analysis combined with LLM evaluation
- **Grade System**: Letter grades (A-F) with detailed explanations
- **Price Analysis**: AI-powered price assessment including MSRP comparison, market positioning, and deal indicators

### User Experience
- **Real-time Progress**: Job-based processing with live progress updates
- **Asynchronous Processing**: Queue-based analysis for better performance
- **Captcha Protection**: reCAPTCHA and hCaptcha support with session persistence
- **Product Metadata**: Title, image, and description extraction for complete product pages
- **Shareable URLs**: SEO-optimized product analysis pages
- **Chrome Extension API**: RESTful API endpoints for browser extension integration

### Infrastructure
- **Database Caching**: Fast repeat lookups with 30-day cache validity
- **Comprehensive Alerting**: Pushover notifications for API errors and system issues
- **Command Line Tools**: Management commands for data processing and system maintenance
- **Test Coverage**: Extensive test suite with 660+ tests covering all major functionality

## Data Collection Methods

The application supports three primary methods for collecting Amazon review data:

### BrightData Web Scraper (Recommended)

Professional managed scraping service with enterprise-grade infrastructure:

```bash
AMAZON_REVIEW_SERVICE=brightdata
BRIGHTDATA_SCRAPER_API=your-api-key-here
```

Benefits:
- Managed anti-bot protection
- High success rates for data collection
- Professional infrastructure
- Built-in retry mechanisms

### Direct Amazon Scraping

Custom scraping implementation with advanced session management:

```bash
AMAZON_REVIEW_SERVICE=scraping
AMAZON_COOKIES_1=your-session-cookies-here
AMAZON_COOKIES_2=your-session-cookies-here
# Up to AMAZON_COOKIES_10 for rotation
```

Features:
- Multi-session cookie rotation
- CAPTCHA detection and alerting
- Proxy integration support
- Bandwidth optimization

### AJAX Bypass (Experimental)

Alternative method using Amazon's internal AJAX endpoints:

```bash
AMAZON_REVIEW_SERVICE=ajax
```

Note: Currently disabled pending optimization. Uses Amazon's review rendering endpoints to bypass traditional page scraping.

## Chrome Extension API

Null Fake provides RESTful API endpoints for Chrome extension integration, allowing browser extensions to submit review data directly from Amazon product pages.

### API Endpoints

#### Submit Reviews for Analysis
```
POST /api/extension/submit-reviews
```

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: your-api-key` (not required for local/testing environments)

**Request Body:**
```json
{
  "asin": "B0CGM1RSZH",
  "country": "ca",
  "product_url": "https://amazon.ca/dp/B0CGM1RSZH",
  "extraction_timestamp": "2025-09-04T23:57:47.173Z",
  "extension_version": "1.5.1",
  "product_info": {
    "title": "Product Title from Amazon Page",
    "description": "Product description extracted from DOM",
    "image_url": "https://m.media-amazon.com/images/I/product-image.jpg",
    "amazon_rating": 4.3,
    "total_reviews_on_amazon": 1247,
    "price": "$29.99",
    "availability": "In Stock"
  },
  "reviews": [
    {
      "author": "Customer Name",
      "content": "Review text content...",
      "date": "2025-03-17",
      "extraction_index": 1,
      "helpful_votes": 0,
      "rating": 5,
      "review_id": "AMAZON_REVIEW_ID",
      "title": "Review title",
      "verified_purchase": true,
      "vine_customer": false
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "asin": "B0CGM1RSZH",
  "country": "ca",
  "analysis_id": 12681,
  "processed_reviews": 99,
  "analysis_complete": true,
  "results": {
    "fake_percentage": 15.2,
    "grade": "B",
    "explanation": "Analysis detected some potentially inauthentic reviews...",
    "amazon_rating": 4.3,
    "adjusted_rating": 4.1,
    "rating_difference": -0.2
  },
  "statistics": {
    "total_reviews_on_amazon": 1247,
    "reviews_analyzed": 99,
    "genuine_reviews": 84,
    "fake_reviews": 15
  },
  "product_info": {
    "title": "Product Title",
    "description": "Product description...",
    "image_url": "https://m.media-amazon.com/images/I/product-image.jpg"
  },
  "view_url": "http://nullfake.com/amazon/ca/B0CGM1RSZH",
  "redirect_url": "http://nullfake.com/amazon/ca/B0CGM1RSZH"
}
```

#### Get Analysis Status
```
GET /api/extension/analysis/{asin}/{country}
```

Returns the current analysis status for a given ASIN and country combination.

### Configuration

```bash
# Optional API key for production (not required for local/testing)
EXTENSION_API_KEY=your-secure-api-key

# API key requirement (automatically disabled for local/testing environments)
EXTENSION_REQUIRE_API_KEY=true
```

### Benefits of Extension Integration

- **Direct DOM Access**: Extensions can extract complete product data directly from Amazon pages
- **No Backend Scraping**: Eliminates the need for server-side product metadata scraping
- **Real-time Data**: Fresh product information and reviews from the user's current session
- **Accurate Totals**: Access to exact review counts displayed on Amazon pages
- **Enhanced Reliability**: Bypasses anti-bot measures by using legitimate browser sessions

## Database Schema

The `asin_data` table stores:
- `asin` - Amazon Standard Identification Number
- `country` - Country code (e.g., 'us', 'ca')
- `product_title` - Product title from Amazon
- `product_description` - Product description from Amazon
- `product_image_url` - Product image URL
- `reviews` - JSON array of fetched reviews
- `openai_result` - JSON of full AI analysis with detailed scores
- `total_reviews_on_amazon` - Total review count reported by Amazon
- `have_product_data` - Boolean indicating complete product metadata
- `price` - Current Amazon price (decimal)
- `currency` - Currency code (USD, CAD, GBP, etc.)
- `price_analysis` - JSON of AI price analysis results
- `price_analysis_status` - Status (pending/processing/completed/failed)

The model calculates:
- `fake_percentage` - Percentage of reviews flagged as potentially fake (score ≥ 85)
- `grade` - Letter grade (A-F) based on fake review percentage
- `explanation` - Human-readable analysis summary
- `amazon_rating` - Original average rating from all reviews
- `adjusted_rating` - Adjusted rating excluding fake reviews

## Technology Stack

- **Backend**: Laravel 12 with Livewire 3
- **Database**: MySQL/PostgreSQL with JSON columns
- **Queue System**: Database-driven job processing for asynchronous analysis
- **Session Management**: Multi-provider session rotation for Amazon access
- **AI Integration**: Multi-provider LLM support with automatic failover
- **Security**: reCAPTCHA/hCaptcha integration with session persistence
- **Monitoring**: Comprehensive alerting via Pushover for system health

## Configuration

### LLM Provider Setup

The application supports three AI providers for review analysis:

#### Option 1: OpenAI (Default)
```bash
LLM_PRIMARY_PROVIDER=openai
OPENAI_API_KEY=sk-proj-your-openai-key-here
OPENAI_MODEL=gpt-4o-mini
```

#### Option 2: DeepSeek (94% cost reduction vs OpenAI)
```bash
LLM_PRIMARY_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-deepseek-key-here
DEEPSEEK_MODEL=deepseek-v3
```

#### Option 3: Self-Hosted Ollama (100% cost reduction)
1. Install Ollama: `curl -fsSL https://ollama.com/install.sh | sh`
2. Pull a model: `ollama pull qwen2.5:7b` or `ollama pull llama3.2:3b`
3. Configure:
```bash
LLM_PRIMARY_PROVIDER=ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b
```

#### Multi-Provider Fallback
```bash
LLM_AUTO_FALLBACK=true
```

### Asynchronous Processing

Control job-based vs immediate processing:

```bash
ANALYSIS_ASYNC_ENABLED=true  # Use job queues (recommended for production)
QUEUE_CONNECTION=database    # Database-backed job processing
```

For immediate processing (development):
```bash
ANALYSIS_ASYNC_ENABLED=false
```

## Management Commands

### LLM Management
- Check provider status: `php artisan llm:manage status`
- Switch providers: `php artisan llm:manage switch --provider=ollama`
- Compare costs: `php artisan llm:manage costs --reviews=100`
- Test providers: `php artisan llm:manage test`

### Data Processing
- Process existing products: `php artisan asin:process-existing --missing-any`
- Clean zero-review products: `php artisan products:cleanup-zero-reviews`
- Backfill total review counts: `php artisan backfill:total-review-counts`

### Price Analysis
Run AI-powered price analysis on products:

```bash
# Analyze products from the last N days (default: 1 day)
php artisan analyze:prices --days=7

# Analyze a specific ASIN
php artisan analyze:prices --asin=B08N5WRWNW

# Preview what would be processed (dry run)
php artisan analyze:prices --days=7 --dry-run

# Force re-analysis of already analyzed products
php artisan analyze:prices --days=7 --force

# Limit number of products to process
php artisan analyze:prices --days=30 --limit=100

# Adjust delay between API calls (default: 100ms)
php artisan analyze:prices --days=7 --delay=200
```

**Note**: Price analysis is automatically queued when new products are analyzed. The console command is for batch processing existing products.

### Session Management
- Check Amazon sessions: `php artisan amazon:cookie-sessions`
- Test scraping functionality: `php artisan test:amazon-scraping`
- Test international URLs: `php artisan test:international-urls`
- Debug proxy connections: `php artisan debug:amazon-scraping`

### Queue Processing
```bash
# Start main analysis queue worker
php artisan queue:work --queue=analysis

# Start price analysis queue worker
php artisan queue:work --queue=price-analysis

# Start worker for all queues (recommended for production)
php artisan queue:work --queue=analysis,product-scraping,price-analysis,default

# Restart workers (after code deployment)
php artisan queue:restart
```

**Production Supervisor Configuration:**
```ini
[program:nullfake-queue]
command=/usr/bin/php artisan queue:work database --queue=analysis,product-scraping,price-analysis,default --sleep=3 --tries=3 --timeout=300
numprocs=4
```

## Usage

1. Enter an Amazon product URL from any supported country (US, Canada, Germany, France, UK, Japan, Mexico, Brazil, India, Singapore, etc.)
2. Complete the captcha (production only)
3. View real-time progress as analysis processes
4. Review detailed results including:
   - Fake review percentage with adjustable thresholds
   - Letter grade (A-F) based on authenticity
   - Detailed explanation of findings
   - Original vs adjusted ratings
   - Product metadata and images
   - Country-specific analysis results

## Development

### Testing
Run the comprehensive test suite:
```bash
# Standard execution
php artisan test

# Parallel execution (faster)
php artisan test --parallel
```

The application includes 530+ tests covering:
- Unit tests for all major services
- Feature tests for user workflows
- Integration tests for external services
- Mock-based testing to prevent external API calls

### Code Style
Maintain code quality with Laravel Pint:
```bash
./vendor/bin/pint
```

## License

MIT

## Shift8

Developed in Toronto, Canada by [Shift8 Web](https://shift8web.ca)