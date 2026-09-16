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

### Added

- **`assertNoNullOnlyOptionGuards()` is enforced over `src/`.** One read is exempt, annotated on its
  own line: `IssueSigningKeyCommand`'s `--days` branch tests `!== null` but validates `is_numeric()`
  and `> 0` inside, so an empty value fails closed with a message.

## [0.1.0] - 2026-07-11

Initial public release.
