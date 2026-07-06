# Changelog

All notable changes to `laranail/license-kit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.1.0](https://github.com/laranail/license-kit/releases/tag/v0.1.0/compare/v0.1.0...v0.1.0) - 2026-07-06

Initial release.

### Added

- **Core licensing** — polymorphic license assignment to any Eloquent model,
  128-bit activation keys (verified via `key_hash`), full lifecycle
  (activation, renewal, grace, expiration, suspension), period-based renewals
  with history, and multi-party approval workflows for license transfers.
- **Seat-based usage control** — device/usage registration with fingerprints
  (max 255 chars, validated on all API inputs), heartbeats with client
  metadata namespaced under `client_data`, over-limit policies (including
  `auto_replace_oldest`), and in-place re-activation of revoked seats.
- **License scopes** — multi-product/software isolation with per-scope signing
  keys, independent rotation schedules, automatic scope-aware key selection,
  and fallback to global keys.
- **License templates** — reusable, tierable license configurations with trial
  days, durations, and scope linkage.
- **Trials** — full trial lifecycle with HMAC-SHA256 fingerprint hashing and
  conversion tracking.
- **Offline verification** — PASETO v4 (Ed25519) tokens with a two-level key
  hierarchy (root CA → signing keys), certificate/key binding checks
  (constant-time compared), cryptographically secure KIDs, key rotation and
  revocation, and Argon2id-encrypted private key storage with
  `sodium_memzero` cleanup and Octane/queue-safe passphrase caching.
- **Tamper-evident audit trail** — hash-chained audit log whose hash covers
  the event identity and the forensic attribution columns (`actor`,
  `actor_type`, `actor_id`, `ip`, `user_agent`, `occurred_at`), with
  `verifyChain()` integrity verification and configurable retention.
- **HTTP API** — activate/deactivate, validate, refresh, heartbeat, usage
  listing/revocation, license-detail, and offline-token endpoints plus a
  `/health` check, with rate limiting, fingerprint validation, and sanitized
  error responses (stable error codes, internals logged server-side).
- **CLI** — `laranail::license-kit.*` Artisan commands (with `licensing:*`
  aliases) for key management (make-root, issue-signing, rotate, list,
  revoke, export), offline token issuance, license management, installation
  verification (`licensing:check`), expiration transitions
  (`licensing:check-expirations`), inactive-usage cleanup
  (`licensing:cleanup-usages`), and expiry notifications
  (`licensing:notify-expiring`).
- **Scheduler integration** — config-driven daily scheduling of the
  maintenance commands (`scheduler.*` in `config/licensing.php`).
- **Events** — domain events covering license, usage, trial, transfer, key,
  and audit operations, for hooking into every lifecycle step.
- **laranail toolchain integration** — built on `laranail/package-tools` +
  `laranail/console`; installation checks register with the unified
  `laranail::package-tools.doctor`, and assets publish under
  `--tag=laranail::license-kit-*` tags.
- **Laravel Boost integration** — a consolidated AI guideline
  (`resources/boost/guidelines/laravel-licensing/core.blade.php`)
  auto-discovered on `boost:install` / `boost:update --discover`, covering
  core concepts, lifecycle, seats, scopes, trials, offline tokens, CLI, and
  API/security rules.
- **Documentation** — full docs tree (getting started, configuration, core
  concepts, key management, offline verification, audit logging, API
  reference, client implementation guide, FAQ, troubleshooting).

## [0.1.0](https://github.com/laranail/license-kit/releases/tag/v0.1.0) - 2026-07-06

Initial release.

### Added

- **Core licensing** — polymorphic license assignment to any Eloquent model,
  128-bit activation keys (verified via `key_hash`), full lifecycle
  (activation, renewal, grace, expiration, suspension), period-based renewals
  with history, and multi-party approval workflows for license transfers.
- **Seat-based usage control** — device/usage registration with fingerprints
  (max 255 chars, validated on all API inputs), heartbeats with client
  metadata namespaced under `client_data`, over-limit policies (including
  `auto_replace_oldest`), and in-place re-activation of revoked seats.
- **License scopes** — multi-product/software isolation with per-scope signing
  keys, independent rotation schedules, automatic scope-aware key selection,
  and fallback to global keys.
- **License templates** — reusable, tierable license configurations with trial
  days, durations, and scope linkage.
- **Trials** — full trial lifecycle with HMAC-SHA256 fingerprint hashing and
  conversion tracking.
- **Offline verification** — PASETO v4 (Ed25519) tokens with a two-level key
  hierarchy (root CA → signing keys), certificate/key binding checks
  (constant-time compared), cryptographically secure KIDs, key rotation and
  revocation, and Argon2id-encrypted private key storage with
  `sodium_memzero` cleanup and Octane/queue-safe passphrase caching.
- **Tamper-evident audit trail** — hash-chained audit log whose hash covers
  the event identity and the forensic attribution columns (`actor`,
  `actor_type`, `actor_id`, `ip`, `user_agent`, `occurred_at`), with
  `verifyChain()` integrity verification and configurable retention.
- **HTTP API** — activate/deactivate, validate, refresh, heartbeat, usage
  listing/revocation, license-detail, and offline-token endpoints plus a
  `/health` check, with rate limiting, fingerprint validation, and sanitized
  error responses (stable error codes, internals logged server-side).
- **CLI** — `laranail::license-kit.*` Artisan commands (with `licensing:*`
  aliases) for key management (make-root, issue-signing, rotate, list,
  revoke, export), offline token issuance, license management, installation
  verification (`licensing:check`), expiration transitions
  (`licensing:check-expirations`), inactive-usage cleanup
  (`licensing:cleanup-usages`), and expiry notifications
  (`licensing:notify-expiring`).
- **Scheduler integration** — config-driven daily scheduling of the
  maintenance commands (`scheduler.*` in `config/licensing.php`).
- **Events** — domain events covering license, usage, trial, transfer, key,
  and audit operations, for hooking into every lifecycle step.
- **laranail toolchain integration** — built on `laranail/package-tools` +
  `laranail/console`; installation checks register with the unified
  `laranail::package-tools.doctor`, and assets publish under
  `--tag=laranail::license-kit-*` tags.
- **Laravel Boost integration** — a consolidated AI guideline
  (`resources/boost/guidelines/laravel-licensing/core.blade.php`)
  auto-discovered on `boost:install` / `boost:update --discover`, covering
  core concepts, lifecycle, seats, scopes, trials, offline tokens, CLI, and
  API/security rules.
- **Documentation** — full docs tree (getting started, configuration, core
  concepts, key management, offline verification, audit logging, API
  reference, client implementation guide, FAQ, troubleshooting).
