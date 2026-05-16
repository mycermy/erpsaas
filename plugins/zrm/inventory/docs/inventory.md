# Inventory Plugin Context

## Purpose
The `zrm/inventory` plugin adds multi-warehouse stock management to ERP SaaS, with:

- item-level inventory tracking linked to offerings
- cost tracking via FIFO, LIFO, or weighted average
- movement audit trail for purchases, sales, adjustments, transfers, and returns
- stock adjustment workflows and accounting journal integration
- Filament resources, dashboard, and low-stock widgets for operational use

## Package And Bootstrapping
- Package name: `zrm/inventory`
- Laravel provider: `Zrm\Inventory\Providers\InventoryServiceProvider`
- Filament plugin ID: `inventory`
- Filament registration is panel-aware and only discovered for panel ID `company`

Runtime registration and discovery:
- resources: `Zrm\Inventory\Filament\Resources`
- pages: `Zrm\Inventory\Filament\Pages`
- clusters: `Zrm\Inventory\Filament\Clusters`
- widgets: `Zrm\Inventory\Filament\Widgets`

The service provider also:
- loads migrations, views, and translations
- merges `config/config.php` into `inventory`
- sets a strict morph map for Invoice and Bill model compatibility
- performs early tenant binding on matched routes (using route `tenant` or `company` parameter)

## Data Model Overview
Primary tables created by the plugin:

- `inventory_items`: per-offering inventory config (SKU, tracking method, reorder settings, account mapping)
- `warehouses`: stock locations
- `inventory_batches`: lot/batch layers used by FIFO/LIFO and inbound batch tracking
- `inventory_stock_levels`: cached stock snapshot per item+warehouse
- `inventory_movements`: immutable movement ledger with reference polymorphism
- `inventory_adjustments`, `inventory_adjustment_items`, `inventory_adjustment_batches`: adjustment workflow and batch allocations
- `inventory_transfers`, `inventory_transfer_items`: warehouse transfer workflow

Cross-module schema changes:
- `offerings.stockable` boolean
- `invoices.inventory_flagged` and `invoices.inventory_flagged_at`
- data fix migration for legacy `transactions.transactionable_type` values

## Core Domain Models
- `InventoryItem`: inventory settings and account links for an offering
- `Warehouse`: stock location and transfer endpoints
- `InventoryBatch`: cost layers and remaining quantities
- `InventoryStockLevel`: on-hand/reserved/available quantities and average cost per warehouse
- `InventoryMovement`: canonical movement ledger and reference linkage
- `InventoryAdjustment` and related items/batches
- `InventoryTransfer` and related items

Core enums:
- `TrackMethod`: `fifo`, `lifo`, `average`
- `MovementType`: `purchase`, `sale`, `adjustment`, `transfer_in`, `transfer_out`, `return`, `initial`
- `AdjustmentStatus`, `AdjustmentType`, `TransferStatus`

## Service Layer
### InventoryService
Main orchestration service for movement recording and stock/cost effects.

Important behavior:
- wraps movement operations in DB transactions
- idempotency guard for referenced movements (`reference_type` + `reference_id` + item + movement type)
- special handling for reversal notes containing `REVERSAL`
- handles adjustment-specific logic for positive and negative quantities
- supports explicit batch allocations (especially damage/write-off scenarios)
- computes and applies COGS allocations for outbound flows
- updates stock-level cache and batch remainders

### COGSService
Accounting-aware helper that:
- records sale/purchase movements through `InventoryService`
- creates COGS journal transactions and entries
- supports return reversal flows for COGS

## Business Workflows
### 1. Stock In (purchase/initial/transfer in)
- create inbound movement
- optionally create/attach batch layer
- update stock levels and average cost

### 2. Stock Out (sale/transfer out/damage)
- determine cost basis (batch allocations or average)
- create outbound movement(s)
- decrement batch quantities when tracked
- update stock-level availability

### 3. Adjustment Approval
`InventoryAdjustmentObserver` reacts when status transitions to Approved:
- records inventory movement(s) per adjustment item
- creates corresponding accounting journal transaction when value-impacting

Observer design details:
- bypasses company global scope where required in observer context
- attempts to determine contra account:
	- equity/opening balance style accounts for stock setup/increase
	- operating expense/COGS style accounts for damage/loss

## Filament Surface
Cluster:
- `Operations` cluster (`inventory/operations`)

Resources:
- `InventoryItemResource`
- `WarehouseResource`
- `InventoryAdjustmentResource`
- `InventoryTransferResource`

Pages:
- `InventoryDashboard`

Widgets:
- `InventoryStatsWidget`
- `LowStockAlertWidget`

UI characteristics:
- resource queries are tenant-scoped (company panel)
- adjustments and transfers become progressively read-only after status changes
- low-stock calculations are driven by `inventory_stock_levels.quantity_available <= inventory_items.reorder_level`

## Routing
Current plugin route files exist but are intentionally minimal/commented:
- `routes/web.php`
- `routes/api.php`

Operational entry points are primarily Filament panel resources/pages/widgets.

## Seeding And Demo Data
Seeder chain:
- `Zrm\Inventory\Database\Seeders\DatabaseSeeder`
- `Zrm\Inventory\Database\Seeders\InventoryDatabaseSeeder`

Seed behavior highlights:
- ensures MYR currency defaults for company context
- creates stockable offerings + inventory items
- creates warehouse(s)
- creates initial stock adjustments, purchases, sales, and damage adjustments

Convenient local reset + seed command:

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class="Zrm\Hr\Database\Seeders\DatabaseSeeder" && php artisan db:seed --class="Zrm\Inventory\Database\Seeders\DatabaseSeeder"
```

## Operational Command
`inventory:reprocess-adjustment {id} {--created-by=}`

Purpose:
- idempotently recreate missing movement records for an adjustment
- useful for recovery after partial failures or legacy data drift

## Bulk Inventory Adjustment CSV Import
Use case: end-of-year stock take where physical counts are imported in one batch.

Implementation surface:
- Filament action on adjustments list page: `Import CSV`
- service: `Zrm\Inventory\Services\InventoryAdjustmentImportService`
- import tracking tables:
	- `inventory_adjustment_imports`
	- `inventory_adjustment_import_rows`

### Required CSV Template (Strict)
Header must match exactly and in this order:

```csv
sku,item_name,quantity_counted,reason
LAPTOP-001,Laptop Computer,25,EOY stock take count
MOUSE-001,Wireless Mouse,120,EOY stock take count
NEW-SKU-001,Uncatalogued Item,8,Found physically during count
```

### Row Semantics
- `sku`: required, validated format `^[A-Za-z0-9][A-Za-z0-9._-]*$`
- `item_name`: required, used for unknown SKU auto-creation
- `quantity_counted`: required non-negative integer (physical count)
- `reason`: optional descriptive note

### Unknown SKU Handling
Importer supports two strategies:
- `flag`: mark row as failed and continue
- `auto_create`: create a stockable offering + inactive inventory item, then process adjustment

Auto-created items defaults:
- offering: product type, non-sellable/non-purchasable, stockable=true
- inventory item: `track_method=fifo`, `track_batches=false`, `active=false`

### Processing Rules
- Import is wrapped in a database transaction.
- Import creates one stocktake adjustment for all valid rows with non-zero deltas.
- For each row:
	- read current `quantity_on_hand` in selected warehouse
	- compute delta: `quantity_adjusted = quantity_counted - quantity_before`
	- skip rows with zero delta (tracked as skipped)
- Created adjustment is auto-approved to apply movements immediately.

### Error Handling
- Missing/incorrect headers: import rejected before row processing.
- Wrong column count on any row: import rejected.
- Invalid SKU format: row failed.
- Invalid quantity (empty/non-integer/negative): row failed.
- Unknown SKU with `flag` strategy: row failed.

Row-level results are recorded in `inventory_adjustment_import_rows` and import summary in `inventory_adjustment_imports` with statuses:
- `completed`
- `completed_with_errors`
- `failed`

## Download Inventory Template
The adjustments list page now provides a `Download Inventory Template` action.

Purpose:
- generate a pre-formatted CSV directly from current inventory data
- let warehouse staff fill in physical counts and notes beside system reference fields
- export data in a deterministic order for operational stock-take use

### Backend Behavior
- implemented in `Zrm\Inventory\Services\InventoryAdjustmentImportService`
- rows are streamed with `response()->streamDownload()`
- query uses a lazy cursor over joined inventory stock, item, offering, and warehouse data
- avoids loading the full export into memory for large datasets

### Sort / Group Order
Rows are ordered by:
- `warehouse_name`
- `warehouse_id`
- `item_category`
- `brand`
- `sku`

This produces contiguous warehouse/category/brand groupings without inserting non-data separator rows.

### CSV Headers
Exact export layout:

```csv
warehouse_id,warehouse_name,item_category,brand,sku,item_name,current_quantity,quantity_counted,reason
```

Column purpose:
- `warehouse_id`: system reference
- `warehouse_name`: system reference
- `item_category`: derived from offering type
- `brand`: reserved export field, currently blank because brand is not modeled on offerings in the current schema
- `sku`: system reference
- `item_name`: system reference
- `current_quantity`: current on-hand quantity from `inventory_stock_levels`
- `quantity_counted`: empty input column for staff to enter physical count
- `reason`: empty input column for notes / stock take remarks

## Important Implementation Notes
- Money-like values are stored as integers (minor units/cents semantics in most fields).
- The plugin relies on company tenancy context (`filament()->getTenant()`) in many UI queries.
- Some observer and service paths intentionally use `withoutGlobalScope(CurrentCompanyScope::class)` for correctness in background/observer execution.
- Accounting posting depends on account mapping quality (`asset_account_id`, COGS/expense/equity account availability).

## Known Gaps / TODO Candidates
- API and web route controllers are scaffolded but mostly inactive.
- Event provider is set to discover events, but explicit listener mappings are minimal.
- Documentation should be updated alongside any new movement type, cost strategy, or posting rule changes.
