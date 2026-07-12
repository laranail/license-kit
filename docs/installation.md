# Installation

How to install `laranail/license-kit`: requirements, package installation, migrations, key generation, and verification.

## Requirements

- **PHP** 8.4.1 or higher
- **Laravel** 13
- **Database** MySQL 8.0+ / PostgreSQL 12+ / SQLite 3.8.8+
- **Extensions**:
  - OpenSSL (for cryptographic operations)
  - Sodium (for PASETO tokens)
  - JSON
  - BCMath or GMP (recommended for better performance)

## Installation steps

### 1. Install via Composer

```bash
composer require laranail/license-kit
```

### 2. Publish resources

Publish all resources (config, migrations, etc.):

```bash
php artisan vendor:publish --provider="Simtabi\Laranail\Licence\Kit\LicensingServiceProvider"
```

Or publish specific resources:

```bash
# Configuration only
php artisan vendor:publish --tag=laranail::license-kit-config

# Migrations only
php artisan vendor:publish --tag=laranail::license-kit-migrations

# Language files
php artisan vendor:publish --tag=laranail::license-kit-translations

# Views (if using built-in UI)
php artisan vendor:publish --tag=laranail::license-kit-views
```

### 3. Configure database

Run the migrations to create the necessary tables:

```bash
php artisan migrate
```

This will create the following tables:
- `licenses` - Main license records
- `license_usages` - Device/seat registrations
- `license_renewals` - Renewal history
- `license_templates` - License templates and tiers
- `license_trials` - Trial management
- `license_transfers` - Transfer records
- `license_transfer_approvals` - Transfer approval workflow
- `license_transfer_history` - Transfer audit trail
- `licensing_keys` - Cryptographic keys
- `licensing_audit_logs` - Audit trail

### 4. Environment configuration

Add these environment variables to your `.env` file:

```env
# Licensing Configuration
LICENSING_KEY_PASSPHRASE=your-secure-passphrase-here

# Optional: Custom storage paths
LICENSING_KEY_PATH=storage/app/licensing/keys
LICENSING_BUNDLE_PATH=storage/app/licensing/public-bundle.json

# Optional: Rate limiting (requests per minute)
LICENSING_RATE_VALIDATE=60
LICENSING_RATE_TOKEN=20
LICENSING_RATE_REGISTER=30

# Optional: Default policies
LICENSING_GRACE_DAYS=14
LICENSING_OVER_LIMIT_POLICY=reject
LICENSING_UNIQUE_USAGE_SCOPE=license

# Optional: Offline token settings
LICENSING_TOKEN_FORMAT=paseto
LICENSING_TOKEN_TTL_DAYS=7
LICENSING_FORCE_ONLINE_DAYS=14
```

### 5. Generate cryptographic keys

Generate the root key pair (required for offline verification):

```bash
php artisan laranail::license-kit.keys.make-root
```

**Important**: This creates your root certificate authority. Back up the generated keys securely.

Generate your first signing key:

```bash
php artisan laranail::license-kit.keys.issue-signing --days=30
```

### 6. Configure models (optional)

If you want to customize the models, update `config/licensing.php`:

```php
'models' => [
    'license' => \App\Models\License::class, // Your custom model
    'license_usage' => \App\Models\LicenseUsage::class,
    // ... other models
],
```

Your custom models should extend the package models:

```php
namespace App\Models;

use Simtabi\Laranail\Licence\Kit\Models\License as BaseLicense;

class License extends BaseLicense
{
    // Your customizations
}
```

### 7. Set up polymorphic relationships

Configure which models can have licenses in `config/licensing.php`:

```php
'morph_map' => [
    'user' => \App\Models\User::class,
    'team' => \App\Models\Team::class,
    'organization' => \App\Models\Organization::class,
],
```

Add the trait to your licensable models:

```php
namespace App\Models;

use Simtabi\Laranail\Licence\Kit\Traits\HasLicenses;

class User extends Authenticatable
{
    use HasLicenses;
    
    // Your model code
}
```

### 8. Schedule jobs (optional)

Add these to your `app/Console/Kernel.php` for automated tasks:

```php
protected function schedule(Schedule $schedule)
{
    // Check for expired licenses daily
    $schedule->command('laranail::license-kit.check-expirations')->daily();
    
    // Check for expired trials
    $schedule->job(new CheckExpiredTrialsJob)->daily();
    
    // Clean up inactive usages (optional)
    $schedule->command('laranail::license-kit.cleanup-usages')->weekly();
    
    // Rotate signing keys monthly
    $schedule->command('laranail::license-kit.keys.rotate --reason=routine')
        ->monthly()
        ->when(fn() => now()->day === 1);
}
```

## Installation verification

### 1. Check installation

Run the installation check command:

```bash
php artisan laranail::license-kit.check
```

This verifies:
- Tables created correctly
- Configuration loaded
- Root key exists
- Signing key is active
- Permissions are correct

### 2. Create test license

```php
use Simtabi\Laranail\Licence\Kit\Models\License;

$license = License::create([
    'key_hash' => License::hashKey('TEST-KEY-123'),
    'licensable_type' => 'user',
    'licensable_id' => 1,
    'status' => 'active',
    'max_usages' => 3,
    'expires_at' => now()->addYear(),
]);

if ($license->exists) {
    echo "Installation successful.";
}
```

### 3. Test key generation

```bash
# List all keys
php artisan laranail::license-kit.keys.list

# Export public keys
php artisan laranail::license-kit.keys.export --format=json
```

## Docker installation

If using Docker, add these services to your `docker-compose.yml`:

```yaml
services:
  app:
    build: .
    volumes:
      - ./storage/app/licensing:/var/www/storage/app/licensing
    environment:
      - LICENSING_KEY_PASSPHRASE=${LICENSING_KEY_PASSPHRASE}
    depends_on:
      - redis
      - mysql

  # Optional: Redis for caching
  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

  # Your database service
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: licensing
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

## Production deployment

### 1. Security checklist

- [ ] Set strong `LICENSING_KEY_PASSPHRASE`
- [ ] Back up cryptographic keys
- [ ] Enable HTTPS for API endpoints
- [ ] Configure rate limiting
- [ ] Set up monitoring/alerting
- [ ] Enable audit logging
- [ ] Restrict key management commands

### 2. Performance optimization

```bash
# Cache configuration
php artisan config:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache
```

### 3. Storage permissions

Ensure proper permissions for key storage:

```bash
# Create directories
mkdir -p storage/app/licensing/keys
mkdir -p storage/app/licensing/backups

# Set permissions
chmod 700 storage/app/licensing/keys
chmod 700 storage/app/licensing/backups

# Set ownership (adjust user as needed)
chown -R www-data:www-data storage/app/licensing
```

### 4. Backup strategy

Set up automated backups for:

1. **Database** - All licensing tables
2. **Keys** - Root and signing keys
3. **Configuration** - Your customized config

Example backup script:

```bash
#!/bin/bash
# backup-licensing.sh

BACKUP_DIR="/backups/licensing/$(date +%Y%m%d)"
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u root -p$DB_PASSWORD \
  --tables licenses license_usages license_renewals \
  > $BACKUP_DIR/licensing.sql

# Backup keys (encrypted)
tar -czf $BACKUP_DIR/keys.tar.gz \
  storage/app/licensing/keys/

# Backup config
cp config/licensing.php $BACKUP_DIR/

# Encrypt backup
gpg --encrypt --recipient backup@company.com \
  $BACKUP_DIR/*

# Upload to S3 (optional)
aws s3 sync $BACKUP_DIR s3://backups/licensing/
```

## Upgrading

When a new release ships:

1. **Backup everything** before upgrading
2. Update the composer dependency:
   ```bash
   composer update laranail/license-kit
   ```
3. Publish and run any new migrations:
   ```bash
   php artisan vendor:publish --tag=laranail::license-kit-migrations --force
   php artisan migrate
   ```
4. Review breaking changes in the [CHANGELOG](../CHANGELOG.md) and
   [UPGRADE.md](../UPGRADE.md)

## Uninstallation

To completely remove the package:

1. Remove from composer:
   ```bash
   composer remove laranail/license-kit
   ```

2. Remove database tables:
   ```bash
   php artisan migrate:rollback --path=database/migrations/licensing
   ```

3. Remove published files:
   ```bash
   rm config/licensing.php
   rm -rf storage/app/licensing
   ```

4. Remove from service providers (if manually registered)

## Troubleshooting installation

### Common issues

#### Sodium extension missing
```
Error: Call to undefined function sodium_crypto_sign_keypair()
```

**Solution**: Install the Sodium PHP extension:
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-sodium

# macOS with Homebrew
brew install libsodium
pecl install libsodium

# Docker
RUN docker-php-ext-install sodium
```

#### Migration fails
```
SQLSTATE[42000]: Syntax error or access violation
```

**Solution**: Ensure your database version meets requirements:
- MySQL 8.0+ for JSON columns
- PostgreSQL 12+ for generated columns
- SQLite 3.8.8+ for partial indexes

#### Key generation fails
```
Unable to generate key pair: Permission denied
```

**Solution**: Fix storage permissions:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage
```

#### Rate limiting not working
```
Too Many Attempts
```

**Solution**: Clear rate limiter cache:
```bash
php artisan cache:clear
redis-cli FLUSHDB  # If using Redis
```

## Next steps

- [Configuration Guide](configuration.md) - Customize the package
- [Basic Usage](basic-usage.md) - Start using licenses
- [Getting Started](getting-started.md) - Quick examples
- [API Reference](tools/models.md) - Detailed documentation

---

[← Docs index](../README.md#documentation)
