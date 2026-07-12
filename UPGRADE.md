# Upgrade Guide

This guide documents the steps required to move between released versions of
`laranail/license-kit`. There are no upgrade paths yet — `0.1.0` is the first
public release. Breaking changes in future releases will be documented here,
alongside the [CHANGELOG](CHANGELOG.md).

## General procedure

When a new release ships:

1. **Backup everything** — the database, `storage/app/licensing/keys/`, and
   `config/licensing.php` — before upgrading.
2. Update the composer dependency:

   ```bash
   composer update laranail/license-kit
   ```

3. Publish and run any new migrations:

   ```bash
   php artisan vendor:publish --tag=laranail::license-kit-migrations --force
   php artisan migrate
   ```

4. Review the [CHANGELOG](CHANGELOG.md) for the release you are moving to.
