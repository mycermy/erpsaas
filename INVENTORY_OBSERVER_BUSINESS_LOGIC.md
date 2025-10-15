# Inventory Observer Business Logic

This document explains when inventory movements are triggered in the system, aligned with real-world business practices.

## 📦 Inventory Movement Triggers

### 1. **Purchase Bills (Stock In)**

**Trigger:** When bill is **created/recorded** (not when paid)

```php
// BillObserver triggers on: wasRecentlyCreated && status != Void
```

**Business Logic:**
- ✅ Goods physically received from supplier
- ✅ Bill is recorded in system
- ✅ Inventory immediately available for use/sale
- ⏳ Payment happens later (30-60 days credit terms)

**Example Flow:**
```
Day 1: Receive 100 units + Bill created (status: Open)
       → Inventory +100 units ✅
       
Day 30: Pay supplier
        → Bill status: Paid
        → No inventory change (already received)
```

---

### 2. **Sales Invoices (Stock Out)**

**Trigger:** When invoice status changes **from Draft to any active status** (Sent, Partial, Paid)

```php
// InvoiceObserver triggers on: wasDraft && isNoLongerDraft
```

**Business Logic:**
- ✅ Goods shipped/delivered to customer
- ✅ Invoice approved/sent
- ✅ Inventory immediately deducted
- ⏳ Payment happens later (credit customers)

**Example Flow:**
```
Day 1: Create invoice (status: Draft)
       → No inventory change
       
Day 1: Approve & send invoice (status: Sent)
       → Inventory -10 units ✅
       → Goods shipped to customer
       
Day 30: Customer pays
        → Invoice status: Paid
        → No inventory change (already shipped)
```

---

### 3. **Inventory Adjustments (Stock Corrections)**

**Trigger:** When adjustment status changes **from Draft to Approved**

```php
// InventoryAdjustmentObserver triggers on: wasDraft && isApproved
```

**Business Logic:**
- ✅ Physical stock count completed
- ✅ Discrepancies identified (damage, theft, count errors)
- ✅ Adjustment approved by manager
- ✅ Inventory updated to match physical reality

**Example Flow:**
```
Day 1: Create adjustment (status: Draft)
       → Add items with quantity differences
       → No inventory change yet
       
Day 2: Manager reviews and approves (status: Approved)
       → Inventory adjusted ✅
       → Movements recorded with reason
```

---

## 🎯 Key Principles

### Inventory ≠ Payment Status

| Document | Inventory Trigger | Payment Trigger |
|----------|------------------|-----------------|
| **Bill** | When received (Open) | When paid (Paid) |
| **Invoice** | When approved/sent (Sent) | When paid (Paid) |

### Why This Matters

1. **Accurate Stock Levels**
   - Stock reflects what's physically available
   - Not delayed by payment terms

2. **Real Business Flow**
   - B2B: 30-60 day payment terms are common
   - Stock moves before money moves

3. **Better Decision Making**
   - Sales team sees real available inventory
   - Purchasing knows what's actually in stock
   - Finance tracks receivables/payables separately

---

## 🔄 Observer Summary

```php
// Bills: Inventory in when goods received
BillObserver::saving()
    if wasRecentlyCreated && status != Void
        → processInventoryInbound()

// Invoices: Inventory out when goods shipped
InvoiceObserver::saving()
    if wasDraft && isNoLongerDraft && status != Void
        → processInventoryOutbound()

// Adjustments: Inventory corrected when approved
InventoryAdjustmentObserver::saving()
    if wasNotApproved && isApproved
        → processInventoryAdjustment()
```

---

## 📊 Seeder Behavior

The `EnhancedInventorySeeder` creates realistic scenarios:

### Bills (30-60 days ago)
- Random status: Open (unpaid) / Partial / Paid
- All trigger inventory in when created
- Reflects real supplier payment terms

### Invoices (1-29 days ago)
- Created as Draft, then changed to Sent/Partial/Paid
- All trigger inventory out when no longer draft
- Reflects real customer payment terms

### Adjustments (1-14 days ago)
- Created as Draft with line items
- Then approved to trigger movement
- Reflects real approval workflow

---

## ✅ Benefits of This Approach

1. **Real-time Inventory**: Stock levels always accurate
2. **Separation of Concerns**: Inventory ≠ Finance
3. **Workflow Support**: Draft → Approved process
4. **Audit Trail**: Clear movement history with reasons
5. **Predictable Behavior**: Consistent across all document types

---

*Last Updated: October 15, 2025*
