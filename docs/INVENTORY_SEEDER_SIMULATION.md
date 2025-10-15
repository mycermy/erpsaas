````markdown
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
 Seeder    
 ... (omitted) ...
```

---

**Last Updated:** October 15, 2025
**Status:**  Complete and tested

````