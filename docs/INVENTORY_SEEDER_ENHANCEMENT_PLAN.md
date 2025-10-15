```markdown
# Inventory Seeder Enhancement Plan

## Current Problem
The `InventorySeeder` creates inventory movements directly without creating the source documents (Bills for purchases, Invoices for sales). This causes:
- Bills page is empty (no purchase records)
- Invoices page is empty (no sales records)
- Inventory movements exist but have no traceable source documents
- Users can't see the full business flow

## Solution Approach

### Phase 1: Create Supporting Data
1. Create sample Vendors (suppliers)
2. Create sample Clients (customers)

### Phase 2: Purchase Flow (Bill  Inventory In)
Instead of directly creating inventory movements:
1. Create a Bill document with:
   - vendor_id
   - bill_number
   - date (matching purchase date)
   - status (Paid/Approved)
   - DocumentLineItems linking to stockable offerings
2. The Bill's observer should trigger inventory movements OR
3. Manually create inventory movements with reference to the Bill

### Phase 3: Sales Flow (Invoice  Inventory Out)
Instead of directly creating inventory movements:
1. Create an Invoice document with:
   - client_id
   - invoice_number
   - date (matching sales date)
   - status (Paid)
   - DocumentLineItems linking to stockable offerings
2. The Invoice's observer should trigger inventory movements OR  
3. Manually create inventory movements with reference to the Invoice

### Phase 4: Adjustment Flow (remains the same)
- InventoryAdjustment already creates proper records
- Keep the current damage adjustment simulation

## Implementation Notes
- Need to check if BillObserver/InvoiceObserver auto-create inventory movements
- If not, need to manually link movements to documents via reference fields
- Ensure proper date sequencing: Bill date  Movement date  Invoice date
- Calculate proper totals (subtotal, tax, total) for documents
- Use MYR currency consistently

## Files to Modify
1. `database/seeders/InventorySeeder.php` - main seeder
2. Possibly create `database/seeders/VendorSeeder.php` if vendors don't exist
3. Possibly create `database/seeders/ClientSeeder.php` if clients don't exist

```