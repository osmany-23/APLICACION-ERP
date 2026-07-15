#!/usr/bin/env bash
# StatusSwitch - Verification Checklist
# Run this to verify the StatusSwitch implementation is complete

echo "🔍 StatusSwitch Implementation Verification Checklist"
echo "======================================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

CHECKS_PASSED=0
CHECKS_TOTAL=0

# Function to check if file exists
check_file() {
  CHECKS_TOTAL=$((CHECKS_TOTAL+1))
  if [ -f "$1" ]; then
    echo -e "${GREEN}✅${NC} File exists: $1"
    CHECKS_PASSED=$((CHECKS_PASSED+1))
  else
    echo -e "${RED}❌${NC} File missing: $1"
  fi
}

# Function to check if file contains text
check_content() {
  CHECKS_TOTAL=$((CHECKS_TOTAL+1))
  if grep -q "$2" "$1" 2>/dev/null; then
    echo -e "${GREEN}✅${NC} Content found in $1: '$2'"
    CHECKS_PASSED=$((CHECKS_PASSED+1))
  else
    echo -e "${RED}❌${NC} Content NOT found in $1: '$2'"
  fi
}

echo "📁 Checking Component Files..."
check_file "frontend/src/components/SwitchField.tsx"
check_file "frontend/src/components/StatusSwitch.md"
check_file "frontend/src/components/AUDIT.md"
check_file "frontend/src/components/IMPLEMENTATION_SUMMARY.md"
echo ""

echo "📄 Checking Component Exports..."
check_content "frontend/src/components/SwitchField.tsx" "export function StatusSwitch"
check_content "frontend/src/components/SwitchField.tsx" "export function StatusSwitchInline"
check_content "frontend/src/components/SwitchField.tsx" "export { StatusSwitch as SwitchField }"
echo ""

echo "🎨 Checking Design Implementation..."
check_content "frontend/src/components/SwitchField.tsx" "#22C55E"
check_content "frontend/src/components/SwitchField.tsx" "#EF4444"
check_content "frontend/src/components/SwitchField.tsx" "🟢 Activo"
check_content "frontend/src/components/SwitchField.tsx" "🔴 Inactivo"
check_content "frontend/src/components/SwitchField.tsx" "duration-200"
echo ""

echo "♿ Checking Accessibility..."
check_content "frontend/src/components/SwitchField.tsx" "role=\"switch\""
check_content "frontend/src/components/SwitchField.tsx" "aria-checked"
check_content "frontend/src/components/SwitchField.tsx" "aria-label"
check_content "frontend/src/components/SwitchField.tsx" "handleKeyDown"
echo ""

echo "📄 Checking Page Updates..."
check_content "frontend/src/pages/DocumentTypesPage.tsx" "StatusSwitch"
check_content "frontend/src/pages/PaymentMethodsPage.tsx" "StatusSwitch"
check_content "frontend/src/pages/PaymentTermsPage.tsx" "StatusSwitch"
check_content "frontend/src/pages/ProductCatalogs.tsx" "StatusSwitch"
check_content "frontend/src/pages/GlobalCatalogs/index.tsx" "StatusSwitch"
check_content "frontend/src/pages/RelationCatalogs.tsx" "StatusSwitch"
echo ""

echo "📊 Results"
echo "=========="
echo "Checks Passed: $CHECKS_PASSED / $CHECKS_TOTAL"

if [ $CHECKS_PASSED -eq $CHECKS_TOTAL ]; then
  echo -e "${GREEN}✅ All verification checks passed!${NC}"
  echo ""
  echo "StatusSwitch is ready for use!"
  exit 0
else
  echo -e "${RED}❌ Some checks failed. Please review.${NC}"
  exit 1
fi
