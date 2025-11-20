#!/bin/bash

# RAGinald API Examples
# ======================

API_URL="http://localhost:8000"

echo "🚀 RAGinald API Examples"
echo "========================"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 1. Health Check (Public)
echo -e "${BLUE}1. Health Check${NC}"
curl -s "$API_URL/api/health" | jq
echo ""
echo ""

# 2. Available Models (Public)
echo -e "${BLUE}2. Available LLM Models${NC}"
curl -s "$API_URL/api/models" | jq
echo ""
echo ""

# 3. Login (Get JWT Token)
echo -e "${BLUE}3. Login (Admin)${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token')
echo "Token: $TOKEN"
echo ""
echo ""

if [ "$TOKEN" == "null" ] || [ -z "$TOKEN" ]; then
    echo "❌ Login failed. Check credentials or JWT configuration."
    exit 1
fi

# 4. Start Requirements Extraction
echo -e "${BLUE}4. Start Requirements Extraction${NC}"
EXTRACTION_RESPONSE=$(curl -s -X POST "$API_URL/api/requirements/extract" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documentPath": "tests/SampleRequirements.md",
    "projectName": "ShopMaster E-Commerce",
    "extractionOptions": {
      "llmModel": "llama3.2",
      "temperature": 0.7,
      "async": true
    }
  }')

echo $EXTRACTION_RESPONSE | jq
JOB_ID=$(echo $EXTRACTION_RESPONSE | jq -r '.id')
echo ""
echo -e "${GREEN}Job ID: $JOB_ID${NC}"
echo ""
echo ""

if [ "$JOB_ID" == "null" ] || [ -z "$JOB_ID" ]; then
    echo "❌ Extraction failed. Check logs and services."
    exit 1
fi

# 5. Check Job Status
echo -e "${BLUE}5. Check Job Status${NC}"
sleep 2
curl -s "$API_URL/api/requirements/jobs/$JOB_ID" \
  -H "Authorization: Bearer $TOKEN" | jq
echo ""
echo ""

# 6. List All Jobs
echo -e "${BLUE}6. List All Jobs${NC}"
curl -s "$API_URL/api/requirements/jobs" \
  -H "Authorization: Bearer $TOKEN" | jq
echo ""
echo ""

echo -e "${GREEN}✨ Examples completed!${NC}"
echo ""
echo "💡 Tips:"
echo "  - Monitor worker logs: tail -f var/log/dev.log"
echo "  - Check message queue: php bin/console messenger:stats"
echo "  - Neo4j Browser: http://localhost:7474"
echo ""

