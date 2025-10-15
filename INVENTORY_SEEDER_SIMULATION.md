# Inventory Seeder Simulation Documentation

## Overview
The InventorySeeder now includes realistic simulations for:
1. **Purchase transactions** - Receiving stock from suppliers
2. **Sales transactions** - Selling stock to customers
3. **Damage adjustments** - Writing off damaged/lost inventory

## Features Implemented

### 1. Purchase Stock Simulation

**Method:** `simulatePurchase()`

**What it does:**
- Simulates 2-3 purchase orders per item
- Creates batches for each purchase with unique batch numbers
- Records purchase movements with realistic dates (15-45 days ago)
- Uses randomized quantities (20-50 units) and costs (MYR 300-1500 per unit)

**Example Output:**
```
Purchase #1: +41 units @ RM1,332.96 (34 days ago)
Purchase #2: +44 units @ RM355.09 (35 days ago)
```

**Technical Details:**
- Batch Number Format: `PO-{WAREHOUSE_CODE}-{DATE}-{INDEX}`
- Movement Type: `MovementType::Purchase`
- Creates inventory batches for FIFO/LIFO tracking
- Updates stock levels automatically

### 2. Sales Transaction Simulation

**Method:** `simulateSales()`

**What it does:**
- Simulates 1-3 sales orders per item
- Ensures sales don't exceed available stock
- Calculates COGS (Cost of Goods Sold) based on item's tracking method (FIFO/LIFO/Average)
- Records sales movements with realistic dates (1-10 days ago)
- Uses negative quantities for outbound movements

**Example Output:**
```
Sale #1: -2 units (COGS: RM710.18) (2 days ago)
Sale #2: -9 units (COGS: RM3,195.81) (9 days ago)
Sale #3: -4 units (COGS: RM1,420.36) (2 days ago)
```

**Technical Details:**
- Movement Type: `MovementType::Sale`
- Quantity: Negative value (outbound)
- COGS calculated using `InventoryService::calculateCOGS()`
- Respects tracking method (FIFO/LIFO/Average)
- Updates batch quantities for batch-tracked items

### 3. Damage Adjustment Simulation

**Method:** `simulateDamageAdjustment()`

**What it does:**
- Simulates damaged/lost stock write-offs
- Damages 1-5% of current stock (minimum 1 unit, maximum 10 units)
- Calculates loss value based on item's cost
- Records adjustment movements with realistic dates (1-7 days ago)

**Example Output:**
```
Adjustment: -2 units damaged (Loss: RM710.18) (6 days ago)
```

**Technical Details:**
- Movement Type: `MovementType::Adjustment`
- Quantity: Negative value (reduction)
- Loss value calculated from COGS
- Useful for demonstrating inventory reconciliation

## Movement Type Breakdown

| Movement Type | Direction | Quantity Sign | Purpose |
|--------------|-----------|---------------|---------|
| Initial | Inbound | Positive | Opening stock |
| Purchase | Inbound | Positive | Stock received |
| Sale | Outbound | Negative | Stock sold |
| Adjustment | Either | Pos/Neg | Stock corrections |
| TransferIn | Inbound | Positive | Warehouse transfer |
| TransferOut | Outbound | Negative | Warehouse transfer |
| Return | Inbound | Positive | Customer returns |

## Inventory Tracking Methods

### FIFO (First In, First Out)
- Oldest stock is sold first
- Example: Laptop Computer uses FIFO

### LIFO (Last In, First Out)
- Newest stock is sold first
- Example: Monitor 27" uses LIFO

### Average Cost
- Weighted average cost per unit
- Example: Wireless Mouse uses Average

## Data Flow

```
┌─────────────┐
│   Seeder    │
└─────┬───────┘
      │
      ├── 1. Create Warehouses (firstOrCreate)
      │   └── Main & Secondary Warehouse
      │
      ├── 2. Create Offerings (firstOrCreate)
      │   └── Products: Laptop, Mouse, Monitor, Keyboard
      │
      ├── 3. Create InventoryItems (firstOrCreate)
      │   ├── SKU, Track Method, Reorder Levels
      │   └── Links to Offering
      │
      ├── 4. Initial Stock (if newly created)
      │   ├── Create Batches (quantity = 0)
      │   └── Record Initial Movement
      │
      ├── 5. Simulate Purchases
      │   ├── Create Batches with stock
      │   ├── Record Purchase Movements
      │   └── Update Stock Levels
      │
      ├── 6. Simulate Sales
      │   ├── Calculate COGS (FIFO/LIFO/Avg)
      │   ├── Record Sale Movements
      │   ├── Update Batches (reduce qty)
      │   └── Update Stock Levels
      │
      └── 7. Simulate Damage
          ├── Calculate Loss Value
          ├── Record Adjustment Movement
          ├── Update Batches
          └── Update Stock Levels
```

## Stock Level Calculation

After all movements, final stock = 
```
Opening Stock (0)
+ All Purchases (80-150 units)
- All Sales (10-30 units)
- Damage Adjustments (1-10 units)
= Final Available Stock
```

## Example Seeder Output Analysis

### Laptop Computer:
- **Purchases:** 41 + 44 = 85 units
- **Sales:** -2 -9 -4 = -15 units
- **Damage:** -2 units
- **Final Stock:** 68 units available

### Monitor 27" (LIFO tracking):
- **Purchases:** 27 + 21 + 43 = 91 units
- **Sales:** -7 -2 -2 = -11 units  
- **Damage:** -3 units
- **Final Stock:** 77 units available

## Database Tables Affected

1. **warehouses** - Warehouse locations
2. **offerings** - Product/service definitions
3. **inventory_items** - Inventory tracking configurations
4. **inventory_batches** - Batch/lot tracking records
5. **inventory_movements** - All stock movements
6. **inventory_stock_levels** - Current stock by warehouse

## Testing Scenarios Covered

✅ Multiple purchases with different costs (FIFO/LIFO testing)
✅ Sales that calculate accurate COGS
✅ Stock availability checks before sales
✅ Damage adjustments reducing inventory
✅ Batch tracking for FIFO/LIFO items
✅ Movement history for audit trail
✅ Stock level accuracy after all movements

## Usage

### Run the Full Seeder
```bash
php artisan db:seed --class=InventorySeeder
```

### Run with Fresh Database
```bash
php artisan migrate:fresh --seed
# or
php artisan migrate:refresh --seed
```

### Run Only Inventory Seeder (Idempotent)
```bash
# Safe to run multiple times
php artisan db:seed --class=InventorySeeder
```

## Idempotent Behavior

The seeder is **idempotent** - safe to run multiple times:

- **Warehouses:** Uses `firstOrCreate` - won't create duplicates
- **Offerings:** Uses `firstOrCreate` - won't create duplicates
- **InventoryItems:** Uses `firstOrCreate` - won't create duplicates
- **Initial Stock:** Only created if item was just created OR no movements exist
- **Purchases/Sales/Adjustments:** Only run for newly created items or when no movements exist

## Sample Data Characteristics

- **4 Products:** Laptop, Mouse, Monitor, Keyboard
- **2 Warehouses:** Main (active), Secondary (inactive)
- **Currency:** MYR (Malaysian Ringgit)
- **Date Range:** Movements from 45 days ago to 1 day ago
- **Realistic Quantities:** 20-150 units per item
- **Realistic Costs:** RM300 - RM1,500 per unit
- **COGS Calculation:** Accurate based on tracking method

## Key Benefits

1. **Realistic Test Data** - Mirrors real-world inventory operations
2. **COGS Testing** - Validates FIFO/LIFO/Average calculations
3. **Movement History** - Complete audit trail
4. **Stock Accuracy** - Tests inventory reconciliation
5. **Multi-Warehouse** - Tests location-based stock management
6. **Batch Tracking** - Tests lot/batch compliance scenarios

## Future Enhancements (Optional)

- [ ] Add transfer movements between warehouses
- [ ] Add return movements (customer returns)
- [ ] Add manufacturing movements (raw material → finished goods)
- [ ] Add cycle count adjustments
- [ ] Add negative stock scenarios (for testing validations)
- [ ] Add bulk import simulations

## Related Files

- `database/seeders/InventorySeeder.php` - Main seeder file
- `app/Services/Inventory/InventoryService.php` - Business logic
- `app/Enums/Inventory/MovementType.php` - Movement type enum
- `app/Enums/Inventory/TrackMethod.php` - Tracking method enum
- `app/Models/Inventory/*.php` - Inventory models

---

**Last Updated:** October 15, 2025
**Status:** ✅ Complete and tested
