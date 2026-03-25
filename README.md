# 🛡️ SMART SHIELD UI

SMART SHIELD UI is an AI-powered Amazon review analysis platform designed to detect fake, AI-generated, and suspicious reviews with high precision. Supporting **14+ Amazon regional domains**, it provides shoppers with an adjusted "Trust Score" (A-F) to make informed purchasing decisions.

---

## 🚀 Docker Quick Start (Recommended)

Run the entire stack (App, Nginx, DB, Queue, Ollama) with a single command. 

1. **Configure Environment:**
   ```bash
   cp .env.example .env
   ```
   *Edit `.env` to add your `OPENAI_API_KEY` or leave as-is to use **Ollama** (Local AI).*

2. **Launch UI:**
   ```bash
   docker-compose -f docker/docker-compose.yml up -d
   ```
   *Access the UI at **[http://localhost:8082](http://localhost:8082)**.*

---

## ✨ Key Features

- **Global Coverage**: Supports US, UK, CA, DE, FR, IT, ES, JP, AU, and more.
- **AI Analysis**: Multi-provider support (OpenAI, DeepSeek, or self-hosted Ollama).
- **Price Insights**: AI assessments of price history, MSRP, and market positioning.
- **Queue-Driven**: Asynchronous processing ensures the UI stays fast while AI works.
- **Open Source**: Transparent methodology and community-driven improvements.

---

## 🛠️ Traditional Setup

If you prefer a direct installation:

```bash
composer install
npm install && npm run build
php artisan migrate
php artisan serve
```

---

## 📖 Management Commands

- **Check AI Status**: `php artisan llm:manage status`
- **Process Products**: `php artisan asin:process-existing`
- **Test Scraping**: `php artisan test:amazon-scraping`

---

## ⚖️ License

Distributed under the MIT License. Developed by the [SMART SHIELD UI Team](https://github.com/INSANE0777/smart-shield).
