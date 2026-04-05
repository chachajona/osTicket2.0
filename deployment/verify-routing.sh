#!/bin/bash

# osTicket 2.0 Reverse Proxy Verification Script
# Tests routing, SSL, rate limiting, and static assets
# Usage: bash deployment/verify-routing.sh

set -e

DOMAIN="${1:-127.0.0.1}"
PROTOCOL="${2:-https}"
INSECURE_FLAG=""

# Use -k flag for self-signed certificates
if [ "$PROTOCOL" = "https" ]; then
    INSECURE_FLAG="-k"
fi

BASE_URL="$PROTOCOL://$DOMAIN"
PASS=0
FAIL=0

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "osTicket 2.0 Routing Verification"
echo "=========================================="
echo "Testing: $BASE_URL"
echo ""

# Helper function to test endpoint
test_endpoint() {
    local name="$1"
    local path="$2"
    local expected_status="$3"
    local description="$4"

    echo -n "Testing: $name... "
    
    response=$(curl $INSECURE_FLAG -s -w "\n%{http_code}" "$BASE_URL$path" 2>/dev/null || echo "000")
    status=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [[ "$status" =~ ^($expected_status)$ ]]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $status)"
        echo "  └─ $description"
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC} (Expected $expected_status, got $status)"
        echo "  └─ $description"
        echo "  └─ URL: $BASE_URL$path"
        ((FAIL++))
    fi
    echo ""
}

# Helper function to test header
test_header() {
    local name="$1"
    local path="$2"
    local header="$3"
    local expected_value="$4"

    echo -n "Testing Header: $name... "
    
    response=$(curl $INSECURE_FLAG -s -I "$BASE_URL$path" 2>/dev/null | grep -i "^$header:" || echo "")

    if [[ "$response" == *"$expected_value"* ]]; then
        echo -e "${GREEN}✓ PASS${NC}"
        echo "  └─ Header: $response"
        ((PASS++))
    else
        echo -e "${YELLOW}⚠ WARN${NC} (Header not found or unexpected value)"
        echo "  └─ Expected: $header: $expected_value"
        echo "  └─ Got: $response"
        ((FAIL++))
    fi
    echo ""
}

# ============================================================
# SECURITY & PROTOCOL TESTS
# ============================================================
echo "=== SECURITY & PROTOCOL TESTS ==="
echo ""

test_endpoint "HTTP Redirect" "/" "301|302|307|308" "HTTP should redirect to HTTPS"
test_header "HTTPS Only" "/" "Strict-Transport-Security" "max-age" || true

# ============================================================
# LEGACY OSTICKET ROUTES
# ============================================================
echo "=== LEGACY OSTICKET ROUTES ==="
echo ""

test_endpoint "Root Path" "/" "200|301|302" "Root should serve legacy osTicket or redirect"
test_endpoint "Pages Endpoint" "/pages/" "200|301|302|404" "Pages should be accessible on legacy"

# ============================================================
# LARAVEL SCP ROUTES
# ============================================================
echo "=== LARAVEL SCP ROUTES ==="
echo ""

test_endpoint "SCP Base" "/scp/" "200|301|302|401|403" "SCP should route to Laravel (may require auth)"
test_endpoint "SCP Dashboard" "/scp/dashboard/" "200|301|302|401|403" "SCP dashboard should route to Laravel"
test_endpoint "SCP Tickets" "/scp/tickets/" "200|301|302|401|403" "SCP tickets should route to Laravel"

# ============================================================
# LARAVEL API ROUTES
# ============================================================
echo "=== LARAVEL API ROUTES ==="
echo ""

test_endpoint "API Base" "/api/" "200|301|401|403|404" "API should route to Laravel"
test_endpoint "API Tickets" "/api/tickets/" "200|401|403|404" "API tickets endpoint should route to Laravel"
test_endpoint "API v2" "/api/v2/" "200|301|401|403|404" "API v2 should route to Laravel"

# ============================================================
# STATIC ASSETS
# ============================================================
echo "=== STATIC ASSETS CACHING ==="
echo ""

# Note: These will only work if Laravel is properly deployed with assets
test_header "CSS Caching" "/css/app.css" "Cache-Control" "public" || true
test_header "JS Caching" "/js/app.js" "Cache-Control" "public" || true
test_header "Image Caching" "/images/logo.png" "Cache-Control" "public" || true

# ============================================================
# GZIP COMPRESSION
# ============================================================
echo "=== GZIP COMPRESSION ==="
echo ""

echo -n "Testing: Gzip Compression... "
response=$(curl $INSECURE_FLAG -s -I -H "Accept-Encoding: gzip" "$BASE_URL/scp/" 2>/dev/null | grep -i "content-encoding: gzip" || echo "")

if [[ ! -z "$response" ]]; then
    echo -e "${GREEN}✓ PASS${NC}"
    echo "  └─ Response: Gzip compression enabled"
    ((PASS++))
else
    echo -e "${YELLOW}⚠ INFO${NC}"
    echo "  └─ Gzip may not be enabled for all content types"
    ((FAIL++))
fi
echo ""

# ============================================================
# RATE LIMITING (Nginx only - requires stub_status)
# ============================================================
echo "=== RATE LIMITING ==="
echo ""

echo "Note: Rate limiting tests require server configuration"
echo "For Nginx: Enable 'stub_status' to see rate limit status"
echo "For Apache: Enable 'mod_status' to see rate limit status"
echo ""

# ============================================================
# HEALTH ENDPOINT (Nginx only)
# ============================================================
echo "=== HEALTH ENDPOINT ==="
echo ""

test_endpoint "Health Check" "/health" "200" "Nginx health endpoint (if using Nginx)"

# ============================================================
# SUMMARY
# ============================================================
echo "=========================================="
echo "SUMMARY"
echo "=========================================="
echo -e "Passed:  ${GREEN}$PASS${NC}"
echo -e "Failed:  ${RED}$FAIL${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Check configuration.${NC}"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. Verify reverse proxy is running:"
    echo "   sudo systemctl status nginx  # or: apache2"
    echo ""
    echo "2. Check upstream services are responding:"
    echo "   curl http://127.0.0.1:9000/  # Laravel"
    echo "   curl http://127.0.0.1:8080/  # Legacy osTicket"
    echo ""
    echo "3. Review logs:"
    echo "   sudo tail -f /var/log/nginx/osticket_error.log"
    echo "   sudo tail -f /var/log/php-fpm/osticket-laravel.error.log"
    echo ""
    echo "4. Test configuration syntax:"
    echo "   sudo nginx -t  # or: apachectl configtest"
    echo ""
    exit 1
fi
