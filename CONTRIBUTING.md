# Contributing

Thanks for your interest in contributing to `laranail/license-kit`.

## Getting started

```bash
git clone https://github.com/laranail/license-kit
cd license-kit
composer install
composer test
```

## Conventions

- PHP `^8.3` (the test matrix runs `8.3 / 8.4 / 8.5`). Keep syntax 8.3-safe.
- `declare(strict_types=1);` at the top of every PHP file.
- Explicit return types and parameter type hints; early returns; curly braces
  on all control structures.
- Follow the existing structure — check sibling files before introducing a new
  pattern.
- Artisan commands follow the laranail naming shape
  `laranail::license-kit.<command>` (extend the base `Commands\Command`, which
  also exposes the legacy `licensing:*` aliases).

## Quality gates

Before opening a pull request:

```bash
composer test       # Pest (Unit + Feature)
composer analyse    # PHPStan
composer format     # Pint
```

All three must be green. New behaviour needs test coverage — add a Pest test
that proves it rather than a manual/tinker script.

## Pull requests

- Keep the subject line ≤ 72 characters, imperative mood; explain the *why* in
  the body.
- One logical change per PR where practical.
- Update `CHANGELOG.md` (Keep a Changelog format) under an `Unreleased` section.
- Do not include AI-assistant attribution in commits or PRs.

## Credits

`laranail/license-kit` is developed and maintained by Simtabi LLC.
