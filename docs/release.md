# Release

How `laranail/license-kit` is versioned and released.

## Process

Releases are tag-driven. Publishing the `vX.Y.Z` GitHub release is the whole ceremony; Packagist picks the tag up automatically.

1. Ensure `main` is green (tests + static analysis) and `git config user.email` is set to your GitHub no-reply address.
2. Publish a GitHub release named `vX.Y.Z` (creating the tag) with a real, human-readable body describing the changes — never a bare "see CHANGELOG" stub.
3. `update-changelog.yml` then writes the release body into `CHANGELOG.md` on `main` automatically (Keep a Changelog format) — don't hand-edit the changelog for the same version afterwards.

## Versioning

Semver. Breaking changes to the public API — the `LicenceKit` facade, the `Contracts\*` interfaces, the published `config/licensing.php` schema, the database schema, or the offline-token format — are a major bump and must be documented in [UPGRADE.md](../UPGRADE.md).

> The offline-token format is a *distributed* contract: deployed clients keep verifying with the public key bundle they shipped with. Treat any change to token claims, the bundle format, or the key hierarchy as breaking unless old clients verify unaffected.

## CI gates

Every push runs the test matrix (`tests.yml`, PHP 8.4/8.5) and static analysis (`static-analysis.yml`: Pint, PHPStan, Rector). A release must be green on both.

---

[← Docs index](../README.md#documentation)
