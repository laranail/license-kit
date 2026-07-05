# laranail/license-kit

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/license-kit.svg)](https://packagist.org/packages/laranail/license-kit)
[![Tests](https://github.com/laranail/license-kit/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/license-kit/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/license-kit/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/license-kit/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> A licensing package for Laravel — offline verification (PASETO v4 / Ed25519), seat-based licensing, full lifecycle (activation, renewal, grace, expiration, suspension), multi-product signing-key scopes with a two-level key hierarchy, an append-only audit trail, and polymorphic license assignment to any Eloquent model.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/license-kit
```

## Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/license-kit](https://opensource.simtabi.com/documentation/laranail/license-kit/)** — getting started, configuration, core concepts, key management, multi-product scopes, offline verification, seats, audit logging, the CLI, the API reference, and recipes.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
