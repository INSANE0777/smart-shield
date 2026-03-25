#!/bin/bash

# Docker Test Script for NullFake
# This script helps test the Docker setup

set -e

echo "🐳 Testing NullFake Docker Setup"
echo "================================"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

echo "✅ Docker is running"

# Check if .env exists
if [ ! -f "../.env" ]; then
    echo "📋 Creating .env file from docker.env.example..."
    cp ../docker.env.example ../.env
    echo "✅ .env file created"
else
    echo "✅ .env file exists"
fi

# Start containers
echo "🚀 Starting Docker containers..."
docker-compose -f docker-compose.yml up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 10

# Check container status
echo "📊 Container Status:"
docker-compose -f docker-compose.yml ps

# Test database connection
echo "🗄️ Testing database connection..."
if docker-compose -f docker-compose.yml exec -T db mysql -u faker -ppassword -e "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed"
fi

# Application initialization is now handled by entrypoint script
echo "🔧 Application initialization handled automatically by entrypoint script..."
echo "   - Laravel app key generation"
echo "   - Database migrations"
echo "   - Permissions setup"

# Test web server
echo "🌐 Testing web server..."
sleep 5
if curl -s http://localhost:8080 > /dev/null; then
    echo "✅ Web server is responding"
else
    echo "❌ Web server is not responding"
fi

# Test Ollama
echo "🤖 Testing Ollama service..."
if curl -s http://localhost:11434/api/tags > /dev/null; then
    echo "✅ Ollama service is running"
    echo "💡 To install a model: docker-compose -f docker/docker-compose.yml exec ollama ollama pull phi4:14b"
else
    echo "❌ Ollama service is not responding"
fi

echo ""
echo "🎉 Docker setup test complete!"
echo ""
echo "📝 Next steps:"
echo "   1. Visit http://localhost:8080 to access NullFake"
echo "   2. Install Ollama model: docker-compose -f docker/docker-compose.yml exec ollama ollama pull phi4:14b"
echo "   3. Configure API keys in .env file if needed"
echo ""
echo "🛠️ Useful commands:"
echo "   - View logs: docker-compose -f docker/docker-compose.yml logs -f"
echo "   - Stop containers: docker-compose -f docker/docker-compose.yml down"
echo "   - Access app container: docker-compose -f docker/docker-compose.yml exec app bash"
