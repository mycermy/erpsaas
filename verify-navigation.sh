#!/bin/bash

# Inventory Navigation Verification Script
# Run this to verify all components are in place

echo "=================================================="
echo "🔍 Inventory System Navigation Verification"
echo "=================================================="
echo ""

echo "✅ Checking Dashboard Page..."
if [ -f "app/Filament/Company/Pages/Dashboard.php" ]; then
    echo "   ✓ Dashboard.php exists"
else
    echo "   ✗ Dashboard.php NOT FOUND"
fi

echo ""
echo "✅ Checking Inventory Reports Page..."
if [ -f "app/Filament/Company/Pages/Inventory/InventoryReports.php" ]; then
    echo "   ✓ InventoryReports.php exists"
else
    echo "   ✗ InventoryReports.php NOT FOUND"
fi

echo ""
echo "✅ Checking View Files..."
if [ -f "resources/views/filament/company/pages/dashboard.blade.php" ]; then
    echo "   ✓ dashboard.blade.php exists"
else
    echo "   ✗ dashboard.blade.php NOT FOUND"
fi

if [ -f "resources/views/filament/company/pages/inventory/inventory-reports.blade.php" ]; then
    echo "   ✓ inventory-reports.blade.php exists"
else
    echo "   ✗ inventory-reports.blade.php NOT FOUND"
fi

echo ""
echo "✅ Checking Routes..."
echo "   Dashboard route:"
php artisan route:list | grep "pages.dashboard" | head -1

echo ""
echo "   Inventory Reports route:"
php artisan route:list | grep "inventory-reports" | head -1

echo ""
echo "✅ Checking Provider Configuration..."
if grep -q "Dashboard::class" app/Providers/Filament/CompanyPanelProvider.php; then
    echo "   ✓ Dashboard imported in provider"
else
    echo "   ✗ Dashboard NOT imported in provider"
fi

if grep -q "InventoryReports::class" app/Providers/Filament/CompanyPanelProvider.php; then
    echo "   ✓ InventoryReports imported in provider"
else
    echo "   ✗ InventoryReports NOT imported in provider"
fi

if grep -q "...Dashboard::getNavigationItems()" app/Providers/Filament/CompanyPanelProvider.php; then
    echo "   ✓ Dashboard added to navigation"
else
    echo "   ✗ Dashboard NOT in navigation"
fi

if grep -q "...InventoryReports::getNavigationItems()" app/Providers/Filament/CompanyPanelProvider.php; then
    echo "   ✓ InventoryReports added to Inventory group"
else
    echo "   ✗ InventoryReports NOT in Inventory group"
fi

echo ""
echo "=================================================="
echo "📋 Summary"
echo "=================================================="
echo ""
echo "If all checks show ✓, the navigation is properly configured."
echo ""
echo "🌐 To see the menu in your browser:"
echo "   1. Open: https://erpsaas.test"
echo "   2. Login and select a company"
echo "   3. Press Cmd+Shift+R to hard refresh"
echo "   4. Look for 'Dashboard' at the top of sidebar"
echo "   5. Scroll to 'Inventory' group and expand it"
echo "   6. You should see 'Inventory Reports' at the bottom"
echo ""
echo "=================================================="
