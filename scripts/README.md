# Test scripts

This folder contains a set of standalone PHP scripts that exercise inventory behavior (flagging, unflagging, batch processing, observer behavior).

Safety notes
- Most scripts use database transactions and roll back at the end, so they are safe to run against a development or seeded test database.
- Ensure you DO NOT run these against a production database unless you know what you're doing. Prefer a local/CI environment.
- `observer-test.php` is wrapped in a transaction and will roll back so no persistent changes remain.

Prerequisites
- Composer dependencies installed (run `composer install`).
- A seeded/dev database with expected fixtures: a `Company`, an `Offering` named "Laptop Computer" (many scripts expect this), `Vendor` and `Client` records, and a default `Warehouse`.
- PHP CLI available.

Quick commands

```
# from the repository root
php scripts/batch-test.php
php scripts/check_inventory.php
php scripts/fresh-db-test.php
php scripts/final-test.php
php scripts/comprehensive-test.php
php scripts/production-ready-test.php
php scripts/simple-flagging-test.php
php scripts/test-inventory-flagging.php
php scripts/observer-test.php
```

Script descriptions
- `batch-test.php` — checks for duplicate batch creation when creating a Bill with a single line item.
- `check_inventory.php` — prints inventory movements and a summary of balances.
- `fresh-db-test.php` — a set of tests intended for a fresh/seeder-backed DB (flagging + auto-unflagging).
- `final-test.php` — end-to-end verification of automatic inventory processing.
- `comprehensive-test.php` — full cycle test: oversell → partial restock → full restock → batch checks.
- `production-ready-test.php` — simulates real-world Invoice → Bill → Auto-unflag cycle.
- `simple-flagging-test.php` — a compact test focused on flagging and unflagging.
- `test-inventory-flagging.php` — thorough flagging test with batch/movements checks.
- `observer-test.php` — verifies observers fire for Bill line item creation (wrapped in a transaction).

If you want, I can also:
- Make `scripts/` executable or add a small helper `run-tests.sh` to run a subset with safety flags.
- Create a git branch and commit these changes, then open a PR with the cleanup notes.
