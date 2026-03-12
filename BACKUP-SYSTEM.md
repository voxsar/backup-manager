# Backup System - Quick Reference

## Overview
The backup system uses **Spatie's DbDumper** for professional database backups with **gzip compression** inside **ZIP archives**, stored in **Wasabi S3 cloud storage**. Backups are organized in database-specific folders and retained indefinitely.

## Key Features
- ✅ **Spatie DbDumper Integration**: Professional-grade database dumps
- ✅ **Gzip + ZIP Compression**: SQL dumps gzipped, then packaged in ZIP (~90% size reduction)
- ✅ **Wasabi S3 Storage**: Secure cloud backup storage (write-only credentials)
- ✅ **Database-Specific Folders**: Each database has its own folder
- ✅ **Unlimited Retention**: Backups never deleted, stored indefinitely
- ✅ **Biweekly Verification**: Automated integrity checks every 2 weeks
- ✅ **Scheduled Backups**: Automated daily backups at 2 AM
- ✅ **Full PostgreSQL/MySQL/SQLite Support**: Proper drivers and dump tools

## Storage Configuration

### Wasabi S3
- **Bucket**: artslab-tech-backup-storage
- **Region**: us-east-1
- **Endpoint**: https://s3.wasabisys.com
- **Security**: Write-only credentials (cannot list/read via API)
- **Structure**: `backups/{database_name}/{database_name}_{timestamp}.sql.gz`

### Example Paths
```
backups/
  └── mattermost_production/
      ├── mattermost_production_2026-03-12_06-49-18.zip
      ├── mattermost_production_2026-03-13_02-00-00.zip
      └── mattermost_production_2026-03-14_02-00-00.zip
```

Each ZIP file contains:
```
{backup_name}.zip
├── db-dumps/
│   └── mattermost_production_2026-03-12_06-49-18.sql.gz
└── manifest.json
```

## Available Commands

### Test Database Connections
Test all configured database credentials to ensure they can connect properly:
```bash
php artisan backup:test-connections

# Test a specific credential by ID
php artisan backup:test-connections --id=1
```

### Check System Status
View comprehensive backup system status including connections, configurations, and recent backups:
```bash
php artisan backup:status
```

### Run Backups
Run scheduled backups (checks if they are due based on cron schedule):
```bash
php artisan backup:run-scheduled

# Force run a specific backup configuration by ID
php artisan backup:run-scheduled --id=1
```

### Verify Backup Integrity
Verify backups by checking file integrity and comparing with live database:
```bash
php artisan backup:verify

# Verify a specific backup configuration
php artisan backup:verify --id=1
```
**Note**: This runs automatically every 2 weeks (biweekly on Sundays at 3 AM)

### Test Wasabi Connection
Test the S3 connection to Wasabi:
```bash
php artisan backup:test-wasabi
```

## Current Configuration

### Database Connections
- **MatterMost**: PostgreSQL database at localhost:5432/mattermost_production
  - Status: ✓ Connected and working

### Backup Configurations
- **Daily Backup**: Scheduled to run daily at 2:00 AM (cron: 0 2 * * *)
  - Database: MatterMost
  - Retention: Unlimited (backups never deleted)
  - Status: Enabled
  - Last Run: Successful
  - Format: ZIP archives containing gzipped SQL dumps

### Backup Storage
- **Location**: Wasabi S3 Cloud Storage (artslab-tech-backup-storage)
- **Format**: ZIP archives containing gzipped SQL dumps + manifest
- **Naming**: `{database_name}_{timestamp}.zip`
- **Organization**: Database-specific folders
- **Contents**: 
  - `db-dumps/{database}_timestamp.sql.gz` - Compressed SQL dump
  - `manifest.json` - Backup metadata
- **Size Reduction**: ~90% compression (19 MB → ~2 MB typical)
- **Retention**: Indefinite (never deleted)

## Recent Fixes Applied

1. **Installed PostgreSQL PDO Extension** (`php8.3-pgsql`)
   - Required for connecting to PostgreSQL databases
   
2. **Fixed Database Port Configuration**
   - Updated from 3306 (MySQL) to 5432 (PostgreSQL)
   
3. **Fixed Connection Configuration**
   - Updated `DatabaseCredential::toConnectionConfig()` to use driver-specific settings
   - PostgreSQL: Uses UTF8 charset instead of utf8mb4
   - MySQL: Uses utf8mb4 charset and collation
   
4. **Updated Dockerfile**
   - Added `postgresql-dev` package to ensure PDO extension can be compiled in containers

5. **Configured Wasabi S3 Storage**
   - Set up Wasabi S3 as primary backup destination
   - Configured write-only credentials for security
   - Organized backups into database-specific folders

6. **Enabled Gzip Compression**
   - SQL dumps compressed with gzip inside ZIP archives
   - ~90% file size reduction
   - Uses Spatie's DbDumper with proper compression

7. **Integrated Spatie DbDumper**
   - Professional-grade database dumps
   - Proper handling of MySQL, PostgreSQL, and SQLite
   - Automatic compression and error handling
   - Creates ZIP archives with manifest files

8. **Implemented Biweekly Verification**
   - Automated backup integrity checks every 2 weeks
   - Validates file integrity and live database connectivity
   - Logs verification results

8. **Disabled Retention/Cleanup**
   - Backups stored indefinitely
   - No automatic deletion of old backups
   - Unlimited backup history

## Monitoring

The system logs are available at:
- Application logs: `storage/logs/laravel.log`
- Backup status: Check via `php artisan backup:status`

## Automation

Backups are scheduled via cron expressions. To run them automatically, ensure Laravel's scheduler is running:
```bash
* * * * * cd /var/www/backup-manager && php artisan schedule:run >> /dev/null 2>&1
```

Or use the supervisor configuration to run it as a background task.
