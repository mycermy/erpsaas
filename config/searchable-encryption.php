<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Searchable Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for PDPA-compliant searchable encryption
    | with blind indexing. These settings control how sensitive personal data
    | (NRIC, bank accounts, salary) is encrypted and indexed.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Blind Index Salt
    |--------------------------------------------------------------------------
    |
    | The salt used for generating blind indexes (HMAC-SHA256 hashes).
    |
    | CRITICAL: Once set, DO NOT change this value! If you change it, existing
    | blind indexes will become invalid and you'll need to regenerate all of them
    | using: php artisan security:regenerate-blind-indexes
    |
    | Default: Uses APP_KEY if BLIND_INDEX_SALT is not set
    |
    | Security: Store in .env file, NEVER commit to version control
    |
    */
    'blind_index_salt' => env('BLIND_INDEX_SALT', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Hashing Algorithm
    |--------------------------------------------------------------------------
    |
    | The hashing algorithm used for blind indexes.
    |
    | Supported: sha256, sha512
    | Recommended: sha256 (faster, still secure)
    |
    */
    'hash_algorithm' => env('BLIND_INDEX_HASH_ALGORITHM', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable/disable automatic audit logging when sensitive data is accessed.
    | Required for PDPA compliance and security monitoring.
    |
    */
    'audit_logging' => [
        'enabled' => env('SEARCHABLE_ENCRYPTION_AUDIT', true),

        // Log to database table
        'database' => true,

        // Log to Laravel log files
        'log_file' => false,

        // Table name for audit logs
        'table' => 'sensitive_data_access_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields Registry
    |--------------------------------------------------------------------------
    |
    | Register which fields across your application are considered sensitive
    | under PDPA. This helps with compliance reporting and monitoring.
    |
    */
    'sensitive_fields' => [
        'high_risk' => [
            'nric',
            'passport_number',
            'bank_account_number',
            'credit_card_number',
            'salary',
            'health_record',
            'criminal_history',
            'biometric_data',
        ],

        'medium_risk' => [
            'phone',
            'address',
            'date_of_birth',
            'marital_status',
        ],

        'low_risk' => [
            'name',
            'email',
            'company',
            'job_title',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention Policy
    |--------------------------------------------------------------------------
    |
    | Define retention periods for sensitive data (PDPA compliance).
    | Data older than these periods can be flagged for review or deletion.
    |
    | Periods are in days.
    |
    */
    'retention_policy' => [
        // Employee records: 7 years after termination (Malaysian employment law)
        'employee_records' => env('RETENTION_EMPLOYEE_RECORDS', 2555),

        // Financial records: 7 years (Malaysian tax law)
        'financial_records' => env('RETENTION_FINANCIAL_RECORDS', 2555),

        // General personal data: 2 years of inactivity
        'personal_data' => env('RETENTION_PERSONAL_DATA', 730),

        // Access logs: 1 year
        'access_logs' => env('RETENTION_ACCESS_LOGS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure alerts and monitoring for suspicious access patterns.
    |
    */
    'monitoring' => [
        // Alert if user accesses more than X sensitive records per hour
        'access_threshold_per_hour' => env('SECURITY_ACCESS_THRESHOLD', 50),

        // Alert if sensitive data is accessed from unusual IP
        'ip_whitelist_enabled' => env('SECURITY_IP_WHITELIST', false),

        // Allowed IP addresses (comma-separated in .env)
        'ip_whitelist' => array_filter(
            explode(',', env('SECURITY_IP_WHITELIST_IPS', ''))
        ),

        // Enable notification when suspicious activity detected
        'notify_on_suspicious_activity' => env('SECURITY_NOTIFY_SUSPICIOUS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Optimize performance for encryption and indexing operations.
    |
    */
    'performance' => [
        // Chunk size for batch operations
        'chunk_size' => env('SEARCHABLE_ENCRYPTION_CHUNK_SIZE', 100),

        // Cache blind index salt (improves performance)
        'cache_salt' => true,

        // Cache duration in seconds
        'cache_duration' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance Features
    |--------------------------------------------------------------------------
    |
    | PDPA compliance features and reporting.
    |
    */
    'compliance' => [
        // Automatically mask sensitive data in logs
        'auto_mask_logs' => env('PDPA_AUTO_MASK_LOGS', true),

        // Enable data subject access request (DSAR) features
        'dsar_enabled' => env('PDPA_DSAR_ENABLED', true),

        // Generate compliance reports
        'reports_enabled' => env('PDPA_REPORTS_ENABLED', true),

        // Notify Data Protection Officer on security events
        'dpo_email' => env('PDPA_DPO_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Models
    |--------------------------------------------------------------------------
    |
    | Models that use SearchableEncryption trait.
    | Used by regenerate-blind-indexes command and compliance reports.
    |
    */
    'models' => [
        'User' => \App\Models\User::class,
        'Employee' => \Erpsaas\Hr\Models\Employee::class,
        'EmployeeSalaryRevision' => \Erpsaas\Hr\Models\EmployeeSalaryRevision::class,
        'PayrollEntry' => \Erpsaas\Hr\Models\PayrollEntry::class,
        // Add your models here
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */
    'development' => [
        // Disable encryption in testing (faster tests)
        'disable_encryption_in_tests' => env('DISABLE_ENCRYPTION_TESTS', false),

        // Use weaker but faster hashing in development
        'use_fast_hashing' => env('APP_ENV') === 'local',

        // Show encryption debug information
        'debug' => env('SEARCHABLE_ENCRYPTION_DEBUG', false),
    ],

];
