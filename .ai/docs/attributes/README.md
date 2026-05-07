# Laravel Attributes Documentation (Offline)

This directory contains offline copies of Laravel PHP Attributes documentation for quick AI reference.

## Structure

```
attributes/
├── eloquent/          # Eloquent model attributes
├── queue/             # Queue job attributes  
├── console/           # Artisan command attributes
├── di/                # Dependency injection attributes
├── controllers/       # Controller attributes
├── form-requests/     # Form request attributes
├── testing/           # Testing attributes
├── factories/         # Factory attributes
├── api-resources/     # API resource attributes
├── ai/                # AI agent attributes
└── php/               # PHP built-in attributes
```

## Quick Setup

### Download All Files

Run the download script to fetch all remaining attribute documentation:

```bash
php .ai/docs/download-attributes.php
```

This will download **80+ markdown files** with detailed docs for every Laravel attribute.

### Or Clone the Repository

Alternatively, you can clone the full repository:

```bash
cd .ai/docs
git clone --depth 1 https://github.com/zulfadliresources/laravel-attributes-list.git temp
cp -r temp/attributes/* attributes/
rm -rf temp
```

## Currently Available

The following key attributes are already downloaded:

### Eloquent (6 files)
- ✅ Fillable.md
- ✅ Hidden.md  
- ✅ Table.md
- ✅ Scope.md (Laravel 12)
- ✅ ScopedBy.md (Laravel 12)
- ✅ ObservedBy.md (Laravel 12)

### Queue (3 files)
- ✅ Connection.md
- ✅ Tries.md
- ✅ Timeout.md

### Dependency Injection (1 file)
- ✅ Singleton.md

## Remaining Files

Run the download script to get:
- 17 more Eloquent attributes
- 9 more Queue attributes
- 6 Console attributes
- 16 more DI attributes
- 2 Controller attributes
- 5 Form Request attributes
- 5 Testing attributes
- 1 Factory attribute
- 2 API Resource attributes
- 8 AI attributes
- 7 PHP built-in attributes

**Total: ~70 additional files**

## Source

Original repository: https://github.com/zulfadliresources/laravel-attributes-list

Fork of: https://github.com/MrPunyapal/laravel-attributes-list
