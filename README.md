# laranail/license-kit

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/license-kit.svg)](https://packagist.org/packages/laranail/license-kit)
[![Tests](https://github.com/laranail/license-kit/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/license-kit/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/license-kit/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/license-kit/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> A licensing engine for Laravel — offline verification (PASETO v4 / Ed25519), seat-based licensing, full lifecycle (activation, renewal, grace, expiration, suspension), multi-product signing-key scopes with a two-level key hierarchy, an append-only audit trail, and polymorphic license assignment to any Eloquent model.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/license-kit
```

## Quick start

```bash
php artisan vendor:publish --provider="Simtabi\Laranail\Licence\Kit\LicensingServiceProvider"
php artisan migrate
php artisan laranail::license-kit.keys.make-root
php artisan laranail::license-kit.keys.issue-signing --days=30
```

```php
use Simtabi\Laranail\Licence\Kit\Models\License;

$license = License::createWithKey([
    'licensable_type' => User::class,
    'licensable_id'   => $user->id,
    'max_usages'      => 3,
    'expires_at'      => now()->addYear(),
]);

$activationKey = $license->license_key; // "LIC-A3F2B9K1-C4D8E5H7-9D2EK8F3-L6A9M1B4"

$found = License::findByKey($activationKey);
$found->verifyKey($activationKey) && $found->activate();
```

See [Getting started](docs/getting-started.md) for seats, offline tokens, and the rest of the tour.

## The moving parts

| Concept | Model | What it does |
|---------|-------|--------------|
| License | `License` | Polymorphic license on any Eloquent model, hashed activation key, full lifecycle |
| Seat | `LicenseUsage` | A registered device/installation — fingerprint, heartbeat, over-limit policies |
| Scope | `LicenseScope` | Per-product isolation with its own signing keys and rotation schedule |
| Template | `LicenseTemplate` | Reusable, tierable license configuration (trial days, duration, features) |
| Trial | `LicenseTrial` | Trial lifecycle with fingerprint hashing and conversion tracking |
| Key | `LicensingKey` | Root CA and signing keys (Ed25519) behind offline tokens |
| Audit entry | `LicensingAuditLog` | Append-only, hash-chained trail of every licensing operation |

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/license-kit](https://opensource.simtabi.com/documentation/laranail/license-kit/)**.

### Guides

- [Installation](docs/installation.md) — requirements, package install, migrations, key generation, verification.
- [Getting started](docs/getting-started.md) — from install to your first license and offline token.
- [Configuration](docs/configuration.md) — every `config/licensing.php` option: models, crypto, policies, services.
- [Basic usage](docs/basic-usage.md) — creating, activating, checking, renewing licenses; seats; features and entitlements.
- [Architecture](docs/architecture.md) — layers, contract seams, the key hierarchy, and why it's built this way.
- [Security](docs/security.md) — defense in depth: key protection, input validation, rate limiting, incident response.
- [Performance](docs/performance.md) — caching, database optimization, and tuning for high-load environments.
- [Client implementation guide](docs/client-implementation.md) — the wire-level spec for building a compatible client in any language.
- [Client library architecture](docs/client-libraries.md) — design patterns for client libraries (desktop, mobile, web, CLI).
- [FAQ](docs/faq.md) — common questions on the model, crypto, performance, and integration.
- [Troubleshooting](docs/troubleshooting.md) — diagnosing installation, activation, key, and token problems.
- [Release](docs/release.md) — how versions are cut; what counts as a breaking change.

### Reference

- [Licenses](docs/tools/licenses.md) — the `License` model: lifecycle, states, keys, metadata, querying.
- [Usage & seats](docs/tools/usage-seats.md) — seat registration, fingerprints, heartbeats, over-limit policies.
- [Renewals](docs/tools/renewals.md) — period-based renewals, history, grace periods, notifications.
- [Templates & tiers](docs/tools/templates-tiers.md) — reusable license configurations and tier hierarchies.
- [Trials](docs/tools/trials.md) — trial lifecycle, extensions, conversion tracking.
- [Offline verification](docs/tools/offline-verification.md) — PASETO v4 tokens, key hierarchy, client-side verification.
- [License transfers](docs/tools/transfers.md) — multi-party transfer workflows with approvals.
- [Audit logging](docs/tools/audit-logging.md) — the tamper-evident, hash-chained audit trail.
- [Scope templates](docs/tools/scope-templates.md) — binding license plans to product scopes.
- [Key management](docs/tools/key-management.md) — root/signing key generation, rotation, revocation, storage.
- [Multi-software signing keys](docs/tools/multi-software-keys.md) — per-product scopes with isolated keys.
- [Commands](docs/tools/commands.md) — the `laranail::license-kit.*` Artisan commands.
- [Models](docs/tools/models.md) — the 11 Eloquent models.
- [Services](docs/tools/services.md) — the business-logic services.
- [Events](docs/tools/events.md) — every dispatched event and its payload.
- [Contracts](docs/tools/contracts.md) — the interfaces behind every service.
- [Enums](docs/tools/enums.md) — the 11 status/policy enums.

### Recipes

- [Set up tiered SaaS licensing](docs/recipes/saas-tiered-licensing.md) — Basic/Pro/Enterprise tiers end to end.
- [Verify desktop licenses offline](docs/recipes/desktop-offline-verification.md) — activation, token storage, and offline checks in a desktop app.
- [Enforce device limits in a mobile app](docs/recipes/mobile-device-limits.md) — device registration and replacement flows.
- [Convert trials to paid licenses](docs/recipes/trial-to-paid-conversion.md) — trial registration, monitoring, and conversion.

### Project

- [CHANGELOG.md](CHANGELOG.md) — release history.
- [UPGRADE.md](UPGRADE.md) — breaking changes and migration steps.
- [CONTRIBUTING.md](CONTRIBUTING.md) — coding conventions, test patterns, PR expectations.
- [SECURITY.md](SECURITY.md) — vulnerability disclosure.
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) — community standards.

## Stability

Pre-1.0 (`v0.x`): the package is production-quality and fully tested, but the public API may still change between minor versions. Semver applies; every breaking change is documented in [UPGRADE.md](UPGRADE.md). Treat the offline-token format as a distributed contract — see [Release](docs/release.md).

## Local development

```bash
git clone https://github.com/laranail/license-kit.git && cd license-kit
composer install
composer test    # Pest
composer lint    # Pint + PHPStan + Rector (dry-run)
```

## Sister packages

The laranail licensing family — `license-kit` is the server side; these cover the client and product side:

- [`laranail/license-verifier`](https://github.com/laranail/license-verifier) — headless, provider-agnostic verification client: PASETO/Ed25519 offline verification, device fingerprinting, seats.
- [`laranail/license-verifier-ui`](https://github.com/laranail/license-verifier-ui) — UI engine that scaffolds owned, themeable verification UI presets (Blade, Livewire, Filament, …).
- [`laranail/product-updater`](https://github.com/laranail/product-updater) — self-update engine for licensed Laravel products: checks a source, downloads, verifies, applies.
- [`laranail/demo-mode`](https://github.com/laranail/demo-mode) — license-aware demo/sandbox controller: read-only and write guards per model/route/feature.

## Community

Bugs and feature requests → [GitHub Issues](https://github.com/laranail/license-kit/issues); questions and ideas → [GitHub Discussions](https://github.com/laranail/license-kit/discussions).

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
