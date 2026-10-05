# Changelog

All notable changes to `laranail/license-kit` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **`keys.issue-signing --kid=` issued a signing key with an empty key id.** The default was applied
  with `??`, which substitutes for `null` only; an option supplied without a value arrives as `''`
  and passed straight through. The empty string was then persisted as the key's `kid`, used when
  signing, and returned to the caller -- and a `kid` is how a key is found again, so rotation and
  lookup silently had nothing to match on.

- **`check-expirations --expiring-within=<non-numeric>` reported no expiring licences.** `(int)` on
  a typo is `0`, so the window became `[now, now]` and the query could not match anything. The
  command then said zero licences were expiring, which is indistinguishable from the good news.

### Changed

- The base `Commands\Command` applies `laranail/package-tools`' `Commands\Concerns\ReadsOptions`,
  whose accessors report an absent and an empty option alike.

- **The API route names, rate limiters and token binding are vendor-scoped.** Route names
  `licensing.*` are now `laranail-license-kit.*` (10 routes), the rate limiters
  `licensing-{validate,register,token}` are `laranail-license-kit.{validate,register,token}`, and
  the offline-token service is bound as `laranail.license-kit.token`. Each lived in a flat,
  host-owned registry, where a sibling package or the application claiming the same key silently
  replaces it. URL paths and the `licensing` config key are unchanged. Requires
  `laranail/package-tools ^0.1.3` for `hasDeprecatedRouteNames()`.

### Added

- **`tests/Feature/NamingConventionTest.php`** reads the live router, rate limiter and container
  registries through package-tools' `AssertsRegisteredNames`, and checks every deprecated alias
  still resolves and announces itself once.

- **`assertNoNullOnlyOptionGuards()` is enforced over `src/`.** One read is exempt, annotated on its
  own line: `IssueSigningKeyCommand`'s `--days` branch tests `!== null` but validates `is_numeric()`
  and `> 0` inside, so an empty value fails closed with a message.

### Deprecated

- **Route names `licensing.*`.** They still generate the same URLs, through package-tools'
  `BareRouteNameAliases`, with one `E_USER_DEPRECATED` notice per name. `Route::has()` answers
  false for them, as for any alias. Use `laranail-license-kit.*`.
- **Rate limiters `licensing-validate`, `licensing-register`, `licensing-token`.** Still registered;
  each announces itself once and delegates to its scoped limiter. Use
  `laranail-license-kit.{validate,register,token}`.
- **Container key `licensing.token`.** An alias of `laranail.license-kit.token`, resolving the same
  instance. A container alias cannot raise a notice, so this is documented only.

All three are removed no earlier than the next minor after 0.1.

## [0.1.0] - 2026-07-11

Initial public release.

[Unreleased]: https://github.com/laranail/license-kit/compare/v0.1.0...HEAD
