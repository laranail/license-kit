# Architecture

How `laranail/license-kit` is put together — the layers, the contract seams, the two-level key hierarchy behind offline verification, and the rationale for the big design decisions.

## Overview

License Kit is a server-side licensing engine for Laravel. Your application (the *license server*) creates and manages licenses; the products you distribute (the *clients*) activate against it and can then verify their license **offline** using public-key–signed tokens. The package is organised in four layers:

| Layer | Namespace | Role |
|-------|-----------|------|
| Models | `Simtabi\Laranail\Licence\Kit\Models` | Eloquent entities — `License`, `LicenseUsage`, `LicenseScope`, `LicenseTemplate`, `LicenseTrial`, `LicenseRenewal`, transfer models, `LicensingKey`, `LicensingAuditLog` |
| Services | `Simtabi\Laranail\Licence\Kit\Services` | Business logic — usage registration, token issue/verify, key/CA management, templates, trials, transfers, audit logging |
| Contracts | `Simtabi\Laranail\Licence\Kit\Contracts` | The public API of every service and the seams for custom implementations |
| Surface | Commands, events, facade, HTTP | `laranail::license-kit.*` Artisan commands, 17 dispatched events, the `LicenceKit` facade, optional API routes |

See the reference pages for each layer: [models](tools/models.md) · [services](tools/services.md) · [contracts](tools/contracts.md) · [events](tools/events.md) · [enums](tools/enums.md) · [commands](tools/commands.md).

## The facade

`LicenceKit` (FQN `Simtabi\Laranail\Licence\Kit\LicenceKit`, facade alias `LicenceKit`) is a thin orchestrator over three contracts — `UsageRegistrar`, `TokenIssuer`, and `TokenVerifier` — exposing the day-to-day operations: `findByKey()`, `register()`, `canRegister()`, `heartbeat()`, `issueToken()`, `verifyToken()`, and `verifyOfflineToken()`.

## Contract-first services

Every service is bound to an interface in the container by `LicensingServiceProvider`:

- `UsageRegistrar` → `UsageRegistrarService` — seat registration, fingerprints, heartbeats, over-limit policies.
- `TokenIssuer` / `TokenVerifier` → the service named by `licensing.offline_token.service` (default `PasetoTokenService`).
- `CertificateAuthority` → `CertificateAuthorityService` — root/signing key generation, rotation, revocation.
- `FingerprintResolver` → `FingerprintResolverService` — how device fingerprints are derived.
- `AuditLogger` → `AuditLoggerService` — the append-only, hash-chained audit trail.
- `LicenseKeyGeneratorContract` / `LicenseKeyRetrieverContract` / `LicenseKeyRegeneratorContract` — activation-key generation and (optional, encrypted) retrieval.

Swap any of them in `config/licensing.php` — see [Configuration](configuration.md) and the [contracts reference](tools/contracts.md).

## The key hierarchy

Offline verification rests on a two-level hierarchy:

```
Root key (CA, Ed25519)            — long-lived, kept offline where possible
   └── Signing keys (Ed25519)     — short-lived, issued/rotated/revoked per schedule
          └── Offline tokens      — PASETO v4 public tokens signed per license+usage
```

- The **root key** only signs *signing keys* (a lightweight certificate binding kid → public key).
- **Signing keys** sign the actual offline tokens; they rotate without touching the root.
- **License scopes** give each product its own signing keys and rotation schedule, with fallback to global keys ([multi-software signing keys](tools/multi-software-keys.md)).
- Clients ship the **public key bundle** only; private keys never leave the server ([key management](tools/key-management.md)).

## Lifecycle and state

Licenses move through activation, renewal, grace, expiration, suspension, and cancellation; every transition is guarded by the `LicenseStatus` enum and recorded by model observers into the audit log. Seats (`LicenseUsage`) have their own status lifecycle with heartbeats and revocation. Scheduled commands (`laranail::license-kit.check-expirations`, `…​.cleanup-usages`, `…​.notify-expiring`) apply time-based transitions; the `CheckExpiredTrialsJob` handles trials. A `Doctor` subsystem backs `laranail::license-kit.check` with installation health checks (tables, root key, signing keys, key storage).

## Why these choices?

- **Why PASETO v4 instead of JWT?** PASETO removes JWT's algorithm-confusion attacks: `v4.public` is *always* Ed25519 — there is no `alg` header to downgrade. A JWS-compatible format remains available for interop ([offline verification](tools/offline-verification.md)).
- **Why Ed25519?** Small keys (32 bytes), small signatures (64 bytes), fast verification on low-powered clients, and no parameter choices to get wrong — ideal when tokens are embedded in desktop/mobile apps.
- **Why a two-level hierarchy?** Signing keys become disposable. Routine rotation and even compromise recovery never require re-shipping the root: clients trust the root, and the root vouches for whichever signing keys are current.
- **Why polymorphic licensing?** Licenses attach to *any* Eloquent model (`licensable`), so users, organisations, sites, and devices are all first-class licensees without schema changes.
- **Why hashed activation keys?** Keys are stored as `key_hash` (with an optional encrypted-retrieval mode), so a database leak alone does not disclose customers' activation keys — see [Security](security.md).
- **Why contract seams everywhere?** Fingerprinting, key storage, token format, audit sinks, and over-limit policies all differ per business; binding interfaces in one provider keeps every policy swappable without forking the package.

## Package integration

The package is built on `laranail/package-tools` (service-provider toolkit) and `laranail/console` (the command base that enables the `laranail::license-kit.*` command namespace). It publishes `config/licensing.php`, migrations, translations, and optional views; API routes in `routes/api.php` are opt-in.

---

[← Docs index](../README.md#documentation)
