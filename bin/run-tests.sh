#!/bin/bash
# Test Runner Script für Requirements Pipeline

set -e

echo "🧪 Requirements Pipeline - Test Runner"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if PHPUnit is installed
if [ ! -f "bin/phpunit" ] && [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${YELLOW}⚠️  PHPUnit nicht gefunden. Installiere Dependencies...${NC}"
    composer install --dev
fi

# Default: All tests
TEST_SUITE="${1:-all}"

case "$TEST_SUITE" in
    "all")
        echo -e "${BLUE}📦 Führe alle Tests aus...${NC}"
        php bin/phpunit
        ;;
    "service")
        echo -e "${BLUE}🔧 Führe Service-Tests aus...${NC}"
        php bin/phpunit tests/Service
        ;;
    "toon")
        echo -e "${BLUE}🎒 Führe TOON-Formatter Tests aus...${NC}"
        php bin/phpunit tests/Service/ToonFormatterServiceTest.php
        ;;
    "extraction")
        echo -e "${BLUE}⚙️  Führe Requirements-Extraction Tests aus...${NC}"
        php bin/phpunit tests/Service/RequirementsExtractionServiceTest.php
        ;;
    "dto")
        echo -e "${BLUE}📋 Führe DTO-Tests aus...${NC}"
        php bin/phpunit tests/Dto
        ;;
    "command")
        echo -e "${BLUE}💻 Führe Command-Tests aus...${NC}"
        php bin/phpunit tests/Command
        ;;
    "coverage")
        echo -e "${BLUE}📊 Generiere Coverage-Report...${NC}"
        XDEBUG_MODE=coverage php bin/phpunit --coverage-html coverage/
        echo -e "${GREEN}✅ Coverage-Report: coverage/index.html${NC}"
        ;;
    "watch")
        echo -e "${BLUE}👀 Watch-Mode (Tests bei Änderungen)${NC}"
        echo -e "${YELLOW}Benötigt: npm install -g nodemon${NC}"
        nodemon -e php --exec "clear && php bin/phpunit"
        ;;
    *)
        echo -e "${YELLOW}❌ Unbekannte Test-Suite: $TEST_SUITE${NC}"
        echo ""
        echo "Verwendung: $0 [all|service|toon|extraction|dto|command|coverage|watch]"
        echo ""
        echo "Beispiele:"
        echo "  $0              # Alle Tests"
        echo "  $0 service      # Nur Service-Tests"
        echo "  $0 toon         # Nur TOON-Formatter Tests"
        echo "  $0 coverage     # Mit Coverage-Report"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}✅ Tests abgeschlossen!${NC}"

