#!/bin/bash

echo "🚀 RAGinald - Setup Script"
echo "=========================="
echo ""

# Check if running in WSL2
if grep -qi microsoft /proc/version; then
    echo "✅ Running in WSL2"
else
    echo "⚠️  Not running in WSL2, continuing anyway..."
fi

# Install Composer dependencies (if not already done)
if [ ! -d "vendor" ]; then
    echo ""
    echo "📦 Installing Composer dependencies..."
    composer install
else
    echo "✅ Composer dependencies already installed"
fi

# Create directories
echo ""
echo "📁 Creating directories..."
mkdir -p var/cache var/log var/data output config/jwt

# Generate JWT keys
if [ ! -f "config/jwt/private.pem" ]; then
    echo ""
    echo "🔐 Generating JWT keys..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
else
    echo "✅ JWT keys already exist"
fi

# Database setup
echo ""
echo "🗄️  Setting up database..."
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# Start Docker services
echo ""
echo "🐳 Starting Docker services..."
docker-compose up -d tika neo4j ollama

# Wait for services
echo ""
echo "⏳ Waiting for services to be ready..."
sleep 10

# Check Ollama models
echo ""
echo "🤖 Checking Ollama models..."
if docker exec raginald_ollama ollama list | grep -q "llama3.2"; then
    echo "✅ llama3.2 already installed"
else
    echo "📥 Pulling llama3.2 model..."
    docker exec raginald_ollama ollama pull llama3.2
fi

if docker exec raginald_ollama ollama list | grep -q "nomic-embed-text"; then
    echo "✅ nomic-embed-text already installed"
else
    echo "📥 Pulling nomic-embed-text model..."
    docker exec raginald_ollama ollama pull nomic-embed-text
fi

# Initialize Neo4j
echo ""
echo "🔧 Initializing Neo4j..."
php bin/console app:neo4j:init

# Test services
echo ""
echo "🧪 Testing services..."
php bin/console app:test:extraction

echo ""
echo "✨ Setup complete!"
echo ""
echo "📚 Next steps:"
echo "  1. Start development server: symfony serve -d"
echo "  2. Start message worker: php bin/console messenger:consume async -vv"
echo "  3. Open API: http://localhost:8000/api"
echo "  4. Login: POST /api/login (admin/admin123)"
echo ""

